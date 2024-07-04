<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Protocol\EspaX\Enum\RequestProperty;
use gipfl\Protocol\EspaX\Enum\ResponseProperty;
use gipfl\Protocol\EspaX\EspaXProtocol;
use gipfl\Protocol\EspaX\Packet\EspaXPacket;
use gipfl\ZfDb\Adapter\Adapter;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class PacketDbLogger implements PacketLogger
{
    const TABLE_TRACE = 'espax_packet_trace';

    /** @var Adapter */
    protected $db;

    /** @var string */
    protected $binaryNodeUuid;
    /**
     * @var Store
     */
    protected $store;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(Store $store, UuidInterface $peerUuid, LoggerInterface $logger)
    {
        $this->db = $store->getDb();
        $this->binaryNodeUuid = $peerUuid->getBytes();
        $this->store = $store;
        $this->logger = $logger;
    }

    public function log(string $direction, EspaXPacket $packet, string $rawPacket = null)
    {
        EspaXProtocol::disableValidationOnRender();
        try {
            $this->db->insert(self::TABLE_TRACE, [
                'ts'                => $this->store->uniqueNow(),
                'direction'         => $direction,
                'node_uuid'         => $this->binaryNodeUuid,
                'session_id'        => $packet->getSessionId(),
                'server_tan'        => $packet->getProperty(ResponseProperty::SP_PR_TAN),
                'problem_reference' => $packet->getProperty(RequestProperty::CP_PR_REF),
                'root_element'      => $packet->getType(),
                'packet_trace'      => $rawPacket ?: $packet->toXml(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Packet logger: ' . $e->getMessage());
        }
        EspaXProtocol::enableValidationOnRender();
    }
}
