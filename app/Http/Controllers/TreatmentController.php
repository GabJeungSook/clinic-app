<?php

namespace App\Http\Controllers;

use App\Models\TreatmentCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreatmentController extends Controller
{
    /**
     * Progress overview of every patient's treatment packages. A patient with
     * several packages appears once per package (service), each with its own
     * session progress.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $courses = TreatmentCourse::query()
            ->with('patient:id,first_name,last_name,code', 'service:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"))
                ->orWhereHas('service', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->orderBy('patient_id')
            ->latest('purchased_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TreatmentCourse $c) => [
                'id' => $c->id,
                'patient' => $c->patient?->full_name,
                'patient_id' => $c->patient_id,
                'service' => $c->service?->name,
                'status' => $c->status->value,
                'total_sessions' => $c->total_sessions,
                'sessions_completed' => $c->sessions_completed,
                'sessions_remaining' => $c->sessions_remaining,
            ]);

        return Inertia::render('Treatments/Index', [
            'courses' => $courses,
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    /**
     * Session history for one package. Performing sessions happens in Record
     * Visit, so this page is read-only with a shortcut to record a visit.
     */
    public function show(TreatmentCourse $treatment): Response
    {
        $treatment->load([
            'patient:id,first_name,last_name,code',
            'sessions' => fn ($q) => $q->with('performer:id,name', 'consumptions.item:id,name', 'consumptions.unit:id,abbreviation')->latest('performed_at'),
        ]);

        return Inertia::render('Treatments/Show', [
            'course' => [
                'id' => $treatment->id,
                'patient' => $treatment->patient?->full_name,
                'patient_id' => $treatment->patient_id,
                'service' => $treatment->service_id ? $treatment->service?->name : null,
                'name' => $treatment->name_snapshot,
                'status' => $treatment->status->value,
                'total_sessions' => $treatment->total_sessions,
                'sessions_completed' => $treatment->sessions_completed,
                'sessions_remaining' => $treatment->sessions_remaining,
            ],
            'sessions' => $treatment->sessions->map(fn ($s) => [
                'id' => $s->id,
                'session_number' => $s->session_number,
                'status' => $s->status->value,
                'performed_at' => $s->performed_at?->toDateTimeString(),
                'performed_by' => $s->performer?->name,
                'notes' => $s->clinical_notes,
                'items' => $s->consumptions->map(fn ($c) => [
                    'name' => $c->item?->name,
                    'qty' => (float) $c->quantity,
                    'unit' => $c->unit?->abbreviation,
                ])->values(),
            ]),
        ]);
    }
}
