<?php

namespace App\Enums;

enum MedicalHistoryType: string
{
    case Allergy = 'allergy';
    case Condition = 'condition';
    case Medication = 'medication';
    case Note = 'note';
    case Consent = 'consent';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Allergy',
            self::Condition => 'Condition',
            self::Medication => 'Medication',
            self::Note => 'Note',
            self::Consent => 'Consent',
        };
    }

    /** Types that represent a safety flag surfaced prominently on the chart. */
    public function isSafetyFlag(): bool
    {
        return in_array($this, [self::Allergy, self::Condition], true);
    }
}
