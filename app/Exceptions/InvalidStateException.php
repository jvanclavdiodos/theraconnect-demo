<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an appointment state-machine transition is requested from a
 * source status that doesn't allow it (e.g. approving a `completed`,
 * `cancelled`, or `no_show` appointment). Catches the missing-guard gap
 * surfaced in the adversarial audit — previously the service happily
 * flipped a `completed` appointment back to `approved`, desyncing
 * attendance metrics and regenerating meeting links for closed cases.
 *
 * Web controllers catch this and convert to a 409 redirect-with-errors
 * response, mirroring how SlotUnavailableException is handled today.
 */
class InvalidStateException extends RuntimeException
{
    public function __construct(string $message = 'The appointment is in a state that does not allow this action.')
    {
        parent::__construct($message);
    }
}
