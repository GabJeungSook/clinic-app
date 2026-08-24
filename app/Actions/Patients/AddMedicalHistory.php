<?php

namespace App\Actions\Patients;

use App\Enums\MedicalHistoryType;
use App\Models\MedicalHistory;
use App\Models\Patient;

class AddMedicalHistory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Patient $patient, array $data, ?int $recordedBy = null): MedicalHistory
    {
        return $patient->medicalHistories()->create([
            'recorded_by' => $recordedBy,
            'type' => $data['type'] ?? MedicalHistoryType::Note,
            'title' => $data['title'],
            'details' => $data['details'] ?? null,
            'attributes' => $data['attributes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }
}
