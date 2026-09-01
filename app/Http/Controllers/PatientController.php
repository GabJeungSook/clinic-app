<?php

namespace App\Http\Controllers;

use App\Actions\Patients\AddMedicalHistory;
use App\Actions\Patients\CreatePatient;
use App\Enums\MedicalHistoryType;
use App\Enums\Sex;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientChartRequest;
use App\Models\Patient;
use App\Models\PatientChart;
use App\Support\Chart\ChartOptions;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);

        $search = trim((string) $request->query('search', ''));

        $patients = Patient::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'sex' => $p->sex?->value,
                'created_at' => $p->created_at?->toDateString(),
            ]);

        return Inertia::render('Patients/Index', [
            'patients' => $patients,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Patients/Create', [
            'sexes' => $this->sexOptions(),
        ]);
    }

    public function store(StorePatientRequest $request, CreatePatient $createPatient): RedirectResponse
    {
        $patient = $createPatient->handle($request->validated());

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient created.');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        $patient->load([
            'chart',
            'medicalHistories' => fn ($q) => $q->latest('recorded_at'),
            'treatmentCourses' => fn ($q) => $q
                ->with(['service:id,name', 'sessions' => fn ($s) => $s->with('performer:id,name')->orderByDesc('session_number')])
                ->latest('purchased_at'),
            'invoices' => fn ($q) => $q->with(['items', 'payments'])->latest('issued_at'),
        ]);

        return Inertia::render('Patients/Show', [
            'meta' => $this->chartMeta(),
            'sexes' => $this->sexOptions(),
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->code,
                'full_name' => $patient->full_name,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'sex' => $patient->sex?->value,
                'occupation' => $patient->occupation,
                'civil_status' => $patient->civil_status,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
                'notes' => $patient->notes,
            ],
            'chart' => $this->chartData($patient->chart),
            'options' => ChartOptions::forFrontend(),
            'safetyFlags' => $patient->medicalHistories
                ->filter(fn ($h) => $h->type->isSafetyFlag())
                ->map(fn ($h) => ['type' => $h->type->value, 'title' => $h->title])
                ->values(),
            'courses' => $patient->treatmentCourses->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name_snapshot,
                'service' => $c->service?->name,
                'status' => $c->status->value,
                'total_sessions' => $c->total_sessions,
                'sessions_completed' => $c->sessions_completed,
                'sessions_remaining' => $c->sessions_remaining,
                'purchased_at' => $c->purchased_at?->toDateString(),
                'price' => (float) $c->price_snapshot,
                'sessions' => $c->sessions->map(fn ($s) => [
                    'id' => $s->id,
                    'number' => $s->session_number,
                    'status' => $s->status->value,
                    'performed_at' => $s->performed_at?->toDateTimeString(),
                    'scheduled_at' => $s->scheduled_at?->toDateTimeString(),
                    'by' => $s->performer?->name,
                    'notes' => $s->clinical_notes,
                ])->values(),
            ]),
            'invoices' => $patient->invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'status' => $i->status->value,
                'subtotal' => (float) $i->subtotal,
                'discount_total' => (float) $i->discount_total,
                'tax_total' => (float) $i->tax_total,
                'grand_total' => (float) $i->grand_total,
                'amount_paid' => (float) $i->amount_paid,
                'issued_at' => $i->issued_at?->toDateString(),
                'items' => $i->items->map(fn ($it) => [
                    'description' => $it->description_snapshot,
                    'quantity' => (float) $it->quantity,
                    'unit_price' => (float) $it->unit_price,
                    'line_total' => (float) $it->line_total,
                ])->values(),
                'payments' => $i->payments->map(fn ($pm) => [
                    'amount' => (float) $pm->amount,
                    'method' => $pm->method?->value,
                    'paid_at' => $pm->paid_at?->toDateString(),
                ])->values(),
            ]),
        ]);
    }

    public function edit(Patient $patient): Response
    {
        $this->authorize('update', $patient);

        return Inertia::render('Patients/Edit', [
            'patient' => $patient->only([
                'id', 'code', 'first_name', 'last_name', 'date_of_birth',
                'sex', 'phone', 'email', 'address', 'notes',
                'emergency_contact_name', 'emergency_contact_phone',
            ]),
            'sexes' => $this->sexOptions(),
        ]);
    }

    public function update(StorePatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient updated.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);
        $patient->delete();

        return redirect()->route('patients.index')->with('success', 'Patient removed.');
    }

    public function storeHistory(Request $request, Patient $patient, AddMedicalHistory $addHistory): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'type' => ['required', \Illuminate\Validation\Rule::enum(MedicalHistoryType::class)],
            'title' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string'],
        ]);

        $addHistory->handle($patient, $data, $request->user()?->id);

        return redirect()->route('patients.show', $patient)->with('success', 'History added.');
    }

    public function updateChart(UpdatePatientChartRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validated();

        // Only touch the fields actually submitted, so a single-card save never
        // wipes the other cards.
        $patientColumns = [
            'first_name', 'last_name', 'code', 'date_of_birth', 'sex',
            'phone', 'email', 'address', 'emergency_contact_name',
            'emergency_contact_phone', 'occupation', 'civil_status',
        ];
        $patientData = Arr::only($data, $patientColumns);
        if ($patientData !== []) {
            $patient->update($patientData);
        }

        $chartData = Arr::except($data, $patientColumns);
        if ($chartData !== []) {
            $patient->chart()->updateOrCreate([], $chartData);
        }

        return redirect()->route('patients.show', $patient)->with('success', 'Chart updated.');
    }

    /** @return array<int, array{value:string, label:string}> */
    private function sexOptions(): array
    {
        return collect(Sex::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all();
    }

    /** Metadata for the printable chart letterhead (mirrors the reports header). */
    private function chartMeta(): array
    {
        return [
            'clinic' => Settings::get('clinic.name'),
            'address' => Settings::get('clinic.address'),
            'phone' => Settings::get('clinic.phone'),
            'generated_at' => now()->format('F j, Y g:iA'),
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ];
    }

    /**
     * The chart shaped for the front end, with safe defaults so a patient with
     * no chart yet renders (and edits) as empty rather than crashing.
     *
     * @return array<string, mixed>
     */
    private function chartData(?PatientChart $chart): array
    {
        $procedures = $chart?->procedures_done ?? [];

        return [
            'history_flags' => [
                'have' => $chart?->history_flags['have'] ?? [],
                'have_others' => $chart?->history_flags['have_others'] ?? null,
                'taking' => $chart?->history_flags['taking'] ?? [],
                'taking_others' => $chart?->history_flags['taking_others'] ?? null,
                'condition' => $chart?->history_flags['condition'] ?? [],
                'condition_others' => $chart?->history_flags['condition_others'] ?? null,
            ],
            'procedures_done' => collect(ChartOptions::PROCEDURES)
                ->mapWithKeys(fn ($label, $key) => [$key => [
                    'done' => (bool) ($procedures[$key]['done'] ?? false),
                    'when' => $procedures[$key]['when'] ?? null,
                ]])->all(),
            'lifestyle' => [
                'avg_sleep' => $chart?->lifestyle['avg_sleep'] ?? null,
                'eating_habits' => $chart?->lifestyle['eating_habits'] ?? null,
                'exercise' => (bool) ($chart?->lifestyle['exercise'] ?? false),
                'past_medical_history' => $chart?->lifestyle['past_medical_history'] ?? null,
                'previous_surgery' => $chart?->lifestyle['previous_surgery'] ?? null,
            ],
            'derma_history' => [
                'had_consult' => (bool) ($chart?->derma_history['had_consult'] ?? false),
                'reason' => $chart?->derma_history['reason'] ?? null,
                'when' => $chart?->derma_history['when'] ?? null,
            ],
            'initial_plan' => [
                'items' => $chart?->initial_plan['items'] ?? [],
                'items_others' => $chart?->initial_plan['items_others'] ?? null,
            ],
            'physician_notes' => $chart?->physician_notes ?? [],
            'assessment_conditions' => [
                'conditions' => $chart?->assessment_conditions['conditions'] ?? [],
                'conditions_others' => $chart?->assessment_conditions['conditions_others'] ?? null,
            ],
            'beauty_plan' => $chart?->beauty_plan ?? [],
            'skin_type' => $chart?->skin_type?->value,
            'face_shape' => $chart?->face_shape?->value,
            'findings' => $chart?->findings,
            'medical_record' => $chart?->medical_record,
            'procedures_notes' => $chart?->procedures_notes,
            'lifestyle_notes' => $chart?->lifestyle_notes,
            'initial_plan_notes' => $chart?->initial_plan_notes,
            'assessment_notes' => $chart?->assessment_notes,
            'beauty_plan_notes' => $chart?->beauty_plan_notes,
        ];
    }
}
