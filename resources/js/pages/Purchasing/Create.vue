<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Purchasing', href: '/purchases' }, { title: 'New', href: '#' }] } });

const props = defineProps<{
    suppliers: Array<{ value: string; label: string }>;
    items: Array<{ value: string; label: string }>;
    units: Array<{ value: string; label: string }>;
}>();

const blankLine = () => ({ inventory_item_id: '', quantity: 1, unit_id: '', unit_cost: 0, batch_number: '', expiry_date: '' });

const form = useForm({
    supplier_id: '',
    reference_no: '',
    notes: '',
    lines: [blankLine()],
});

const addLine = () => form.lines.push(blankLine());
const removeLine = (i: number) => form.lines.length > 1 && form.lines.splice(i, 1);
const total = computed(() => form.lines.reduce((s, l) => s + Number(l.quantity) * Number(l.unit_cost), 0));
const submit = () => form.post('/purchases');
</script>

<template>
    <Head title="New purchase" />
    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader><CardTitle>New purchase</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Supplier</Label>
                            <SearchSelect v-model="form.supplier_id" :options="suppliers" placeholder="— optional —" empty-label="— none —" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Reference no.</Label>
                            <Input v-model="form.reference_no" placeholder="Auto if blank" />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th class="px-2 py-2 font-medium">Item</th>
                                    <th class="px-2 py-2 font-medium">Qty</th>
                                    <th class="px-2 py-2 font-medium">Unit</th>
                                    <th class="px-2 py-2 font-medium">Cost/unit</th>
                                    <th class="px-2 py-2 font-medium">Batch</th>
                                    <th class="px-2 py-2 font-medium">Expiry</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, i) in form.lines" :key="i" class="border-t">
                                    <td class="px-2 py-1.5">
                                        <div class="w-44"><SearchSelect v-model="line.inventory_item_id" :options="items" placeholder="Select…" /></div>
                                    </td>
                                    <td class="px-2 py-1.5"><Input type="number" step="0.001" class="w-20" v-model="line.quantity" /></td>
                                    <td class="px-2 py-1.5">
                                        <div class="w-28"><SearchSelect v-model="line.unit_id" :options="units" placeholder="…" /></div>
                                    </td>
                                    <td class="px-2 py-1.5"><Input type="number" step="0.01" class="h-8 w-24" v-model="line.unit_cost" /></td>
                                    <td class="px-2 py-1.5"><Input class="h-8 w-24" v-model="line.batch_number" /></td>
                                    <td class="px-2 py-1.5"><Input type="date" class="h-8 w-36" v-model="line.expiry_date" /></td>
                                    <td class="px-2 py-1.5"><Button type="button" variant="ghost" size="sm" @click="removeLine(i)"><Trash2 class="size-4 text-rose-600" /></Button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <Button type="button" variant="secondary" size="sm" @click="addLine"><Plus class="size-4" /> Add line</Button>
                        <span class="text-sm text-muted-foreground">Total: <strong>{{ total.toLocaleString() }}</strong></span>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="ghost"><Link href="/purchases">Cancel</Link></Button>
                        <Button type="submit" :disabled="form.processing">Save purchase</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
