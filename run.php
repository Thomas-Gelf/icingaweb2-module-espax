<?php

require_once __DIR__ . '/vendor/autoload.php';

if (PHP_VERSION_ID < 80000 && !interface_exists(Stringable::class)) {
    interface Stringable
    {
        public function __toString();
    }
}
