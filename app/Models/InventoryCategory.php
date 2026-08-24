<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    use BelongsToBranch, HasUlids;

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
