<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AreaChart from '@/components/charts/AreaChart.vue';
import ReportShell from '@/components/ReportShell.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Patients', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    series: Array<{ label: string; value: number }>;
    topPatients: Array<{ name: string; invoices: number; total: number }>;
    demographics: { male: number; female: number; other: number };
    totals: { total: number; new_month: number; new_30: number };
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const maxSpend = computed(() => Math.max(1, ...props.topPatients.map((p) => p.total)));
const demoTotal = computed(() => Math.max(1, props.demographics.male + props.demographics.female + props.demographics.other));
const pct = (n: number) => Math.round((n / demoTotal.value) * 100);
</script>

<template>
    <Head title="Patients report" />
    <ReportShell title="Patients report" subtitle="Last 30 days" :meta="meta">
        <div class="grid grid-cols-3 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Total patients</p><p class="text-2xl font-semibold">{{ totals.total }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">New this month</p><p class="text-2xl font-semibold">{{ totals.new_month }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">New (30d)</p><p class="text-2xl font-semibold">{{ totals.new_30 }}</p></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">New patients — last 30 days</CardTitle></CardHeader>
            <CardContent>
                <AreaChart :data="series" />
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle class="text-base">Top patients by spend</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-4 py-2 font-medium">Patient</th><th class="px-4 py-2 text-right font-medium">Invoices</th><th class="px-4 py-2 text-right font-medium">Total</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in topPatients" :key="i" class="border-b last:border-0">
                                <td class="px-4 py-2">{{ p.name }}</td>
                                <td class="px-4 py-2 text-right text-muted-foreground">{{ p.invoices }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ money(p.total) }}</td>
                            </tr>
                            <tr v-if="topPatients.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">No billed patients yet.</td></tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">Demographics</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="row in [{ label: 'Female', v: demographics.female, c: 'bg-pink-500' }, { label: 'Male', v: demographics.male, c: 'bg-sky-500' }, { label: 'Other', v: demographics.other, c: 'bg-violet-500' }]" :key="row.label" class="text-sm">
                        <div class="flex justify-between"><span>{{ row.label }}</span><span class="font-medium">{{ row.v }} · {{ pct(row.v) }}%</span></div>
                        <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full" :class="row.c" :style="{ width: `${pct(row.v)}%` }" /></div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </ReportShell>
</template>
