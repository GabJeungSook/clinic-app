<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';

interface Appt {
    id: string;
    name: string;
    service: string | null;
    time: string;
    status: string;
    date: string;
}

const props = defineProps<{
    appointments: Appt[];
    calendar: { month: string; label: string; prev: string; next: string; current: string; today: string };
    status: string;
}>();

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const byDate = computed(() => {
    const map: Record<string, Appt[]> = {};
    for (const a of props.appointments) (map[a.date] ??= []).push(a);
    return map;
});

const cells = computed(() => {
    const [y, m] = props.calendar.month.split('-').map(Number);
    const monthIdx = m - 1;
    const startWeekday = new Date(y, monthIdx, 1).getDay();
    const daysInMonth = new Date(y, monthIdx + 1, 0).getDate();

    const out: Array<{ day: number; dateStr: string } | null> = [];
    for (let i = 0; i < startWeekday; i++) out.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        out.push({ day: d, dateStr: `${props.calendar.month}-${String(d).padStart(2, '0')}` });
    }
    while (out.length % 7 !== 0) out.push(null);
    return out;
});

const href = (month: string) => `/appointments?view=calendar&month=${month}&status=${props.status}`;

const dot: Record<string, string> = {
    scheduled: 'bg-amber-500',
    confirmed: 'bg-emerald-500',
    completed: 'bg-sky-500',
    cancelled: 'bg-muted-foreground',
    no_show: 'bg-rose-500',
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Month navigation -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold tracking-tight">{{ calendar.label }}</h2>
            <div class="flex items-center gap-1">
                <Link :href="href(calendar.current)" class="rounded-lg border px-3 py-1.5 text-sm hover:bg-muted">Today</Link>
                <Link :href="href(calendar.prev)" class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"><ChevronLeft class="size-4" /></Link>
                <Link :href="href(calendar.next)" class="flex size-9 items-center justify-center rounded-lg border hover:bg-muted"><ChevronRight class="size-4" /></Link>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[720px]">
                <!-- Weekday header -->
                <div class="grid grid-cols-7 border-b text-xs font-medium text-muted-foreground">
                    <div v-for="w in weekdays" :key="w" class="px-2 py-2 text-center">{{ w }}</div>
                </div>

                <!-- Day grid -->
                <div class="grid grid-cols-7">
                    <div
                        v-for="(cell, i) in cells"
                        :key="i"
                        class="group relative min-h-28 border-b border-r p-1.5"
                        :class="[
                            i % 7 === 0 ? 'border-l' : '',
                            cell && cell.dateStr === calendar.today ? 'bg-primary/5' : cell ? '' : 'bg-muted/30',
                        ]"
                    >
                        <template v-if="cell">
                            <div class="mb-1 flex items-center justify-between">
                                <span
                                    class="flex size-6 items-center justify-center rounded-full text-xs"
                                    :class="cell.dateStr === calendar.today ? 'bg-primary font-semibold text-primary-foreground' : 'text-muted-foreground'"
                                >{{ cell.day }}</span>
                                <Link
                                    :href="`/appointments/create?date=${cell.dateStr}`"
                                    class="hidden size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground group-hover:flex"
                                    title="Book on this day"
                                ><Plus class="size-3.5" /></Link>
                            </div>

                            <div class="flex flex-col gap-1">
                                <div
                                    v-for="a in (byDate[cell.dateStr] || []).slice(0, 3)"
                                    :key="a.id"
                                    class="flex items-center gap-1 truncate rounded bg-muted/70 px-1.5 py-0.5 text-[11px]"
                                    :title="`${a.time} · ${a.name}${a.service ? ' · ' + a.service : ''} (${a.status.replace('_', ' ')})`"
                                >
                                    <span class="size-1.5 shrink-0 rounded-full" :class="dot[a.status]"></span>
                                    <span class="shrink-0 font-medium">{{ a.time }}</span>
                                    <span class="truncate text-muted-foreground">{{ a.name }}</span>
                                </div>
                                <span
                                    v-if="(byDate[cell.dateStr] || []).length > 3"
                                    class="px-1.5 text-[11px] font-medium text-muted-foreground"
                                >+{{ (byDate[cell.dateStr] || []).length - 3 }} more</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-amber-500"></span> Scheduled</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Confirmed</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-sky-500"></span> Completed</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> No show</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-muted-foreground"></span> Cancelled</span>
        </div>
    </div>
</template>
