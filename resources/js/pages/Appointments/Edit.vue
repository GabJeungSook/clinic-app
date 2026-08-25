<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Plus, Trash2 } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Appointments', href: '/appointments' }, { title: 'Edit', href: '#' }] } });

interface ServiceOpt { value: string; label: string; duration: number | null; sessions: number; price: number }
interface CourseOpt { value: string; patient_id: string; service_id: string; label: string; remaining: number }
interface Row { service_id: string; course_id: string }

const props = defineProps<{
    patients: Array<{ value: string; label: string }>;
    services: ServiceOpt[];
    staff: Array<{ value: string; label: string }>;
    courses: CourseOpt[];
    currency: string;
    appointment: {
        id: string;
        name: string;
        patient_id: string | null;
        service_id: string | null;
        course_id: string | null;
        services: Array<{ service_id: string; course_id: string | null }>;
        staff_id: number | null;
        scheduled_at: string | null;
        duration_minutes: number | null;
        notes: string | null;
        status: string;
    };
}>();

const a = props.appointment;
const initialServices: Row[] = a.services.length
    ? a.services.map((s) => ({ service_id: s.service_id, course_id: s.course_id ?? '' }))
    : a.service_id
        ? [{ service_id: a.service_id, course_id: a.course_id ?? '' }]
        : [{ service_id: '', course_id: '' }];

const form = useForm({
    patient_id: a.patient_id ?? '',
    services: initialServices,
    staff_id: a.staff_id ?? ('' as number | ''),
    scheduled_at: a.scheduled_at ?? '',
    duration_minutes: a.duration_minutes,
    notes: a.notes ?? '',
});

const money = (n: number) => `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const servicesById = computed(() => Object.fromEntries(props.services.map((s) => [s.value, s])));
const svc = (row: Row): ServiceOpt | undefined => servicesById.value[row.service_id];
const courseFor = (row: Row): CourseOpt | undefined =>
    props.courses.find((c) => c.patient_id === form.patient_id && c.service_id === row.service_id);

const addRow = () => form.services.push({ service_id: '', course_id: '' });
const removeRow = (i: number) => form.services.splice(i, 1);

function onServiceChange(row: Row) {
    const c = courseFor(row);
    row.course_id = c ? c.value : '';
    recomputeDuration();
}
function recomputeDuration() {
    const total = form.services.reduce((sum, r) => sum + (svc(r)?.duration ?? 0), 0);
    if (total > 0) form.duration_minutes = total;
}
watch(() => form.patient_id, () => form.services.forEach((r) => { r.course_id = courseFor(r)?.value ?? ''; }));

const total = computed(() =>
    form.services.reduce((sum, r) => sum + (r.course_id ? 0 : svc(r)?.price ?? 0), 0));
const chosen = computed(() => form.services.filter((r) => r.service_id));

const submit = () =>
    form.transform((data) => ({
        ...data,
        services: data.services.filter((r) => r.service_id).map((r) => ({ service_id: r.service_id, course_id: r.course_id || null })),
    })).put(`/appointments/${a.id}`);
</script>

<template>
    <Head title="Edit appointment" />
    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader>
                <CardTitle>Edit appointment</CardTitle>
                <p class="text-sm text-muted-foreground">Reschedule or update {{ a.name }}'s booking.</p>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label>Patient</Label>
                        <SearchSelect v-model="form.patient_id" :options="patients" />
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <!-- Services -->
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>Services</Label>
                            <Button type="button" variant="secondary" size="sm" @click="addRow"><Plus class="size-4" /> Add service</Button>
                        </div>
                        <div v-for="(row, i) in form.services" :key="i" class="rounded-lg border p-3">
                            <div class="flex items-start gap-2">
                                <div class="grid flex-1 gap-1.5">
                                    <SearchSelect v-model="row.service_id" :options="services" placeholder="— select a service —" empty-label="— none —" @update:model-value="onServiceChange(row)" />
                                    <div v-if="svc(row)" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
                                        <span class="text-muted-foreground">{{ svc(row)!.sessions }} session{{ svc(row)!.sessions === 1 ? '' : 's' }}</span>
                                        <span v-if="row.course_id" class="font-medium text-primary">From package · {{ courseFor(row)?.remaining }} left ({{ money(0) }})</span>
                                        <span v-else class="font-medium">{{ money(svc(row)!.price) }}</span>
                                    </div>
                                </div>
                                <Button type="button" variant="ghost" size="icon-sm" @click="removeRow(i)"><Trash2 class="size-4 text-rose-600" /></Button>
                            </div>
                        </div>
                        <div v-if="chosen.length" class="flex justify-between rounded-lg bg-muted/40 px-3 py-2 text-sm">
                            <span class="text-muted-foreground">{{ chosen.length }} service{{ chosen.length === 1 ? '' : 's' }} · total</span>
                            <span class="font-semibold">{{ money(total) }}</span>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Date &amp; time *</Label>
                            <Input type="datetime-local" v-model="form.scheduled_at" />
                            <InputError :message="form.errors.scheduled_at" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Duration (min)</Label>
                            <Input type="number" min="5" step="5" v-model="form.duration_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Provider</Label>
                        <SearchSelect v-model="form.staff_id" :options="staff" placeholder="— unassigned —" empty-label="— unassigned —" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Notes</Label>
                        <textarea v-model="form.notes" rows="2" class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="ghost"><Link href="/appointments">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing || !form.scheduled_at">Save changes</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
