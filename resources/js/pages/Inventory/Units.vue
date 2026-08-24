<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Trash2, ArrowLeft, Search } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import Pagination from '@/components/Pagination.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }, { title: 'Units', href: '/inventory/units' }] } });

const props = defineProps<{
    units: {
        data: Array<{ id: string; name: string; abbreviation: string; base: string | null; factor_to_base: number }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    baseOptions: Array<{ value: string; label: string }>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/inventory/units', { search: search.value }, { preserveState: true, replace: true });

const form = useForm({ name: '', abbreviation: '', base_unit_id: '', factor_to_base: 1 });
const isDerived = computed(() => !!form.base_unit_id);

const add = () => form.post('/inventory/units', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (id: string) => router.delete(`/inventory/units/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Units" />
    <div class=" grid w-full gap-6 p-4 md:grid-cols-3 md:p-6">
        <Card class="border-none md:col-span-2">
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Units of measure</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ units.total }} total</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative w-40">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input v-model="search" placeholder="Search…" class="h-9 pl-8" @keyup.enter="submitSearch" />
                    </div>
                    <Button as-child variant="ghost" size="sm"><Link href="/inventory"><ArrowLeft class="size-4" /> Back</Link></Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-4 py-2 font-medium">Unit</th><th class="px-4 py-2 font-medium">Conversion</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in units.data" :key="u.id" class="border-b last:border-0">
                            <td class="px-4 py-2"><span class="font-medium">{{ u.name }}</span> <span class="text-muted-foreground">({{ u.abbreviation }})</span></td>
                            <td class="px-4 py-2 text-muted-foreground">
                                <span v-if="u.base">1 {{ u.abbreviation }} = {{ Number(u.factor_to_base) }} {{ u.base }}</span>
                                <span v-else>Base unit</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <ConfirmDialog title="Delete unit?" description="This unit of measure will be removed." @confirm="remove(u.id)">
                                    <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-rose-600" /></Button>
                                </ConfirmDialog>
                            </td>
                        </tr>
                        <tr v-if="units.data.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">No units found.</td></tr>
                    </tbody>
                </table>
                <div v-if="units.links.length > 3" class="p-3"><Pagination :links="units.links" /></div>
            </CardContent>
        </Card>

        <Card class="border-none">
            <CardHeader><CardTitle>Add unit</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="add">
                    <div class="grid gap-1.5"><Label>Name</Label><Input v-model="form.name" placeholder="e.g. Box" /></div>
                    <div class="grid gap-1.5"><Label>Abbreviation</Label><Input v-model="form.abbreviation" placeholder="e.g. box" /></div>
                    <div class="grid gap-1.5">
                        <Label>Converts to base unit</Label>
                        <SearchSelect v-model="form.base_unit_id" :options="baseOptions" placeholder="— it IS a base unit —" empty-label="— it IS a base unit —" />
                    </div>
                    <div v-if="isDerived" class="grid gap-1.5">
                        <Label>How many base units per 1?</Label>
                        <Input type="number" step="0.000001" min="0.000001" v-model="form.factor_to_base" />
                        <p class="text-xs text-muted-foreground">e.g. 1 box = 100 pieces → enter 100.</p>
                    </div>
                    <Button type="submit" :disabled="form.processing || !form.name || !form.abbreviation">Add unit</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
