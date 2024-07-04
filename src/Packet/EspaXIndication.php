<?php

namespace gipfl\Protocol\EspaX\Packet;

class EspaXIndication implements EspaXPacket
{
    use SimpleEspaXPacket;

    protected $requiresInvocationId = false;

    public function getIndicationType(): string
    {
        return substr($this->type, 4);
    }
}
