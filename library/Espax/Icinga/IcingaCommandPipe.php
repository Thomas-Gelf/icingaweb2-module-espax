<?php

namespace Icinga\Module\Espax\Icinga;

use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Exception\CommandTransportException;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use RuntimeException;

class IcingaCommandPipe
{
    public function acknowledge($author, $message, $host, $service = null)
    {
        $object = $this->getObject($host, $service);
        if ($object->acknowledged) {
            return false;
        }

        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObject($object)
            ->setAuthor($author)
            ->setComment($message)
            ->setPersistent(false)
            ->setSticky(false)
            ->setNotify(false)
            ;

        try {
            $transport = new CommandTransport();
            $transport->send($cmd);
        } catch (CommandTransportException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return true;
    }

    protected function getObject($hostname, $service)
    {
        if ($service === null) {
            return $this->getHostObject($hostname);
        }

        return $this->getServiceObject($hostname, $service);
    }

    protected function getHostObject($hostname)
    {
        $host = new Host(MonitoringBackend::instance(), $hostname);

        if ($host->fetch() === false) {
            throw new RuntimeException('No such host found: %s', $hostname);
        }

        return $host;
    }

    protected function getServiceObject($hostname, $service)
    {
        $service = new Service(MonitoringBackend::instance(), $hostname, $service);

        if ($service->fetch() === false) {
            throw new RuntimeException(
                'No service "%s" found on host "%s"',
                $service,
                $hostname
            );
        }

        return $service;
    }
}
