<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Search, ClipboardCheck } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }, { title: 'Stock count', href: '#' }] },
});

interface Row {
    id: string;
    name: string;
    category: string | null;
    unit: string | null;
    on_hand: number;
}

const props = defineProps<{ items: Row[] }>();

// Counted values keyed by item id; blank/undefined means "not counted yet".
const counts = ref<Record<string, number | null>>({});
const search = ref('');
const saving = ref(false);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter(
        (i) => i.name.toLowerCase().includes(q) || (i.category ?? '').toLowerCase().includes(q),
    );
});

function diffFor(row: Row): number | null {
    const c = counts.value[row.id];
    if (c === null || c === undefined || (c as unknown as string) === '') return null;
    return Math.round((Number(c) - row.on_hand) * 1000) / 1000;
}

const changedCount = computed(
    () => props.items.filter((i) => { const d = diffFor(i); return d !== null && d !== 0; }).length,
);
const countedCount = computed(
    () => props.items.filter((i) => { const c = counts.value[i.id]; return c !== null && c !== undefined && (c as unknown as string) !== ''; }).length,
);

function save() {
    saving.value = true;
    const payload = props.items
        .filter((i) => { const c = counts.value[i.id]; return c !== null && c !== undefined && (c as unknown as string) !== ''; })
        .map((i) => ({ id: i.id, counted: Number(counts.value[i.id]) }));

    router.post('/inventory/stocktake', { counts: payload }, { onFinish: () => (saving.value = false) });
}
</script>

<template>
    <Head title="Stock count" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Stock count</h1>
                <p class="text-sm text-muted-foreground">
                    Count what is physically on the shelf and type it in. Leave items blank to skip them.
                </p>
            </div>
            <Button :disabled="saving || countedCount === 0" @click="save">
                <ClipboardCheck class="size-4" />
                Save count<span v-if="changedCount > 0"> · {{ changedCount }} to correct</span>
            </Button>
        </div>

        <div class="relative max-w-sm">
            <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
            <Input v-model="search" placeholder="Search item or category…" class="pl-8" />
        </div>

        <Card class="border-none">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Item</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-right font-medium">System</th>
                                <th class="px-4 py-2 text-right font-medium">Counted</th>
                                <th class="px-4 py-2 text-right font-medium">Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in filtered" :key="i.id" class="border-b last:border-0">
                                <td class="px-4 py-2 font-medium">{{ i.name }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ i.category ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-muted-foreground">
                                    {{ Number(i.on_hand) }} <span class="text-xs">{{ i.unit }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Input
                                        v-model="counts[i.id]"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        class="ml-auto h-8 w-28 text-right"
                                        placeholder="—"
                                    />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span v-if="diffFor(i) === null" class="text-muted-foreground">—</span>
                                    <Badge v-else-if="diffFor(i) === 0" variant="secondary">match</Badge>
                                    <Badge
                                        v-else
                                        :class="diffFor(i)! > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-600 text-white'"
                                    >
                                        {{ diffFor(i)! > 0 ? '+' : '' }}{{ diffFor(i) }} {{ i.unit }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
