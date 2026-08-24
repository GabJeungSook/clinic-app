<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }, { title: 'New item', href: '#' }] } });

defineProps<{
    categories: Array<{ value: string; label: string }>;
    units: Array<{ value: string; label: string }>;
    types: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    name: '',
    sku: '',
    barcode: '',
    type: 'consumable',
    inventory_category_id: '',
    base_unit_id: '',
    is_batch_tracked: true,
    track_expiry: true,
    reorder_level: 0,
    reorder_qty: 0,
    default_sell_price: 0,
    is_active: true,
    opening_qty: null as number | null,
    opening_unit_cost: null as number | null,
    opening_expiry: '',
});

const submit = () => form.post('/inventory');
</script>

<template>
    <Head title="New item" />
    <div class=" w-full p-4 md:p-6">
        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <Card>
                <CardHeader><CardTitle>New inventory item</CardTitle></CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Name *</Label>
                        <Input v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Type *</Label>
                        <SearchSelect v-model="form.type" :options="types" :sort="false" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category</Label>
                        <SearchSelect v-model="form.inventory_category_id" :options="categories" placeholder="—" empty-label="—" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Base unit *</Label>
                        <SearchSelect v-model="form.base_unit_id" :options="units" placeholder="Select unit…" />
                        <InputError :message="form.errors.base_unit_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>SKU</Label>
                        <Input v-model="form.sku" />
                        <InputError :message="form.errors.sku" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reorder level *</Label>
                        <Input type="number" step="0.001" min="0" v-model="form.reorder_level" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reorder qty</Label>
                        <Input type="number" step="0.001" min="0" v-model="form.reorder_qty" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Sell price (retail)</Label>
                        <Input type="number" step="0.01" min="0" v-model="form.default_sell_price" />
                    </div>
                    <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_batch_tracked" /> Batch tracked</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.track_expiry" /> Track expiry</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" /> Active</label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Opening stock <span class="text-sm font-normal text-muted-foreground">(optional)</span></CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label>Quantity on hand</Label>
                        <Input type="number" step="0.001" min="0" v-model="form.opening_qty" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Unit cost</Label>
                        <Input type="number" step="0.01" min="0" v-model="form.opening_unit_cost" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Expiry date</Label>
                        <Input type="date" v-model="form.opening_expiry" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button as-child variant="ghost"><Link href="/inventory">Cancel</Link></Button>
                <Button type="submit" :disabled="form.processing || !form.base_unit_id">Save item</Button>
            </div>
        </form>
    </div>
</template>
