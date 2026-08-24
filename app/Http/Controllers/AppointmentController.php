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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
        return $appointments->map(fn (Appointment $a) => [
            'id' => $a->id,
            'name' => $a->displayName(),
            'phone' => $a->patient?->phone ?? $a->guest_phone,
            'patient_id' => $a->patient_id,
            'service' => $a->service?->name,
            'service_id' => $a->service_id,
            'course_id' => $a->course_id,
            'staff' => $a->staff?->name,
            'scheduled_at' => $a->scheduled_at?->toIso8601String(),
            'date' => $a->scheduled_at?->toDateString(),
            'time' => $a->scheduled_at?->format('g:iA'),
            'status' => $a->status->value,
            'notes' => $a->notes,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Appointments/Create', [
            'patients' => Patient::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'code'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->full_name} ({$p->code})"]),
            'services' => Service::query()->where('is_active', true)
                ->with('category:id,name,sort_order')
                ->get(['id', 'name', 'service_category_id', 'duration_minutes'])
                ->sortBy([fn ($s) => $s->category?->sort_order ?? 999, fn ($s) => $s->name])
                ->values()
                ->map(fn ($s) => ['value' => $s->id, 'label' => ($s->category ? "{$s->category->name} · " : '') . $s->name, 'duration' => $s->duration_minutes]),
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
            'preselectedPatient' => $request->query('patient'),
            'preselectedDate' => $request->query('date'),
        ]);
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

        Appointment::create([
            ...$data,
            'status' => AppointmentStatus::Scheduled,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('appointments.index')->with('success', 'Appointment booked.');
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
        $appointment->update($this->validated($request));

        return redirect()->route('appointments.index')->with('success', 'Appointment updated.');
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        $appointment->update(['status' => $data['status']]);

        return back()->with('success', 'Appointment updated.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $appointment->delete();

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
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
