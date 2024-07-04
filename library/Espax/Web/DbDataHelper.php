<?php

namespace Icinga\Module\Espax\Web;

use gipfl\Json\JsonString;
use Icinga\Module\Espax\ProblemReference;

class DbDataHelper
{
    public static function describeProblemReference(object $notification): string
    {
        $refClass = $notification->problem_reference_implementation;
        if (class_exists($refClass)) {
            /** @var class-string|ProblemReference $refClass */
            return $refClass::fromSerialization(
                JsonString::decode($notification->problem_reference_details)
            )->getDisplayString();
        }

        return $notification->problem_reference;
    }
}
