<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import AddUnitDialog from '@/components/AddUnitDialog.vue';
import { Plus, X } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Services', href: '/services' }, { title: 'New', href: '#' }] },
});

defineProps<{
    categories: Array<{ value: string; label: string }>;
    items: Array<{ value: string; label: string }>;
    units: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    name: '',
    code: '',
    service_category_id: '',
    description: '',
    default_session_count: 1,
    default_price: 0,
    cost: 0,
    default_interval_days: null as number | null,
    duration_minutes: null as number | null,
    is_active: true,
    consumables: [] as Array<{ inventory_item_id: string; quantity: number; unit_id: string }>,
});

// Price is entered per session; the stored default_price is the package total.
const perSession = ref(0);
const sessions = computed(() => Math.max(1, Number(form.default_session_count) || 1));
watch([perSession, sessions], () => {
    form.default_price = Math.round(Number(perSession.value) * sessions.value * 100) / 100;
});

const money = (n: number) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const addRow = () => form.consumables.push({ inventory_item_id: '', quantity: 1, unit_id: '' });
const removeRow = (i: number) => form.consumables.splice(i, 1);

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            consumables: data.consumables.filter((c) => c.inventory_item_id && c.unit_id && Number(c.quantity) > 0),
        }))
        .post('/services');
</script>

<template>
    <Head title="New service" />
    <div class="grid w-full gap-6 p-4 md:grid-cols-2 md:p-6">
        <Card>
            <CardHeader><CardTitle>New service</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="name">Name *</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="cat">Category</Label>
                            <SearchSelect id="cat" v-model="form.service_category_id" :options="categories" placeholder="—" empty-label="—" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="sessions">Sessions *</Label>
                            <Input id="sessions" type="number" min="1" v-model="form.default_session_count" />
                            <InputError :message="form.errors.default_session_count" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="price">Price per session *</Label>
                            <Input id="price" type="number" step="0.01" min="0" v-model="perSession" />
                            <p v-if="sessions > 1" class="text-xs text-muted-foreground">Package total ({{ sessions }} sessions): <strong>{{ money(form.default_price) }}</strong></p>
                            <InputError :message="form.errors.default_price" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="cost">Cost per session</Label>
                            <Input id="cost" type="number" step="0.01" min="0" v-model="form.cost" />
                            <p class="text-xs text-muted-foreground">What it costs you to deliver one session — used for gross vs net sales in reports.</p>
                            <InputError :message="form.errors.cost" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="interval">Interval (days)</Label>
                            <Input id="interval" type="number" min="0" v-model="form.default_interval_days" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="duration">Duration (min)</Label>
                            <Input id="duration" type="number" min="0" v-model="form.duration_minutes" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="desc">Description</Label>
                        <textarea id="desc" v-model="form.description" rows="2" class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" /> Active</label>
                    <div class="flex justify-end gap-2">
                        <Button as-child variant="ghost"><Link href="/services">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing || !form.name">Create service</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Bill of materials (optional at creation) -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Bill of materials</CardTitle>
                <AddUnitDialog />
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm text-muted-foreground">Items consumed from stock each session. Optional — you can also add these later.</p>
                <div v-for="(c, i) in form.consumables" :key="i" class="flex flex-wrap items-end gap-2">
                    <div class="grid min-w-0 flex-1 gap-1"><Label class="text-xs">Item</Label>
                        <SearchSelect v-model="c.inventory_item_id" :options="items" placeholder="Select item…" />
                    </div>
                    <div class="grid w-20 gap-1"><Label class="text-xs">Qty</Label><Input type="number" step="0.001" min="0.001" v-model="c.quantity" /></div>
                    <div class="grid w-32 gap-1"><Label class="text-xs">Unit</Label>
                        <SearchSelect v-model="c.unit_id" :options="units" placeholder="…" />
                    </div>
                    <Button type="button" variant="ghost" size="icon-sm" @click="removeRow(i)"><X class="size-4" /></Button>
                </div>
                <Button type="button" variant="secondary" size="sm" @click="addRow"><Plus class="size-4" /> Add item</Button>
            </CardContent>
        </Card>
    </div>
</template>
