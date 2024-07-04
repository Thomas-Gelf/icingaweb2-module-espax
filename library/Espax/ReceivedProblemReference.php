<?php

namespace Icinga\Module\Espax;

use gipfl\Json\JsonString;

class ReceivedProblemReference implements ProblemReference
{
    /** @var string */
    protected $reference;

    public function __construct(string $reference)
    {
        $this->reference = $reference;
    }

    public function __toString(): string
    {
        return $this->reference;
    }

    public function jsonSerialize()
    {
        return JsonString::encode((object) [
            'reference' => $this->reference,
        ]);
    }

    public static function fromSerialization($any): ProblemReference
    {
        return new ReceivedProblemReference($any->reference);
    }

    public function getDisplayString(): string
    {
        return $this->reference;
    }
}
