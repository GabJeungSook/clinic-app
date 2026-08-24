<?php

namespace App\Exceptions;

use RuntimeException;

class NoSessionsRemainingException extends RuntimeException
{
    public static function forCourse(string $courseName): self
    {
        return new self("No sessions remaining on course \"{$courseName}\".");
    }
}
