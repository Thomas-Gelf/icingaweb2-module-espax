<?php

namespace Icinga\Module\Espax\Daemon;

use Icinga\Module\Espax\Icinga\IcingaProblemReference;
use React\Promise\PromiseInterface;

class RpcNamespaceEspaX
{
    /** @var EspaxConnections */
    protected $connections;

    public function __construct(EspaxConnections $connections)
    {
        $this->connections = $connections;
    }

    /**
     * @param string $host
     * @param string $service // or null, which is not yet supported by NamespacedPacketHandler
     * @param string $destination
     * @param string $message
     * @param string $connection // or null, which is not yet supported by NamespacedPacketHandler
     * @param int $timeout // or null, which is not yet supported by NamespacedPacketHandler
     */
    public function sendIcingaProblemRequest(
        string $host,
        ?string $service,
        string $destination,
        string $message,
        ?string $connection,
        ?int $timeout
    ): PromiseInterface {
        return $this->connections->getConnection($connection)->sendNotification(
            new IcingaProblemReference($host, $service),
            $destination,
            $message,
            $timeout
        );
    }

    /**
     * @param string $host
     * @param string $service // or null, which is not yet supported by NamespacedPacketHandler
     */
    public function recoverIcingaProblemRequest(
        string $host,
        ?string $service,
        ?string $connection
    ): PromiseInterface {
        return $this->connections->getConnection($connection)->sendNotification(
            new IcingaProblemReference($host, $service),
            $destination,
            $message
        );
    }
}
