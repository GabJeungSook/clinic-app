<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SearchSelect from '@/components/SearchSelect.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, Trash2, X, UserPlus, AlertTriangle, CheckCircle2 } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Checkout', href: '/checkout' }] } });

interface Bom { inventory_item_id: string; item_name: string | null; quantity: number; unit_id: string | null }
interface ServiceOpt { value: string; label: string; price: number; sessions: number; bom: Bom[] }
interface CourseOpt { value: string; patient_id: string; service_id: string; label: string; total: number; completed: number; remaining: number; paid: boolean }
interface RetailOpt { value: string; label: string; price: number; unit: string | null; on_hand: number; reorder: number; is_low: boolean }
interface ConsumableOpt { value: string; label: string; base_unit_id: string; on_hand: number; is_low: boolean }
interface Consumable { inventory_item_id: string; quantity: number; unit_id: string }
interface ServiceLine { service_id: string; course_id: string; sessions: number; price: number; discount: number; promotion_id: string; notes: string; consumables: Consumable[] }
interface RetailLine { inventory_item_id: string; label: string; unit: string; quantity: number; unit_price: number; discount: number; on_hand: number; is_low: boolean }
interface FreebieLine { inventory_item_id: string; label: string; unit: string; quantity: number; on_hand: number; is_low: boolean }
interface ManualLine { description: string; quantity: number; unit_price: number; discount: number }
interface PaymentLine { method: string; amount: number; reference: string }
interface OutstandingInv { invoice_id: string; invoice_no: string; label: string; balance: number; per_session: number | null; course_id: string | null }
interface SettlementRow extends OutstandingInv { include: boolean; amount: number; discount: number; method: string }

const props = defineProps<{
    patients: Array<{ value: string; label: string }>;
    services: ServiceOpt[];
    items: RetailOpt[];
    consumableItems: ConsumableOpt[];
    units: Array<{ value: string; label: string }>;
    courses: CourseOpt[];
    promotions: Array<{ value: string; label: string; min_spend: number }>;
    methods: Array<{ value: string; label: string }>;
    tax: { enabled: boolean; rate: number; inclusive: boolean };
    currency: string;
    preselectedPatient: string | null;
    preselectedService: string | null;
    preselectedCourse: string | null;
    appointmentPrefills: Record<string, Array<{ service_id: string; course_id: string | null }>>;
    outstanding: Record<string, OutstandingInv[]>;
}>();

const servicesById = computed(() => Object.fromEntries(props.services.map((s) => [s.value, s])));
const itemsById = computed(() => Object.fromEntries(props.items.map((i) => [i.value, i])));
const consumableById = computed(() => Object.fromEntries(props.consumableItems.map((i) => [i.value, i])));
const courseById = computed(() => Object.fromEntries(props.courses.map((c) => [c.value, c])));

// Prepaid-package timeline helpers (n is a 1-based session number).
const courseFor = (line: ServiceLine): CourseOpt | undefined => courseById.value[line.course_id];
const sessionState = (c: CourseOpt, n: number): 'done' | 'today' | 'upcoming' =>
    n <= c.completed ? 'done' : n === c.completed + 1 ? 'today' : 'upcoming';
const dotClass = (c: CourseOpt, n: number) => {
    const s = sessionState(c, n);
    if (s === 'done') return 'border-emerald-500 bg-emerald-500 text-white';
    if (s === 'today') return 'border-primary bg-primary text-primary-foreground ring-2 ring-primary/30';
    return c.paid ? 'border-emerald-300 text-emerald-600' : 'border-muted-foreground/30 text-muted-foreground';
};
const connectorClass = (c: CourseOpt, n: number) =>
    n <= c.completed + 1 ? 'bg-emerald-400' : c.paid ? 'bg-emerald-200' : 'bg-muted';

const form = useForm({
    patient_id: props.preselectedPatient ?? '',
    invoice_promotion_id: '',
    invoice_discount: 0,
    notes: '',
    services: [] as ServiceLine[],
    retail: [] as RetailLine[],
    freebies: [] as FreebieLine[],
    manual: [] as ManualLine[],
    payments: [] as PaymentLine[],
    settlements: [] as SettlementRow[],
    generate_receipt: true,
});

// Build the patient's outstanding-balance rows, pre-filled to collect one session
// (for packages) or the full balance (for anything else). Rebuilt on patient change.
function buildSettlements(patientId: string) {
    const list = props.outstanding[patientId] ?? [];
    form.settlements = list.map((o) => ({
        ...o,
        include: true,
        amount: Math.min(o.per_session ?? o.balance, o.balance),
        discount: 0,
        method: 'cash',
    }));
}

