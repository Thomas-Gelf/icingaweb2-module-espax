<?php

namespace Icinga\Module\Espax\Clicommands;

use gipfl\Protocol\EspaX\Packet\PacketFactory;
use Icinga\Module\Espax\Daemon\NodeConfig;
use Icinga\Module\Espax\Daemon\PacketDbLogger;
use Icinga\Module\Espax\Daemon\PacketDirection;
use Icinga\Module\Espax\Daemon\Store;
use Icinga\Module\Espax\Db\DbFactory;

/**
 * @api
 */
class LabCommand extends Command
{
    public function traceloadAction()
    {
        $db = DbFactory::db();
        $config = $this->Config();
        $nodeConfig = NodeConfig::fromArray($config->getSection('node')->toArray());
        $store = new Store($db, $nodeConfig);
        $packetLogger = new PacketDbLogger($store, $nodeConfig->uuid, $this->logger);
        $files = [
            '2023-08-29_09-47-53-0808_REQ.LOGIN.xml',
            '2023-08-29_09-47-53-0920_RSP.LOGIN.xml',
            '2023-08-29_09-47-56-0886_REQ.P-START.xml',
            '2023-08-29_09-47-56-0964_IND.P-STARTED.xml',
            '2023-08-29_09-47-56-0964_RSP.P-START.xml',
            '2023-08-29_09-48-05-0981_REQ.HEARTBEAT.xml',
            '2023-08-29_09-48-06-0077_RSP.HEARTBEAT.xml',
            '2023-08-29_09-48-06-0904_IND.P-EVENT.xml',
            '2023-08-29_09-48-08-0245_IND.P-EVENT.xml',
            '2023-08-29_09-48-08-0920_IND.P-EVENT.xml',
            '2023-08-29_09-48-16-0291_IND.P-EVENT.xml',
            '2023-08-29_09-48-19-0275_IND.P-EVENT.xml',
        ];
        foreach ($files as $file) {
            $filename = dirname(__DIR__, 2) . '/lab-log/' . $file;
            $content = file_get_contents($filename);
            // $content = file_get_contents();
            $packet = PacketFactory::packetFromSimpleXml(simplexml_load_string($content), $this->logger);
            $packetLogger->log(PacketDirection::INBOUND, $packet, $content);
        }
    }
}
