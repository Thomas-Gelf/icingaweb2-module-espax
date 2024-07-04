<?php

namespace gipfl\Protocol\EspaX\Packet;

use gipfl\Protocol\EspaX\Enum\EspaXResponseCode;

class EspaXResponse implements EspaXPacket
{
    use SimpleEspaXPacket;

    /** @var ?string|int */
    public $invokeId;

    public function getResponseType(): string
    {
        return substr($this->type, 4);
    }

    public function getCode(): EspaXResponseCode
    {
        return new EspaXResponseCode((int) $this->requireProperty('RSP-CODE'));
    }

    public function getReason(): string
    {
        return $this->requireProperty('RSP-REASON');
    }
}
