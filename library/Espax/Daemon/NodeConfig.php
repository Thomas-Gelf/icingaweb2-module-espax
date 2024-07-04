<?php

namespace Icinga\Module\Espax\Daemon;

use Icinga\Exception\ConfigurationError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class NodeConfig
{
    /**
     * @readonly
     * @var UuidInterface
     */
    public $uuid;

    /**
     * @readonly
     * @var int
     */
    public $number = 1;

    /**
     * @readonly
     * @var int
     */
    public $nodeCount = 1;

    protected function __construct()
    {
    }

    public static function fromArray(array $config): NodeConfig
    {
        $self = new static();
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'uuid':
                    $self->uuid = Uuid::fromString($value);
                    break;
                case 'number':
                    $self->number = (int) $value;
                    break;
                case 'node_count':
                    $self->nodeCount = (int) $value;
                    break;
                default:
                    throw new ConfigurationError('Invalid config option: ' . $key);
            }
        }
        if ($self->uuid === null) {
            throw new ConfigurationError("'uuid' is required in [node] configuration section");
        }

        return $self;
    }
}
