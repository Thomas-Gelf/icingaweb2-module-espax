<?php

namespace gipfl\Protocol\EspaX\Packet;

use SimpleXMLElement;

interface EspaXPacket
{
    public function getType(): string;
    public function getProperty(string $property, ?string $default = null): ?string;
    public function requireProperty(string $property): string;
    public function getProperties(): array;
    public function hasSessionId(): bool;
    public function setSessionId(string $sessionId): void;
    public function getSessionId(): ?string;
    public function toXml(): string;
    public static function fromSimpleXml(string $type, SimpleXMLElement $espa): EspaXPacket;
}
