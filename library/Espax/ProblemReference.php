<?php

namespace Icinga\Module\Espax;

use gipfl\Json\JsonSerialization;
use Stringable;

interface ProblemReference extends JsonSerialization, Stringable
{
    public function getDisplayString(): string;
}
