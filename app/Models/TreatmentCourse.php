<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\SessionStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class TreatmentCourse extends Model implements Auditable
{
    use AuditableTrait, BelongsToBranch, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected $appends = ['sessions_completed', 'sessions_remaining'];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'price_snapshot' => 'decimal:2',
            'purchased_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }

    /**
     * Completed sessions are the ONLY source of truth for consumption of the
     * course. Uses the eager-loaded relation when present to avoid N+1.
     */
    protected function sessionsCompleted(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->relationLoaded('sessions')) {
                    return $this->sessions
                        ->where('status', SessionStatus::Completed)
                        ->count();
                }

                return $this->sessions()
                    ->where('status', SessionStatus::Completed->value)
                    ->count();
            }
        );
    }

    protected function sessionsRemaining(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, $this->total_sessions - $this->sessions_completed),
        );
    }

    public function isFullyConsumed(): bool
    {
        return $this->sessions_remaining <= 0;
    }
}
