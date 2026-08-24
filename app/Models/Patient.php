<?php

namespace App\Models;

use App\Enums\Sex;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Patient extends Model implements Auditable
{
    use AuditableTrait, BelongsToBranch, HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'sex' => Sex::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function medicalHistories(): HasMany
    {
        return $this->hasMany(MedicalHistory::class);
    }

    public function treatmentCourses(): HasMany
    {
        return $this->hasMany(TreatmentCourse::class);
    }

    public function treatmentSessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Allergies and chronic conditions surfaced as safety flags on the chart. */
    public function safetyFlags(): HasMany
    {
        return $this->hasMany(MedicalHistory::class)
            ->whereIn('type', ['allergy', 'condition']);
    }
}
