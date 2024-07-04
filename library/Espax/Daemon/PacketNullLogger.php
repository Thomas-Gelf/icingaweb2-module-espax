<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Protocol\EspaX\Packet\EspaXPacket;

class PacketNullLogger implements PacketLogger
{
    public function log(string $direction, EspaXPacket $packet)
    {
    }
}
