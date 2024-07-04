<?php

namespace Icinga\Module\Espax\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;

interface DbBasedComponent
{
    public function initDb(Db $db): void;
    public function stopDb(): void;
}
