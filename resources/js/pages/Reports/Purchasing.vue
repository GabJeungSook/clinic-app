<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ReportShell from '@/components/ReportShell.vue';
import PrintableSection from '@/components/PrintableSection.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Purchasing', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    range: { from: string; to: string; label: string };
    totals: { spend: number; count: number; suppliers: number; lines: number };
    bySupplier: Array<{ label: string; total: number; count: number }>;
    topItems: Array<{ label: string; spend: number; qty: number }>;
    ledger: Array<{ reference: string | null; supplier: string | null; received_at: string | null; items: number; total: number }>;
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const qty = (n: number) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });
const maxSupplier = computed(() => Math.max(1, ...props.bySupplier.map((s) => s.total)));
const maxItem = computed(() => Math.max(1, ...props.topItems.map((s) => s.spend)));

const from = ref(props.range.from);
const to = ref(props.range.to);
const applyRange = () => router.get('/reports/purchasing', { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Purchasing report" />
    <ReportShell title="Purchasing report" :subtitle="range.label" :meta="meta">
        <!-- Date range (screen only) -->
        <div class="no-print flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-3">
            <div class="grid gap-1"><Label class="text-xs">From</Label><Input type="date" v-model="from" class="h-9 w-40" /></div>
            <div class="grid gap-1"><Label class="text-xs">To</Label><Input type="date" v-model="to" class="h-9 w-40" /></div>
            <Button size="sm" @click="applyRange">Apply range</Button>
        </div>

        <!-- Totals -->
        <div class="report-kpis grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Total spend</p><p class="text-2xl font-semibold">{{ money(totals.spend) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Purchases</p><p class="text-2xl font-semibold">{{ totals.count }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Suppliers</p><p class="text-2xl font-semibold">{{ totals.suppliers }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Item lines</p><p class="text-2xl font-semibold">{{ totals.lines }}</p></CardContent></Card>
        </div>

        <!-- Spend by supplier -->
        <PrintableSection section-key="by-supplier" title="Spend by supplier" :subtitle="range.label" :meta="meta">
            <div class="space-y-2 p-4">
                <div v-for="(s, i) in bySupplier" :key="i" class="text-sm">
                    <div class="flex justify-between"><span>{{ s.label }} <span class="text-xs text-muted-foreground">· {{ s.count }} purchase(s)</span></span><span class="font-medium">{{ money(s.total) }}</span></div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" :style="{ width: `${(s.total / maxSupplier) * 100}%` }" /></div>
                </div>
                <p v-if="bySupplier.length === 0" class="py-4 text-center text-muted-foreground">No purchases received in this period.</p>
            </div>
        </PrintableSection>

        <!-- Top purchased items -->
        <PrintableSection section-key="top-items" title="Most purchased items" :subtitle="range.label" :meta="meta">
            <div class="space-y-2 p-4">
                <div v-for="(it, i) in topItems" :key="i" class="text-sm">
                    <div class="flex justify-between"><span>{{ it.label }} <span class="text-xs text-muted-foreground">· {{ qty(it.qty) }} units</span></span><span class="font-medium">{{ money(it.spend) }}</span></div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-emerald-500" :style="{ width: `${(it.spend / maxItem) * 100}%` }" /></div>
                </div>
                <p v-if="topItems.length === 0" class="py-4 text-center text-muted-foreground">No items received in this period.</p>
            </div>
        </PrintableSection>

        <!-- Purchases ledger -->
        <PrintableSection section-key="purchases" title="Purchases received" :subtitle="range.label" :meta="meta">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Received</th>
                            <th class="px-4 py-2 font-medium">Reference</th>
                            <th class="px-4 py-2 font-medium">Supplier</th>
                            <th class="px-4 py-2 text-right font-medium">Items</th>
                            <th class="px-4 py-2 text-right font-medium">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in ledger" :key="i" class="border-b last:border-0">
                            <td class="whitespace-nowrap px-4 py-2 text-muted-foreground">{{ p.received_at }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ p.reference ?? '—' }}</td>
                            <td class="px-4 py-2">{{ p.supplier ?? 'No supplier' }}</td>
                            <td class="px-4 py-2 text-right text-muted-foreground">{{ p.items }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ money(p.total) }}</td>
                        </tr>
                        <tr v-if="ledger.length === 0"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No purchases received in this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </PrintableSection>
    </ReportShell>
</template>
