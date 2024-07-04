<?php

namespace gipfl\Protocol\EspaX\Packet;

use DateTime;
use gipfl\Protocol\EspaX\Enum\EspaXElementProperty;
use gipfl\Protocol\EspaX\EspaXProtocol;
use RuntimeException;
use SimpleXMLElement;

trait SimpleEspaXPacket
{
    /** @var ?string 1-32 characters */
    public $sessionId;

    protected $properties = [];

    /** @var string */
    protected $type;

    public function __construct(string $type, array $properties = [])
    {
        $this->type = $type;
        $this->properties = $properties;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getProperty(string $property, ?string $default = null): ?string
    {
        if (array_key_exists($property, $this->properties)) {
            return $this->properties[$property];
        }

        return $default;
    }

    public function requireProperty(string $property): string
    {
        if (array_key_exists($property, $this->properties)) {
            return $this->properties[$property];
        }

        throw new RuntimeException(sprintf('There is no %s property in this packet', $property));
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function hasSessionId(): bool
    {
        return $this->sessionId !== null;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function toXml(): string
    {
        $now = new DateTime();
        $espa = new SimpleXMLElement('<' . '?xml version="1.0"?' . '>' . "\n" . sprintf(
            '<ESPA-X xmlns="%s" version="%s"></ESPA-X>',
            EspaXProtocol::ESPA_NAMESPACE,
            EspaXProtocol::ESPA_VERSION
        ));
        $espa->addAttribute(EspaXElementProperty::TIMESTAMP, $now->format('Y-m-d\TH:i:s')); // 2023-08-29T11:47:53

        $request = $espa->addChild($this->type);
        if (isset($this->invokeId) && $this) { // TODO: ??
            $request->addAttribute(EspaXElementProperty::INVOKE_ID, $this->invokeId);
        }
        if (isset($this->sessionId)) {
            $request->addAttribute(EspaXElementProperty::SESSION_ID, $this->sessionId);
        }

        foreach ($this->properties as $property => $value) {
            $request->addChild($property, $value);
        }

        return EspaXProtocol::renderSimpleXml($espa);
    }

    /**
     * @return static|EspaXPacket
     */
    public static function fromSimpleXml(string $type, SimpleXMLElement $espa): EspaXPacket
    {
        $properties = [];
        foreach ($espa as $key => $value) {
            $properties[$key] = (string) $value;
        }
        $self = new static($type, $properties);
        if (isset($espa[EspaXElementProperty::INVOKE_ID])) {
            if (property_exists($self, 'invokeId')) {
                // TODO: this is an unsigned long, we only support signed long
                $self->invokeId = (int) $espa[EspaXElementProperty::INVOKE_ID];
            }
        }
        if (isset($espa[EspaXElementProperty::SESSION_ID])) {
            if (property_exists($self, 'sessionId')) {
                $self->sessionId = (string) $espa[EspaXElementProperty::SESSION_ID];
            }
        }

        return $self;
    }
}
