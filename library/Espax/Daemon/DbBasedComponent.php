<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;

interface DbBasedComponent
{
    public function initDb(Db $db): void;
    public function stopDb(): void;
}
