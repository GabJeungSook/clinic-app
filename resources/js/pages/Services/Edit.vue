<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';
import { Trash2 } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import AddUnitDialog from '@/components/AddUnitDialog.vue';

const props = defineProps<{
    service: Record<string, string | number | boolean | null>;
    categories: Array<{ value: string; label: string }>;
    consumables: Array<{ id: string; item: string | null; quantity: number; unit: string | null; is_optional: boolean }>;
    items: Array<{ value: string; label: string }>;
    units: Array<{ value: string; label: string }>;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Services', href: '/services' }, { title: 'Edit', href: '#' }] } });

const form = useForm({
    name: props.service.name as string,
    code: (props.service.code as string) ?? '',
    service_category_id: (props.service.service_category_id as string) ?? '',
    description: (props.service.description as string) ?? '',
    default_session_count: props.service.default_session_count as number,
    default_price: props.service.default_price as number,
    default_interval_days: props.service.default_interval_days as number | null,
    duration_minutes: props.service.duration_minutes as number | null,
    is_active: !!props.service.is_active,
});

// Price is entered per session; the stored default_price is the package total.
const sessions = computed(() => Math.max(1, Number(form.default_session_count) || 1));
const perSession = ref(Math.round(((props.service.default_price as number) / Math.max(1, (props.service.default_session_count as number) || 1)) * 100) / 100);
watch([perSession, sessions], () => {
    form.default_price = Math.round(Number(perSession.value) * sessions.value * 100) / 100;
});
const money = (n: number) => `₱${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const bom = useForm({ inventory_item_id: '', quantity: 1, unit_id: '', is_optional: false });

const save = () => form.put(`/services/${props.service.id}`);
const addConsumable = () =>
    bom.post(`/services/${props.service.id}/consumables`, {
        preserveScroll: true,
        onSuccess: () => bom.reset('inventory_item_id', 'quantity'),
    });
const removeConsumable = (id: string) =>
    router.delete(`/services/${props.service.id}/consumables/${id}`, { preserveScroll: true });
</script>

<template>
    <Head :title="`Edit ${service.name}`" />
    <div class=" grid w-full gap-6 p-4 md:grid-cols-2 md:p-6">
        <Card>
            <CardHeader><CardTitle>Service details</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="grid gap-1.5">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="sessions">Sessions</Label>
                            <Input id="sessions" type="number" min="1" v-model="form.default_session_count" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="price">Price per session</Label>
                            <Input id="price" type="number" step="0.01" min="0" v-model="perSession" />
                        </div>
                    </div>
                    <p v-if="sessions > 1" class="-mt-2 text-xs text-muted-foreground">Package total ({{ sessions }} sessions): <strong>{{ money(form.default_price) }}</strong></p>
                    <div class="grid gap-1.5">
                        <Label for="cat">Category</Label>
                        <SearchSelect id="cat" v-model="form.service_category_id" :options="categories" placeholder="—" empty-label="—" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" /> Active</label>
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Bill of materials</CardTitle>
                <AddUnitDialog />
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-sm text-muted-foreground">Items automatically consumed from stock each session.</p>
                <ul class="divide-y divide-border">
                    <li v-for="c in consumables" :key="c.id" class="flex items-center justify-between py-2 text-sm">
                        <span>{{ c.item }} — <strong>{{ c.quantity }}</strong> {{ c.unit }}</span>
                        <ConfirmDialog title="Remove item?" description="This item will no longer be consumed by this service." confirm-text="Remove" @confirm="removeConsumable(c.id)">
                            <Button variant="ghost" size="sm"><Trash2 class="size-4 text-rose-600" /></Button>
                        </ConfirmDialog>
                    </li>
                    <li v-if="consumables.length === 0" class="py-3 text-center text-muted-foreground">No consumables set.</li>
                </ul>

                <form class="space-y-3 border-t pt-4" @submit.prevent="addConsumable">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_5rem_minmax(0,8rem)] sm:items-end">
                        <div class="grid min-w-0 gap-1.5">
                            <Label>Item</Label>
                            <SearchSelect v-model="bom.inventory_item_id" :options="items" placeholder="Select…" />
                        </div>
                        <div class="grid min-w-0 gap-1.5">
                            <Label>Qty</Label>
                            <Input type="number" step="0.001" min="0.001" v-model="bom.quantity" />
                        </div>
                        <div class="grid min-w-0 gap-1.5">
                            <Label>Unit</Label>
                            <SearchSelect v-model="bom.unit_id" :options="units" placeholder="…" />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="bom.processing || !bom.inventory_item_id || !bom.unit_id"
                    >
                        Add item
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
