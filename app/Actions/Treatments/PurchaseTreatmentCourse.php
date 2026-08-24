<?php

namespace App\Actions\Treatments;

use App\Enums\CourseStatus;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentCourse;

/**
 * Create a purchased treatment course for a patient from a service, snapshotting
 * the service name and price so later catalog edits never rewrite history.
 */
class PurchaseTreatmentCourse
{
    public function handle(
        Patient $patient,
        Service $service,
        ?int $totalSessions = null,
        ?float $price = null,
        ?\DateTimeInterface $purchasedAt = null,
        ?\DateTimeInterface $expiresAt = null,
        ?string $notes = null,
    ): TreatmentCourse {
        return TreatmentCourse::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => $price ?? (float) $service->default_price,
            'total_sessions' => $totalSessions ?? (int) $service->default_session_count,
            'status' => CourseStatus::Active,
            'purchased_at' => $purchasedAt ?? now(),
            'expires_at' => $expiresAt,
            'notes' => $notes,
        ]);
    }
}
