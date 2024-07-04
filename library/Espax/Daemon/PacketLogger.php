<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\Protocol\EspaX\Packet\EspaXPacket;

interface PacketLogger
{
    public function log(string $direction, EspaXPacket $packet);
}
