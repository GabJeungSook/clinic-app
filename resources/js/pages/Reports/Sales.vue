<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ReportShell from '@/components/ReportShell.vue';
import PrintableSection from '@/components/PrintableSection.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Sales', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    preset: string;
    range: { from: string; to: string; label: string };
    presets: Array<{ value: string; label: string }>;
    totals: { count: number; subtotal: number; discount: number; tax: number; grand: number; collected: number; outstanding: number };
    byStatus: Array<{ label: string; count: number; total: number }>;
    byMethod: Array<{ label: string; value: number }>;
    itemsSold: Array<{ label: string; qty: number; total: number }>;
    ledger: Array<{ issued_at: string | null; invoice_no: string; patient: string | null; status: string; items: number; subtotal: number; discount: number; tax: number; grand: number; paid: number; due: number; methods: string[] }>;
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const qty = (n: number) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });
const maxMethod = computed(() => Math.max(1, ...props.byMethod.map((m) => m.value)));

const from = ref(props.range.from);
const to = ref(props.range.to);
const applyPreset = (preset: string) => router.get('/reports/sales', { preset }, { preserveState: true, preserveScroll: true });
const applyCustom = () => router.get('/reports/sales', { preset: 'custom', from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Sales report" />
    <ReportShell title="Sales report" :subtitle="range.label" :meta="meta">
        <!-- Period filter (screen only) -->
        <div class="no-print flex flex-col gap-3 rounded-lg border bg-muted/30 p-3">
            <div class="flex flex-wrap gap-1">
                <Button
                    v-for="p in presets"
                    :key="p.value"
                    size="sm"
                    :variant="preset === p.value ? 'default' : 'outline'"
                    @click="p.value === 'custom' ? applyCustom() : applyPreset(p.value)"
                >{{ p.label }}</Button>
            </div>
            <div v-if="preset === 'custom'" class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1"><Label class="text-xs">From</Label><Input type="date" v-model="from" class="h-9 w-40" /></div>
                <div class="grid gap-1"><Label class="text-xs">To</Label><Input type="date" v-model="to" class="h-9 w-40" /></div>
                <Button size="sm" @click="applyCustom">Apply</Button>
            </div>
        </div>

        <!-- Headline totals -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Gross sales</p><p class="text-2xl font-semibold">{{ money(totals.grand) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Invoices</p><p class="text-2xl font-semibold">{{ totals.count }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Collected</p><p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ money(totals.collected) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Outstanding</p><p class="text-2xl font-semibold" :class="totals.outstanding > 0 ? 'text-amber-600 dark:text-amber-400' : ''">{{ money(totals.outstanding) }}</p></CardContent></Card>
        </div>

        <!-- Money breakdown strip -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Subtotal</p><p class="text-lg font-semibold">{{ money(totals.subtotal) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Discounts</p><p class="text-lg font-semibold">-{{ money(totals.discount) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Tax</p><p class="text-lg font-semibold">{{ money(totals.tax) }}</p></CardContent></Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Sales by status -->
            <PrintableSection section-key="by-status" title="Sales by status" :subtitle="range.label" :meta="meta">
                <div class="space-y-1 p-4 text-sm">
                    <div v-for="(s, i) in byStatus" :key="i" class="flex items-center justify-between border-b py-1.5 last:border-0">
                        <span>{{ s.label }} <span class="text-xs text-muted-foreground">· {{ s.count }} invoice(s)</span></span>
                        <span class="font-medium">{{ money(s.total) }}</span>
                    </div>
                    <p v-if="byStatus.length === 0" class="py-4 text-center text-muted-foreground">No sales in this period.</p>
                </div>
            </PrintableSection>

            <!-- Payments by method -->
            <PrintableSection section-key="by-method" title="Payments by method" :subtitle="range.label" :meta="meta">
                <div class="space-y-2 p-4">
                    <div v-for="(m, i) in byMethod" :key="i" class="text-sm">
                        <div class="flex justify-between"><span>{{ m.label }}</span><span class="font-medium">{{ money(m.value) }}</span></div>
                        <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" :style="{ width: `${(m.value / maxMethod) * 100}%` }" /></div>
                    </div>
                    <p v-if="byMethod.length === 0" class="py-4 text-center text-muted-foreground">No payments in this period.</p>
                </div>
            </PrintableSection>
        </div>

        <!-- Items sold -->
        <PrintableSection section-key="items-sold" title="Items &amp; services sold" :subtitle="range.label" :meta="meta">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Item / service</th>
                            <th class="px-4 py-2 text-right font-medium">Qty</th>
                            <th class="px-4 py-2 text-right font-medium">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(it, i) in itemsSold" :key="i" class="border-b last:border-0">
                            <td class="px-4 py-2">{{ it.label }}</td>
                            <td class="px-4 py-2 text-right text-muted-foreground">{{ qty(it.qty) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ money(it.total) }}</td>
                        </tr>
                        <tr v-if="itemsSold.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Nothing sold in this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </PrintableSection>

        <!-- Full invoice ledger -->
        <PrintableSection section-key="invoices" title="Invoice detail" :subtitle="range.label" :meta="meta">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Date</th>
                            <th class="px-3 py-2 font-medium">Invoice</th>
                            <th class="px-3 py-2 font-medium">Patient</th>
                            <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                            <th class="px-3 py-2 text-right font-medium">Disc</th>
                            <th class="px-3 py-2 text-right font-medium">Tax</th>
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                            <th class="px-3 py-2 text-right font-medium">Paid</th>
                            <th class="px-3 py-2 text-right font-medium">Due</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in ledger" :key="i" class="border-b last:border-0">
                            <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ r.issued_at }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ r.invoice_no }}</td>
                            <td class="px-3 py-2">{{ r.patient ?? 'Walk-in' }}</td>
                            <td class="px-3 py-2 text-right">{{ money(r.subtotal) }}</td>
                            <td class="px-3 py-2 text-right">{{ r.discount > 0 ? '-' + money(r.discount) : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ money(r.tax) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ money(r.grand) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ money(r.paid) }}</td>
                            <td class="px-3 py-2 text-right" :class="r.due > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">{{ money(r.due) }}</td>
                            <td class="px-3 py-2">{{ r.status }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ r.methods.join(', ') || '—' }}</td>
                        </tr>
                        <tr v-if="ledger.length === 0"><td colspan="11" class="px-4 py-8 text-center text-muted-foreground">No invoices in this period.</td></tr>
                    </tbody>
                    <tfoot v-if="ledger.length > 0" class="border-t-2 font-semibold">
                        <tr>
                            <td class="px-3 py-2" colspan="3">Total · {{ totals.count }} invoice(s)</td>
                            <td class="px-3 py-2 text-right">{{ money(totals.subtotal) }}</td>
                            <td class="px-3 py-2 text-right">-{{ money(totals.discount) }}</td>
                            <td class="px-3 py-2 text-right">{{ money(totals.tax) }}</td>
                            <td class="px-3 py-2 text-right">{{ money(totals.grand) }}</td>
                            <td class="px-3 py-2 text-right">{{ money(totals.collected) }}</td>
                            <td class="px-3 py-2 text-right">{{ money(totals.outstanding) }}</td>
                            <td class="px-3 py-2" colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </PrintableSection>
    </ReportShell>
</template>
