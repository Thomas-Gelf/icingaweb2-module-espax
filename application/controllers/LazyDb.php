<?php

namespace Icinga\Module\Espax\Controllers;

use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use Icinga\Module\Espax\Db\DbFactory;

trait LazyDb
{
    protected $db = null;

    protected function db(): ZfDb
    {
        if ($this->db === null) {
            $this->db = DbFactory::db();
        }

        return $this->db;
    }
}
