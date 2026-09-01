<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { TriangleAlert, Pencil, CreditCard, Plus, Trash2, ChevronDown } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import ChartShell from '@/components/ChartShell.vue';

type Option = { value: string; label: string };

interface Chart {
    history_flags: { have: string[]; have_others: string | null; taking: string[]; taking_others: string | null; condition: string[]; condition_others: string | null };
    procedures_done: Record<string, { done: boolean; when: string | null }>;
    lifestyle: { avg_sleep: string | null; eating_habits: string | null; exercise: boolean; past_medical_history: string | null; previous_surgery: string | null };
    derma_history: { had_consult: boolean; reason: string | null; when: string | null };
    initial_plan: { items: string[]; items_others: string | null };
    physician_notes: Array<{ observations?: string | null; test_ordered?: string | null; results?: string | null; additional_notes?: string | null }>;
    assessment_conditions: { conditions: string[]; conditions_others: string | null };
    beauty_plan: Array<{ procedure?: string | null; price?: number | string | null; timeline?: string | null }>;
    skin_type: string | null;
    face_shape: string | null;
    findings: string | null;
    medical_record: string | null;
    procedures_notes: string | null;
    lifestyle_notes: string | null;
    initial_plan_notes: string | null;
    assessment_notes: string | null;
    beauty_plan_notes: string | null;
}

interface Session { id: string; number: number | null; status: string; performed_at: string | null; scheduled_at: string | null; by: string | null; notes: string | null }
interface Course { id: string; name: string; service: string | null; status: string; total_sessions: number; sessions_completed: number; sessions_remaining: number; purchased_at: string | null; price: number; sessions: Session[] }
interface Invoice { id: string; invoice_no: string; status: string; subtotal: number; discount_total: number; tax_total: number; grand_total: number; amount_paid: number; issued_at: string | null; items: Array<{ description: string | null; quantity: number; unit_price: number; line_total: number }>; payments: Array<{ amount: number; method: string | null; paid_at: string | null }> }

const props = defineProps<{
    meta: { clinic: string | null; address?: string | null; phone?: string | null; generated_at: string; currency: string };
    patient: Record<string, string | null>;
    chart: Chart;
    options: { have: Option[]; taking: Option[]; condition: Option[]; procedures: Option[]; initialPlan: Option[]; assessment: Option[]; skinTypes: Option[]; faceShapes: Option[] };
    sexes: Option[];
    safetyFlags: Array<{ type: string; title: string }>;
    courses: Course[];
    invoices: Invoice[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Patients', href: '/patients' }, { title: 'Chart', href: '#' }] },
});

