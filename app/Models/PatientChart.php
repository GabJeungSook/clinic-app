<?php

namespace App\Models;

use App\Enums\FaceShape;
use App\Enums\SkinType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class PatientChart extends Model implements Auditable
{
    use AuditableTrait, BelongsToBranch, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'history_flags' => 'array',
            'procedures_done' => 'array',
            'lifestyle' => 'array',
            'derma_history' => 'array',
            'initial_plan' => 'array',
            'physician_notes' => 'array',
            'assessment_conditions' => 'array',
            'beauty_plan' => 'array',
            'skin_type' => SkinType::class,
            'face_shape' => FaceShape::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
