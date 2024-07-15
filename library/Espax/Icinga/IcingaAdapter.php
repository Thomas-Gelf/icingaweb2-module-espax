<?php

namespace Icinga\Module\Espax\Icinga;

use Icinga\Application\Icinga;
use Psr\Log\LoggerInterface;

class IcingaAdapter
{
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function ack(IcingaProblemReference $reference, $username, $message): void
    {
        try {
            if ($this->hasIcingaDbModule()) {
                $cmd = new IcingaDbCommandPipe();
            } elseif ($this->hasMonitoringModule()) {
                $cmd = new IcingaCommandPipe();
            } else {
                $this->logger->warning(sprintf(
                    'Failed to acknowledge %s, I have neither IcingaDB nor the monitoring module',
                    $reference->getDisplayString()
                ));
                return;
            }
            $acknowledged = $cmd->acknowledge($username, $message, $reference->getHost(), $reference->getService());
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Failed to acknowledge %s: %s',
                $reference->getDisplayString(),
                $e->getMessage()
            ));
        }

        if ($acknowledged) {
            $this->logger->info('Icinga problem has been acknowledged for ' . $reference->getDisplayString());
        }
    }

    protected function hasIcingaDbModule(): bool
    {
        return Icinga::app()->getModuleManager()->hasLoaded('icingadb');
    }

    protected function hasMonitoringModule(): bool
    {
        return Icinga::app()->getModuleManager()->hasLoaded('icingadb');
    }
}
