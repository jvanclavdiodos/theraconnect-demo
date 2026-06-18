<?php

namespace App\Exceptions;

use RuntimeException;

class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'That time slot is no longer available for the selected clinician.')
    {
        parent::__construct($message);
    }
}
