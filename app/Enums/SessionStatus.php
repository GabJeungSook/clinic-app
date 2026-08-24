<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::NoShow => 'No show',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Only completed sessions count against a treatment course's total. */
    public function countsAgainstCourse(): bool
    {
        return $this === self::Completed;
    }
}
