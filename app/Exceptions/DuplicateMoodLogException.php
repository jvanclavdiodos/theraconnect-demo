<?php

namespace App\Exceptions;

use App\Models\MoodLog;
use RuntimeException;

class DuplicateMoodLogException extends RuntimeException
{
    public function __construct(public readonly MoodLog $moodLog)
    {
        parent::__construct('Today\'s mood check-in has already been completed.');
    }
}
