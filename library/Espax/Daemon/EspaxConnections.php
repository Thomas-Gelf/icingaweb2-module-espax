<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Protocol\EspaX\EspaXClient;
use gipfl\Protocol\EspaX\EspaXConnectionConfig;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Config;
use Psr\Log\LoggerInterface;
use RuntimeException;

class EspaxConnections implements DbBasedComponent
{
    /** @var EspaxConnection[] */
    protected $connections = [];

    /** @var ?EspaxConnection */
    protected $defaultConnection = null;

    /** @var ?Db */
    protected $db = null;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getConnection(?string $name = null): EspaxConnection
    {
        if ($name === null) {
            if ($this->defaultConnection === null) {
                throw new RuntimeException('There is no default ESPA-X connection available');
            }
            return $this->defaultConnection;
        }

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        throw new RuntimeException("There is no '$name' ESPA-X connection named  available");
    }

    public function setConnection(string $name, EspaxConnection $connection): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name]->stop();
            if ($this->defaultConnection === $this->connections[$name]) {
                $this->defaultConnection = null;
            }
        }
        if ($this->defaultConnection === null) {
            $this->defaultConnection = $connection;
        }

        $this->connections[$name] = $connection;
    }

    public function initDb(Db $db): void
    {
        $this->db = $db;

        $config = Config::module('espax');
        $nodeConfig = NodeConfig::fromArray($config->getSection('node')->toArray());
        $store = new Store($db, $nodeConfig);
        $packetLogger = new PacketDbLogger($store, $nodeConfig->uuid, $this->logger);
        $this->loadConnectionsFromConfig($store, $packetLogger, $this->logger);
    }

    public function stopDb(): void
    {
        foreach ($this->connections as $connection) {
            $connection->stop();
        }
        $this->connections = [];
        $this->db = null;
    }

    protected function loadConnectionsFromConfig(
        Store $store,
        PacketLogger $packetLogger,
        LoggerInterface $logger
    ): void {
        $connectionConfigs = Config::module('espax', 'connections')->toArray();
        if (empty($connectionConfigs)) {
            $logger->warning(
                'No ESPA-X connections have been configured in '.
                Config::module('espax', 'connections')->getConfigFile()
            );
        }
        // TODO: something like $processState->setComponentState('espa-x', $connectionInfo); would be nice
        foreach ($connectionConfigs as $name => $config) {
            $this->setConnection($name, new EspaxConnection(
                new EspaXClient(EspaXConnectionConfig::fromArray($config), $this->logger),
                $store,
                $packetLogger,
                $logger
            ));
        }
    }

    public function stop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->stop();
        }
    }
}
