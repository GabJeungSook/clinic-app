<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ReportShell from '@/components/ReportShell.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Expenses', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    preset: string;
    range: { from: string; to: string; label: string };
    presets: Array<{ value: string; label: string }>;
    totals: { total: number; count: number };
    byCategory: Array<{ label: string; value: number }>;
    ledger: Array<{ spent_at: string | null; description: string; category: string | null; by: string | null; amount: number }>;
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const maxCat = computed(() => Math.max(1, ...props.byCategory.map((c) => c.value)));

const from = ref(props.range.from);
const to = ref(props.range.to);
const applyPreset = (preset: string) => router.get('/reports/expenses', { preset }, { preserveState: true, preserveScroll: true });
const applyCustom = () => router.get('/reports/expenses', { preset: 'custom', from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Expenses report" />
    <ReportShell title="Expenses report" :subtitle="range.label" :meta="meta">
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
        <div class="report-kpis grid grid-cols-2 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Total expenses</p><p class="text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ money(totals.total) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Entries</p><p class="text-2xl font-semibold">{{ totals.count }}</p></CardContent></Card>
        </div>

        <!-- By category -->
        <Card>
            <CardHeader><CardTitle class="text-base">By category</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <div v-for="(c, i) in byCategory" :key="i" class="text-sm">
                    <div class="flex justify-between"><span>{{ c.label }}</span><span class="font-medium">{{ money(c.value) }}</span></div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" :style="{ width: `${(c.value / maxCat) * 100}%` }" /></div>
                </div>
                <p v-if="byCategory.length === 0" class="py-4 text-center text-muted-foreground">No expenses in this period.</p>
            </CardContent>
        </Card>

        <!-- Ledger -->
        <Card>
            <CardHeader><CardTitle class="text-base">Expense detail</CardTitle></CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 font-medium">Description</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 font-medium">Recorded by</th>
                                <th class="px-4 py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e, i) in ledger" :key="i" class="border-b last:border-0">
                                <td class="px-4 py-2 whitespace-nowrap">{{ e.spent_at }}</td>
                                <td class="px-4 py-2">{{ e.description }}</td>
                                <td class="px-4 py-2">{{ e.category ?? '—' }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ e.by ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ money(e.amount) }}</td>
                            </tr>
                            <tr v-if="ledger.length === 0"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No expenses in this period.</td></tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </ReportShell>
</template>
