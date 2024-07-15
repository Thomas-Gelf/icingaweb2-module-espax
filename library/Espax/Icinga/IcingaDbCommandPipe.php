<?php

namespace Icinga\Module\Espax\Icinga;

use ArrayIterator;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingadb\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Icingadb\Command\Transport\CommandTransport;
use Icinga\Module\Icingadb\Command\Transport\CommandTransportException;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Orm\Model;
use ipl\Stdlib\Filter;
use RuntimeException;

class IcingaDbCommandPipe
{
    use Database;

    public function acknowledge(string $author, string $message, string $hostname, ?string $serviceName = null)
    {
        if ($serviceName === null) {
            $query = Host::on($this->getDb())->with(['state']);
            $query->filter(Filter::equal('host.name', $hostname));

            $object = $query->first();
            if ($object === null) {
                throw new NotFoundError(t("Host not found: $hostname"));
            } else {
                assert($object instanceof Host);
            }
        } else {
            $query = Service::on($this->getDb())->with([
                'state',
                'icon_image',
                'host',
                'host.state',
                'timeperiod'
            ]);
            $query->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostname)
            ));

            $object = $query->first();
            if ($object === null) {
                throw new NotFoundError("Service '$serviceName' on '$hostname' not found");
            } else {
                assert($object instanceof Service);
            }
        }

        return $this->acknowledgeObject($author, $message, $object);
    }

    public function acknowledgeObject($author, $message, Model $object)
    {
        /** @var Service $object */
        if ($object->state->is_acknowledged) {
            return false;
        }

        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObjects(new ArrayIterator([$object]))
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
}
