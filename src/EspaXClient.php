<?php

namespace gipfl\Protocol\EspaX;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Protocol\EspaX\Enum\RequestType;
use gipfl\Protocol\EspaX\Enum\RootTypePrefix;
use gipfl\Protocol\EspaX\Packet\EspaXCommand;
use gipfl\Protocol\EspaX\Packet\EspaXIndication;
use gipfl\Protocol\EspaX\Packet\EspaXPacket;
use gipfl\Protocol\EspaX\Packet\EspaXRequest;
use gipfl\Protocol\EspaX\Packet\EspaXResponse;
use gipfl\Protocol\EspaX\Packet\PacketFactory;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Promise\Timer\timeout;

class EspaXClient implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected const MAX_INVOKE_ID = 4294967285;

    /** @var EspaXConnectionConfig */
    protected $config;

    /** @var ?ConnectionInterface */
    protected $connection;

    /** @var ?PromiseInterface */
    protected $pendingConnection;

    /** @var Connector */
    protected $connector;

    /** @var int */
    protected $lastInvokeId = 0;

    /** @var ?string */
    protected $sessionId = null;

    /** @var Deferred[] */
    protected $pendingRequests = [];

    /** @var string */
    protected $buffer = '';

    /** @var LoggerInterface */
    protected $logger;

    /** @var ?TimerInterface */
    protected $heartbeatTimer;

    protected $logXml = false;

    protected $validateIncomingXml = false;

    public function __construct(EspaXConnectionConfig $config, LoggerInterface $logger)
    {
        $this->connector = new Connector();
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeHeartbeat();
    }

    protected function initializeHeartbeat(): void
    {
        $this->heartbeatTimer = Loop::addPeriodicTimer(5, function () {
            if ($this->sessionId === null) {
                return;
            }

            $this->logger->debug('Sending heartbeat for ' . $this->sessionId);
            timeout($this->request(RequestType::HEARTBEAT), $this->config->heartbeatTimeout)
                ->then(function (EspaXResponse $response) {
                    $this->logger->debug('Got heartbeat response');
                }, function (Exception $e) {
                    $this->logger->error('Heartbeat failed: ' . $e->getMessage());
                    $this->closeConnection();
                });
        });
    }

    public function connect(): PromiseInterface
    {
        return $this->connection();
    }

    public function stop(): void
    {
        $this->closeConnection();
    }

    protected function processData($data): void
    {
        $this->buffer .= $data;
        $this->processBuffer();
    }

    protected static function shorten(string $string, int $maxLength): string
    {
        if (strlen($string) <= $maxLength) {
            return $string;
        }

        return "$string...";
    }

    protected function processBuffer(): void
    {
        if (strlen($this->buffer) < 10) {
            return;
        }
        if (substr($this->buffer, 0, 2) !== EspaXProtocol::PACKET_PREFIX) {
            $this->logger->error('Valid ESPA-X header expected, got ' . bin2hex(substr($this->buffer, 0, 10)));
            $this->buffer = '';
            $this->closeConnection();
            return;
        }
        $length = unpack('N', $this->buffer, 2)[1];
        if (strlen($this->buffer) < $length) {
            return;
        }

        $xml = substr($this->buffer, 10, $length - 10);
        $this->buffer = substr($this->buffer, $length);
        if ($this->logXml) {
            $this->logger->notice('Got XML: ' . $xml);
        }
        $this->processXml($xml);
    }

    protected function processXml(string $string): void
    {
        try {
            $xml = new SimpleXMLElement($string);
            // PHP Warning:  DOMDocument::schemaValidate(): Element '{
            //    http://ns.espa-x.org/espa-x}SS-NETW-NO': [facet 'pattern'] The value '+491231234567' is not
            //    accepted by the pattern '[0-9*#]+'. in /shared/PHP/Icinga/modules/espax/src/EspaXProtocol.php
            //    on line 27
            if ($this->validateIncomingXml) {
                EspaXProtocol::validateSimpleXml($xml);
            }
            if ($packet = PacketFactory::packetFromSimpleXml($xml, $this->logger)) {
                if ($packet instanceof EspaXRequest) {
                    $this->processRequest($packet);
                } elseif ($packet instanceof EspaXResponse) {
                    $this->processResponse($packet);
                } elseif ($packet instanceof EspaXIndication) {
                    $this->emit(RootTypePrefix::INDICATION, [$packet]);
                } elseif ($packet instanceof EspaXCommand) {
                    $this->emit(RootTypePrefix::COMMAND, [$packet]);
                }
                // Hint: else currently is null for RootTypePrefix::PROPRIETARY
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to deal with ESPA-X packet: ' . $e->getMessage());
            $this->closeConnection();
        }
    }

    protected function processRequest(EspaXRequest $request): void
    {
        $this->logger->warning('Got a request, unhandled - we are not a server: ' . $request->getType());
    }

    protected function processResponse(EspaXResponse $response): void
    {
        $id = $response->invokeId;
        if (isset($this->pendingRequests[$id])) {
            if ($sessionId = $response->getSessionId()) {
                if ($sessionId !== $this->sessionId) {
                    $this->sessionId = $sessionId;
                }
            }
            $this->pendingRequests[$id]->resolve($response);
        } else {
            $this->logger->warning("Got unexpected Response for invocationID=$id");
            // TODO: PacketLogger
        }
    }

    protected function closeConnection(): void
    {
        if ($this->heartbeatTimer) {
            Loop::cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
        $this->pendingConnection = null;
        if ($this->connection) {
            $this->connection->end();
            $this->connection = null;
            $this->sessionId = null;
            foreach ($this->pendingRequests as $request) {
                $request->promise()->cancel();
            }
            $this->pendingRequests = [];
        }
    }

    /**
     * @return PromiseInterface<ConnectionInterface>
     */
    protected function connection(): PromiseInterface
    {
        if ($this->connection === null) {
            if ($this->pendingConnection === null) {
                $socket = $this->config->host . ':' . $this->config->port;
                $this->logger->debug("Connecting to $socket");
                $this->pendingConnection = $this->connector->connect($socket)
                    ->then(function (ConnectionInterface $connection) {
                        $this->logger->notice('Got connection');
                        $this->connection = $connection;
                        $this->pendingConnection = null;
                        $connection->on('data', function ($data) {
                            $this->processData($data);
                        });
                        $connection->on('close', function () {
                            $this->logger->notice('Connection closed');
                            $this->connection = null;
                        });

                        return $connection;
                    }, function (Exception $e) {
                        // TODO: reconnect
                        $this->logger->error($e->getMessage());
                        return reject($e);
                    });
            }
            return $this->pendingConnection;
        }

        return resolve($this->connection);
    }

    public function request(string $requestType, array $properties = [], $timeout = 10): PromiseInterface
    {
        $request = new EspaXRequest(RootTypePrefix::REQUEST . $requestType, $properties);
        return timeout($this->connectionForRequest($request)
            ->then(function (ConnectionInterface $connection) use ($request) {
                return $this->sendRequest($connection, $request);
            }), $timeout);
    }

    /**
     * @return PromiseInterface<ConnectionInterface>
     */
    protected function authenticatedConnection(): PromiseInterface
    {
        if ($this->sessionId) {
            return resolve($this->connection);
        }

        if ($this->config->username === null || $this->config->password === null) {
            return reject(new RuntimeException('Cannot authenticate, username and password are required'));
        }

        return $this->connection()->then(function (ConnectionInterface $connection) {
            return $this->sendRequest($connection, new EspaXRequest(RootTypePrefix::REQUEST . RequestType::LOGIN, [
                'LI-USER'     => $this->config->username,
                'LI-PASSWORD' => $this->config->password,
                'LI-CLIENT'   => 'ESPA-X for Icinga',
                'LI-CLIENTSW' => 'v0.0.999',
            ]))->then(function (EspaXResponse $response) use ($connection) {
                // TODO: delegate to client, check response code
                $this->logger->notice('Login succeeded: ' . $response->getProperty('LI-SERVER'));
                // ["LI-SERVER"]=> string(22) "New Voice alarm server"
                // ["RSP-CODE"]=> string(3) "200"
                // ["RSP-REASON"]=> string(2) "OK"
                return $connection;
            }, function (Exception $e) {
                $this->logger->error('Login failed: ' . $e->getMessage());
                return reject($e);
            });
        });
    }

    /**
     * @return PromiseInterface<ConnectionInterface>
     */
    protected function connectionForRequest(EspaXRequest $request): PromiseInterface
    {
        if (in_array($request->getRequestType(), ['LOGIN', 'ERROR'])) {
            return $this->connection();
        }

        return $this->authenticatedConnection();
    }

    protected function sendRequest(ConnectionInterface $connection, EspaXRequest $request): PromiseInterface
    {
        $id = ++$this->lastInvokeId;
        if ($id > self::MAX_INVOKE_ID) {
            $this->lastInvokeId = $id = 1;
        }
        $request->invokeId = $id;

        if ($this->sessionId) {
            $request->sessionId = $this->sessionId;
        }

        $deferred = new Deferred();
        try {
            $this->sendPacket($connection, $id, $request);
            $this->pendingRequests[$id] = $deferred;
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            if ($e instanceof Exception) {
                return reject($e);
            }

            return reject(new RuntimeException($e->getMessage(), 0, $e));
        }

        return $deferred->promise()->always(function () use ($id) {
            unset($this->pendingRequests[$id]);
        });
    }

    protected function sendPacket(ConnectionInterface $connection, $id, EspaXPacket $packet)
    {
        $xml = $packet->toXml();
        $header = self::createEspaxHeader($id, $xml);
        if ($this->logXml) {
            $this->logger->notice('Sending Request XML: ' . $xml);
        }
        $connection->write($header . $packet->toXml());
    }

    protected static function createEspaxHeader(int $invokeId, string $body): string
    {
        // 2 Bytes EX + 4 Bytes length + 4 Bytes Invocation-ID
        return EspaXProtocol::PACKET_PREFIX . pack('N', strlen($body) + 10) . pack('N', $invokeId);
    }
}
