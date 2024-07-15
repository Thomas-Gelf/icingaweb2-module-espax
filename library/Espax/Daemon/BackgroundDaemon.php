<?php

namespace Icinga\Module\Espax\Daemon;

use Evenement\EventEmitterInterface;
use Exception;
use gipfl\Cli\Process;
use gipfl\IcingaCliDaemon\DbResourceConfigWatch;
use gipfl\Protocol\JsonRpc\Error;
use gipfl\Protocol\JsonRpc\Handler\FailingPacketHandler;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\SimpleDaemon\DaemonState;
use gipfl\SimpleDaemon\DaemonTask;
use gipfl\SimpleDaemon\SystemdAwareTask;
use gipfl\Socket\UnixSocketInspection;
use gipfl\Socket\UnixSocketPeer;
use gipfl\SystemD\NotifySystemD;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

use function React\Promise\resolve;

class BackgroundDaemon implements DaemonTask, SystemdAwareTask, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const REQUIRED_PHP_EXTENSIONS = ['posix', 'pcntl', 'dom', 'xml', 'simplexml'];
    public const SOCKET = '/run/icinga-espax/daemon.sock';

    const STATE_STOPPED  = 'stopped';
    const STATE_STOPPING  = 'stopping';
    const STATE_STARTING = 'starting';
    const STATE_READY    = 'ready';
    const STATE_FAILED   = 'failed';
    const STATE_IDLE     = 'idle';

    /** @var DaemonDb */
    protected $daemonDb;

    /** @var DaemonProcessState */
    protected $processState;

    /** @var DaemonProcessDetails */
    protected $processDetails;

    /** @var ControlSocket */
    protected $controlSocket;

    /** @var NamespacedPacketHandler */
    protected $handler;

    /** @var NamespacedPacketHandler */
    protected $packetHandler;

    /** @var LoopInterface */
    private $loop;

    /** @var NotifySystemD|boolean */
    protected $systemd;

    /** @var DaemonState */
    protected $daemonState;

    /** @var EspaxConnections */
    public $connections;

    /** @var bool */
    protected $reloading = false;

    /** @var bool */
    protected $shuttingDown = false;

    public function start(LoopInterface $loop)
    {
        $this->connections = new EspaxConnections($this->logger);
        $this->loop = $loop;
        $this->daemonState = $this->initializeDaemonState();
        $this->setInitialDaemonState();
        $this->packetHandler = new NamespacedPacketHandler();
        $this->packetHandler->registerNamespace('espax', new RpcNamespaceEspaX($this->connections));
        $dbApi = new RpcNamespaceEspaXDb($this->logger);
        $this->packetHandler->registerNamespace('espaxDb', $dbApi);
        $this->initializeControlSocket(self::SOCKET);
        $this->processDetails = $this
            ->initializeProcessDetails($this->systemd);
        $this->processState = new DaemonProcessState(Application::PROCESS_NAME);
        $this->daemonState->setState(self::STATE_READY);
        $this->processState->setSystemd($this->systemd);
        if ($this->systemd) {
            $this->systemd->setReady();
        }
        $this->setState('ready');
        $this->daemonDb = $this->initializeDb($this->processDetails, $this->processState);
        $this->daemonDb->register($dbApi);
        $this->daemonDb->register($this->connections);
        $this->daemonDb->run();
        return resolve();
    }

    public function stop()
    {
        $this->daemonState->setState(self::STATE_STOPPING);
        $this->shutdown();
        $this->daemonState->setState(self::STATE_STOPPED);
        return resolve();
    }

    /**
     * @param NotifySystemD|false $systemd
     * @return DaemonProcessDetails
     */
    protected function initializeProcessDetails($systemd)
    {
        if ($systemd && $systemd->hasInvocationId()) {
            $uuid = $systemd->getInvocationId();
        } else {
            try {
                $uuid = \bin2hex(Uuid::uuid4()->getBytes());
            } catch (Exception $e) {
                $uuid = 'deadc0de' . \substr(\md5(\getmypid()), 0, 24);
            }
        }
        $processDetails = new DaemonProcessDetails($uuid);
        if ($systemd) {
            $processDetails->set('running_with_systemd', 'y');
        }

        return $processDetails;
    }

    protected function initializeDaemonState(): DaemonState
    {
        $daemonState = new DaemonState();
        // $daemonState->setComponentStates($this->componentStates);
        $daemonState->on(DaemonState::ON_CHANGE, function ($processTitle, $statusSummary) use ($daemonState) {
            if (strlen($statusSummary) === 0) {
                Process::setTitle($processTitle);
            } else {
                Process::setTitle("$processTitle: $statusSummary");
            }

            if ($this->systemd && strlen($statusSummary) > 0) {
                $this->systemd->setStatus($statusSummary);
            }
            /*
            foreach ($this->componentStates as $component => $state) {
                $newState = $daemonState->getComponentState($component);
                if ($state !== $newState) {
                    $this->loop->futureTick(function () use ($component, $state, $newState) {
                        $this->onComponentChange($component, $state, $newState);
                    });
                }
            }
            $this->componentStates = $daemonState->getComponentStates();
            */
        });

        return $daemonState;
    }

    protected function initializeDb(
        DaemonProcessDetails $processDetails,
        DaemonProcessState $processState,
        $dbResourceName = null
    ) {
        $db = new DaemonDb($processDetails, $this->logger);
        $db->on(DaemonDb::ON_STATE_CHANGE, function ($state, $level = null) use ($processState) {
            // TODO: level is sent but not used
            $processState->setComponentState('db', $state);
        });
        $db->on(DaemonDb::ON_SCHEMA_OUTDATED, function () {
            $this->reloading = true;
            $this->setState('reloading the main process');
            $this->daemonDb->disconnect();
            Process::restart();
        });
        $db->on(DaemonDb::ON_SCHEMA_CHANGE, function ($startupSchema, $dbSchema) {
            $this->logger->warning(sprintf(
                "DB schema version changed. Started with %d, DB has %d. Restarting.",
                $startupSchema,
                $dbSchema
            ));
            $this->reload();
        });

        $db->setConfigWatch(
            $dbResourceName
                ? DbResourceConfigWatch::name($dbResourceName)
                : DbResourceConfigWatch::module('espax')
        );

        return $db;
    }

    protected function setState($state)
    {
        if ($this->processState) {
            $this->processState->setState($state);
        }

        return $this;
    }

    protected function setInitialDaemonState()
    {
        $daemonState = $this->daemonState;
        $daemonState->setProcessTitle(Application::PROCESS_NAME);
        $daemonState->setState(self::STATE_STARTING);
    }

    protected function initializeControlSocket(string $path): void
    {
        if (empty($path)) {
            throw new \InvalidArgumentException('Control socket path expected, got none');
        }
        $this->logger->info("[socket] launching control socket in $path");
        $socket = new ControlSocket($path);
        $socket->run();
        $this->addSocketEventHandlers($socket);
        $this->handler = new NamespacedPacketHandler();
        $this->controlSocket = $socket;
    }

    protected function addSocketEventHandlers(EventEmitterInterface $socket): void
    {
        $socket->on('connection', function (ConnectionInterface $connection) {
            $jsonRpc = new JsonRpcConnection(new StreamWrapper($connection));
            $jsonRpc->setLogger($this->logger);

            $peer = UnixSocketInspection::getPeer($connection);
            if (!$this->isAllowed($peer)) {
                $jsonRpc->setHandler(new FailingPacketHandler(new Error(Error::METHOD_NOT_FOUND, sprintf(
                    '%s is not allowed to control this socket',
                    $peer->getUsername()
                ))));
                Loop::get()->addTimer(10, function () use ($connection) {
                    $connection->close();
                });
                return;
            }
            $jsonRpc->setHandler($this->packetHandler);
        });
        $socket->on('error', function (Exception $error) {
            // Connection error, Socket remains functional
            $this->logger->error($error->getMessage());
        });
    }

    protected function isAllowed(UnixSocketPeer $peer): bool
    {
        if ($peer->getUid() === 0) {
            return true;
        }
        $myGid = posix_getegid();
        $peerGid = $peer->getGid();
        // Hint: $myGid makes also part of id -G, this is the fast lane for those using
        //       php-fpm and the user icingaweb2 (with the very same main group as we have)
        if ($peerGid === $myGid) {
            return true;
        }

        $uid = $peer->getUid();
        return in_array($myGid, array_map('intval', explode(' ', `id -G $uid`)));
    }

    public function setSystemd(NotifySystemD $systemd)
    {
        $this->systemd = $systemd;
    }


    protected function registerSignalHandlers(LoopInterface $loop)
    {
        $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        };
        $funcReload = function () {
            $this->reload();
        };
        $loop->addSignal(SIGHUP, $funcReload);
        $loop->addSignal(SIGINT, $func);
        $loop->addSignal(SIGTERM, $func);
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        $this->loop->removeSignal($signal, $func);
        $this->shutdown();
    }

    public function reload()
    {
        if ($this->reloading) {
            $this->logger->error('Ignoring reload request, reload is already in progress');
            return;
        }
        $this->reloading = true;
        $this->setState('reloading the main process');
        try {
            $this->prepareShutdown();
            $this->logger->info('Ready to reload');
        } catch (Exception $e) {
            $this->logger->error('Ignoring problem on reload: ' . $e->getMessage());
        }
        $this->loop->addTimer(0.1, function () {
            $this->loop->stop();
            Process::restart();
        });
    }

    protected function shutdown()
    {
        try {
            $this->prepareShutdown();
            $this->logger->info('Ready to shut down');
        } catch (Exception $e) {
            $this->logger->error('Ignoring problem on shutdown: ' . $e->getMessage());
        }
        $this->loop->addTimer(0.1, function () {
            $this->loop->stop();
        });
    }

    protected function prepareShutdown()
    {
        if ($this->shuttingDown) {
            $this->logger->error('Ignoring shutdown request, shutdown is already in progress');
            return;
        }
        $this->logger->info('Shutting down');
        $this->shuttingDown = true;
        $this->setState('shutting down');
        $this->controlSocket->shutdown();
        $this->logger->info('Control socket has been closed');
        $this->connections->stop();
        $this->daemonDb->disconnect();
    }
}
