<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /** Statuses still on the books (upcoming / actionable). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }
}
