<?php

namespace gipfl\Protocol\EspaX\Packet;

class EspaXCommand implements EspaXPacket
{
    use SimpleEspaXPacket;

    public function getCommandType(): string
    {
        return substr($this->type, 4);
    }
}
