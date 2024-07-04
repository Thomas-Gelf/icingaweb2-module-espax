<?php

namespace Icinga\Module\Espax\Db;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Espax\Config\IcingaResource;

class DbFactory
{
    /**
     * @var Db|null
     */
    protected static $db = null;

    public static function db(): Db
    {
        if (self::$db === null) {
            if ($name =Config::module('espax')->get('db', 'resource')) {
                self::$db = ZfDbConnectionFactory::connection(
                    IcingaResource::requireResourceConfig($name, 'db')
                );
            } else {
                throw new ConfigurationError(sprintf(
                    'Found no "%s" in the "[%s]" section of %s',
                    'resource',
                    'db',
                    Config::module('espax')->getConfigFile()
                ));
            }
        }
        return self::$db;
    }
}
