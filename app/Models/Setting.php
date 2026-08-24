<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToBranch, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
