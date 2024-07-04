<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;

class RpcNamespaceEspaXDb implements DbBasedComponent
{
    /** @var ?Adapter */
    protected $db = null;

    /**
     * @param string $reference
     */
    public function deleteNotificationRequest(string $reference): bool
    {
        if ($this->db === null) {
            throw new \Exception('ESPA-X daemon has no DB connection');
        }

        $this->db->delete(PacketDbLogger::TABLE_TRACE, $this->db->quoteInto('problem_reference = ?', $reference));
        $this->db->delete(Store::TABLE_NOTIFICATION, $this->db->quoteInto('problem_reference = ?', $reference));

        return true;
    }

    public function initDb(Adapter $db): void
    {
        $this->db = $db;
    }

    public function stopDb(): void
    {
        $this->db = null;
    }
}
