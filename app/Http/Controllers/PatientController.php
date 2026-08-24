<?php

namespace App\Http\Controllers;

use App\Actions\Patients\AddMedicalHistory;
use App\Actions\Patients\CreatePatient;
use App\Enums\MedicalHistoryType;
use App\Enums\Sex;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'medicalHistories' => fn ($q) => $q->latest('recorded_at'),
            'treatmentCourses' => fn ($q) => $q->with('service:id,name')->latest('purchased_at'),
            'invoices' => fn ($q) => $q->latest('issued_at'),
        ]);

        return Inertia::render('Patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->code,
                'full_name' => $patient->full_name,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'sex' => $patient->sex?->value,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
                'notes' => $patient->notes,
            ],
            'safetyFlags' => $patient->medicalHistories
                ->filter(fn ($h) => $h->type->isSafetyFlag())
                ->map(fn ($h) => ['type' => $h->type->value, 'title' => $h->title])
                ->values(),
            'histories' => $patient->medicalHistories->map(fn ($h) => [
                'id' => $h->id,
                'type' => $h->type->value,
                'title' => $h->title,
                'details' => $h->details,
                'recorded_at' => $h->recorded_at?->toDateString(),
            ]),
            'courses' => $patient->treatmentCourses->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name_snapshot,
                'service' => $c->service?->name,
                'status' => $c->status->value,
                'total_sessions' => $c->total_sessions,
                'sessions_completed' => $c->sessions_completed,
                'sessions_remaining' => $c->sessions_remaining,
            ]),
            'invoices' => $patient->invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'status' => $i->status->value,
                'grand_total' => (float) $i->grand_total,
                'amount_paid' => (float) $i->amount_paid,
                'issued_at' => $i->issued_at?->toDateString(),
            ]),
            'historyTypes' => collect(MedicalHistoryType::cases())
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'visits' => $patient->treatmentSessions()
                ->where('status', \App\Enums\SessionStatus::Completed)
                ->with('service:id,name', 'performer:id,name', 'consumptions.item:id,name', 'consumptions.unit:id,abbreviation')
                ->latest('performed_at')
                ->limit(50)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'service' => $s->service?->name,
                    'performed_at' => $s->performed_at?->toDateTimeString(),
                    'by' => $s->performer?->name,
                    'notes' => $s->clinical_notes,
                    'items' => $s->consumptions->map(fn ($c) => [
                        'name' => $c->item?->name,
                        'qty' => (float) $c->quantity,
                        'unit' => $c->unit?->abbreviation,
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

    /** @return array<int, array{value:string, label:string}> */
    private function sexOptions(): array
    {
        return collect(Sex::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all();
    }
}