const money = (n: number) => `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// ── Quick-add patient ────────────────────────────────────────────────────────
const patientDialog = ref(false);
const patientForm = useForm({ first_name: '', last_name: '', phone: '' });
const savePatient = () => patientForm.post('/checkout/patient', { onSuccess: () => (patientDialog.value = false) });

// ── Service lines ────────────────────────────────────────────────────────────
const bomToConsumables = (s?: ServiceOpt): Consumable[] =>
    s ? s.bom.map((b) => ({ inventory_item_id: b.inventory_item_id, quantity: b.quantity, unit_id: b.unit_id ?? '' })) : [];
const blankService = (): ServiceLine => ({ service_id: '', course_id: '', sessions: 1, price: 0, discount: 0, promotion_id: '', notes: '', consumables: [] });
const addService = () => form.services.push(blankService());
const removeService = (i: number) => form.services.splice(i, 1);

function onServiceChange(line: ServiceLine) {
    const s = servicesById.value[line.service_id];
    line.course_id = '';
    if (s) {
        line.price = s.price;   // per-session price
        line.consumables = bomToConsumables(s);
    }
}
const patientCourses = computed(() => props.courses.filter((c) => c.patient_id === form.patient_id));
function addCourseLine(course: CourseOpt) {
    const s = servicesById.value[course.service_id];
    form.services.push({ service_id: course.service_id, course_id: course.value, sessions: 1, price: 0, discount: 0, promotion_id: '', notes: '', consumables: bomToConsumables(s) });
}
const serviceLineNet = (l: ServiceLine) => Math.max(0, Number(l.sessions) * Number(l.price) - Number(l.discount));

// Auto-load the service(s) the patient came in for, from today's booking.
const prefilledFrom = ref<string | null>(null);
function pushServiceRef(serviceId: string, courseId: string | null) {
    const s = servicesById.value[serviceId];
    if (!s) return;
    const course = courseId ? props.courses.find((c) => c.value === courseId) : undefined;
    form.services.push({
        service_id: s.value,
        course_id: course?.value ?? '',
        sessions: 1,
        price: course ? 0 : s.price,
        discount: 0,
        promotion_id: '',
        notes: '',
        consumables: bomToConsumables(s),
    });
}
function maybePrefillFromAppointment(patientId: string) {
    if (!patientId || form.services.length > 0) return;
    const list = props.appointmentPrefills[patientId];
    if (!list?.length) return;
    list.forEach((r) => pushServiceRef(r.service_id, r.course_id));
    prefilledFrom.value = patientId;
}
watch(() => form.patient_id, (id, prev) => {
    if (id === prev) return;
    // Services are tied to a patient — switching patients clears the previous
    // patient's service lines, then loads the new patient's booked services.
    form.services = [];
    prefilledFrom.value = null;
    maybePrefillFromAppointment(id);
    buildSettlements(id);
});
const addConsumable = (line: ServiceLine) => line.consumables.push({ inventory_item_id: '', quantity: 1, unit_id: '' });
function onConsumableChange(c: Consumable) {
    const it = consumableById.value[c.inventory_item_id];
    if (it && !c.unit_id) c.unit_id = it.base_unit_id;
}
const consumableWarning = (c: Consumable) => {
    const it = consumableById.value[c.inventory_item_id];
    if (!it) return '';
    if (it.on_hand <= 0) return 'out of stock';
    if (it.is_low) return `low stock (${it.on_hand} left)`;
    return '';
};

// ── Retail lines ─────────────────────────────────────────────────────────────
const retailPicker = computed<{ value: string; label: string }[]>(() =>
    props.items.map((i) => ({
        value: i.value,
        label: i.on_hand <= 0 ? `${i.label} — out of stock` : `${i.label} — ${money(i.price)} · ${i.on_hand} ${i.unit ?? ''} left`,
    })));
const retailError = ref('');
function addRetail(itemId: string) {
    const it = itemsById.value[itemId];
    if (!it) return;
    if (it.on_hand <= 0) {
        retailError.value = `${it.label} is out of stock and can't be sold.`;
        return;
    }
    retailError.value = '';
    form.retail.push({ inventory_item_id: it.value, label: it.label, unit: it.unit ?? '', quantity: 1, unit_price: it.price, discount: 0, on_hand: it.on_hand, is_low: it.is_low });
}
const removeRetail = (i: number) => form.retail.splice(i, 1);
const retailRowError = (l: RetailLine) => Number(l.quantity) > l.on_hand;
const retailRowWarn = (l: RetailLine) => !retailRowError(l) && (l.is_low || Number(l.quantity) >= l.on_hand);
const retailOk = computed(() => form.retail.every((l) => !retailRowError(l)));

// ── Freebies — given free (₱0) but still deducted from stock ──────────────────
const freebieError = ref('');
function addFreebie(itemId: string) {
    const it = itemsById.value[itemId];
    if (!it) return;
    if (it.on_hand <= 0) {
        freebieError.value = `${it.label} is out of stock.`;
        return;
    }
    freebieError.value = '';
    form.freebies.push({ inventory_item_id: it.value, label: it.label, unit: it.unit ?? '', quantity: 1, on_hand: it.on_hand, is_low: it.is_low });
}
const removeFreebie = (i: number) => form.freebies.splice(i, 1);
const freebieRowError = (l: FreebieLine) => Number(l.quantity) > l.on_hand;
const freebieOk = computed(() => form.freebies.every((l) => !freebieRowError(l)));

