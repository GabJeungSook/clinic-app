<?php

namespace App\Actions\Treatments;

use App\Enums\SessionStatus;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\TreatmentSession;

/**
 * Create a treatment session record. Either attached to a multi-session course
 * or ad-hoc (single service). Defaults to scheduled; the front desk can create
 * a booking-like row now and complete it when performed.
 */
class StartTreatmentSession
{
    public function handle(
        Patient $patient,
        Service $service,
        ?TreatmentCourse $course = null,
        SessionStatus $status = SessionStatus::Scheduled,
        ?int $performedBy = null,
        ?\DateTimeInterface $scheduledAt = null,
        ?string $clinicalNotes = null,
    ): TreatmentSession {
        return TreatmentSession::create([
            'treatment_course_id' => $course?->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'performed_by' => $performedBy,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'clinical_notes' => $clinicalNotes,
        ]);
    }
}
