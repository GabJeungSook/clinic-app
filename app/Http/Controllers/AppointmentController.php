<?php

namespace App\Http\Controllers;

use App\Actions\Patients\CreatePatient;
use App\Enums\AppointmentStatus;
use App\Enums\CourseStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $view = $request->query('view', 'list');
        $status = $request->query('status', 'all');
        $statuses = collect(AppointmentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]);

        // Calendar mode: everything within the requested month.
        if ($view === 'calendar') {
            $monthStr = (string) $request->query('month', '');
            $month = $monthStr !== ''
                ? Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth()
                : Carbon::today()->startOfMonth();

            $appointments = $this->mapAppointments(
                Appointment::query()
                    ->with('patient:id,first_name,last_name', 'service:id,name', 'staff:id,name')
                    ->whereBetween('scheduled_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->endOfDay()])
                    ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                    ->orderBy('scheduled_at')
                    ->get(),
            );

            return Inertia::render('Appointments/Index', [
                'view' => 'calendar',
                'appointments' => $appointments,
                'filters' => ['range' => 'all', 'status' => $status],
                'statuses' => $statuses,
                'calendar' => [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('F Y'),
                    'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
                    'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
                    'current' => Carbon::today()->format('Y-m'),
                    'today' => Carbon::today()->toDateString(),
                ],
            ]);
        }

        // List mode.
        $range = $request->query('range', 'upcoming');
        $search = trim((string) $request->query('search', ''));

        $appointments = $this->mapAppointments(
            Appointment::query()
                ->with('patient:id,first_name,last_name', 'service:id,name', 'staff:id,name')
                ->when($range === 'upcoming', fn ($q) => $q->where('scheduled_at', '>=', Carbon::today()))
                ->when($range === 'today', fn ($q) => $q->whereBetween('scheduled_at', [Carbon::today(), Carbon::today()->endOfDay()]))
                ->when($range === 'past', fn ($q) => $q->where('scheduled_at', '<', Carbon::today()))
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->whereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhere('guest_name', 'like', "%{$search}%")))
                ->orderBy('scheduled_at', $range === 'past' ? 'desc' : 'asc')
                ->limit(200)
                ->get(),
        );

        return Inertia::render('Appointments/Index', [
            'view' => 'list',
            'appointments' => $appointments,
            'filters' => ['range' => $range, 'status' => $status, 'search' => $search],
            'statuses' => $statuses,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Appointment>  $appointments
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mapAppointments($appointments)
    {
        // Resolve every referenced service once so we can list their names.
        $ids = $appointments->flatMap(fn (Appointment $a) => collect($a->services ?? [])->pluck('service_id'))
            ->merge($appointments->pluck('service_id'))
            ->filter()->unique();
        $names = Service::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $appointments->map(function (Appointment $a) use ($names) {
            $lineNames = collect($a->services ?? [])->pluck('service_id')->map(fn ($id) => $names[$id] ?? null)->filter();
            if ($lineNames->isEmpty() && $a->service?->name) {
                $lineNames = collect([$a->service->name]);
            }

            return [
                'id' => $a->id,
                'name' => $a->displayName(),
                'phone' => $a->patient?->phone ?? $a->guest_phone,
                'patient_id' => $a->patient_id,
                'service' => $lineNames->isNotEmpty() ? $lineNames->implode(', ') : null,
                'service_id' => $a->service_id,
                'course_id' => $a->course_id,
                'services' => collect($a->services ?? [])->map(fn ($s) => [
                    'service_id' => $s['service_id'],
                    'course_id' => $s['course_id'] ?? null,
                ])->values(),
                'staff' => $a->staff?->name,
                'scheduled_at' => $a->scheduled_at?->toIso8601String(),
                'date' => $a->scheduled_at?->toDateString(),
                'time' => $a->scheduled_at?->format('g:iA'),
                'status' => $a->status->value,
                'notes' => $a->notes,
            ];
        });
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Appointments/Create', [
            ...$this->formOptions(),
            'preselectedPatient' => $request->query('patient'),
            'preselectedDate' => $request->query('date'),
        ]);
    }

    public function edit(Appointment $appointment): Response
    {
        return Inertia::render('Appointments/Edit', [
            ...$this->formOptions(),
            'appointment' => [
                'id' => $appointment->id,
                'name' => $appointment->displayName(),
                'patient_id' => $appointment->patient_id,
                'service_id' => $appointment->service_id,
                'course_id' => $appointment->course_id,
                'services' => collect($appointment->services ?? [])->map(fn ($s) => [
                    'service_id' => $s['service_id'],
                    'course_id' => $s['course_id'] ?? null,
                ])->values(),
                'staff_id' => $appointment->staff_id,
                'scheduled_at' => $appointment->scheduled_at?->format('Y-m-d\TH:i'),
                'duration_minutes' => $appointment->duration_minutes,
                'notes' => $appointment->notes,
                'status' => $appointment->status->value,
            ],
        ]);
    }

    /** @return array<string, mixed> Shared select options for the booking form. */
    private function formOptions(): array
    {
        return [
            'patients' => Patient::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'code'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->full_name} ({$p->code})"]),
            'services' => Service::query()->where('is_active', true)
                ->with('category:id,name,sort_order')
                ->get(['id', 'name', 'service_category_id', 'duration_minutes', 'default_session_count', 'default_price'])
                ->sortBy([fn ($s) => $s->category?->sort_order ?? 999, fn ($s) => $s->name])
                ->values()
                ->map(fn ($s) => [
                    'value' => $s->id,
                    'label' => ($s->category ? "{$s->category->name} · " : '') . $s->name,
                    'duration' => $s->duration_minutes,
                    'sessions' => (int) $s->default_session_count,
                    'price' => (float) $s->default_price,
                ]),
            'staff' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]),
            // Active prepaid courses so a patient's ongoing package can be booked.
            'courses' => TreatmentCourse::query()->where('status', CourseStatus::Active)
                ->get(['id', 'patient_id', 'service_id', 'name_snapshot'])
                ->map(fn (TreatmentCourse $c) => [
                    'value' => $c->id,
                    'patient_id' => $c->patient_id,
                    'service_id' => $c->service_id,
                    'label' => $c->name_snapshot,
                    'remaining' => $c->sessions_remaining,
                ])
                ->filter(fn ($c) => $c['remaining'] > 0)
                ->values(),
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ];
    }

    public function store(Request $request, CreatePatient $createPatient): RedirectResponse
    {
        $data = $this->validated($request);

        // A guest booking mints a real patient record straight away, so they are
        // searchable and can flow into checkout even before availing a service.
        if (empty($data['patient_id']) && ! empty($data['guest_name'])) {
            [$first, $last] = $this->splitName($data['guest_name']);
            $patient = $createPatient->handle([
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $data['guest_phone'] ?? null,
            ]);
            $data['patient_id'] = $patient->id;
        }
        unset($data['guest_name'], $data['guest_phone']);

        $data = $this->applyServices($data);
        $this->assertNoConflict($data);

        Appointment::create([
            ...$data,
            'status' => AppointmentStatus::Scheduled,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('appointments.index')->with('success', 'Appointment booked.');
    }

    /**
     * Normalise the multi-service list and mirror the first entry into the
     * primary service_id/course_id columns (kept for calendar/reports). Accepts
     * a single service_id too, for backward compatibility.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyServices(array $data): array
    {
        $services = $data['services'] ?? [];
        if (empty($services) && ! empty($data['service_id'])) {
            $services = [['service_id' => $data['service_id'], 'course_id' => $data['course_id'] ?? null]];
        }
        $services = array_values(array_map(fn ($s) => [
            'service_id' => $s['service_id'],
            'course_id' => $s['course_id'] ?? null,
        ], $services));

        $data['services'] = $services ?: null;
        $data['service_id'] = $services[0]['service_id'] ?? null;
        $data['course_id'] = $services[0]['course_id'] ?? null;

        return $data;
    }

    /** @return array{0:string, 1:string} first and last name from a full name. */
    private function splitName(string $name): array
    {
        $name = trim($name);
        $pos = strpos($name, ' ');

        return $pos === false ? [$name, ''] : [substr($name, 0, $pos), trim(substr($name, $pos + 1))];
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['guest_name'], $data['guest_phone']);

        $data = $this->applyServices($data);
        $this->assertNoConflict($data, $appointment->id);

        $appointment->update($data);

        return redirect()->route('appointments.index')->with('success', 'Appointment updated.');
    }

    /**
     * Reject a booking whose time range overlaps another active booking. An
     * overlap is a clash unless the two are assigned to DIFFERENT providers
     * (who can work in parallel). The same patient, the same provider, or any
     * unassigned booking overlapping another always conflicts — so slots can't
     * be silently double-booked.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoConflict(array $data, ?string $ignoreId = null): void
    {
        if (empty($data['scheduled_at'])) {
            return;
        }

        $staffId = $data['staff_id'] ?? null;
        $patientId = $data['patient_id'] ?? null;

        $start = Carbon::parse($data['scheduled_at']);
        $duration = (int) ($data['duration_minutes'] ?? 0);
        if ($duration <= 0) {
            // Sum the durations of every booked service (fallback: single primary).
            $ids = collect($data['services'] ?? [])->pluck('service_id')->filter()->all();
            if (empty($ids) && ! empty($data['service_id'])) {
                $ids = [$data['service_id']];
            }
            $duration = (int) Service::query()->whereIn('id', $ids)->sum('duration_minutes');
        }
        $end = $start->copy()->addMinutes(max(5, $duration ?: 30));

        $candidates = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->whereDate('scheduled_at', $start->toDateString())
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->with('staff:id,name', 'patient:id,first_name,last_name')
            ->get();

        foreach ($candidates as $c) {
            $cStart = $c->scheduled_at;
            $cEnd = $cStart->copy()->addMinutes((int) ($c->duration_minutes ?? 30));

            // Half-open intervals overlap when each starts before the other ends.
            if (! ($start < $cEnd && $cStart < $end)) {
                continue;
            }

            $when = $cStart->format('g:iA') . '–' . $cEnd->format('g:iA');

            if ($patientId && $c->patient_id === $patientId) {
                throw ValidationException::withMessages([
                    'scheduled_at' => "This patient already has an appointment {$when}. Please choose another time.",
                ]);
            }

            // Allowed only when both sides have a provider and they differ.
            $differentProviders = $staffId && $c->staff_id && (int) $c->staff_id !== (int) $staffId;
            if (! $differentProviders) {
                $who = $c->staff?->name
                    ? "{$c->staff->name} is already booked"
                    : "That slot overlaps an existing appointment ({$c->patient?->full_name})";

                throw ValidationException::withMessages([
                    'scheduled_at' => "{$who} {$when}. Assign different providers to run appointments together, or pick another time.",
                ]);
            }
        }
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        // Completed is reached only by checking the patient out — never by hand.
        if ($data['status'] === AppointmentStatus::Completed->value) {
            throw ValidationException::withMessages([
                'status' => 'Complete an appointment by checking the patient out.',
            ]);
        }

        $appointment->update(['status' => $data['status']]);

        return back()->with('success', 'Appointment updated.');
    }

    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        $deletePatient = $request->boolean('delete_patient');
        $patient = $appointment->patient;

        $appointment->delete();

        // Optionally clean up the patient too — but only if they were never a real
        // patient (no invoices, courses, sessions, or any other appointment).
        if ($deletePatient && $patient) {
            $hasOtherRecords = $patient->invoices()->exists()
                || $patient->treatmentCourses()->exists()
                || $patient->treatmentSessions()->exists()
                || Appointment::query()->where('patient_id', $patient->id)->exists();

            if (! $hasOtherRecords) {
                $patient->delete();

                return back()->with('success', 'Appointment and patient removed.');
            }

            return back()->with('success', 'Appointment removed. Patient kept — they have other records.');
        }

        return back()->with('success', 'Appointment removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['nullable', 'string', 'exists:patients,id', 'required_without:guest_name'],
            'guest_name' => ['nullable', 'string', 'max:150', 'required_without:patient_id'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'service_id' => ['nullable', 'string', 'exists:services,id'],
            'course_id' => ['nullable', 'string', 'exists:treatment_courses,id'],
            'services' => ['array'],
            'services.*.service_id' => ['required', 'string', 'exists:services,id'],
            'services.*.course_id' => ['nullable', 'string', 'exists:treatment_courses,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
