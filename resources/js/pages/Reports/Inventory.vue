<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowDownToLine, ArrowUpFromLine } from '@lucide/vue';
import ReportShell from '@/components/ReportShell.vue';
import PrintableSection from '@/components/PrintableSection.vue';
import { usePrint } from '@/lib/print';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Inventory', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    range: { from: string; to: string; label: string };
    stockValue: number;
    movementTotals: { in_count: number; out_count: number };
    itemSummary: Array<{ item: string | null; in: number; out: number; net: number }>;
    ledger: Array<{ item: string | null; type: string; direction: string; quantity: number; occurred_at: string | null; by: string | null; reason: string | null }>;
    lowStock: Array<{ name: string; on_hand: number; reorder: number; unit: string | null }>;
    expiring: Array<{ item: string | null; batch: string | null; expiry: string | null; qty: number; expired: boolean }>;
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const qty = (n: number) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });

const from = ref(props.range.from);
const to = ref(props.range.to);
const applyRange = () => router.get('/reports/inventory', { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });

// Summary cards belong to the whole-report printout only, not to a single-list print.
const { activeSection } = usePrint();
</script>

<template>
    <Head title="Inventory report" />
    <ReportShell title="Inventory report" :subtitle="range.label" :meta="meta">
        <!-- Date range picker (screen only) -->
        <div class="no-print flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-3">
            <div class="grid gap-1"><Label class="text-xs">From</Label><Input type="date" v-model="from" class="h-9 w-40" /></div>
            <div class="grid gap-1"><Label class="text-xs">To</Label><Input type="date" v-model="to" class="h-9 w-40" /></div>
            <Button size="sm" @click="applyRange">Apply range</Button>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" :class="{ 'print-hidden': activeSection !== null }">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Stock value on hand</p><p class="text-2xl font-semibold">{{ money(stockValue) }}</p></CardContent></Card>
            <Card><CardContent class="flex items-center gap-3 p-4">
                <div class="rounded-lg bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"><ArrowDownToLine class="size-5" /></div>
                <div><p class="text-xs text-muted-foreground">Stock-in entries</p><p class="text-2xl font-semibold">{{ movementTotals.in_count }}</p></div>
            </CardContent></Card>
            <Card><CardContent class="flex items-center gap-3 p-4">
                <div class="rounded-lg bg-rose-100 p-2 text-rose-700 dark:bg-rose-950 dark:text-rose-300"><ArrowUpFromLine class="size-5" /></div>
                <div><p class="text-xs text-muted-foreground">Stock-out entries</p><p class="text-2xl font-semibold">{{ movementTotals.out_count }}</p></div>
            </CardContent></Card>
        </div>

        <!-- Ins & outs per item -->
        <PrintableSection section-key="ins-outs" title="Ins & outs per item" :subtitle="range.label" :meta="meta">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Item</th>
                            <th class="px-4 py-2 text-right font-medium">In</th>
                            <th class="px-4 py-2 text-right font-medium">Out</th>
                            <th class="px-4 py-2 text-right font-medium">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, k) in itemSummary" :key="k" class="border-b last:border-0">
                            <td class="px-4 py-2">{{ r.item ?? '—' }}</td>
                            <td class="px-4 py-2 text-right text-emerald-600">+{{ qty(r.in) }}</td>
                            <td class="px-4 py-2 text-right text-rose-600">-{{ qty(r.out) }}</td>
                            <td class="px-4 py-2 text-right font-medium" :class="r.net < 0 ? 'text-rose-600' : ''">{{ r.net > 0 ? '+' : '' }}{{ qty(r.net) }}</td>
                        </tr>
                        <tr v-if="itemSummary.length === 0"><td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No stock movements in this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </PrintableSection>

        <!-- Detailed movement ledger -->
        <PrintableSection section-key="movement-log" title="Movement log" :subtitle="range.label" :meta="meta">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Item</th>
                            <th class="px-4 py-2 font-medium">Type</th>
                            <th class="px-4 py-2 text-right font-medium">Qty</th>
                            <th class="px-4 py-2 font-medium">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(m, k) in ledger" :key="k" class="border-b last:border-0">
                            <td class="whitespace-nowrap px-4 py-2 text-muted-foreground">{{ m.occurred_at }}</td>
                            <td class="px-4 py-2">
                                {{ m.item ?? '—' }}
                                <span v-if="m.reason" class="block text-xs text-muted-foreground">{{ m.reason }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <Badge :class="m.direction === 'in' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'">{{ m.type }}</Badge>
                            </td>
                            <td class="px-4 py-2 text-right font-medium" :class="m.direction === 'in' ? 'text-emerald-600' : 'text-rose-600'">{{ m.quantity > 0 ? '+' : '' }}{{ qty(m.quantity) }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ m.by ?? '—' }}</td>
                        </tr>
                        <tr v-if="ledger.length === 0"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No stock movements in this period.</td></tr>
                    </tbody>
                </table>
            </div>
        </PrintableSection>

        <!-- Low stock -->
        <PrintableSection section-key="low-stock" title="Low stock items" subtitle="Current stock levels" :meta="meta">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                    <tr><th class="px-4 py-2 font-medium">Item</th><th class="px-4 py-2 text-right font-medium">On hand / reorder</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="(i, k) in lowStock" :key="k" class="border-b last:border-0">
                        <td class="px-4 py-2">{{ i.name }}</td>
                        <td class="px-4 py-2 text-right text-muted-foreground">{{ qty(i.on_hand) }} / {{ qty(i.reorder) }} <span v-if="i.unit">{{ i.unit }}</span></td>
                        <td class="px-4 py-2 text-right"><Badge variant="destructive">Low</Badge></td>
                    </tr>
                    <tr v-if="lowStock.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">All items above reorder level.</td></tr>
                </tbody>
            </table>
        </PrintableSection>

        <!-- Expiring / expired -->
        <PrintableSection section-key="expiring" title="Expiring / expired batches" subtitle="Current batches" :meta="meta">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                    <tr><th class="px-4 py-2 font-medium">Item</th><th class="px-4 py-2 text-right font-medium">Qty</th><th class="px-4 py-2 text-right font-medium">Expiry</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(b, k) in expiring" :key="k" class="border-b last:border-0">
                        <td class="px-4 py-2">{{ b.item }} <span class="text-xs text-muted-foreground">{{ b.batch }}</span></td>
                        <td class="px-4 py-2 text-right">{{ qty(b.qty) }}</td>
                        <td class="px-4 py-2 text-right"><Badge :variant="b.expired ? 'destructive' : 'secondary'">{{ b.expiry }}</Badge></td>
                    </tr>
                    <tr v-if="expiring.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Nothing expiring soon.</td></tr>
                </tbody>
            </table>
        </PrintableSection>
    </ReportShell>
</template>
