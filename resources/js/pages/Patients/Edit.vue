<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';

const props = defineProps<{
    patient: Record<string, string | null>;
    sexes: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Patients', href: '/patients' }, { title: 'Edit', href: '#' }],
    },
});

const form = useForm({
    code: props.patient.code ?? '',
    first_name: props.patient.first_name ?? '',
    last_name: props.patient.last_name ?? '',
    date_of_birth: props.patient.date_of_birth ?? '',
    sex: props.patient.sex ?? '',
    phone: props.patient.phone ?? '',
    email: props.patient.email ?? '',
    address: props.patient.address ?? '',
    emergency_contact_name: props.patient.emergency_contact_name ?? '',
    emergency_contact_phone: props.patient.emergency_contact_phone ?? '',
    notes: props.patient.notes ?? '',
});

function submit() {
    form.put(`/patients/${props.patient.id}`);
}
</script>

<template>
    <Head title="Edit patient" />

    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader>
                <CardTitle>Edit patient</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="first_name">First name *</Label>
                        <Input id="first_name" v-model="form.first_name" />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="last_name">Last name *</Label>
                        <Input id="last_name" v-model="form.last_name" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="code">Patient code</Label>
                        <Input id="code" v-model="form.code" />
                        <InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="dob">Date of birth</Label>
                        <Input id="dob" type="date" v-model="form.date_of_birth" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="sex">Sex</Label>
                        <SearchSelect id="sex" v-model="form.sex" :options="sexes" placeholder="—" empty-label="—" :sort="false" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="email">Email</Label>
                        <Input id="email" type="email" v-model="form.email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="address">Address</Label>
                        <Input id="address" v-model="form.address" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25"
                        />
                    </div>

                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button as-child variant="ghost"><Link :href="`/patients/${patient.id}`">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Update</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
