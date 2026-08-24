<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use BelongsToBranch, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'unit_cost' => 'decimal:4',
            'initial_quantity' => 'decimal:3',
            'qty_remaining_cache' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Live remaining quantity for this batch from the ledger. */
    public function qtyRemaining(): float
    {
        return (float) $this->movements()->sum('quantity');
    }

    public function refreshRemainingCache(): float
    {
        $remaining = $this->qtyRemaining();
        $this->forceFill(['qty_remaining_cache' => $remaining])->saveQuietly();

        return $remaining;
    }

    public function isExpired(?\DateTimeInterface $asOf = null): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->isBefore($asOf ?? now());
    }
}
