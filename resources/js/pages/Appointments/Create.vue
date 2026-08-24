<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Appointments', href: '/appointments' }, { title: 'Book', href: '#' }] } });

interface CourseOpt { value: string; patient_id: string; service_id: string; label: string; remaining: number }

const props = defineProps<{
    patients: Array<{ value: string; label: string }>;
    services: Array<{ value: string; label: string; duration: number | null }>;
    staff: Array<{ value: string; label: string }>;
    courses: CourseOpt[];
    preselectedPatient: string | null;
    preselectedDate: string | null;
}>();

const form = useForm({
    patient_id: props.preselectedPatient ?? '',
    guest_name: '',
    guest_phone: '',
    service_id: '',
    course_id: '',
    staff_id: '',
    scheduled_at: props.preselectedDate ? `${props.preselectedDate}T09:00` : '',
    duration_minutes: null as number | null,
    notes: '',
});

const isGuest = computed(() => !form.patient_id);
const patientCourses = computed(() => props.courses.filter((c) => c.patient_id === form.patient_id));

// Picking a package sets the service to that course's service.
function onCoursePick() {
    const c = props.courses.find((x) => x.value === form.course_id);
    if (c) form.service_id = c.service_id;
}
// Clearing the patient clears any package link.
watch(() => form.patient_id, () => { form.course_id = ''; });

watch(() => form.service_id, (id) => {
    const s = props.services.find((x) => x.value === id);
    if (s?.duration) form.duration_minutes = s.duration;
});

const submit = () => form.post('/appointments');
</script>

<template>
    <Head title="Book appointment" />
    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader><CardTitle>Book an appointment</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label>Patient</Label>
                        <SearchSelect v-model="form.patient_id" :options="patients" placeholder="— New patient (quick add) —" empty-label="— New patient (quick add) —" />
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <div v-if="isGuest" class="grid gap-4 rounded-lg border border-dashed p-3 sm:grid-cols-2">
                        <div class="grid gap-1.5 sm:col-span-2">
                            <p class="text-xs text-muted-foreground">A patient record will be created for this booking so you can chart and check them out later.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Full name *</Label>
                            <Input v-model="form.guest_name" placeholder="First Last" />
                            <InputError :message="form.errors.guest_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Contact number</Label>
                            <Input v-model="form.guest_phone" />
                        </div>
                    </div>

                    <div v-if="patientCourses.length" class="grid gap-1.5 rounded-lg border border-l-4 border-l-primary p-3">
                        <Label>Book from an ongoing package</Label>
                        <SearchSelect
                            v-model="form.course_id"
                            :options="patientCourses.map((c) => ({ value: c.value, label: `${c.label} — ${c.remaining} left` }))"
                            placeholder="— book a new service instead —"
                            empty-label="— book a new service instead —"
                            @update:model-value="onCoursePick"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Service</Label>
                        <SearchSelect v-model="form.service_id" :options="services" placeholder="— optional —" empty-label="— none —" :disabled="!!form.course_id" />
                        <p v-if="form.course_id" class="text-xs text-muted-foreground">Service is set by the selected package.</p>
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
                        <Button type="submit" :disabled="form.processing || !form.scheduled_at">Book appointment</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
