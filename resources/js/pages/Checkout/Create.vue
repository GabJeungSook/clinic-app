<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SearchSelect from '@/components/SearchSelect.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, Trash2, X, UserPlus, AlertTriangle } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Checkout', href: '/checkout' }] } });

interface Bom { inventory_item_id: string; item_name: string | null; quantity: number; unit_id: string | null }
interface ServiceOpt { value: string; label: string; price: number; sessions: number; bom: Bom[] }
interface CourseOpt { value: string; patient_id: string; service_id: string; label: string; remaining: number }
interface RetailOpt { value: string; label: string; price: number; unit: string | null; on_hand: number; reorder: number; is_low: boolean }
interface ConsumableOpt { value: string; label: string; base_unit_id: string; on_hand: number; is_low: boolean }
interface Consumable { inventory_item_id: string; quantity: number; unit_id: string }
interface ServiceLine { service_id: string; course_id: string; sessions: number; price: number; discount: number; promotion_id: string; notes: string; consumables: Consumable[] }
interface RetailLine { inventory_item_id: string; label: string; unit: string; quantity: number; unit_price: number; discount: number; on_hand: number; is_low: boolean }
interface ManualLine { description: string; quantity: number; unit_price: number; discount: number }
interface PaymentLine { method: string; amount: number; reference: string }

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
}>();

const servicesById = computed(() => Object.fromEntries(props.services.map((s) => [s.value, s])));
const itemsById = computed(() => Object.fromEntries(props.items.map((i) => [i.value, i])));
const consumableById = computed(() => Object.fromEntries(props.consumableItems.map((i) => [i.value, i])));

const form = useForm({
    patient_id: props.preselectedPatient ?? '',
    invoice_promotion_id: '',
    notes: '',
    services: [] as ServiceLine[],
    retail: [] as RetailLine[],
    manual: [] as ManualLine[],
    payments: [] as PaymentLine[],
    generate_receipt: true,
});

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
watch(() => form.patient_id, (id) => maybePrefillFromAppointment(id));
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

// ── Manual lines ─────────────────────────────────────────────────────────────
const addManual = () => form.manual.push({ description: '', quantity: 1, unit_price: 0, discount: 0 });
const removeManual = (i: number) => form.manual.splice(i, 1);

// ── Totals ───────────────────────────────────────────────────────────────────
const lineNet = (qty: number, price: number, discount: number) => Math.max(0, Number(qty) * Number(price) - Number(discount));
const subtotal = computed(() =>
    form.services.reduce((s, l) => s + serviceLineNet(l), 0) +
    form.retail.reduce((s, l) => s + lineNet(l.quantity, l.unit_price, l.discount), 0) +
    form.manual.reduce((s, l) => s + lineNet(l.quantity, l.unit_price, l.discount), 0));
const tax = computed(() => {
    if (!props.tax.enabled || subtotal.value <= 0) return 0;
    return props.tax.inclusive ? subtotal.value - subtotal.value / (1 + props.tax.rate / 100) : (subtotal.value * props.tax.rate) / 100;
});
const grand = computed(() => (props.tax.enabled && !props.tax.inclusive ? subtotal.value + tax.value : subtotal.value));

// ── Payments (split) ─────────────────────────────────────────────────────────
const paid = computed(() => form.payments.reduce((s, p) => s + (Number(p.amount) || 0), 0));
const balance = computed(() => Math.max(0, Math.round((grand.value - paid.value) * 100) / 100));
const addPayment = () => form.payments.push({ method: 'cash', amount: 0, reference: '' });
const addRemaining = () => form.payments.push({ method: 'cash', amount: balance.value, reference: '' });
const removePayment = (i: number) => form.payments.splice(i, 1);

// ── Guards + submit ──────────────────────────────────────────────────────────
const requiresPatient = computed(() => form.services.length > 0);
const hasLines = computed(() => form.services.length + form.retail.length + form.manual.length > 0);
const canSubmit = computed(() => hasLines.value && retailOk.value && !(requiresPatient.value && !form.patient_id));

function submit() {
    form
        .transform((data) => ({
            patient_id: data.patient_id || null,
            invoice_promotion_id: data.invoice_promotion_id || null,
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
                manual: data.manual
                    .filter((l) => l.description && Number(l.quantity) > 0)
                    .map((l) => ({ description: l.description, quantity: Number(l.quantity) || 0, unit_price: Number(l.unit_price) || 0, discount: Number(l.discount) || 0 })),
            },
            payments: data.payments.filter((p) => Number(p.amount) > 0).map((p) => ({ method: p.method, amount: Number(p.amount), reference: p.reference || null })),
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
    // Deep-linked or preselected patient → pull in their booking's services.
    maybePrefillFromAppointment(form.patient_id);
});
</script>

<template>
    <Head title="Checkout" />
    <div class="flex w-full flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Checkout</h1>
            <p class="text-sm text-muted-foreground">Perform services, sell products, and take payment — all in one place.</p>
        </div>

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
                        <div v-if="line.course_id" class="mt-3 rounded-md bg-primary/5 px-3 py-2 text-xs font-medium text-primary">
                            Drawing one prepaid session from the package — no charge (₱0).
                        </div>

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
                    <SearchSelect model-value="" :options="retailPicker" placeholder="Select product to sell…" @update:model-value="(v) => v && addRetail(String(v))" />
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

        <!-- Invoice-level + payment -->
        <Card class="border-none">
            <CardContent class="grid gap-6 p-4 lg:grid-cols-2">
                <div class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label>Whole-invoice promotion</Label>
                        <SearchSelect v-model="form.invoice_promotion_id" :options="promotions" placeholder="— none —" empty-label="— none —" />
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
                </div>

                <div class="flex flex-col justify-between gap-3 rounded-lg bg-muted/40 p-4">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-muted-foreground">Subtotal</span><span>{{ money(subtotal) }}</span></div>
                        <div v-if="props.tax.enabled" class="flex justify-between"><span class="text-muted-foreground">Tax ({{ props.tax.rate }}%{{ props.tax.inclusive ? ' incl' : '' }})</span><span>{{ money(tax) }}</span></div>
                        <div class="flex justify-between border-t pt-1 text-lg font-semibold"><span>Total</span><span>{{ money(grand) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Paid</span><span>{{ money(paid) }}</span></div>
                        <div class="flex justify-between font-medium" :class="balance > 0 ? 'text-amber-600' : 'text-emerald-600'"><span>Balance</span><span>{{ money(balance) }}</span></div>
                    </div>
                    <p v-if="!retailOk" class="text-xs text-rose-600">A retail line exceeds available stock — reduce the quantity.</p>
                    <Button type="button" class="w-full" :disabled="form.processing || !canSubmit" @click="submit">Complete checkout</Button>
                </div>
            </CardContent>
        </Card>

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
