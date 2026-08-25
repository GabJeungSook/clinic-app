<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { CalendarPlus, Trash2, Clock, List, CalendarDays, Search, Pencil } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Appointments', href: '/appointments' }] } });

interface Appt {
    id: string;
    name: string;
    phone: string | null;
    patient_id: string | null;
    service: string | null;
    service_id: string | null;
    course_id: string | null;
    staff: string | null;
    date: string;
    time: string;
    status: string;
    notes: string | null;
}

const props = defineProps<{
    view: string;
    appointments: Appt[];
    filters: { range: string; status: string; search?: string };
    statuses: Array<{ value: string; label: string }>;
    calendar?: { month: string; label: string; prev: string; next: string; current: string; today: string };
}>();

const isCalendar = computed(() => props.view === 'calendar');
const search = ref(props.filters.search ?? '');
const submitSearch = () =>
    router.get('/appointments', { view: 'list', range: props.filters.range, status: props.filters.status, search: search.value }, { preserveState: true, replace: true });

const grouped = computed(() => {
    const map: Record<string, Appt[]> = {};
    for (const a of props.appointments) (map[a.date] ??= []).push(a);
    return Object.entries(map).map(([date, items]) => ({ date, items }));
});

const dateLabel = (d: string) =>
    `${new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long' })} · ${fmt(d)}`;

const switchView = (view: string) => {
    if (view === 'calendar') router.get('/appointments', { view: 'calendar', status: props.filters.status }, { preserveState: false });
    else router.get('/appointments', { view: 'list', range: 'upcoming', status: props.filters.status }, { preserveState: false });
};

const setStatus = (status: string) => {
    if (isCalendar.value) router.get('/appointments', { view: 'calendar', month: props.calendar?.month, status }, { preserveState: true, replace: true });
    else router.get('/appointments', { view: 'list', range: props.filters.range, status, search: search.value }, { preserveState: true, replace: true });
};

const setRange = (range: string) =>
    router.get('/appointments', { view: 'list', range, status: props.filters.status, search: search.value }, { preserveState: true, replace: true });

const postStatus = (id: string, status: string) =>
    router.post(`/appointments/${id}/status`, { status }, { preserveScroll: true });

// Confirming (= patient has arrived) asks for a quick confirmation first.
const confirming = ref<Appt | null>(null);
const changeStatus = (id: string, status: string) => {
    if (status === 'confirmed') {
        confirming.value = props.appointments.find((a) => a.id === id) ?? null;
        return;
    }
    postStatus(id, status);
};
const confirmArrival = () => {
    if (!confirming.value) return;
    postStatus(confirming.value.id, 'confirmed');
    confirming.value = null;
};

// "Completed" is set automatically when a patient is checked out, so it's not a
// manual choice; a completed booking is a finished, read-only record.
const editableStatuses = computed(() => props.statuses.filter((s) => s.value !== 'completed'));

// Deletion is only offered for resolved bookings (cancelled / no-show), with an
// option to also remove a patient who was created for a booking that fell through.
const canDelete = (status: string) => status === 'cancelled' || status === 'no_show';
const deleting = ref<Appt | null>(null);
const alsoDeletePatient = ref(false);
const openDelete = (a: Appt) => {
    deleting.value = a;
    alsoDeletePatient.value = false;
};
const confirmDelete = () => {
    if (!deleting.value) return;
    router.delete(`/appointments/${deleting.value.id}`, {
        data: { delete_patient: alsoDeletePatient.value },
        preserveScroll: true,
        onSuccess: () => { deleting.value = null; },
    });
};

const tone: Record<string, string> = {
    scheduled: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    completed: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    cancelled: 'bg-muted text-muted-foreground',
    no_show: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
};
const ranges = [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'today', label: 'Today' },
    { value: 'past', label: 'Past' },
    { value: 'all', label: 'All' },
];
</script>

