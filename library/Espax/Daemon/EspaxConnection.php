<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Protocol\EspaX\Enum\EventPriority;
use gipfl\Protocol\EspaX\Enum\EventProcessStatus;
use gipfl\Protocol\EspaX\Enum\EventStatus;
use gipfl\Protocol\EspaX\Enum\IndicationProperty;
use gipfl\Protocol\EspaX\Enum\IndicationType;
use gipfl\Protocol\EspaX\Enum\ProcessMask;
use gipfl\Protocol\EspaX\Enum\RequestProperty;
use gipfl\Protocol\EspaX\Enum\RequestType;
use gipfl\Protocol\EspaX\Enum\ResponseProperty;
use gipfl\Protocol\EspaX\Enum\RootTypePrefix;
use gipfl\Protocol\EspaX\EspaXClient;
use gipfl\Protocol\EspaX\Packet\EspaXIndication;
use gipfl\Protocol\EspaX\Packet\EspaXResponse;
use Icinga\Module\Espax\Icinga\IcingaProblemReference;
use Icinga\Module\Espax\Icinga\SimpleNotification;

use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

class EspaxConnection
{
    /** @var Store */
    protected $store;

    /** @var EspaXClient */
    protected $client;

    /** @var PacketLogger */
    protected $packetLogger;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(EspaXClient $client, Store $store, PacketLogger $packetLogger, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->store = $store;
        $this->packetLogger = $packetLogger;
        $this->logger = $logger;
        $this->applyIndicationHandlers($client);
    }

    /**
     * @return PromiseInterface<?bool>
     */
    public function sendNotification(
        IcingaProblemReference $reference,
        string $destination,
        string $message,
        ?int $timeout = null
    ): PromiseInterface {
        try {
            if ($this->store->hasPendingNotification($reference)) {
                $this->logger->debug(sprintf('Notification for %s is already pending', $reference->getDisplayString()));
                return resolve(null);
            }
        } catch (\Exception $e) {
            // Simple reconnect
            $this->logger->error($e->getMessage());
            if (str_contains($e->getMessage(), 'has gone away')) {
                $db = $this->store->getDb();
                try {
                    $this->logger->notice('Trying to reconnect to DB');
                    $db->closeConnection();
                    if ($this->store->hasPendingNotification($reference)) {
                        return resolve(null);
                    }
                } catch (\Exception $e) {
                    $this->logger->notice('Reconnection failed: ' . $e->getMessage());
                }
            }

            return reject($e);
        }

        $notification = new SimpleNotification($reference, $destination, $message);
        $ts = $this->store->createNotification($notification);

        return $this->client->request(RequestType::PROCESS_START, [
            RequestProperty::CP_PR_REF       => $notification->reference->__toString(),
            // Not shortening: if it is too long, it must fail
            RequestProperty::CP_CALLING_NAME => $notification->destination, // e.g. IT-Rufbereitschaft%‘
            RequestProperty::CP_TEXT_MSG     => $this->shorten($message, 160),
            RequestProperty::CP_PRIO         => EventPriority::STANDARD,
        ])->then(function (EspaXResponse $response) use ($notification, $ts) {
            $this->packetLogger->log(PacketDirection::INBOUND, $response);
            // TODO: new Class for created Notification?
            /*
            <RSP-CODE>200</RSP-CODE>
            <RSP-REASON>OK</RSP-REASON>
            <CP-PR-REF>9e01a476cf4e5e6eb29d764967ac7ad8</CP-PR-REF>
            <SP-PR-TAN>2033246342</SP-PR-TAN>
            */
            $tan = $response->requireProperty(ResponseProperty::SP_PR_TAN); // Unsigned long, max 20 numeric Characters
            if ($response->getCode()->isSuccess()) {
                $this->logger->notice("Notification has been accepted with tan $tan");
                $this->store->setSent($ts, $tan);
                return true;
            } else {
                $this->logger->error('Notification failed: ' . $response->getReason());
                $this->store->setFailed($ts, $response->getReason());
                return false;
            }
        }, function (\Exception $e) use ($notification, $ts) {
            $this->logger->error('Notification failed: ' . $e->getMessage());
            $this->store->setFailed($ts, $e->getMessage());
            return false;
        });
    }

