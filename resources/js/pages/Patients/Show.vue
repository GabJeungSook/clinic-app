<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { TriangleAlert, Pencil, CreditCard, Syringe } from '@lucide/vue';
import SearchSelect from '@/components/SearchSelect.vue';

const props = defineProps<{
    patient: Record<string, string | null>;
    safetyFlags: Array<{ type: string; title: string }>;
    histories: Array<{ id: string; type: string; title: string; details: string | null; recorded_at: string | null }>;
    courses: Array<{ id: string; name: string; service: string | null; status: string; total_sessions: number; sessions_completed: number; sessions_remaining: number }>;
    invoices: Array<{ id: string; invoice_no: string; status: string; grand_total: number; amount_paid: number; issued_at: string | null }>;
    historyTypes: Array<{ value: string; label: string }>;
    visits: Array<{ id: string; service: string | null; performed_at: string | null; by: string | null; notes: string | null; items: Array<{ name: string | null; qty: number; unit: string | null }> }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Patients', href: '/patients' }, { title: 'Profile', href: '#' }] },
});

const historyForm = useForm({ type: 'note', title: '', details: '' });

function addHistory() {
    historyForm.post(`/patients/${props.patient.id}/histories`, {
        preserveScroll: true,
        onSuccess: () => historyForm.reset('title', 'details'),
    });
}

const statusTone: Record<string, string> = {
    active: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    unpaid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    partially_paid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};
</script>

<template>
    <Head :title="patient.full_name ?? 'Patient'" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ patient.full_name }}</h1>
                <p class="text-sm text-muted-foreground">
                    <span class="font-mono">{{ patient.code }}</span>
                    <span v-if="patient.phone"> · {{ patient.phone }}</span>
                    <span v-if="patient.sex"> · <span class="capitalize">{{ patient.sex }}</span></span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button as-child>
                    <Link :href="`/checkout?patient=${patient.id}`"><CreditCard class="size-4" /> Checkout</Link>
                </Button>
                <Button as-child variant="secondary">
                    <Link :href="`/patients/${patient.id}/edit`"><Pencil class="size-4" /> Edit</Link>
                </Button>
            </div>
        </div>

        <!-- Safety flags -->
        <div v-if="safetyFlags.length" class="flex flex-wrap items-center gap-2 rounded-lg border border-rose-300/60 bg-rose-50/60 p-3 dark:bg-rose-950/20">
            <TriangleAlert class="size-4 text-rose-600" />
            <span class="text-sm font-medium text-rose-700 dark:text-rose-300">Alerts:</span>
            <Badge v-for="(f, i) in safetyFlags" :key="i" variant="destructive">{{ f.title }}</Badge>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Courses -->
            <Card>
                <CardHeader><CardTitle class="text-base">Treatment courses</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="courses.length === 0" class="py-4 text-center text-sm text-muted-foreground">No courses yet.</p>
                    <div v-for="c in courses" :key="c.id" class="rounded-lg border p-3">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ c.name }}</span>
                            <Badge :class="statusTone[c.status]">{{ c.status }}</Badge>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full bg-primary"
                                    :style="{ width: `${(c.sessions_completed / c.total_sessions) * 100}%` }"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ c.sessions_completed }}/{{ c.total_sessions }} · {{ c.sessions_remaining }} left
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Invoices -->
            <Card>
                <CardHeader><CardTitle class="text-base">Invoices</CardTitle></CardHeader>
                <CardContent>
                    <p v-if="invoices.length === 0" class="py-4 text-center text-sm text-muted-foreground">No invoices.</p>
                    <ul v-else class="divide-y divide-border">
                        <li v-for="inv in invoices" :key="inv.id" class="flex items-center justify-between py-2 text-sm">
                            <span class="font-mono text-xs">{{ inv.invoice_no }}</span>
                            <span>{{ fmt(inv.issued_at) }}</span>
                            <Badge :class="statusTone[inv.status]">{{ inv.status }}</Badge>
                            <span class="font-medium">{{ inv.grand_total.toLocaleString() }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Visit history -->
        <Card>
            <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Syringe class="size-4" /> Visit history</CardTitle></CardHeader>
            <CardContent>
                <p v-if="visits.length === 0" class="py-4 text-center text-sm text-muted-foreground">No recorded visits yet.</p>
                <ol v-else class="relative flex flex-col gap-4 border-l border-border pl-5">
                    <li v-for="v in visits" :key="v.id" class="relative">
                        <span class="absolute -left-[23px] top-1 size-2.5 rounded-full bg-primary ring-4 ring-background"></span>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-medium">{{ v.service ?? 'Service' }}</span>
                            <span class="text-xs text-muted-foreground">{{ fmt(v.performed_at) }}<span v-if="v.by"> · {{ v.by }}</span></span>
                        </div>
                        <p v-if="v.notes" class="mt-0.5 text-sm text-muted-foreground">{{ v.notes }}</p>
                        <div v-if="v.items.length" class="mt-1.5 flex flex-wrap gap-1.5">
                            <span v-for="(it, k) in v.items" :key="k" class="rounded-full bg-muted px-2 py-0.5 text-xs">
                                {{ it.name }} · {{ it.qty }}{{ it.unit ? ' ' + it.unit : '' }}
                            </span>
                        </div>
                    </li>
                </ol>
            </CardContent>
        </Card>

        <!-- Medical history -->
        <Card>
            <CardHeader><CardTitle class="text-base">Medical history</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <form
                    class="grid grid-cols-1 gap-3 sm:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"
                    @submit.prevent="addHistory"
                >
                    <div class="grid gap-1.5">
                        <Label for="h-type">Type</Label>
                        <SearchSelect id="h-type" v-model="historyForm.type" :options="historyTypes" :sort="false" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="h-title">Title</Label>
                        <Input id="h-title" v-model="historyForm.title" placeholder="e.g. Allergic to lidocaine" />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="h-details">Details</Label>
                        <Input id="h-details" v-model="historyForm.details" />
                    </div>
                    <Button type="submit" class="w-full sm:w-auto" :disabled="historyForm.processing || !historyForm.title">Add</Button>
                </form>

                <ul class="divide-y divide-border">
                    <li v-for="h in histories" :key="h.id" class="flex items-start justify-between gap-3 py-2 text-sm">
                        <div>
                            <Badge variant="outline" class="mr-2 capitalize">{{ h.type }}</Badge>
                            <span class="font-medium">{{ h.title }}</span>
                            <p v-if="h.details" class="text-muted-foreground">{{ h.details }}</p>
                        </div>
                        <span class="whitespace-nowrap text-xs text-muted-foreground">{{ fmt(h.recorded_at) }}</span>
                    </li>
                    <li v-if="histories.length === 0" class="py-4 text-center text-muted-foreground">No history recorded.</li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
