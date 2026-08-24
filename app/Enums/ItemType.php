<?php

namespace App\Enums;

/**
 * Classifies a stockable inventory item.
 *  - Consumable: used up during services (cotton, syringes, gauze).
 *  - Product:    clinical products applied during services (fillers, serums).
 *  - Retail:     sold to patients over the counter.
 *  - Medication: drugs / injectables requiring tighter tracking.
 */
enum ItemType: string
{
    case Consumable = 'consumable';
    case Product = 'product';
    case Retail = 'retail';
    case Medication = 'medication';

    public function label(): string
    {
        return match ($this) {
            self::Consumable => 'Consumable',
            self::Product => 'Clinical product',
            self::Retail => 'Retail product',
            self::Medication => 'Medication',
        };
    }
}
