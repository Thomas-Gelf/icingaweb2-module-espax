<?php

namespace Icinga\Module\Espax\Icinga;

use Icinga\Module\Espax\ProblemReference;

class SimpleNotification
{
    /** @var ProblemReference */
    public $reference;

    /** @var string */
    public $destination;

   /** @var string */
    public $message;

    public function __construct(ProblemReference $reference, string $destination, string $message)
    {
        $this->reference = $reference;
        $this->destination = $destination;
        $this->message = $message;
    }
}