// ── Manual lines ─────────────────────────────────────────────────────────────
const addManual = () => form.manual.push({ description: '', quantity: 1, unit_price: 0, discount: 0 });
const removeManual = (i: number) => form.manual.splice(i, 1);

// ── Totals ───────────────────────────────────────────────────────────────────
const lineNet = (qty: number, price: number, discount: number) => Math.max(0, Number(qty) * Number(price) - Number(discount));
// Per-category subtotals, surfaced as a breakdown in the summary panel.
const servicesSubtotal = computed(() => form.services.reduce((s, l) => s + serviceLineNet(l), 0));
const retailSubtotal = computed(() => form.retail.reduce((s, l) => s + lineNet(l.quantity, l.unit_price, l.discount), 0));
const manualSubtotal = computed(() => form.manual.reduce((s, l) => s + lineNet(l.quantity, l.unit_price, l.discount), 0));
const subtotal = computed(() => servicesSubtotal.value + retailSubtotal.value + manualSubtotal.value);

// Itemised lines for the summary panel — so staff see exactly what's included.
const serviceItems = computed(() => form.services.filter((l) => l.service_id).map((l) => {
    const course = l.course_id ? courseById.value[l.course_id] : undefined;
    return {
        name: servicesById.value[l.service_id]?.label ?? 'Service',
        qty: Math.max(1, Number(l.sessions) || 1),
        prepaid: !!l.course_id,
        // Only truly "prepaid" when the package invoice is fully settled; otherwise
        // the session is drawn from the package but its cost is still owed.
        coursePaid: course?.paid ?? false,
        amount: l.course_id ? 0 : serviceLineNet(l),
    };
}));
const retailItems = computed(() => form.retail.filter((l) => l.inventory_item_id).map((l) => ({
    name: l.label, qty: Number(l.quantity) || 0, amount: lineNet(l.quantity, l.unit_price, l.discount),
})));
const manualItems = computed(() => form.manual.filter((l) => l.description).map((l) => ({
    name: l.description, qty: Number(l.quantity) || 0, amount: lineNet(l.quantity, l.unit_price, l.discount),
})));
const freebieItems = computed(() => form.freebies.filter((l) => l.inventory_item_id).map((l) => ({
    name: l.label, qty: Number(l.quantity) || 0,
})));
const invoiceDiscount = computed(() => Math.min(Math.max(0, Number(form.invoice_discount) || 0), subtotal.value));
const discountedSubtotal = computed(() => Math.max(0, subtotal.value - invoiceDiscount.value));
const tax = computed(() => {
    if (!props.tax.enabled || discountedSubtotal.value <= 0) return 0;
    return props.tax.inclusive ? discountedSubtotal.value - discountedSubtotal.value / (1 + props.tax.rate / 100) : (discountedSubtotal.value * props.tax.rate) / 100;
});
const grand = computed(() => (props.tax.enabled && !props.tax.inclusive ? discountedSubtotal.value + tax.value : discountedSubtotal.value));

// ── Payments (split) ─────────────────────────────────────────────────────────
const paid = computed(() => form.payments.reduce((s, p) => s + (Number(p.amount) || 0), 0));
const balance = computed(() => Math.max(0, Math.round((grand.value - paid.value) * 100) / 100));
const addPayment = () => form.payments.push({ method: 'cash', amount: 0, reference: '' });
const addRemaining = () => form.payments.push({ method: 'cash', amount: balance.value, reference: '' });
const removePayment = (i: number) => form.payments.splice(i, 1);

// ── Outstanding balance settlements ───────────────────────────────────────────
const patientLabel = computed(() => props.patients.find((p) => p.value === form.patient_id)?.label ?? 'This patient');
const drawnCourseIds = computed(() => new Set(form.services.filter((s) => s.course_id).map((s) => s.course_id)));
const includedSettlements = computed(() => form.settlements.filter((s) => s.include && Number(s.amount) > 0));
const settlementsTotal = computed(() => includedSettlements.value.reduce((sum, s) => sum + (Number(s.amount) || 0), 0));
function settlementError(s: SettlementRow): string {
    const collect = (Number(s.amount) || 0) + (Number(s.discount) || 0);
    if (collect - 0.01 > s.balance) return 'Exceeds the balance owed.';
    // A session drawn from this package must be paid for (payment + discount ≥ one session).
    if (s.per_session != null && drawnCourseIds.value.has(s.course_id) && collect + 0.01 < s.per_session) {
        return `Collect at least one session (${money(s.per_session)}).`;
    }
    return '';
}
const settlementsOk = computed(() => form.settlements.filter((s) => s.include).every((s) => !settlementError(s)));

// ── Guards + submit ──────────────────────────────────────────────────────────
const requiresPatient = computed(() => form.services.length > 0);
const hasLines = computed(() => form.services.length + form.retail.length + form.freebies.length + form.manual.length > 0);
const canSubmit = computed(() =>
    (hasLines.value || includedSettlements.value.length > 0) &&
    retailOk.value && freebieOk.value && settlementsOk.value &&
    !(requiresPatient.value && !form.patient_id));

