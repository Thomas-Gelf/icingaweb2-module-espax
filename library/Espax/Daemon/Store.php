<?php

namespace Icinga\Module\Espax\Daemon;

use Exception;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Espax\Icinga\IcingaAdapter;
use Icinga\Module\Espax\Icinga\IcingaProblemReference;
use Icinga\Module\Espax\ProblemReference;
use Icinga\Module\Espax\Icinga\SimpleNotification;

use stdClass;

use function get_class;

class Store
{
    public const TABLE_NOTIFICATION = 'espax_notification';
    public const TABLE_NOTIFICATION_HISTORY = 'espax_notification_history';

    /** @var Adapter */
    protected $db;

    /** @var NodeConfig */
    protected $node;

    /** @var ?int */
    protected $lastNow = null;

    /** @var IcingaAdapter */
    protected $icinga;

    public function __construct(Adapter $db, IcingaAdapter $icinga, NodeConfig $node)
    {
        $this->db = $db;
        $this->node = $node;
        $this->icinga = $icinga;
    }

    public function loadPendingNotification(ProblemReference $reference): ?SimpleNotification
    {
        $db = $this->db;
        if ($row = $db->fetchRow(
            $db->select()->from(self::TABLE_NOTIFICATION)->where('problem_reference = ?', $reference->getReferenceKey())
        )) {
            // TODO: Das ist wenig?!
            return new SimpleNotification(
                $reference,
                $row->destination,
                $row->message
            );
        }

        return null;
    }

    public function loadPendingNotificationPropertiesForReferenceKey(string $referenceKey): ?stdClass
    {
        $db = $this->db;
        if ($row = $db->fetchRow(
            $db->select()->from(self::TABLE_NOTIFICATION)->where('problem_reference = ?', $referenceKey)
        )) {
            return $row;
        }

        return null;
    }
    public function loadPendingNotificationPropertiesForTs(int $ts): ?stdClass
    {
        $db = $this->db;
        if ($row = $db->fetchRow(
            $db->select()->from(self::TABLE_NOTIFICATION)->where('ts = ?', $ts)
        )) {
            return $row;
        }

        return null;
    }

    public function hasPendingNotification(ProblemReference $reference): bool
    {
        $db = $this->db;
        if ($db->fetchRow(
            $db->select()
                ->from(self::TABLE_NOTIFICATION, 'problem_reference')
                ->where('problem_reference = ?', $reference->getReferenceKey())
        )) {
            return true;
        }

        return false;
    }

    public function deleteNotification(ProblemReference $reference): bool
    {
        $db = $this->db;
        if ($db->delete(
            self::TABLE_NOTIFICATION,
            $db->quoteInto('problem_reference = ?', $reference->getReferenceKey())
        )) {
            return true;
        }

        return false;
    }

    public function uniqueNow(): int
    {
        $node = $this->node;
        $now = (int) floor(microtime(true) * 1000 / $node->nodeCount) * $node->nodeCount + $node->number;
        while ($now === $this->lastNow) {
            $now += $node->nodeCount;
        }
        $this->lastNow = $now;

        return $now;
    }

    protected function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function getDb(): Adapter
    {
        return $this->db;
    }

    public function createNotification(SimpleNotification $notification): int
    {
        $ts = $this->uniqueNow();
        $this->db->insert(self::TABLE_NOTIFICATION, [
            'ts'          => $this->uniqueNow(),
            'node_uuid'   => $this->node->uuid->getBytes(),
            'destination' => $notification->destination,
            'message'     => $notification->message,
            'problem_reference' => $notification->reference->__toString(),
            'problem_reference_implementation' => get_class($notification->reference),
            'problem_reference_details' => JsonString::encode($notification->reference->jsonSerialize()),
        ]);

        return $ts;
    }

    /**
     * We shipped the packet
     */
    public function setSent($ts, $tan): void
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_sent'     => $this->now(),
            'problem_tan' => $tan,
        ], $this->whereTs($ts));
    }

    /**
     * ESPA-X-Server confirmed receipt
     */
    public function setConfirmed(string $reference): void
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_confirmed' => $this->now(),
        ], $this->whereReference($reference));
    }

    /**
     * Accepted by the final destination
     */
    public function setAccepted(string $referenceKey, ?string $acceptedBy): void
    {
        $now = $this->now();
        $notification = $this->loadPendingNotificationPropertiesForReferenceKey($referenceKey);
        if (! $notification) {
            return;
        }
        $reference = $this->referenceFromDbRow($notification);
        $notification->ts_accepted = $now;
        $notification->accepted_by = $acceptedBy;
        $this->archiveNotification((array) $notification, $reference);
        if ($reference instanceof IcingaProblemReference) {
            $this->icinga->ack($reference, $acceptedBy ?? 'ESPA-X', 'Problem has been accepted');
        }
    }

    protected function referenceFromDbRow(stdClass $row): ProblemReference
    {
        /** @var class-string|ProblemReference $refClass */
        $refClass = $row->problem_reference_implementation;
        /** @var class-string|ProblemReference $refClass */
        return $refClass::fromSerialization(
            JsonString::decode($row->problem_reference_details)
        );
    }

    /**
     * Accepted by the final destination
     *
     * TODO: Accepted_by
     */
    public function setFailed(int $ts, string $errorMessage): void
    {
        $now = $this->now();
        $notification = $this->loadPendingNotificationPropertiesForReferenceKey($ts);
        if (! $notification) {
            return;
        }
        $reference = $this->referenceFromDbRow($notification);
        $notification->ts_accepted = $now;
        $notification->error_message = $errorMessage;
        $this->archiveNotification((array) $notification, $reference);
    }

    protected function archiveNotification(array $properties, ProblemReference $reference)
    {
        $this->db->beginTransaction();
        try {
            $this->db->insert(self::TABLE_NOTIFICATION_HISTORY, $properties);
            $this->deleteNotification($reference);
            $this->db->commit();
        } catch (Exception $e) {
            try {
                $this->db->rollBack();
            } catch (Exception $e) {
            }
        }
    }

    protected function whereTs(int $ts): string
    {
        return $this->db->quoteInto('ts = ?', $ts);
    }

    protected function whereReference(string $reference): string
    {
        return $this->db->quoteInto('problem_reference = ?', $reference);
    }
}
