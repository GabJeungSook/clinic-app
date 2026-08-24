<?php

namespace App\Actions\Patients;

use App\Models\Patient;
use App\Support\DocumentNumber;

class CreatePatient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Patient
    {
        if (empty($data['code'])) {
            $data['code'] = DocumentNumber::next(Patient::query(), 'P', 'code', 4);
        }

        return Patient::create($data);
    }
}