    /**
     * @return PromiseInterface<?bool>
     */
    public function sendRecovery(
        IcingaProblemReference $reference,
        string $destination,
        string $message
    ): PromiseInterface {
        try {
            $notification = $this->store->loadPendingNotification($reference);
        } catch (\Exception $e) {
            // Simple reconnect
            if (str_contains($e->getMessage(), 'has gone away')) {
                $this-
                $db = $this->store->getDb();
                try {
                    $db->closeConnection();
                } catch (\Exception $e) {
                    // Ignoring error on close
                }
                $notification = $this->store->loadPendingNotification($reference);
            } else {
                throw $e;
            }
        }
        if ($notification === null) {
            return resolve(null);
        }

        $this->store->deleteNotification($reference);
        return $this->client->request(RequestType::PROCESS_STOP, [
            RequestProperty::CP_PR_REF  => $notification->reference->__toString(),
            RequestProperty::CP_PR_MASK => ProcessMask::ALL,
            // SP-PR-TAN ?
        ])->then(function (EspaXResponse $response) {
            if ($response->getCode()->isSuccess()) {
                // Log OK?
                return true;
            } else {
                // Log $response->getReason()
                return false;
            }
        }, function (\Exception $e) use ($notification) {
            // Log error
            return false;
        });
    }

    public function stop(): void
    {
        $this->client->stop();
    }

    protected function applyIndicationHandlers(EspaXClient $client): void
    {
        $client->on(RootTypePrefix::INDICATION, function (EspaXIndication $indication) {
            $this->packetLogger->log(PacketDirection::INBOUND, $indication);
            switch ($indication->getIndicationType()) {
                case IndicationType::PROCESS_STARTED:
                    $this->processIndicationStarted($indication);
                    break;
                case IndicationType::PROCESS_EVENT:
                    $this->processIndicationEvent($indication);
                    break;
            }
        });
    }

    protected function processIndicationEvent(EspaXIndication $indication): void
    {
        /*
        CP-PR-REF:    9e01a476cf4e5e6eb29d764967ac7ad8
        SP-PR-TAN:    2033246342
        SS-NETW-NO:   00491231234567
        SS-NETW-NAME: John Doe APP 1
        SS-DIRECTION: Outbound
        SS-STATUS:    Completed
        SS-RESULT:    Accepted
        */
        $tan = $indication->requireProperty(ResponseProperty::SP_PR_TAN);
        // Hint: SS-STATUS is optional
        if ($indication->getProperty(IndicationProperty::SS_STATUS) === EventStatus::COMPLETED) {
            switch ($indication->requireProperty(IndicationProperty::SS_RESULT)) {
                // Prepared, Queued, Active, Conversation setup, Conversation, Postprocessing
                case 'Accepted':
                    $this->store->setAccepted($tan, $indication->getProperty(IndicationProperty::SS_NETWORK_NAME));
                    break;
            }
        }
    }

    protected function processIndicationStarted(EspaXIndication $indication): void
    {
        /*
        CP-PR-REF:  9e01a476cf4e5e6eb29d764967ac7ad8 // from CP-PR-REF, here for 'app1.example.com!HTTP Check'
        SP-PR-TAN:  822123123213                     // the only required property
        SP-PR-NAME: B                                 // from CP-CALLINGNAME
        SP-CREATED: 2023-08-29T11:48:19
        SP-STATUS:  Active
        */
        $tan = $indication->requireProperty(ResponseProperty::SP_PR_TAN);
        switch ($indication->getProperty(IndicationProperty::SP_STATUS)) {
            case EventProcessStatus::ACTIVE:
                $this->store->setConfirmed($tan);
                break;
            default:
        }
    }

    protected function shorten(string $string, int $maxLength): string
    {
        if (strlen($string) <= $maxLength) {
            return $string;
        }

        return substr($string, 0, $maxLength - 3) . '...';
    }
}
