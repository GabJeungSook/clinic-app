<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class InventoryItem extends Model implements Auditable
{
    use AuditableTrait, BelongsToBranch, HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'is_batch_tracked' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'default_sell_price' => 'decimal:2',
            'stock_on_hand_cache' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** Live on-hand quantity from the ledger (authoritative). */
    public function stockOnHand(): float
    {
        return (float) $this->movements()->sum('quantity');
    }

    /**
     * On-hand quantity that may actually be CONSUMED — i.e. total on hand minus
     * anything sitting in an already-expired batch. Expired stock must be written
     * off, never used on a patient, so it does not count towards what is usable.
     * Non-batch-tracked items have no expiry, so their usable == on hand.
     */
    public function usableStockOnHand(?\DateTimeInterface $asOf = null): float
    {
        if (! $this->is_batch_tracked) {
            return $this->stockOnHand();
        }

        return (float) $this->batches()
            ->where(fn ($q) => $q
                ->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', $asOf ?? now()))
            ->sum('qty_remaining_cache');
    }

    /** Recompute and persist the on-hand cache; returns the value. */
    public function refreshStockCache(): float
    {
        $onHand = $this->stockOnHand();
        $this->forceFill(['stock_on_hand_cache' => $onHand])->saveQuietly();

        return $onHand;
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_on_hand_cache <= (float) $this->reorder_level;
    }
}
