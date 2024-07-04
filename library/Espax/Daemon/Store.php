<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Espax\ProblemReference;
use Icinga\Module\Espax\Icinga\SimpleNotification;

use function get_class;

class Store
{
    public const TABLE_NOTIFICATION = 'espax_notification';

    /** @var Adapter */
    protected $db;

    /** @var NodeConfig */
    protected $node;

    /** @var ?int */
    protected $lastNow = null;

    public function __construct(Adapter $db, NodeConfig $node)
    {
        $this->db = $db;
        $this->node = $node;
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
            'ts'        => $this->uniqueNow(),
            'node_uuid' => $this->node->uuid->getBytes(),
            'destination' => $notification->destination,
            'message' => $notification->message,
            'problem_reference' => $notification->reference->__toString(),
            'problem_reference_implementation' => get_class($notification->reference),
            'problem_reference_details' => JsonString::encode($notification->reference->jsonSerialize()),
        ]);

        return $ts;
    }

    /**
     * We shipped the packet
     */
    public function setSent($ts, $tan)
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_sent'     => $this->now(),
            'problem_tan' => $tan,
        ], $this->whereTs($ts));
    }

    /**
     * ESPA-X-Server confirmed receipt
     */
    public function setConfirmed(string $tan)
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_confirmed' => $this->now(),
        ], $this->whereTan($tan));
    }

    /**
     * Accepted by the final destination
     */
    public function setAccepted(string $tan, ?string $acceptedBy)
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_accepted'    => $this->now(),
            'ts_accepted_by' => $acceptedBy,
        ], $this->whereTan($tan));
    }

    /**
     * Accepted by the final destination
     *
     * TODO: Accepted_by
     */
    public function setFailed(int $ts, string $errorMessage)
    {
        $this->db->update(self::TABLE_NOTIFICATION, [
            'ts_failed'     => $this->now(),
            'error_message' => $errorMessage,
        ], $this->whereTs($ts));
    }

    protected function whereTs(int $ts): string
    {
        return $this->db->quoteInto('ts = ?', $ts);
    }

    protected function whereTan(string $tan): string
    {
        return $this->db->quoteInto('problem_tan = ?', $tan);
    }
}