function submit() {
    form
        .transform((data) => ({
            patient_id: data.patient_id || null,
            invoice_promotion_id: data.invoice_promotion_id || null,
            invoice_discount: Number(data.invoice_discount) || 0,
            notes: data.notes || null,
            line_groups: {
                services: data.services
                    .filter((l) => l.service_id)
                    .map((l) => ({
                        service_id: l.service_id,
                        course_id: l.course_id || null,
                        sessions: Math.max(1, Number(l.sessions) || 1),
                        price: Number(l.price) || 0,
                        discount: Number(l.discount) || 0,
                        promotion_id: l.promotion_id || null,
                        notes: l.notes || null,
                        consumables: l.consumables.filter((c) => c.inventory_item_id && Number(c.quantity) > 0),
                    })),
                retail: data.retail
                    .filter((l) => l.inventory_item_id)
                    .map((l) => ({ inventory_item_id: l.inventory_item_id, quantity: Number(l.quantity) || 0, unit_price: Number(l.unit_price) || 0, discount: Number(l.discount) || 0 })),
                freebies: data.freebies
                    .filter((l) => l.inventory_item_id && Number(l.quantity) > 0)
                    .map((l) => ({ inventory_item_id: l.inventory_item_id, quantity: Number(l.quantity) || 0 })),
                manual: data.manual
                    .filter((l) => l.description && Number(l.quantity) > 0)
                    .map((l) => ({ description: l.description, quantity: Number(l.quantity) || 0, unit_price: Number(l.unit_price) || 0, discount: Number(l.discount) || 0 })),
            },
            payments: data.payments.filter((p) => Number(p.amount) > 0).map((p) => ({ method: p.method, amount: Number(p.amount), reference: p.reference || null })),
            settlements: data.settlements
                .filter((s) => s.include && Number(s.amount) > 0)
                .map((s) => ({ invoice_id: s.invoice_id, amount: Number(s.amount) || 0, discount: Number(s.discount) || 0, method: s.method })),
            generate_receipt: data.generate_receipt,
        }))
        .post('/checkout');
}

onMounted(() => {
    if (props.preselectedService) {
        const s = servicesById.value[props.preselectedService];
        if (s) {
            const course = props.preselectedCourse ? props.courses.find((c) => c.value === props.preselectedCourse) : undefined;
            form.services.push({
                service_id: s.value,
                course_id: course?.value ?? '',
                sessions: 1,
                price: course ? 0 : s.price,
                discount: 0,
                promotion_id: '',
                notes: '',
                consumables: bomToConsumables(s),
            });
        }
    }
    // Deep-linked or preselected patient → pull in their booking's services + balances.
    maybePrefillFromAppointment(form.patient_id);
    if (form.patient_id) buildSettlements(form.patient_id);
});
</script>

