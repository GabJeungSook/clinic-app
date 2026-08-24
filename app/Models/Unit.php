<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    use BelongsToBranch, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'factor_to_base' => 'decimal:6',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * Convert a quantity expressed in THIS unit into the item base unit.
     * e.g. 2 boxes (factor 100) -> 200 pieces.
     */
    public function toBase(float|string $quantity): float
    {
        return (float) $quantity * (float) $this->factor_to_base;
    }
}