// --- left-nav shortcuts ----------------------------------------------------
const sections = [
    { id: 'sec-info', label: 'Patient info' },
    { id: 'sec-history', label: 'Patient history' },
    { id: 'sec-procedures', label: 'Procedures done' },
    { id: 'sec-lifestyle', label: 'Lifestyle & derma' },
    { id: 'sec-plan', label: 'Initial plan' },
    { id: 'sec-assessment', label: "Doctor's assessment" },
    { id: 'sec-notes', label: 'Physician notes' },
    { id: 'sec-findings', label: 'Findings' },
    { id: 'sec-beauty', label: 'Beauty plan' },
    { id: 'sec-medical', label: 'Medical records' },
    { id: 'sec-courses', label: 'Courses & plans' },
    { id: 'sec-invoices', label: 'Invoices' },
];
const activeId = ref('');
let observer: IntersectionObserver | null = null;
onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => entries.forEach((e) => { if (e.isIntersecting) activeId.value = e.target.id; }),
        { rootMargin: '-80px 0px -65% 0px', threshold: 0 },
    );
    sections.forEach((s) => { const el = document.getElementById(s.id); if (el) observer!.observe(el); });
});
onBeforeUnmount(() => observer?.disconnect());
function scrollTo(id: string) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// --- display helpers -------------------------------------------------------
const dash = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : String(v));
const yesNo = (v: unknown) => (v ? 'Yes' : 'No');
const money = (v: unknown) => `${props.meta.currency}${Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtDate = (v: unknown) => (v ? fmt(v as string) : '—');
const labelOf = (list: Option[], key: string) => list.find((o) => o.value === key)?.label ?? key;
const pick = (list: Option[], keys: string[]) => (keys ?? []).map((k) => labelOf(list, k));

const skinTypeLabel = computed(() => (props.chart.skin_type ? labelOf(props.options.skinTypes, props.chart.skin_type) : null));
const faceShapeLabel = computed(() => (props.chart.face_shape ? labelOf(props.options.faceShapes, props.chart.face_shape) : null));
const subtitle = computed(() => [props.patient.code, props.patient.sex].filter(Boolean).join(' · '));

const statusTone: Record<string, string> = {
    active: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    unpaid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    partially_paid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};

const hasPhysicianNotes = computed(() => props.chart.physician_notes.some((r) => r.observations || r.test_ordered || r.results || r.additional_notes));
const hasBeautyPlan = computed(() => props.chart.beauty_plan.some((r) => r.procedure || r.price || r.timeline));

// --- per-card editing ------------------------------------------------------
const c = () => props.chart;
const buildInitial = () => ({
    first_name: props.patient.first_name ?? '',
    last_name: props.patient.last_name ?? '',
    code: props.patient.code ?? '',
    date_of_birth: props.patient.date_of_birth ?? '',
    sex: props.patient.sex ?? '',
    phone: props.patient.phone ?? '',
    email: props.patient.email ?? '',
    address: props.patient.address ?? '',
    emergency_contact_name: props.patient.emergency_contact_name ?? '',
    emergency_contact_phone: props.patient.emergency_contact_phone ?? '',
    occupation: props.patient.occupation ?? '',
    civil_status: props.patient.civil_status ?? '',
    history_flags: {
        have: [...c().history_flags.have],
        have_others: c().history_flags.have_others ?? '',
        taking: [...c().history_flags.taking],
        taking_others: c().history_flags.taking_others ?? '',
        condition: [...c().history_flags.condition],
        condition_others: c().history_flags.condition_others ?? '',
    },
    procedures_done: props.options.procedures.reduce((acc, p) => {
        acc[p.value] = { done: c().procedures_done[p.value]?.done ?? false, when: c().procedures_done[p.value]?.when ?? '' };
        return acc;
    }, {} as Record<string, { done: boolean; when: string }>),
    lifestyle: {
        avg_sleep: c().lifestyle.avg_sleep ?? '',
        eating_habits: c().lifestyle.eating_habits ?? '',
        exercise: c().lifestyle.exercise ?? false,
        past_medical_history: c().lifestyle.past_medical_history ?? '',
        previous_surgery: c().lifestyle.previous_surgery ?? '',
    },
    derma_history: {
        had_consult: c().derma_history.had_consult ?? false,
        reason: c().derma_history.reason ?? '',
        when: c().derma_history.when ?? '',
    },
    initial_plan: { items: [...c().initial_plan.items], items_others: c().initial_plan.items_others ?? '' },
    physician_notes: c().physician_notes.map((r) => ({ observations: r.observations ?? '', test_ordered: r.test_ordered ?? '', results: r.results ?? '', additional_notes: r.additional_notes ?? '' })),
    assessment_conditions: { conditions: [...c().assessment_conditions.conditions], conditions_others: c().assessment_conditions.conditions_others ?? '' },
    beauty_plan: c().beauty_plan.map((r) => ({ procedure: r.procedure ?? '', price: r.price ?? '', timeline: r.timeline ?? '' })),
    skin_type: c().skin_type ?? '',
    face_shape: c().face_shape ?? '',
    findings: c().findings ?? '',
    medical_record: c().medical_record ?? '',
    procedures_notes: c().procedures_notes ?? '',
    lifestyle_notes: c().lifestyle_notes ?? '',
    initial_plan_notes: c().initial_plan_notes ?? '',
    assessment_notes: c().assessment_notes ?? '',
    beauty_plan_notes: c().beauty_plan_notes ?? '',
});

const form = useForm(buildInitial());
const editing = ref<string | null>(null);
const sectionTitles: Record<string, string> = {
    info: 'Patient information',
    history: 'Patient history',
    procedures: 'Aesthetic procedures done',
    lifestyle: 'Lifestyle & derma history',
    plan: 'Initial plan',
    assessment: "Doctor's assessment",
    notes: 'Physician notes',
    findings: 'Findings',
    beauty: 'Beauty plan',
    medical: 'Medical records',
};

function openEdit(key: string) {
    Object.assign(form, buildInitial());
    if (key === 'notes' && form.physician_notes.length === 0) addNote();
    if (key === 'beauty' && form.beauty_plan.length === 0) addBeauty();
    form.clearErrors();
    editing.value = key;
}
function toggle(arr: string[], key: string) {
    const i = arr.indexOf(key);
    if (i === -1) arr.push(key);
    else arr.splice(i, 1);
}
const addNote = () => form.physician_notes.push({ observations: '', test_ordered: '', results: '', additional_notes: '' });
const removeNote = (i: number) => form.physician_notes.splice(i, 1);
const addBeauty = () => form.beauty_plan.push({ procedure: '', price: '', timeline: '' });
const removeBeauty = (i: number) => form.beauty_plan.splice(i, 1);

function save() {
    form.patch(`/patients/${props.patient.id}/chart`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

const textareaClass =
    'w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25';
const selectClass =
    'h-9 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25';

const civilStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];

// Invoice details modal.
const selectedInvoice = ref<Invoice | null>(null);
</script>

<template>
    <Head :title="patient.full_name ?? 'Patient chart'" />

    <div class="flex">
        <!-- Left section shortcuts (screen only) -->
        <nav class="no-print sticky top-16 hidden h-[calc(100vh-4rem)] w-52 shrink-0 overflow-y-auto border-r p-3 lg:block">
            <p class="px-2 pb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Jump to</p>
            <button
                v-for="s in sections"
                :key="s.id"
                type="button"
                class="block w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                :class="activeId === s.id ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'"
                @click="scrollTo(s.id)"
            >
                {{ s.label }}
            </button>
        </nav>

        <ChartShell title="Patient Chart" :subtitle="patient.full_name ?? subtitle" :meta="meta" class="min-w-0 flex-1">
            <template #actions>
                <Button as-child size="sm">
                    <Link :href="`/checkout?patient=${patient.id}`"><CreditCard class="size-4" /> Checkout</Link>
                </Button>
            </template>

            <!-- 1. Patient information --------------------------------------------->
            <Card id="sec-info" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Patient information</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('info')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-facts w-full text-sm">
                        <tbody>
                            <tr><th>Name</th><td>{{ dash(patient.full_name) }}</td><th>Patient code</th><td class="font-mono">{{ dash(patient.code) }}</td></tr>
                            <tr><th>Date of birth</th><td>{{ dash(patient.date_of_birth) }}</td><th>Sex</th><td class="capitalize">{{ dash(patient.sex) }}</td></tr>
                            <tr><th>Occupation</th><td>{{ dash(patient.occupation) }}</td><th>Civil status</th><td class="capitalize">{{ dash(patient.civil_status) }}</td></tr>
                            <tr><th>Phone</th><td>{{ dash(patient.phone) }}</td><th>Email</th><td>{{ dash(patient.email) }}</td></tr>
                            <tr><th>Address</th><td colspan="3">{{ dash(patient.address) }}</td></tr>
                            <tr><th>Emergency contact</th><td>{{ dash(patient.emergency_contact_name) }}</td><th>Emergency phone</th><td>{{ dash(patient.emergency_contact_phone) }}</td></tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Safety flags (also prints) -->
            <div v-if="safetyFlags.length" class="flex flex-wrap items-center gap-2 rounded-lg border border-rose-300/60 bg-rose-50/60 p-3 dark:bg-rose-950/20">
                <TriangleAlert class="size-4 text-rose-600" />
                <span class="text-sm font-medium text-rose-700 dark:text-rose-300">Alerts:</span>
                <Badge v-for="(f, i) in safetyFlags" :key="i" variant="destructive">{{ f.title }}</Badge>
            </div>

            <!-- 2. Patient history ------------------------------------------------->
            <Card id="sec-history" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Patient history</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('history')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-facts w-full text-sm">
                        <tbody>
                            <tr><th>I have</th><td>{{ [...pick(options.have, chart.history_flags.have), chart.history_flags.have_others].filter(Boolean).join(', ') || '—' }}</td></tr>
                            <tr><th>I am taking</th><td>{{ [...pick(options.taking, chart.history_flags.taking), chart.history_flags.taking_others].filter(Boolean).join(', ') || '—' }}</td></tr>
                            <tr><th>Current condition</th><td>{{ [...pick(options.condition, chart.history_flags.condition), chart.history_flags.condition_others].filter(Boolean).join(', ') || '—' }}</td></tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- 3. Aesthetic procedures done --------------------------------------->
            <Card id="sec-procedures" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Aesthetic procedures done</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('procedures')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-grid w-full text-sm">
                        <thead><tr><th>Procedure</th><th>Done</th><th>When</th></tr></thead>
                        <tbody>
                            <tr v-for="p in options.procedures" :key="p.value">
                                <td>{{ p.label }}</td>
                                <td>{{ yesNo(chart.procedures_done[p.value]?.done) }}</td>
                                <td>{{ fmtDate(chart.procedures_done[p.value]?.when) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="chart.procedures_notes" class="mt-3 whitespace-pre-line text-sm"><span class="font-medium text-muted-foreground">Notes: </span>{{ chart.procedures_notes }}</p>
                </CardContent>
            </Card>

            <!-- 4/5. Lifestyle & derma history ------------------------------------->
            <Card id="sec-lifestyle" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Lifestyle &amp; derma history</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('lifestyle')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-facts w-full text-sm">
                        <tbody>
                            <tr><th>Average sleep</th><td>{{ dash(chart.lifestyle.avg_sleep) }}</td><th>Exercise</th><td>{{ yesNo(chart.lifestyle.exercise) }}</td></tr>
                            <tr><th>Eating habits</th><td colspan="3">{{ dash(chart.lifestyle.eating_habits) }}</td></tr>
                            <tr><th>Past medical history</th><td colspan="3">{{ dash(chart.lifestyle.past_medical_history) }}</td></tr>
                            <tr><th>Previous surgery / hospitalization</th><td colspan="3">{{ dash(chart.lifestyle.previous_surgery) }}</td></tr>
                            <tr><th>Previous derma consultation</th><td>{{ yesNo(chart.derma_history.had_consult) }}</td><th>When</th><td>{{ fmtDate(chart.derma_history.when) }}</td></tr>
                            <tr><th>Reason for consultation</th><td colspan="3">{{ dash(chart.derma_history.reason) }}</td></tr>
                        </tbody>
                    </table>
                    <p v-if="chart.lifestyle_notes" class="mt-3 whitespace-pre-line text-sm"><span class="font-medium text-muted-foreground">Notes: </span>{{ chart.lifestyle_notes }}</p>
                </CardContent>
            </Card>

            <!-- 7. Initial plan ---------------------------------------------------->
            <Card id="sec-plan" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Initial plan</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('plan')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-facts w-full text-sm">
                        <tbody>
                            <tr><th>Planned procedures</th><td>{{ [...pick(options.initialPlan, chart.initial_plan.items), chart.initial_plan.items_others].filter(Boolean).join(', ') || '—' }}</td></tr>
                            <tr><th>Face shape</th><td>{{ dash(faceShapeLabel) }}</td></tr>
                        </tbody>
                    </table>
                    <p v-if="chart.initial_plan_notes" class="mt-3 whitespace-pre-line text-sm"><span class="font-medium text-muted-foreground">Notes: </span>{{ chart.initial_plan_notes }}</p>
                </CardContent>
            </Card>

            <!-- 9. Doctor's assessment --------------------------------------------->
            <Card id="sec-assessment" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Doctor's assessment</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('assessment')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <table class="chart-facts w-full text-sm">
                        <tbody>
                            <tr><th>Skin conditions</th><td>{{ [...pick(options.assessment, chart.assessment_conditions.conditions), chart.assessment_conditions.conditions_others].filter(Boolean).join(', ') || '—' }}</td></tr>
                            <tr><th>Skin type</th><td>{{ dash(skinTypeLabel) }}</td></tr>
                        </tbody>
                    </table>
                    <p v-if="chart.assessment_notes" class="mt-3 whitespace-pre-line text-sm"><span class="font-medium text-muted-foreground">Notes: </span>{{ chart.assessment_notes }}</p>
                </CardContent>
            </Card>

            <!-- 6. Physician notes ------------------------------------------------->
            <Card id="sec-notes" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Physician notes</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('notes')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <p v-if="!hasPhysicianNotes" class="py-3 text-center text-sm text-muted-foreground">No physician notes recorded.</p>
                    <table v-else class="chart-grid w-full text-sm">
                        <thead><tr><th>Observations</th><th>Test ordered</th><th>Results</th><th>Additional notes</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in chart.physician_notes" :key="i">
                                <td>{{ dash(r.observations) }}</td><td>{{ dash(r.test_ordered) }}</td><td>{{ dash(r.results) }}</td><td>{{ dash(r.additional_notes) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- 8. Findings -------------------------------------------------------->
            <Card id="sec-findings" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Findings</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('findings')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-line text-sm" :class="{ 'text-muted-foreground': !chart.findings }">{{ chart.findings || 'No findings recorded.' }}</p>
                </CardContent>
            </Card>

            <!-- 10. Beauty plan ---------------------------------------------------->
            <Card id="sec-beauty" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Beauty plan</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('beauty')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <p v-if="!hasBeautyPlan" class="py-3 text-center text-sm text-muted-foreground">No beauty plan recorded.</p>
                    <table v-else class="chart-grid w-full text-sm">
                        <thead><tr><th>Procedure</th><th>Price</th><th>Timeline</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in chart.beauty_plan" :key="i">
                                <td>{{ dash(r.procedure) }}</td><td>{{ r.price ? money(r.price) : '—' }}</td><td>{{ dash(r.timeline) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="chart.beauty_plan_notes" class="mt-3 whitespace-pre-line text-sm"><span class="font-medium text-muted-foreground">Notes: </span>{{ chart.beauty_plan_notes }}</p>
                </CardContent>
            </Card>

            <!-- Medical records (free text) ---------------------------------------->
            <Card id="sec-medical" class="scroll-mt-20">
                <CardHeader>
                    <CardTitle class="text-base">Medical records</CardTitle>
                    <CardAction class="no-print"><Button variant="ghost" size="sm" @click="openEdit('medical')"><Pencil class="size-4" /> Edit</Button></CardAction>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-line text-sm" :class="{ 'text-muted-foreground': !chart.medical_record }">{{ chart.medical_record || 'No medical record entered.' }}</p>
                </CardContent>
            </Card>

            <!-- Treatment courses (plans) ------------------------------------------>
            <Card id="sec-courses" class="scroll-mt-20">
                <CardHeader><CardTitle class="text-base">Treatment courses &amp; plans</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="courses.length === 0" class="py-4 text-center text-sm text-muted-foreground">No courses yet.</p>
                    <Collapsible v-for="co in courses" :key="co.id" class="rounded-lg border">
                        <CollapsibleTrigger class="group flex w-full items-center gap-3 p-3 text-left">
                            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                            <span class="font-medium">{{ co.name }}</span>
                            <Badge :class="statusTone[co.status]">{{ co.status }}</Badge>
                            <span class="ml-auto whitespace-nowrap text-xs text-muted-foreground">{{ co.sessions_completed }}/{{ co.total_sessions }} · {{ co.sessions_remaining }} left</span>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="space-y-3 border-t p-3">
                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground">
                                <span>Purchased: {{ fmt(co.purchased_at) }}</span>
                                <span>Price: {{ money(co.price) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full bg-primary" :style="{ width: `${Math.min(100, (co.sessions_completed / co.total_sessions) * 100)}%` }" />
                            </div>
                            <p v-if="!co.sessions.length" class="text-sm text-muted-foreground">No sessions recorded yet.</p>
                            <ol v-else class="space-y-2">
                                <li v-for="s in co.sessions" :key="s.id" class="rounded-md border p-2 text-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="font-medium">Session {{ s.number ?? '—' }} · <span class="capitalize">{{ s.status.replace('_', ' ') }}</span></span>
                                        <span class="text-xs text-muted-foreground">{{ fmt(s.performed_at ?? s.scheduled_at) }}</span>
                                    </div>
                                    <p v-if="s.by" class="mt-0.5 text-sm font-medium text-primary">{{ s.by }}</p>
                                    <p v-if="s.notes" class="mt-1 whitespace-pre-line text-muted-foreground">{{ s.notes }}</p>
                                    <p v-else class="mt-1 text-xs italic text-muted-foreground/70">No notes.</p>
                                </li>
                            </ol>
                        </CollapsibleContent>
                    </Collapsible>
                </CardContent>
            </Card>

            <!-- Invoices ----------------------------------------------------------->
            <Card id="sec-invoices" class="scroll-mt-20">
                <CardHeader><CardTitle class="text-base">Invoices</CardTitle></CardHeader>
                <CardContent>
                    <p v-if="invoices.length === 0" class="py-4 text-center text-sm text-muted-foreground">No invoices.</p>
                    <template v-else>
                        <table class="chart-grid w-full text-sm">
                            <thead><tr><th>Invoice</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                <tr v-for="inv in invoices" :key="inv.id" class="cursor-pointer hover:bg-muted/40" @click="selectedInvoice = inv">
                                    <td class="font-mono text-xs">{{ inv.invoice_no }}</td>
                                    <td>{{ fmt(inv.issued_at) }}</td>
                                    <td class="capitalize">{{ inv.status.replace('_', ' ') }}</td>
                                    <td class="text-right">{{ money(inv.grand_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="no-print mt-2 text-xs text-muted-foreground">Tap a row to see the invoice details.</p>
                    </template>
                </CardContent>
            </Card>
        </ChartShell>
    </div>

    <!-- Per-card edit dialog -->
    <Dialog :open="editing !== null" @update:open="(v) => { if (!v) editing = null; }">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ editing ? sectionTitles[editing] : '' }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-1">
                <!-- Patient information -->
                <template v-if="editing === 'info'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5"><Label for="first_name">First name *</Label><Input id="first_name" v-model="form.first_name" /><InputError :message="form.errors.first_name" /></div>
                        <div class="grid gap-1.5"><Label for="last_name">Last name *</Label><Input id="last_name" v-model="form.last_name" /><InputError :message="form.errors.last_name" /></div>
                        <div class="grid gap-1.5"><Label for="code">Patient code</Label><Input id="code" v-model="form.code" /><InputError :message="form.errors.code" /></div>
                        <div class="grid gap-1.5"><Label for="dob">Date of birth</Label><Input id="dob" type="date" v-model="form.date_of_birth" /></div>
                        <div class="grid gap-1.5">
                            <Label for="sex">Sex</Label>
                            <select id="sex" v-model="form.sex" :class="selectClass"><option value="">—</option><option v-for="s in sexes" :key="s.value" :value="s.value">{{ s.label }}</option></select>
                        </div>
                        <div class="grid gap-1.5"><Label for="occupation">Occupation</Label><Input id="occupation" v-model="form.occupation" /></div>
                        <div class="grid gap-1.5">
                            <Label for="civil_status">Civil status</Label>
                            <select id="civil_status" v-model="form.civil_status" :class="selectClass"><option value="">—</option><option v-for="cs in civilStatuses" :key="cs" :value="cs">{{ cs }}</option></select>
                        </div>
                        <div class="grid gap-1.5"><Label for="phone">Phone</Label><Input id="phone" v-model="form.phone" /></div>
                        <div class="grid gap-1.5"><Label for="email">Email</Label><Input id="email" type="email" v-model="form.email" /><InputError :message="form.errors.email" /></div>
                        <div class="grid gap-1.5 sm:col-span-2"><Label for="address">Address</Label><Input id="address" v-model="form.address" /></div>
                        <div class="grid gap-1.5"><Label for="ec_name">Emergency contact</Label><Input id="ec_name" v-model="form.emergency_contact_name" /></div>
                        <div class="grid gap-1.5"><Label for="ec_phone">Emergency phone</Label><Input id="ec_phone" v-model="form.emergency_contact_phone" /></div>
                    </div>
                </template>

                <!-- Patient history -->
                <template v-else-if="editing === 'history'">
                    <div>
                        <p class="mb-2 text-sm font-medium">I have</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label v-for="o in options.have" :key="o.value" class="flex items-center gap-2 text-sm">
                                <Checkbox :model-value="form.history_flags.have.includes(o.value)" @update:model-value="toggle(form.history_flags.have, o.value)" /> {{ o.label }}
                            </label>
                        </div>
                        <Input v-model="form.history_flags.have_others" placeholder="Others…" class="mt-2" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium">I am taking</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label v-for="o in options.taking" :key="o.value" class="flex items-center gap-2 text-sm">
                                <Checkbox :model-value="form.history_flags.taking.includes(o.value)" @update:model-value="toggle(form.history_flags.taking, o.value)" /> {{ o.label }}
                            </label>
                        </div>
                        <Input v-model="form.history_flags.taking_others" placeholder="Others…" class="mt-2" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium">Current condition</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label v-for="o in options.condition" :key="o.value" class="flex items-center gap-2 text-sm">
                                <Checkbox :model-value="form.history_flags.condition.includes(o.value)" @update:model-value="toggle(form.history_flags.condition, o.value)" /> {{ o.label }}
                            </label>
                        </div>
                        <Input v-model="form.history_flags.condition_others" placeholder="Others…" class="mt-2" />
                    </div>
                </template>

                <!-- Procedures done -->
                <template v-else-if="editing === 'procedures'">
                    <div v-for="p in options.procedures" :key="p.value" class="grid items-center gap-3 sm:grid-cols-[10rem_auto_minmax(0,1fr)]">
                        <label class="flex items-center gap-2 text-sm"><Checkbox v-model="form.procedures_done[p.value].done" /> {{ p.label }}</label>
                        <span class="text-sm text-muted-foreground">If yes, when:</span>
                        <Input v-model="form.procedures_done[p.value].when" type="date" />
                    </div>
                    <div class="grid gap-1.5"><Label>Notes</Label><textarea v-model="form.procedures_notes" rows="2" :class="textareaClass" /></div>
                </template>

                <!-- Lifestyle & derma -->
                <template v-else-if="editing === 'lifestyle'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5"><Label for="avg_sleep">Average sleep</Label><Input id="avg_sleep" v-model="form.lifestyle.avg_sleep" placeholder="e.g. 7 hours" /></div>
                        <label class="flex items-center gap-2 self-end pb-2 text-sm"><Checkbox v-model="form.lifestyle.exercise" /> Exercises regularly</label>
                        <div class="grid gap-1.5 sm:col-span-2"><Label for="eating_habits">Eating habits</Label><Input id="eating_habits" v-model="form.lifestyle.eating_habits" /></div>
                        <div class="grid gap-1.5 sm:col-span-2"><Label for="pmh">Past medical history</Label><textarea id="pmh" v-model="form.lifestyle.past_medical_history" rows="2" :class="textareaClass" /></div>
                        <div class="grid gap-1.5 sm:col-span-2"><Label for="surgery">Previous surgery / hospitalization</Label><textarea id="surgery" v-model="form.lifestyle.previous_surgery" rows="2" :class="textareaClass" /></div>
                    </div>
                    <div class="grid gap-4 border-t pt-4 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm"><Checkbox v-model="form.derma_history.had_consult" /> Previous derma consultation</label>
                        <div class="grid gap-1.5"><Label for="derma_when">When</Label><Input id="derma_when" type="date" v-model="form.derma_history.when" /></div>
                        <div class="grid gap-1.5 sm:col-span-2"><Label for="derma_reason">Reason for consultation</Label><Input id="derma_reason" v-model="form.derma_history.reason" /></div>
                    </div>
                    <div class="grid gap-1.5"><Label>Notes</Label><textarea v-model="form.lifestyle_notes" rows="2" :class="textareaClass" /></div>
                </template>

                <!-- Initial plan -->
                <template v-else-if="editing === 'plan'">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label v-for="o in options.initialPlan" :key="o.value" class="flex items-center gap-2 text-sm">
                            <Checkbox :model-value="form.initial_plan.items.includes(o.value)" @update:model-value="toggle(form.initial_plan.items, o.value)" /> {{ o.label }}
                        </label>
                    </div>
                    <Input v-model="form.initial_plan.items_others" placeholder="Others…" />
                    <div class="grid max-w-xs gap-1.5">
                        <Label for="face_shape">Face shape</Label>
                        <select id="face_shape" v-model="form.face_shape" :class="selectClass"><option value="">—</option><option v-for="o in options.faceShapes" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                    </div>
                    <div class="grid gap-1.5"><Label>Notes</Label><textarea v-model="form.initial_plan_notes" rows="2" :class="textareaClass" /></div>
                </template>

                <!-- Doctor's assessment -->
                <template v-else-if="editing === 'assessment'">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label v-for="o in options.assessment" :key="o.value" class="flex items-center gap-2 text-sm">
                            <Checkbox :model-value="form.assessment_conditions.conditions.includes(o.value)" @update:model-value="toggle(form.assessment_conditions.conditions, o.value)" /> {{ o.label }}
                        </label>
                    </div>
                    <Input v-model="form.assessment_conditions.conditions_others" placeholder="Others…" />
                    <div class="grid max-w-xs gap-1.5">
                        <Label for="skin_type">Skin type</Label>
                        <select id="skin_type" v-model="form.skin_type" :class="selectClass"><option value="">—</option><option v-for="o in options.skinTypes" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                    </div>
                    <div class="grid gap-1.5"><Label>Notes</Label><textarea v-model="form.assessment_notes" rows="2" :class="textareaClass" /></div>
                </template>

                <!-- Physician notes -->
                <template v-else-if="editing === 'notes'">
                    <div v-for="(row, i) in form.physician_notes" :key="i" class="space-y-3 rounded-lg border p-3">
                        <div class="grid gap-1.5"><Label>Observations</Label><textarea v-model="row.observations" rows="2" :class="textareaClass" /></div>
                        <div class="grid gap-1.5"><Label>Test ordered</Label><textarea v-model="row.test_ordered" rows="2" :class="textareaClass" /></div>
                        <div class="grid gap-1.5"><Label>Results</Label><textarea v-model="row.results" rows="2" :class="textareaClass" /></div>
                        <div class="grid gap-1.5"><Label>Additional notes</Label><textarea v-model="row.additional_notes" rows="2" :class="textareaClass" /></div>
                        <div><Button type="button" variant="ghost" size="sm" class="text-destructive" @click="removeNote(i)"><Trash2 class="size-4" /> Remove</Button></div>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="addNote"><Plus class="size-4" /> Add row</Button>
                </template>

                <!-- Findings -->
                <template v-else-if="editing === 'findings'">
                    <textarea v-model="form.findings" rows="6" :class="textareaClass" placeholder="Clinical findings…" />
                </template>

                <!-- Beauty plan -->
                <template v-else-if="editing === 'beauty'">
                    <div v-for="(row, i) in form.beauty_plan" :key="i" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_8rem_10rem_auto]">
                        <div class="grid gap-1.5"><Label>Procedure</Label><Input v-model="row.procedure" /></div>
                        <div class="grid gap-1.5"><Label>Price</Label><Input v-model="row.price" type="number" step="0.01" min="0" /></div>
                        <div class="grid gap-1.5"><Label>Timeline</Label><Input v-model="row.timeline" placeholder="e.g. 3 months" /></div>
                        <Button type="button" variant="ghost" size="icon" class="text-destructive" @click="removeBeauty(i)"><Trash2 class="size-4" /></Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="addBeauty"><Plus class="size-4" /> Add procedure</Button>
                    <div class="grid gap-1.5"><Label>Notes</Label><textarea v-model="form.beauty_plan_notes" rows="2" :class="textareaClass" /></div>
                </template>

                <!-- Medical records -->
                <template v-else-if="editing === 'medical'">
                    <textarea v-model="form.medical_record" rows="12" :class="textareaClass" placeholder="Type the patient's full medical record here…" />
                </template>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="editing = null">Cancel</Button>
                <Button :disabled="form.processing" @click="save">Save</Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>

    <!-- Invoice details -->
    <Dialog :open="selectedInvoice !== null" @update:open="(v) => { if (!v) selectedInvoice = null; }">
        <DialogScrollContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Invoice {{ selectedInvoice?.invoice_no }}</DialogTitle>
            </DialogHeader>
            <div v-if="selectedInvoice" class="space-y-4 text-sm">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-muted-foreground">
                    <span>Date: {{ fmt(selectedInvoice.issued_at) }}</span>
                    <span class="capitalize">Status: {{ selectedInvoice.status.replace('_', ' ') }}</span>
                </div>
                <table class="chart-grid w-full">
                    <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        <tr v-for="(it, i) in selectedInvoice.items" :key="i">
                            <td>{{ dash(it.description) }}</td>
                            <td class="text-right">{{ it.quantity }}</td>
                            <td class="text-right">{{ money(it.unit_price) }}</td>
                            <td class="text-right">{{ money(it.line_total) }}</td>
                        </tr>
                        <tr v-if="!selectedInvoice.items.length"><td colspan="4" class="py-3 text-center text-muted-foreground">No line items.</td></tr>
                    </tbody>
                </table>
                <div class="ml-auto w-full max-w-xs space-y-1">
                    <div class="flex justify-between"><span class="text-muted-foreground">Subtotal</span><span>{{ money(selectedInvoice.subtotal) }}</span></div>
                    <div v-if="selectedInvoice.discount_total" class="flex justify-between"><span class="text-muted-foreground">Discount</span><span>−{{ money(selectedInvoice.discount_total) }}</span></div>
                    <div v-if="selectedInvoice.tax_total" class="flex justify-between"><span class="text-muted-foreground">Tax</span><span>{{ money(selectedInvoice.tax_total) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Total</span><span>{{ money(selectedInvoice.grand_total) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted-foreground">Paid</span><span>{{ money(selectedInvoice.amount_paid) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Balance</span><span>{{ money(selectedInvoice.grand_total - selectedInvoice.amount_paid) }}</span></div>
                </div>
                <div v-if="selectedInvoice.payments.length">
                    <p class="mb-1 font-medium">Payments</p>
                    <ul class="space-y-1">
                        <li v-for="(pm, i) in selectedInvoice.payments" :key="i" class="flex justify-between">
                            <span class="capitalize text-muted-foreground">{{ pm.method }} · {{ fmt(pm.paid_at) }}</span>
                            <span>{{ money(pm.amount) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <DialogFooter>
                <Button variant="ghost" @click="selectedInvoice = null">Close</Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>

<style scoped>
.chart-facts th {
    width: 1%;
    white-space: nowrap;
    padding: 0.35rem 1rem 0.35rem 0;
    text-align: left;
    font-weight: 500;
    color: var(--muted-foreground);
    vertical-align: top;
}
.chart-facts td {
    padding: 0.35rem 1.5rem 0.35rem 0;
    vertical-align: top;
}
.chart-grid th {
    text-align: left;
    font-weight: 500;
    color: var(--muted-foreground);
    border-bottom: 1px solid var(--border);
    padding: 0.4rem 0.75rem 0.4rem 0;
}
.chart-grid td {
    padding: 0.4rem 0.75rem 0.4rem 0;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
/* Let a numeric column's header align with its right-aligned values (beats the
   default `.chart-grid th { text-align: left }`). */
.chart-grid th.text-right,
.chart-grid td.text-right {
    text-align: right;
    padding-right: 0;
}
</style>
