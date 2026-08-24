<?php

namespace App\Models;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use BelongsToBranch, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'applies_to' => PromotionScope::class,
            'value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** Whether the promotion may be applied on the given date. */
    public function isValidOn(?\DateTimeInterface $date = null): bool
    {
        $date = $date ?? now();

        if (! $this->is_active) {
            return false;
        }
        if ($this->valid_from && $date < $this->valid_from) {
            return false;
        }
        if ($this->valid_to && $date > $this->valid_to->endOfDay()) {
            return false;
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /** Discount amount this promotion yields for a given base amount. */
    public function discountFor(float $base): float
    {
        $discount = $this->type === PromotionType::Percent
            ? $base * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $base), 2);
    }
}
