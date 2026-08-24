<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use BelongsToBranch, HasUlids;

    protected $guarded = [];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
