<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }, { title: 'Edit item', href: '#' }] } });

const props = defineProps<{
    item: Record<string, string | number | boolean | null>;
    categories: Array<{ value: string; label: string }>;
    units: Array<{ value: string; label: string }>;
    types: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    name: props.item.name as string,
    sku: (props.item.sku as string) ?? '',
    barcode: (props.item.barcode as string) ?? '',
    type: props.item.type as string,
    inventory_category_id: (props.item.inventory_category_id as string) ?? '',
    new_category: '',
    base_unit_id: props.item.base_unit_id as string,
    is_batch_tracked: !!props.item.is_batch_tracked,
    track_expiry: !!props.item.track_expiry,
    reorder_level: props.item.reorder_level as number,
    reorder_qty: props.item.reorder_qty as number,
    default_sell_price: props.item.default_sell_price as number,
    is_active: !!props.item.is_active,
});

const NEW_CATEGORY = '__new__';
const categoryOptions = computed(() => [...props.categories, { value: NEW_CATEGORY, label: '+ Add new category…' }]);
const categoryChoice = ref((props.item.inventory_category_id as string) ?? '');
watch(categoryChoice, (v) => {
    if (v === NEW_CATEGORY) form.inventory_category_id = '';
    else { form.inventory_category_id = v; form.new_category = ''; }
});

const submit = () => form.put(`/inventory/${props.item.id}`);
</script>

<template>
    <Head :title="`Edit ${item.name}`" />
    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader><CardTitle>Edit item</CardTitle></CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="grid gap-4 md:grid-cols-2">
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
                        <SearchSelect v-model="categoryChoice" :options="categoryOptions" placeholder="—" empty-label="—" />
                        <Input v-if="categoryChoice === NEW_CATEGORY" v-model="form.new_category" placeholder="New category name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Base unit *</Label>
                        <SearchSelect v-model="form.base_unit_id" :options="units" placeholder="Select unit…" />
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
                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button as-child variant="ghost"><Link :href="`/inventory/${item.id}`">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Save changes</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
