<?php

namespace Icinga\Module\Espax\Icinga;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Espax\ProblemReference;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class IcingaProblemReference implements ProblemReference
{
    use TranslationHelper;

    const NS_UUID = 'b6f99902-4e2f-4fab-b7aa-1e55f328b325';

    /** @var string */
    protected $host;

    /** @var ?string */
    protected $service;

    /** @var ?UuidInterface */
    private static $nsUuid = null;

    public function __construct(string $host, ?string $service = null)
    {
        $this->host = $host;
        $this->service = $service;
    }

    public function getReferenceKey(): string
    {
        return str_replace('-', '', $this->getReferenceUuid()->toString());
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function jsonSerialize(): object
    {
        if ($this->service === null) {
            return (object) ['host' => $this->host];
        }

        return (object) [
            'host'    => $this->host,
            'service' => $this->service,
        ];
    }

    public function __toString()
    {
        return self::getReferenceKey();
    }

    public function getDisplayString(): string
    {
        if ($this->service === null) {
            return sprintf($this->translate('%s (host problem)'), $this->host);
        }

        return sprintf($this->translate('%s on %s'), $this->service, $this->host);
    }

    public static function fromSerialization($any): ProblemReference
    {
        return new IcingaProblemReference($any->host, $any->service ?? null);
    }

    protected function getReferenceUuid(): UuidInterface
    {
        if ($this->service === null) {
            return Uuid::uuid5(self::getNamespaceUuid(), $this->host);
        }

        return Uuid::uuid5(self::getNamespaceUuid(), $this->host . '!' . $this->service);
    }

    protected static function getNamespaceUuid(): UuidInterface
    {
        if (self::$nsUuid === null) {
            self::$nsUuid = Uuid::fromString(self::NS_UUID);
        }

        return self::$nsUuid;
    }
}
