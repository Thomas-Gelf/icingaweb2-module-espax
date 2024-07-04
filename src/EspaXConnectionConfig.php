<?php

namespace gipfl\Protocol\EspaX;

use InvalidArgumentException;

class EspaXConnectionConfig
{
    /**
     * @readonly
     * @var string
     */
    public $host;

    /**
     * @readonly
     * @var string
     */
    public $port;

    /**
     * @readonly
     * @var string
     */
    public $username;

    /**
     * @readonly
     * @var string
     */
    public $password;

    /**
     * @readonly
     * @var int
     */
    public $heartbeatInterval = 5;

    /**
     * @readonly
     * @var int
     */
    public $heartbeatTimeout = 2;

    protected function __construct()
    {
    }

    public static function fromArray(array $config): EspaXConnectionConfig
    {
        $self = new static();
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'host':
                    $self->host = (string) $value;
                    break;
                case 'port':
                    $self->port = (string) $value;
                    break;
                case 'username':
                    $self->username = (string) $value;
                    break;
                case 'password':
                    $self->password = (string) $value;
                    break;
                case 'heartbeat_interval':
                    $self->heartbeatInterval = (int) $value;
                    break;
                case 'heartbeat_timeout':
                    $self->heartbeatTimeout = (int) $value;
                    break;
                default:
                    throw new InvalidArgumentException('Invalid config option: ' . $key);
            }
        }
        if ($self->host === null) {
            throw new InvalidArgumentException("'host' config is required");
        }
        if ($self->port === null) {
            throw new InvalidArgumentException("'port' config is required");
        }

        return $self;
    }
}
