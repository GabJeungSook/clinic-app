<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import BarChart from '@/components/charts/BarChart.vue';
import ReportShell from '@/components/ReportShell.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Treatments', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    series: Array<{ label: string; value: number }>;
    topServices: Array<{ label: string; count: number }>;
    totals: { sessions_30: number; active_courses: number; completed_courses: number };
}>();

const maxSvc = computed(() => Math.max(1, ...props.topServices.map((s) => s.count)));
</script>

<template>
    <Head title="Treatments report" />
    <ReportShell title="Treatments report" subtitle="Last 30 days" :meta="meta">
        <div class="report-kpis grid grid-cols-3 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Sessions (30d)</p><p class="text-2xl font-semibold">{{ totals.sessions_30 }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Active courses</p><p class="text-2xl font-semibold">{{ totals.active_courses }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Completed</p><p class="text-2xl font-semibold">{{ totals.completed_courses }}</p></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">Sessions performed — last 30 days</CardTitle></CardHeader>
            <CardContent>
                <BarChart :data="series" color="bg-violet-500" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="text-base">Top courses sold</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <div v-for="(s, i) in topServices" :key="i" class="text-sm">
                    <div class="flex justify-between"><span>{{ s.label }}</span><span class="font-medium">{{ s.count }}</span></div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-violet-500" :style="{ width: `${(s.count / maxSvc) * 100}%` }" /></div>
                </div>
                <p v-if="topServices.length === 0" class="py-4 text-center text-muted-foreground">No courses sold.</p>
            </CardContent>
        </Card>
    </ReportShell>
</template>
