<?php

namespace gipfl\Protocol\EspaX\Packet;

class EspaXRequest implements EspaXPacket
{
    use SimpleEspaXPacket;

    /** @var ?string|int */
    public $invokeId;

    public function getRequestType(): string
    {
        return substr($this->type, 4);
    }
}
