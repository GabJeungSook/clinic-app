<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import BarChart from '@/components/charts/BarChart.vue';
import ReportShell from '@/components/ReportShell.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Appointments', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    series: Array<{ label: string; value: number }>;
    statusCounts: Array<{ label: string; value: number }>;
    topServices: Array<{ label: string; count: number }>;
    totals: { total: number; completed: number; no_show: number; no_show_rate: number };
}>();

const maxSvc = computed(() => Math.max(1, ...props.topServices.map((s) => s.count)));
const maxStatus = computed(() => Math.max(1, ...props.statusCounts.map((s) => s.value)));
</script>

<template>
    <Head title="Appointments report" />
    <ReportShell title="Appointments report" subtitle="Last 30 days" :meta="meta">
        <div class="report-kpis grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Booked</p><p class="text-2xl font-semibold">{{ totals.total }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Completed</p><p class="text-2xl font-semibold">{{ totals.completed }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">No-shows</p><p class="text-2xl font-semibold">{{ totals.no_show }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">No-show rate</p><p class="text-2xl font-semibold">{{ totals.no_show_rate }}%</p></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">Bookings per day — last 30 days</CardTitle></CardHeader>
            <CardContent>
                <BarChart :data="series" color="bg-sky-500" />
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle class="text-base">Status breakdown</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div v-for="(s, i) in statusCounts" :key="i" class="text-sm">
                        <div class="flex justify-between"><span>{{ s.label }}</span><span class="font-medium">{{ s.value }}</span></div>
                        <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-sky-500" :style="{ width: `${(s.value / maxStatus) * 100}%` }" /></div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">Most booked services</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div v-for="(s, i) in topServices" :key="i" class="text-sm">
                        <div class="flex justify-between"><span>{{ s.label }}</span><span class="font-medium">{{ s.count }}</span></div>
                        <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" :style="{ width: `${(s.count / maxSvc) * 100}%` }" /></div>
                    </div>
                    <p v-if="topServices.length === 0" class="py-4 text-center text-muted-foreground">No bookings yet.</p>
                </CardContent>
            </Card>
        </div>
    </ReportShell>
</template>
