<?php

namespace App\Enums;

/**
 * What a promotion applies to. `Invoice` = whole-invoice discount;
 * the others restrict the promotion to matching invoice lines.
 */
enum PromotionScope: string
{
    case Invoice = 'invoice';
    case Service = 'service';
    case Item = 'item';
    case Category = 'category';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Whole invoice',
            self::Service => 'Specific service',
            self::Item => 'Specific item',
            self::Category => 'Category',
        };
    }
}
