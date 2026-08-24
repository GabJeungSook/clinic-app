<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AreaChart from '@/components/charts/AreaChart.vue';
import ReportShell from '@/components/ReportShell.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Reports', href: '/reports/revenue' }, { title: 'Sales', href: '#' }] } });

const props = defineProps<{
    meta: { clinic: string | null; generated_at: string; currency: string };
    preset: string;
    range: { from: string; to: string; label: string };
    presets: Array<{ value: string; label: string }>;
    series: Array<{ label: string; value: number }>;
    methodBreakdown: Array<{ label: string; value: number }>;
    totals: { gross: number; refunds: number; net: number; count: number };
}>();

const money = (n: number) => `${props.meta.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const maxMethod = computed(() => Math.max(1, ...props.methodBreakdown.map((m) => m.value)));

const from = ref(props.range.from);
const to = ref(props.range.to);

const applyPreset = (preset: string) => router.get('/reports/revenue', { preset }, { preserveState: true, preserveScroll: true });
const applyCustom = () => router.get('/reports/revenue', { preset: 'custom', from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
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

        <!-- Totals -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Net sales</p><p class="text-2xl font-semibold">{{ money(totals.net) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Gross received</p><p class="text-2xl font-semibold">{{ money(totals.gross) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Refunds</p><p class="text-2xl font-semibold">{{ money(totals.refunds) }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Payments</p><p class="text-2xl font-semibold">{{ totals.count }}</p></CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">Sales — {{ range.label }}</CardTitle></CardHeader>
            <CardContent>
                <AreaChart :data="series" :format-value="money" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="text-base">By payment method</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <div v-for="(m, i) in methodBreakdown" :key="i" class="text-sm">
                    <div class="flex justify-between"><span>{{ m.label }}</span><span class="font-medium">{{ money(m.value) }}</span></div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" :style="{ width: `${(m.value / maxMethod) * 100}%` }" /></div>
                </div>
                <p v-if="methodBreakdown.length === 0" class="py-4 text-center text-muted-foreground">No payments in this period.</p>
            </CardContent>
        </Card>
    </ReportShell>
</template>
