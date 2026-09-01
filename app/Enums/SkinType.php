<?php

namespace App\Enums;

enum SkinType: string
{
    case Dry = 'dry';
    case Oily = 'oily';
    case Combination = 'combination';
    case Sensitive = 'sensitive';
    case Normal = 'normal';
    case HyperPigmented = 'hyper_pigmented';

    public function label(): string
    {
        return match ($this) {
            self::Dry => 'Dry',
            self::Oily => 'Oily',
            self::Combination => 'Combination',
            self::Sensitive => 'Sensitive',
            self::Normal => 'Normal',
            self::HyperPigmented => 'Hyper-Pigmented',
        };
    }
}
