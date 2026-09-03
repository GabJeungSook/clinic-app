<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AreaChart from '@/components/charts/AreaChart.vue';
import BarChart from '@/components/charts/BarChart.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    Users,
    CalendarDays,
    ReceiptText,
    TriangleAlert,
    CalendarClock,
    Wallet,
    ArrowRight,
    Plus,
} from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

interface Stats {
    revenue_today: number;
    revenue_month: number;
    patients: number;
    active_courses: number;
    sessions_today: number;
    open_invoices: number;
    outstanding_amount: number;
    low_stock_count: number;
    expiring_soon_count: number;
    oversold_count: number;
    appointments_today: number;
}

interface Point { label: string; value: number }

const props = defineProps<{
    stats: Stats;
    lowStock: Array<{ id: string; name: string; unit: string | null; stock_on_hand_cache: number; reorder_level: number }>;
    oversold: Array<{ id: string; name: string; unit: string | null; on_hand: number }>;
    expiringSoon: Array<{ id: string; batch_number: string | null; expiry_date: string; qty_remaining_cache: number; item: { name: string } }>;
    appointmentsToday: Array<{ id: string; name: string; service: string | null; time: string }>;
    revenueSeries: Point[];
    sessionsSeries: Point[];
    currency: string;
}>();

const page = usePage();
const appName = page.props.name as string;
const firstName = computed(() => {
    const full = (page.props.auth?.user?.name as string | undefined) ?? '';
    return full.split(' ')[0] || 'there';
});
const money = (n: number) =>
    `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});
const today = computed(() =>
    new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
);

// Four headline metrics, reference-style: label + big value + a contextual sub-line.
const kpis = computed(() => [
    {
        label: "Today's revenue",
        value: money(props.stats.revenue_today),
        icon: Wallet,
        chip: 'bg-primary/10 text-primary',
        sub: 'This month',
        subValue: money(props.stats.revenue_month),
        subClass: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        label: 'Appointments today',
        value: props.stats.appointments_today,
        icon: CalendarDays,
        chip: 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400',
        sub: 'Sessions performed',
        subValue: String(props.stats.sessions_today),
        subClass: 'text-muted-foreground',
    },
    {
        label: 'Patients',
        value: props.stats.patients,
        icon: Users,
        chip: 'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
        sub: 'Active courses',
        subValue: String(props.stats.active_courses),
        subClass: 'text-muted-foreground',
    },
    {
        label: 'Open invoices',
        value: props.stats.open_invoices,
        icon: ReceiptText,
        chip: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
        sub: 'Outstanding',
        subValue: money(props.stats.outstanding_amount),
        subClass: props.stats.outstanding_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Greeting -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ greeting }}, {{ firstName }}!</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ appName }} · {{ today }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link href="/appointments"><CalendarDays class="size-4" /> Appointments</Link>
                </Button>
                <Button as-child>
                    <Link href="/checkout"><Plus class="size-4" /> New checkout</Link>
                </Button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="kpi in kpis" :key="kpi.label" class="border-none transition-shadow hover:shadow-[0_2px_4px_hsl(320_30%_20%/0.06),0_16px_40px_-20px_hsl(320_40%_30%/0.22)]">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-muted-foreground">{{ kpi.label }}</p>
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl" :class="kpi.chip">
                            <component :is="kpi.icon" class="size-4.5" />
                        </div>
                    </div>
                    <p class="mt-3 text-3xl font-semibold leading-none tracking-tight">{{ kpi.value }}</p>
                    <p class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                        {{ kpi.sub }}
                        <span class="font-semibold" :class="kpi.subClass">{{ kpi.subValue }}</span>
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Charts -->
        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="border-none lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Revenue</CardTitle>
                    <p class="text-xs text-muted-foreground">Last 14 days</p>
                </CardHeader>
                <CardContent>
                    <AreaChart :data="revenueSeries" :format-value="money" />
                </CardContent>
            </Card>

            <Card class="border-none">
                <CardHeader>
                    <CardTitle class="text-base">Sessions performed</CardTitle>
                    <p class="text-xs text-muted-foreground">Last 14 days</p>
                </CardHeader>
                <CardContent>
                    <BarChart :data="sessionsSeries" color="bg-violet-500" />
                </CardContent>
            </Card>
        </div>

        <!-- Oversold / needs-attention banner -->
        <Card v-if="oversold.length > 0" class="border-rose-300/60 bg-rose-50/60 dark:bg-rose-950/20">
            <CardContent class="flex flex-wrap items-center gap-3 p-4">
                <div class="flex size-9 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-900">
                    <TriangleAlert class="size-5" />
                </div>
                <span class="text-sm">
                    <strong>{{ stats.oversold_count }}</strong> item(s) show negative stock (oversold) —
                    <span class="text-muted-foreground">
                        {{ oversold.slice(0, 3).map((o) => `${o.name} (${Number(o.on_hand)} ${o.unit ?? ''})`).join(', ') }}<span v-if="oversold.length > 3"> …</span>.
                    </span>
                    Do a stock count to correct them.
                </span>
                <Link href="/inventory?sort=stock_asc" class="ml-auto flex items-center gap-1 text-sm font-medium text-rose-700 hover:underline dark:text-rose-400">
                    Review <ArrowRight class="size-3.5" />
                </Link>
            </CardContent>
        </Card>

        <!-- Outstanding banner -->
        <Card v-if="stats.outstanding_amount > 0" class="border-amber-300/60 bg-amber-50/60 dark:bg-amber-950/20">
            <CardContent class="flex items-center gap-3 p-4">
                <div class="flex size-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900">
                    <ReceiptText class="size-5" />
                </div>
                <span class="text-sm">
                    <strong>{{ money(stats.outstanding_amount) }}</strong> outstanding across
                    {{ stats.open_invoices }} unpaid invoice(s).
                </span>
                <Link href="/invoices?status=unpaid" class="ml-auto flex items-center gap-1 text-sm font-medium text-amber-700 hover:underline dark:text-amber-400">
                    View <ArrowRight class="size-3.5" />
                </Link>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Today's schedule -->
            <Card class="border-none">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-950">
                            <CalendarDays class="size-4" />
                        </span>
                        Today's schedule
                    </CardTitle>
                    <Badge variant="secondary">{{ stats.appointments_today }}</Badge>
                </CardHeader>
                <CardContent>
                    <p v-if="appointmentsToday.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        No appointments today.
                    </p>
                    <ul v-else class="divide-y divide-border">
                        <li v-for="a in appointmentsToday" :key="a.id" class="flex items-center justify-between gap-2 py-2.5 text-sm">
                            <span class="min-w-0">
                                <span class="font-medium">{{ a.name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ a.service ?? 'No service' }}</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{{ a.time }}</span>
                        </li>
                    </ul>
                    <Link href="/appointments" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        View appointments <ArrowRight class="size-3.5" />
                    </Link>
                </CardContent>
            </Card>

            <!-- Low stock -->
            <Card class="border-none">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-950">
                            <TriangleAlert class="size-4" />
                        </span>
                        Low stock
                    </CardTitle>
                    <Badge variant="secondary">{{ stats.low_stock_count }}</Badge>
                </CardHeader>
                <CardContent>
                    <p v-if="lowStock.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Everything is well stocked.
                    </p>
                    <ul v-else class="divide-y divide-border">
                        <li v-for="i in lowStock" :key="i.id" class="flex items-center justify-between py-2.5 text-sm">
                            <span class="font-medium">{{ i.name }}</span>
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                                {{ Number(i.stock_on_hand_cache) }} / {{ Number(i.reorder_level) }} {{ i.unit }}
                            </span>
                        </li>
                    </ul>
                    <Link href="/inventory" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        View inventory <ArrowRight class="size-3.5" />
                    </Link>
                </CardContent>
            </Card>

            <!-- Expiring soon -->
            <Card class="border-none">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-950">
                            <CalendarClock class="size-4" />
                        </span>
                        Expiring soon
                    </CardTitle>
                    <Badge variant="secondary">{{ stats.expiring_soon_count }}</Badge>
                </CardHeader>
                <CardContent>
                    <p v-if="expiringSoon.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        No batches nearing expiry.
                    </p>
                    <ul v-else class="divide-y divide-border">
                        <li v-for="b in expiringSoon" :key="b.id" class="flex items-center justify-between py-2.5 text-sm">
                            <span class="font-medium">
                                {{ b.item.name }}
                                <span class="text-muted-foreground">· {{ b.batch_number ?? '—' }}</span>
                            </span>
                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-600 dark:bg-rose-950 dark:text-rose-400">{{ fmt(b.expiry_date) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
