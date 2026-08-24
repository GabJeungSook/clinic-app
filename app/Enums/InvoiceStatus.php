<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
            self::Refunded => 'Refunded',
        };
    }

    /** Statuses that still expect money to be collected. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Unpaid, self::PartiallyPaid], true);
    }
}
