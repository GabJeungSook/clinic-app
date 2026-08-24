<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(string $itemName, float $requested, float $available): self
    {
        return new self(sprintf(
            'Insufficient stock for "%s": requested %s, available %s.',
            $itemName,
            rtrim(rtrim(number_format($requested, 3), '0'), '.'),
            rtrim(rtrim(number_format($available, 3), '0'), '.'),
        ));
    }
}
