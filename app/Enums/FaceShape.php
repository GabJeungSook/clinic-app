<?php

namespace App\Enums;

enum FaceShape: string
{
    case Square = 'square';
    case Round = 'round';
    case Pear = 'pear';
    case Oblong = 'oblong';
    case Oval = 'oval';
    case Rectangle = 'rectangle';
    case Triangle = 'triangle';
    case Diamond = 'diamond';
    case Heart = 'heart';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square',
            self::Round => 'Round',
            self::Pear => 'Pear',
            self::Oblong => 'Oblong',
            self::Oval => 'Oval',
            self::Rectangle => 'Rectangle',
            self::Triangle => 'Triangle',
            self::Diamond => 'Diamond',
            self::Heart => 'Heart',
        };
    }
}
