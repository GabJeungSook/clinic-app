<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import SearchSelect from '@/components/SearchSelect.vue';
import { Search, Plus, Tags, Ruler, ShoppingCart, ClipboardCheck } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }] },
});

interface ItemRow {
    id: string;
    name: string;
    sku: string | null;
    type_label: string;
    unit: string | null;
    category: string | null;
    on_hand: number;
    reorder_level: number;
    is_low: boolean;
    is_negative: boolean;
    is_active: boolean;
}

const props = defineProps<{
    items: {
        data: ItemRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string; sort: string };
    expiryThresholdDays: number;
}>();

const search = ref(props.filters.search ?? '');
const sort = ref(props.filters.sort ?? 'low');

// Only offer the reorder shortcut to users who can create purchases.
const canPurchase = computed(() => {
    const perms = (usePage().props.auth as { permissions?: string[] })?.permissions ?? [];
    return perms.includes('purchasing.view');
});

const reordering = ref(false);
function createReorder() {
    reordering.value = true;
    router.post('/purchases/reorder', {}, { onFinish: () => (reordering.value = false) });
}

const sortOptions = [
    { value: 'low', label: 'Low stock first' },
    { value: 'name', label: 'Name (A–Z)' },
    { value: 'stock_asc', label: 'Stock: low to high' },
    { value: 'stock_desc', label: 'Stock: high to low' },
];

function applyFilters() {
    router.get('/inventory', { search: search.value, sort: sort.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Inventory" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Inventory</h1>
                <p class="text-sm text-muted-foreground">
                    {{ items.total }} items · expiry alerts within {{ expiryThresholdDays }} days
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline"><Link href="/inventory/categories"><Tags class="size-4" /> Categories</Link></Button>
                <Button as-child variant="outline"><Link href="/inventory/units"><Ruler class="size-4" /> Units</Link></Button>
                <Button as-child variant="outline"><Link href="/inventory/stocktake"><ClipboardCheck class="size-4" /> Stock count</Link></Button>
                <Button v-if="canPurchase" variant="outline" :disabled="reordering" @click="createReorder">
                    <ShoppingCart class="size-4" /> Reorder low stock
                </Button>
                <Button as-child><Link href="/inventory/create"><Plus class="size-4" /> New item</Link></Button>
            </div>
        </div>

        <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search name or SKU…" class="pl-8" />
            </div>
            <div class="w-48">
                <SearchSelect v-model="sort" :options="sortOptions" :sort="false" @update:model-value="applyFilters" />
            </div>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card class="border-none">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Item</th>
                                <th class="px-4 py-2 font-medium">Type</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-right font-medium">On hand</th>
                                <th class="px-4 py-2 text-right font-medium">Reorder</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="i in items.data"
                                :key="i.id"
                                class="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                                @click="router.visit(`/inventory/${i.id}`)"
                            >
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ i.name }}</div>
                                    <div v-if="i.sku" class="font-mono text-xs text-muted-foreground">{{ i.sku }}</div>
                                </td>
                                <td class="px-4 py-2">{{ i.type_label }}</td>
                                <td class="px-4 py-2">{{ i.category ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-medium">
                                    {{ Number(i.on_hand) }} <span class="text-xs text-muted-foreground">{{ i.unit }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-muted-foreground">{{ Number(i.reorder_level) }} <span class="text-xs">{{ i.unit }}</span></td>
                                <td class="px-4 py-2">
                                    <Badge v-if="!i.is_active" class="bg-muted text-muted-foreground">Inactive</Badge>
                                    <Badge v-else-if="i.is_negative" class="bg-rose-600 text-white">Oversold</Badge>
                                    <Badge v-else-if="i.is_low" variant="destructive">Reorder</Badge>
                                    <Badge v-else class="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">In stock</Badge>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="items.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, i) in items.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    v-html="link.label"
                />
                <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
