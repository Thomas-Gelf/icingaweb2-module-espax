<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Application\Config;
use Icinga\Module\Espax\Icinga\IcingaAdapter;
use Psr\Log\LoggerInterface;

class RpcNamespaceEspaXDb implements DbBasedComponent
{
    /** @var LoggerInterface */
    protected $logger;
    /** @var ?Adapter */
    protected $db = null;
    /** @var ?Store */
    protected $store = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param int $ts
     * @param string $username
     */
    public function deleteNotificationRequest(int $ts, string $username): bool
    {
        if ($this->db === null) {
            throw new \Exception('ESPA-X daemon has no DB connection');
        }
        $now = (int) floor(microtime(true) * 1000);

        $notification = $this->store->loadPendingNotificationPropertiesForTs($ts);
        if (! $notification) {
            return false;
        }
        $reference = Store::referenceFromDbRow($notification);
        $notification->ts_failed = $now;
        $notification->error_message = sprintf('Manually cancelled by %s', $username);
        $this->store->archiveNotification((array) $notification, $reference);
        $this->logger->notice(sprintf('%s has manually been cancelled by %s', $reference, $username));

        return true;
    }

    public function initDb(Adapter $db): void
    {
        $this->db = $db;
        $config = Config::module('espax');
        $nodeConfig = NodeConfig::fromArray($config->getSection('node')->toArray());
        $this->store = new Store($db, new IcingaAdapter($this->logger), $nodeConfig);
    }

    public function stopDb(): void
    {
        $this->db = null;
        $this->store = null;
    }
}
