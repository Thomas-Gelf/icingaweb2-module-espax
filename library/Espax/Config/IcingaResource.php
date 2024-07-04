<?php

namespace Icinga\Module\Espax\Config;

use gipfl\DataType\Settings;
use Icinga\Application\Config;
use InvalidArgumentException;

class IcingaResource
{
    /** @var Config|null */
    protected static $resources = null;

    /**
     * @return string[]
     */
    public static function listResourceNames(): array
    {
        return self::getResources()->keys();
    }

    public static function requireResourceConfig(string $name, ?string $enforcedType = null): Settings
    {
        self::assertResourceExists($name);
        $section = self::getResources()->getSection($name);
        if ($enforcedType !== null && $section->get('type') !== $enforcedType) {
            throw new InvalidArgumentException(sprintf(
                "Resource of type '%s' required, but '%s' is '%s'",
                $enforcedType,
                $name,
                $section->get('type')
            ));
        }

        return Settings::fromSerialization($section->toArray());
    }

    public static function assertResourceExists(string $name): void
    {
        if (! self::getResources()->hasSection($name)) {
            throw new InvalidArgumentException("There is no resource named '$name'");
        }
    }

    public static function forgetConfig(): void
    {
        self::$resources = null;
    }

    protected static function getResources(): Config
    {
        if (self::$resources === null) {
            self::$resources = Config::app('resources');
        }

        return self::$resources;
    }
}
