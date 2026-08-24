<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Trash2, Search } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import Pagination from '@/components/Pagination.vue';
import { fmt } from '@/lib/datetime';

defineOptions({ layout: { breadcrumbs: [{ title: 'Billing', href: '/invoices' }, { title: 'Promotions', href: '/promotions' }] } });

interface Promo {
    id: string;
    name: string;
    code: string | null;
    type: string;
    value: number;
    applies_to: string;
    min_spend: number | null;
    valid_from: string | null;
    valid_to: string | null;
    max_uses: number | null;
    used_count: number;
    is_active: boolean;
    status: string;
}

const props = defineProps<{
    promotions: {
        data: Promo[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    types: Array<{ value: string; label: string }>;
    scopes: Array<{ value: string; label: string }>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/promotions', { search: search.value }, { preserveState: true, replace: true });

const form = useForm({
    name: '',
    code: '',
    type: 'percent',
    value: 10,
    applies_to: 'invoice',
    valid_from: '',
    valid_to: '',
    min_spend: null as number | null,
    max_uses: null as number | null,
    is_active: true,
});

const add = () => form.post('/promotions', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (id: string) => router.delete(`/promotions/${id}`, { preserveScroll: true });

const statusTone: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    scheduled: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    expired: 'bg-muted text-muted-foreground',
    inactive: 'bg-muted text-muted-foreground',
    used_up: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};
const statusLabel: Record<string, string> = {
    active: 'Active now', scheduled: 'Scheduled', expired: 'Expired', inactive: 'Off', used_up: 'Used up',
};

const windowLabel = (p: Promo) => {
    if (!p.valid_from && !p.valid_to) return 'Always';
    return `${p.valid_from ? fmt(p.valid_from) : '…'} → ${p.valid_to ? fmt(p.valid_to) : '…'}`;
};
</script>

<template>
    <Head title="Promotions" />
    <div class=" grid w-full gap-6 p-4 lg:grid-cols-5 md:p-6">
        <Card class="border-none lg:col-span-3">
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Promotions</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ promotions.total }} total</p>
                </div>
                <div class="relative w-48">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search…" class="h-9 pl-8" @keyup.enter="submitSearch" />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Promotion</th>
                                <th class="px-4 py-2 font-medium">Discount</th>
                                <th class="px-4 py-2 font-medium">Window</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in promotions.data" :key="p.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ p.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        <span v-if="p.code" class="font-mono">{{ p.code }}</span>
                                        <span class="capitalize"> · {{ p.applies_to }}</span>
                                        <span v-if="p.min_spend"> · min {{ p.min_spend.toLocaleString() }}</span>
                                        <span v-if="p.max_uses"> · {{ p.used_count }}/{{ p.max_uses }} used</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 font-medium">{{ p.type === 'percent' ? p.value + '%' : p.value.toLocaleString() }}</td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">{{ windowLabel(p) }}</td>
                                <td class="px-4 py-2"><Badge :class="statusTone[p.status]">{{ statusLabel[p.status] }}</Badge></td>
                                <td class="px-4 py-2 text-right">
                                    <ConfirmDialog title="Delete promotion?" description="This promotion will be removed." @confirm="remove(p.id)">
                                        <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-rose-600" /></Button>
                                    </ConfirmDialog>
                                </td>
                            </tr>
                            <tr v-if="promotions.data.length === 0"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No promotions found.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="promotions.links.length > 3" class="p-3"><Pagination :links="promotions.links" /></div>
            </CardContent>
        </Card>

        <Card class="border-none lg:col-span-2">
            <CardHeader>
                <CardTitle>New promotion</CardTitle>
                <p class="text-xs text-muted-foreground">Set a date window for seasonal promos (e.g. Christmas, long weekend). Leave dates blank for an always-on promo.</p>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="add">
                    <div class="grid gap-1.5"><Label>Name</Label><Input v-model="form.name" placeholder="e.g. Christmas Promo" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5"><Label>Code</Label><Input v-model="form.code" placeholder="XMAS2026" /></div>
                        <div class="grid gap-1.5">
                            <Label>Type</Label>
                            <SearchSelect v-model="form.type" :options="types" :sort="false" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>{{ form.type === 'percent' ? 'Percent %' : 'Amount' }}</Label>
                            <Input type="number" step="0.01" min="0" v-model="form.value" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Applies to</Label>
                            <SearchSelect v-model="form.applies_to" :options="scopes" :sort="false" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5"><Label>Valid from</Label><Input type="date" v-model="form.valid_from" /></div>
                        <div class="grid gap-1.5"><Label>Valid to</Label><Input type="date" v-model="form.valid_to" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5"><Label>Min spend</Label><Input type="number" step="0.01" min="0" v-model="form.min_spend" placeholder="optional" /></div>
                        <div class="grid gap-1.5"><Label>Max uses</Label><Input type="number" min="1" v-model="form.max_uses" placeholder="unlimited" /></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" /> Active</label>
                    <Button type="submit" :disabled="form.processing || !form.name">Create promotion</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