<template>
    <Head title="Appointments" />
    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold tracking-tight">Appointments</h1>
            <div class="flex items-center gap-2">
                <!-- View toggle -->
                <div class="flex rounded-lg border p-0.5">
                    <button
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition"
                        :class="!isCalendar ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="switchView('list')"
                    ><List class="size-4" /> List</button>
                    <button
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition"
                        :class="isCalendar ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="switchView('calendar')"
                    ><CalendarDays class="size-4" /> Calendar</button>
                </div>
                <Button as-child><Link href="/appointments/create"><CalendarPlus class="size-4" /> Book</Link></Button>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <div v-if="!isCalendar" class="flex gap-1">
                <Button v-for="r in ranges" :key="r.value" size="sm" :variant="filters.range === r.value ? 'default' : 'outline'" @click="setRange(r.value)">{{ r.label }}</Button>
            </div>
            <div v-if="!isCalendar" class="relative w-56">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search patient…" class="h-9 pl-8" @keyup.enter="submitSearch" />
            </div>
            <div class="w-44">
                <SearchSelect
                    :model-value="filters.status"
                    :options="[{ value: 'all', label: 'All statuses' }, ...statuses]"
                    :sort="false"
                    @update:model-value="(v) => setStatus(String(v))"
                />
            </div>
        </div>

        <!-- Calendar view -->
        <Card v-if="isCalendar && calendar" class="border-none">
            <CardContent class="p-4">
                <AppointmentCalendar :appointments="appointments" :calendar="calendar" :status="filters.status" />
            </CardContent>
        </Card>

        <!-- List view -->
        <template v-else>
            <div v-if="grouped.length === 0" class="py-16 text-center text-muted-foreground">
                No appointments for this view.
            </div>

            <div v-for="group in grouped" :key="group.date" class="flex flex-col gap-2">
                <h2 class="mt-2 text-sm font-semibold text-muted-foreground">{{ dateLabel(group.date) }}</h2>
                <Card class="border-none">
                    <CardContent class="divide-y divide-border p-0">
                        <div v-for="a in group.items" :key="a.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div class="flex w-20 shrink-0 items-center gap-1.5 text-sm font-medium">
                                <Clock class="size-3.5 text-muted-foreground" />{{ a.time }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ a.name }}</div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ a.service ?? 'No service' }}
                                    <span v-if="a.staff"> · {{ a.staff }}</span>
                                    <span v-if="a.phone"> · {{ a.phone }}</span>
                                </div>
                            </div>
                            <Badge :class="tone[a.status]" class="capitalize">{{ a.status.replace('_', ' ') }}</Badge>
                            <!-- Completed bookings are finished records: no status change, no actions. -->
                            <template v-if="a.status !== 'completed'">
                                <Button v-if="a.status === 'scheduled' || a.status === 'confirmed'" as-child variant="ghost" size="icon-sm" title="Edit / reschedule">
                                    <Link :href="`/appointments/${a.id}/edit`"><Pencil class="size-4" /></Link>
                                </Button>
                                <div class="w-40">
                                    <SearchSelect
                                        :model-value="a.status"
                                        :options="editableStatuses"
                                        :sort="false"
                                        @update:model-value="(v) => changeStatus(a.id, String(v))"
                                    />
                                </div>
                                <Button v-if="canDelete(a.status)" variant="ghost" size="icon-sm" title="Delete booking" @click="openDelete(a)">
                                    <Trash2 class="size-4 text-rose-600" />
                                </Button>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- Confirm arrival -->
        <Dialog :open="confirming !== null" @update:open="(v) => { if (!v) confirming = null; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirm this appointment?</DialogTitle>
                    <DialogDescription>
                        This marks {{ confirming?.name }}'s {{ confirming?.time }} booking as <strong>confirmed</strong> — use it when the patient has arrived at the clinic.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="ghost" @click="confirming = null">Cancel</Button>
                    <Button @click="confirmArrival">Confirm arrival</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete booking (with optional patient cleanup) -->
        <Dialog :open="deleting !== null" @update:open="(v) => { if (!v) deleting = null; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete appointment?</DialogTitle>
                    <DialogDescription>
                        This permanently removes the {{ deleting?.status.replace('_', ' ') }} booking for {{ deleting?.name }}.
                    </DialogDescription>
                </DialogHeader>
                <label v-if="deleting?.patient_id" class="flex items-start gap-2.5 rounded-lg border p-3 text-sm">
                    <Checkbox v-model="alsoDeletePatient" class="mt-0.5" />
                    <span>
                        Also delete the patient record for <strong>{{ deleting?.name }}</strong>.
                        <span class="mt-0.5 block text-xs text-muted-foreground">Kept automatically if they have any invoices, courses, sessions, or other appointments.</span>
                    </span>
                </label>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="ghost" @click="deleting = null">Cancel</Button>
                    <Button variant="destructive" @click="confirmDelete">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
