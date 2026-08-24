<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class TreatmentSession extends Model implements Auditable
{
    use AuditableTrait, BelongsToBranch, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_at' => 'datetime',
            'performed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TreatmentCourse::class, 'treatment_course_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(TreatmentSessionConsumption::class);
    }
}
