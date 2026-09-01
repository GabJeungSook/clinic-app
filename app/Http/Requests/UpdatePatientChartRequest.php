<?php

namespace App\Http\Requests;

use App\Enums\FaceShape;
use App\Enums\Sex;
use App\Enums\SkinType;
use App\Support\Chart\ChartOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePatientChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('patients.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            // Demographics that live on the patient record. `sometimes` so a
            // single-card save that omits them never triggers required errors.
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('patients', 'code')
                    ->where('branch_id', $this->user()?->branch_id)
                    ->ignore($patientId),
            ],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'sex' => ['sometimes', 'nullable', new Enum(Sex::class)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'civil_status' => ['nullable', 'string', 'max:50'],

            // Single-select clinical fields.
            'skin_type' => ['nullable', new Enum(SkinType::class)],
            'face_shape' => ['nullable', new Enum(FaceShape::class)],
            'findings' => ['nullable', 'string'],
            'medical_record' => ['nullable', 'string'],

            // Per-section free-text notes.
            'procedures_notes' => ['nullable', 'string'],
            'lifestyle_notes' => ['nullable', 'string'],
            'initial_plan_notes' => ['nullable', 'string'],
            'assessment_notes' => ['nullable', 'string'],
            'beauty_plan_notes' => ['nullable', 'string'],

            // Patient history intake checklists.
            'history_flags' => ['nullable', 'array'],
            'history_flags.have' => ['array'],
            'history_flags.have.*' => ['string', Rule::in(ChartOptions::keys(ChartOptions::HAVE))],
            'history_flags.have_others' => ['nullable', 'string', 'max:255'],
            'history_flags.taking' => ['array'],
            'history_flags.taking.*' => ['string', Rule::in(ChartOptions::keys(ChartOptions::TAKING))],
            'history_flags.taking_others' => ['nullable', 'string', 'max:255'],
            'history_flags.condition' => ['array'],
            'history_flags.condition.*' => ['string', Rule::in(ChartOptions::keys(ChartOptions::CONDITION))],
            'history_flags.condition_others' => ['nullable', 'string', 'max:255'],

            // Aesthetic procedures done.
            'procedures_done' => ['nullable', 'array'],
            'procedures_done.*.done' => ['boolean'],
            'procedures_done.*.when' => ['nullable', 'string', 'max:100'],

            // Lifestyle check.
            'lifestyle' => ['nullable', 'array'],
            'lifestyle.avg_sleep' => ['nullable', 'string', 'max:50'],
            'lifestyle.eating_habits' => ['nullable', 'string', 'max:255'],
            'lifestyle.exercise' => ['nullable', 'boolean'],
            'lifestyle.past_medical_history' => ['nullable', 'string'],
            'lifestyle.previous_surgery' => ['nullable', 'string'],

            // Derma history.
            'derma_history' => ['nullable', 'array'],
            'derma_history.had_consult' => ['nullable', 'boolean'],
            'derma_history.reason' => ['nullable', 'string', 'max:255'],
            'derma_history.when' => ['nullable', 'string', 'max:100'],

            // Initial plan.
            'initial_plan' => ['nullable', 'array'],
            'initial_plan.items' => ['array'],
            'initial_plan.items.*' => ['string', Rule::in(ChartOptions::keys(ChartOptions::INITIAL_PLAN))],
            'initial_plan.items_others' => ['nullable', 'string', 'max:255'],

            // Physician notes (repeatable rows).
            'physician_notes' => ['nullable', 'array'],
            'physician_notes.*.observations' => ['nullable', 'string'],
            'physician_notes.*.test_ordered' => ['nullable', 'string'],
            'physician_notes.*.results' => ['nullable', 'string'],
            'physician_notes.*.additional_notes' => ['nullable', 'string'],

            // Doctor's assessment.
            'assessment_conditions' => ['nullable', 'array'],
            'assessment_conditions.conditions' => ['array'],
            'assessment_conditions.conditions.*' => ['string', Rule::in(ChartOptions::keys(ChartOptions::ASSESSMENT))],
            'assessment_conditions.conditions_others' => ['nullable', 'string', 'max:255'],

            // Beauty plan (repeatable rows).
            'beauty_plan' => ['nullable', 'array'],
            'beauty_plan.*.procedure' => ['nullable', 'string', 'max:255'],
            'beauty_plan.*.price' => ['nullable', 'numeric', 'min:0'],
            'beauty_plan.*.timeline' => ['nullable', 'string', 'max:100'],
        ];
    }
}