<template>
    <Head title="Checkout" />
    <div class="w-full p-4 md:p-6">
        <div class="mb-4">
            <h1 class="text-xl font-semibold tracking-tight">Checkout</h1>
            <p class="text-sm text-muted-foreground">Perform services, sell products, and take payment — all in one place.</p>
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <!-- Main column: everything you enter -->
        <div class="flex min-w-0 flex-col gap-4">
        <!-- Patient -->
        <Card class="border-none">
            <CardContent class="p-4">
                <div class="grid gap-1.5">
                    <div class="flex items-center justify-between">
                        <Label>Patient <span class="text-xs font-normal text-muted-foreground">(required for services; optional for retail-only)</span></Label>
                        <Button type="button" variant="ghost" size="sm" @click="patientDialog = true"><UserPlus class="size-4" /> New patient</Button>
                    </div>
                    <SearchSelect v-model="form.patient_id" :options="patients" placeholder="Walk-in (retail only)" empty-label="Walk-in (retail only)" />
                    <p v-if="!form.patient_id" class="text-xs text-muted-foreground">No patient selected — retail-only sale. Choose or add a patient to perform services.</p>
                </div>
            </CardContent>
        </Card>

        <!-- Services (only when a patient is selected) -->
        <template v-if="form.patient_id">
            <!-- Ongoing packages -->
            <Card v-if="patientCourses.length" class="border-none border-l-4 border-l-primary">
                <CardContent class="p-4">
                    <p class="mb-2 text-sm font-medium">Continue an ongoing package</p>
                    <div class="flex flex-wrap gap-2">
                        <Button v-for="c in patientCourses" :key="c.value" type="button" variant="secondary" size="sm" @click="addCourseLine(c)">
                            <Plus class="size-4" /> {{ c.label }} — {{ c.remaining }} left
                        </Button>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">Adds the next session from a prepaid package (₱0).</p>
                </CardContent>
            </Card>

            <Card class="border-none">
                <CardHeader class="flex flex-row items-center justify-between py-3">
                    <CardTitle class="text-base">Services performed</CardTitle>
                    <Button type="button" variant="secondary" size="sm" @click="addService"><Plus class="size-4" /> Add service</Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="prefilledFrom === form.patient_id" class="rounded-lg bg-primary/5 px-3 py-2 text-xs font-medium text-primary">Loaded from the patient's appointment — adjust if needed.</p>
                    <p v-if="form.services.length === 0" class="py-2 text-center text-sm text-muted-foreground">No services added.</p>
                    <div v-for="(line, i) in form.services" :key="i" class="rounded-lg border p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Service {{ i + 1 }}</span>
                            <Button variant="ghost" size="icon-sm" @click="removeService(i)"><Trash2 class="size-4 text-rose-600" /></Button>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Service</Label>
                            <SearchSelect v-model="line.service_id" :options="services" placeholder="Select service…" @update:model-value="onServiceChange(line)" />
                        </div>

                        <!-- Prepaid session drawn from an existing package -->
                        <template v-if="line.course_id">
                            <div v-if="courseFor(line)" class="mt-3 rounded-lg border bg-muted/30 p-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Prepaid package progress</span>
                                    <span v-if="courseFor(line)!.paid" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        <CheckCircle2 class="size-3" /> Fully paid
                                    </span>
                                    <span v-else class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Balance on original invoice</span>
                                </div>
                                <div class="flex items-center">
                                    <template v-for="n in courseFor(line)!.total" :key="n">
                                        <div v-if="n > 1" class="h-0.5 flex-1" :class="connectorClass(courseFor(line)!, n)" />
                                        <div class="flex size-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold" :class="dotClass(courseFor(line)!, n)">
                                            <CheckCircle2 v-if="sessionState(courseFor(line)!, n) === 'done'" class="size-3.5" />
                                            <template v-else>{{ n }}</template>
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Session {{ courseFor(line)!.completed + 1 }} of {{ courseFor(line)!.total }} today — no charge (₱0).
                                    <span v-if="courseFor(line)!.paid">All sessions in this package are paid.</span>
                                    <span v-else>{{ courseFor(line)!.remaining - 1 }} left after this.</span>
                                </p>
                            </div>
                            <div v-else class="mt-3 rounded-md bg-primary/5 px-3 py-2 text-xs font-medium text-primary">
                                Drawing one prepaid session from the package — no charge (₱0).
                            </div>
                        </template>

                        <!-- Per-session billing (pay 1 today, or prepay more) -->
                        <template v-else>
                            <div class="mt-4 grid gap-4 sm:grid-cols-4">
                                <div class="grid gap-1.5"><Label>Sessions</Label><Input type="number" min="1" step="1" v-model="line.sessions" /></div>
                                <div class="grid gap-1.5"><Label>Price / session</Label><Input type="number" step="0.01" min="0" v-model="line.price" /></div>
                                <div class="grid gap-1.5"><Label>Discount</Label><Input type="number" step="0.01" min="0" v-model="line.discount" /></div>
                                <div class="grid gap-1.5">
                                    <Label>Promotion</Label>
                                    <SearchSelect v-model="line.promotion_id" :options="promotions" placeholder="— none —" empty-label="— none —" />
                                </div>
                            </div>
                            <div v-if="line.service_id" class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">
                                    {{ line.sessions }} × {{ money(Number(line.price)) }}
                                    <span v-if="Number(line.sessions) > 1">· 1 performed today, {{ Number(line.sessions) - 1 }} prepaid</span>
                                </span>
                                <span class="font-semibold">{{ money(serviceLineNet(line)) }}</span>
                            </div>
                        </template>
                        <div class="mt-4 grid gap-1.5">
                            <Label>Notes</Label>
                            <Input v-model="line.notes" placeholder="e.g. area treated, observations" />
                        </div>

                        <!-- Consumables -->
                        <div class="mt-4 rounded-lg border p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium">Items used</span>
                                <Button type="button" variant="secondary" size="sm" @click="addConsumable(line)"><Plus class="size-4" /> Add item</Button>
                            </div>
                            <p v-if="line.consumables.length === 0" class="py-2 text-center text-xs text-muted-foreground">No items will be deducted for this service.</p>
                            <div v-for="(c, ci) in line.consumables" :key="ci" class="mb-2 flex flex-wrap items-end gap-2">
                                <div class="grid min-w-0 flex-1 gap-1"><Label class="text-xs">Item</Label>
                                    <SearchSelect v-model="c.inventory_item_id" :options="consumableItems" placeholder="Select item…" @update:model-value="onConsumableChange(c)" />
                                    <span v-if="consumableWarning(c)" class="text-xs text-amber-600">{{ consumableWarning(c) }}</span>
                                </div>
                                <div class="grid w-24 gap-1"><Label class="text-xs">Qty</Label><Input type="number" step="0.001" min="0" v-model="c.quantity" /></div>
                                <div class="grid w-28 gap-1"><Label class="text-xs">Unit</Label>
                                    <SearchSelect v-model="c.unit_id" :options="units" placeholder="—" empty-label="—" />
                                </div>
                                <Button type="button" variant="ghost" size="icon-sm" @click="line.consumables.splice(ci, 1)"><X class="size-4" /></Button>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">Prefilled from the service recipe — add extras or remove what wasn't used.</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- Retail products -->
        <Card class="border-none">
            <CardHeader class="py-3"><CardTitle class="text-base">Retail products</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-1.5">
                    <Label>Add a product</Label>
                    <SearchSelect model-value="" :options="retailPicker" :search-threshold="0" placeholder="Select product to sell…" @update:model-value="(v) => v && addRetail(String(v))" />
                    <p v-if="retailError" class="text-xs text-rose-600">{{ retailError }}</p>
                    <p v-else class="text-xs text-muted-foreground">Selling a product deducts it from inventory stock. Out-of-stock products can't be added.</p>
                </div>
                <div v-if="form.retail.length" class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-2 py-2 font-medium">Product</th><th class="px-2 py-2 font-medium">Qty</th><th class="px-2 py-2 font-medium">Price</th><th class="px-2 py-2 font-medium">Disc.</th><th class="px-2 py-2 text-right font-medium">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(l, i) in form.retail" :key="i" class="border-t">
                                <td class="px-2 py-1.5">
                                    {{ l.label }} <span v-if="l.unit" class="text-xs text-muted-foreground">/ {{ l.unit }}</span>
                                    <span v-if="retailRowError(l)" class="block text-xs text-rose-600"><AlertTriangle class="inline size-3" /> only {{ l.on_hand }} in stock</span>
                                    <span v-else-if="retailRowWarn(l)" class="block text-xs text-amber-600">low stock — {{ l.on_hand }} left</span>
                                </td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.001" min="0" :max="l.on_hand" class="h-8 w-20" v-model="l.quantity" /></td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.01" min="0" class="h-8 w-24" v-model="l.unit_price" /></td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.01" min="0" class="h-8 w-20" v-model="l.discount" /></td>
                                <td class="px-2 py-1.5 text-right">{{ money(lineNet(l.quantity, l.unit_price, l.discount)) }}</td>
                                <td class="px-2 py-1.5"><Button type="button" variant="ghost" size="icon-sm" @click="removeRetail(i)"><Trash2 class="size-4 text-rose-600" /></Button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Freebies -->
        <Card class="border-none">
            <CardHeader class="py-3">
                <CardTitle class="text-base">Freebies <span class="text-xs font-normal text-muted-foreground">— given free, still deducted from stock</span></CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-1.5">
                    <Label>Add a freebie</Label>
                    <SearchSelect model-value="" :options="retailPicker" :search-threshold="0" placeholder="Select a product to give free…" @update:model-value="(v) => v && addFreebie(String(v))" />
                    <p v-if="freebieError" class="text-xs text-rose-600">{{ freebieError }}</p>
                    <p v-else class="text-xs text-muted-foreground">Billed at ₱0 (not added to the payment) but still deducted from inventory.</p>
                </div>
                <div v-if="form.freebies.length" class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-2 py-2 font-medium">Product</th><th class="px-2 py-2 font-medium">Qty</th><th class="px-2 py-2 text-right font-medium">Value</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(l, i) in form.freebies" :key="i" class="border-t">
                                <td class="px-2 py-1.5">
                                    {{ l.label }} <span v-if="l.unit" class="text-xs text-muted-foreground">/ {{ l.unit }}</span>
                                    <span v-if="freebieRowError(l)" class="block text-xs text-rose-600"><AlertTriangle class="inline size-3" /> only {{ l.on_hand }} in stock</span>
                                </td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.001" min="0" :max="l.on_hand" class="h-8 w-20" v-model="l.quantity" /></td>
                                <td class="px-2 py-1.5 text-right font-medium text-emerald-600">FREE</td>
                                <td class="px-2 py-1.5"><Button type="button" variant="ghost" size="icon-sm" @click="removeFreebie(i)"><Trash2 class="size-4 text-rose-600" /></Button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Manual lines -->
        <Card class="border-none">
            <CardHeader class="flex flex-row items-center justify-between py-3">
                <CardTitle class="text-base">Other charges</CardTitle>
                <Button type="button" variant="secondary" size="sm" @click="addManual"><Plus class="size-4" /> Add line</Button>
            </CardHeader>
            <CardContent>
                <div v-if="form.manual.length" class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-2 py-2 font-medium">Description</th><th class="px-2 py-2 font-medium">Qty</th><th class="px-2 py-2 font-medium">Price</th><th class="px-2 py-2 font-medium">Disc.</th><th class="px-2 py-2 text-right font-medium">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(l, i) in form.manual" :key="i" class="border-t">
                                <td class="px-2 py-1.5"><Input class="h-8" v-model="l.description" placeholder="Description" /></td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.001" min="0" class="h-8 w-20" v-model="l.quantity" /></td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.01" min="0" class="h-8 w-24" v-model="l.unit_price" /></td>
                                <td class="px-2 py-1.5"><Input type="number" step="0.01" min="0" class="h-8 w-20" v-model="l.discount" /></td>
                                <td class="px-2 py-1.5 text-right">{{ money(lineNet(l.quantity, l.unit_price, l.discount)) }}</td>
                                <td class="px-2 py-1.5"><Button type="button" variant="ghost" size="icon-sm" @click="removeManual(i)"><Trash2 class="size-4 text-rose-600" /></Button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="py-1 text-center text-sm text-muted-foreground">For ad-hoc charges not in the catalog.</p>
            </CardContent>
        </Card>

        <!-- Outstanding balance — settle existing invoices without leaving checkout -->
        <Card v-if="form.settlements.length" class="border-none border-l-4 border-l-amber-400">
            <CardHeader class="py-3">
                <CardTitle class="text-base">Outstanding balance <span class="text-xs font-normal text-muted-foreground">— collect now</span></CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-xs text-muted-foreground">{{ patientLabel }} has an unpaid balance. Collecting here applies straight to the original invoice — no need to open Billing.</p>
                <div v-for="s in form.settlements" :key="s.invoice_id" class="rounded-lg border p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" v-model="s.include" /> {{ s.label }}
                        </label>
                        <span class="text-xs text-muted-foreground">Balance {{ money(s.balance) }} · {{ s.invoice_no }}</span>
                    </div>
                    <template v-if="s.include">
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1"><Label class="text-xs">Collect</Label><Input type="number" step="0.01" min="0" :max="s.balance" v-model="s.amount" /></div>
                            <div class="grid gap-1"><Label class="text-xs">Discount</Label><Input type="number" step="0.01" min="0" v-model="s.discount" /></div>
                            <div class="grid gap-1"><Label class="text-xs">Method</Label><SearchSelect v-model="s.method" :options="methods" :sort="false" /></div>
                        </div>
                        <p v-if="s.per_session != null && drawnCourseIds.has(s.course_id)" class="mt-1.5 text-xs text-muted-foreground">Per session: {{ money(s.per_session) }} — pay one session now, or settle more.</p>
                        <p v-if="settlementError(s)" class="mt-1 text-xs text-rose-600">{{ settlementError(s) }}</p>
                    </template>
                </div>
            </CardContent>
        </Card>

        <!-- Payment & adjustments -->
        <Card class="border-none">
            <CardHeader class="py-3"><CardTitle class="text-base">Payment &amp; adjustments</CardTitle></CardHeader>
            <CardContent class="grid gap-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>Whole-invoice promotion</Label>
                        <SearchSelect v-model="form.invoice_promotion_id" :options="promotions" placeholder="— none —" empty-label="— none —" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Discount (whole order)</Label>
                        <Input type="number" step="0.01" min="0" v-model="form.invoice_discount" placeholder="0.00" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Notes</Label>
                    <textarea v-model="form.notes" rows="2" class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25" />
                </div>

                <div class="rounded-lg border p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium">Payments</span>
                        <div class="flex gap-2">
                            <Button type="button" variant="ghost" size="sm" @click="addPayment"><Plus class="size-4" /> Add</Button>
                            <Button type="button" variant="secondary" size="sm" :disabled="balance <= 0" @click="addRemaining">Add remaining</Button>
                        </div>
                    </div>
                    <p v-if="form.payments.length === 0" class="py-2 text-center text-xs text-muted-foreground">No payment now — the invoice will be left unpaid.</p>
                    <div v-for="(p, i) in form.payments" :key="i" class="mb-2 flex flex-wrap items-end gap-2">
                        <div class="grid w-32 gap-1"><Label class="text-xs">Method</Label>
                            <SearchSelect v-model="p.method" :options="methods" :sort="false" />
                        </div>
                        <div class="grid w-28 gap-1"><Label class="text-xs">Amount</Label><Input type="number" step="0.01" min="0" v-model="p.amount" /></div>
                        <div class="grid min-w-0 flex-1 gap-1"><Label class="text-xs">Reference</Label><Input v-model="p.reference" placeholder="e.g. GCash ref #" /></div>
                        <Button type="button" variant="ghost" size="icon-sm" @click="removePayment(i)"><X class="size-4" /></Button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.generate_receipt" /> Generate receipt after checkout</label>
            </CardContent>
        </Card>
        </div><!-- /main column -->

        <!-- Sticky order summary — pinned below the header, capped to the viewport -->
        <div class="lg:sticky lg:top-20">
            <Card class="border-none bg-muted/40 lg:max-h-[calc(100vh-6rem)]">
                <CardHeader class="shrink-0 py-3"><CardTitle class="text-base">Order summary</CardTitle></CardHeader>
                <CardContent class="min-h-0 flex-1 space-y-4 overflow-y-auto">
                    <p v-if="!hasLines && !includedSettlements.length" class="py-4 text-center text-sm text-muted-foreground">
                        Add a service or product, or settle a balance.
                    </p>
                    <template v-else>
                      <template v-if="hasLines">
                        <!-- Itemised lines by category -->
                        <div v-if="serviceItems.length" class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Services</p>
                            <div v-for="(it, i) in serviceItems" :key="'s' + i" class="flex justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate">{{ it.name }} <span class="text-xs text-muted-foreground">×{{ it.qty }}</span></span>
                                <span v-if="it.prepaid" class="shrink-0 text-xs" :class="it.coursePaid ? 'text-emerald-600' : 'text-amber-600'">{{ it.coursePaid ? 'prepaid' : 'on package · balance due' }}</span>
                                <span v-else class="shrink-0">{{ money(it.amount) }}</span>
                            </div>
                        </div>
                        <div v-if="retailItems.length" class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Retail products</p>
                            <div v-for="(it, i) in retailItems" :key="'r' + i" class="flex justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate">{{ it.name }} <span class="text-xs text-muted-foreground">×{{ it.qty }}</span></span>
                                <span class="shrink-0">{{ money(it.amount) }}</span>
                            </div>
                        </div>
                        <div v-if="manualItems.length" class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Other charges</p>
                            <div v-for="(it, i) in manualItems" :key="'m' + i" class="flex justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate">{{ it.name }} <span class="text-xs text-muted-foreground">×{{ it.qty }}</span></span>
                                <span class="shrink-0">{{ money(it.amount) }}</span>
                            </div>
                        </div>
                        <div v-if="freebieItems.length" class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Freebies</p>
                            <div v-for="(it, i) in freebieItems" :key="'f' + i" class="flex justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate">{{ it.name }} <span class="text-xs text-muted-foreground">×{{ it.qty }}</span></span>
                                <span class="shrink-0 text-xs text-emerald-600">free</span>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-1 border-t pt-2 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Subtotal</span><span>{{ money(subtotal) }}</span></div>
                            <div v-if="invoiceDiscount > 0" class="flex justify-between text-rose-600"><span>Order discount</span><span>−{{ money(invoiceDiscount) }}</span></div>
                            <div v-if="props.tax.enabled" class="flex justify-between"><span class="text-muted-foreground">Tax ({{ props.tax.rate }}%{{ props.tax.inclusive ? ' incl' : '' }})</span><span>{{ money(tax) }}</span></div>
                            <div class="flex justify-between border-t pt-1.5 text-lg font-semibold"><span>Total</span><span>{{ money(grand) }}</span></div>
                        </div>

                        <p v-if="form.invoice_promotion_id || form.services.some((l) => l.promotion_id)" class="rounded-md bg-primary/5 px-2 py-1.5 text-xs text-primary">
                            A promotion is applied — the final total may drop further at checkout.
                        </p>

                        <!-- Payment status for today's new charges -->
                        <div class="space-y-1 border-t pt-2 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Paid</span><span>{{ money(paid) }}</span></div>
                            <div class="flex justify-between font-medium" :class="balance > 0 ? 'text-amber-600' : 'text-emerald-600'"><span>Balance</span><span>{{ money(balance) }}</span></div>
                        </div>
                      </template>

                      <!-- Outstanding balances being settled now -->
                      <div v-if="includedSettlements.length" class="space-y-1" :class="hasLines ? 'border-t pt-2' : ''">
                          <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Balance settled</p>
                          <div v-for="s in includedSettlements" :key="s.invoice_id" class="flex justify-between gap-2 text-sm">
                              <span class="min-w-0 truncate">{{ s.label }} <span class="text-xs text-muted-foreground">{{ s.invoice_no }}</span></span>
                              <span class="shrink-0">{{ money(Number(s.amount) || 0) }}</span>
                          </div>
                      </div>

                      <!-- The one number to act on -->
                      <div v-if="includedSettlements.length" class="flex justify-between border-t pt-1.5 text-lg font-semibold">
                          <span>To collect now</span><span>{{ money(grand + settlementsTotal) }}</span>
                      </div>
                    </template>
                </CardContent>

                <!-- Action footer stays visible while the list above scrolls -->
                <div class="shrink-0 space-y-2 border-t px-6 pt-4">
                    <p v-if="requiresPatient && !form.patient_id" class="text-xs text-rose-600">Select a patient to perform services.</p>
                    <p v-if="!retailOk || !freebieOk" class="text-xs text-rose-600">A line exceeds available stock — reduce the quantity.</p>
                    <Button type="button" class="w-full" :disabled="form.processing || !canSubmit" @click="submit">Complete checkout</Button>
                </div>
            </Card>
        </div>
        </div><!-- /grid -->

        <!-- Quick-add patient dialog -->
        <Dialog v-model:open="patientDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader><DialogTitle>New patient</DialogTitle></DialogHeader>
                <form class="grid gap-3" @submit.prevent="savePatient">
                    <div class="grid gap-1.5"><Label>First name *</Label><Input v-model="patientForm.first_name" /></div>
                    <div class="grid gap-1.5"><Label>Last name</Label><Input v-model="patientForm.last_name" /></div>
                    <div class="grid gap-1.5"><Label>Contact number</Label><Input v-model="patientForm.phone" /></div>
                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button type="button" variant="ghost" @click="patientDialog = false">Cancel</Button>
                        <Button type="submit" :disabled="patientForm.processing || !patientForm.first_name">Add patient</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
