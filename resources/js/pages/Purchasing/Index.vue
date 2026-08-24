<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Pagination.vue';
import { Plus, Users, Search } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Purchasing', href: '/purchases' }] } });

const props = defineProps<{
    purchases: {
        data: Array<{ id: string; reference_no: string | null; supplier: string | null; status: string; items: number; total_cost: number; received_at: string | null; created_at: string | null }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/purchases', { search: search.value }, { preserveState: true, replace: true });

const tone: Record<string, string> = {
    draft: 'bg-muted text-foreground',
    ordered: 'bg-sky-100 text-sky-700',
    received: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};
</script>

<template>
    <Head title="Purchasing" />
    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Purchases</h1>
                <p class="text-sm text-muted-foreground">{{ purchases.total }} total</p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="secondary"><Link href="/suppliers"><Users class="size-4" /> Suppliers</Link></Button>
                <Button as-child><Link href="/purchases/create"><Plus class="size-4" /> New purchase</Link></Button>
            </div>
        </div>

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search reference or supplier…" class="pl-8" />
            </div>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-4 py-2 font-medium">Ref</th><th class="px-4 py-2 font-medium">Supplier</th><th class="px-4 py-2 text-center font-medium">Lines</th><th class="px-4 py-2 text-right font-medium">Total</th><th class="px-4 py-2 font-medium">Status</th><th class="px-4 py-2 font-medium">Date</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in purchases.data" :key="p.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/40" @click="router.visit(`/purchases/${p.id}`)">
                                <td class="px-4 py-2 font-mono text-xs">{{ p.reference_no }}</td>
                                <td class="px-4 py-2">{{ p.supplier ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">{{ p.items }}</td>
                                <td class="px-4 py-2 text-right">{{ p.total_cost.toLocaleString() }}</td>
                                <td class="px-4 py-2"><Badge :class="tone[p.status]">{{ p.status }}</Badge></td>
                                <td class="px-4 py-2 text-muted-foreground">{{ fmt(p.received_at ?? p.created_at) }}</td>
                            </tr>
                            <tr v-if="purchases.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No purchases found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Pagination :links="purchases.links" />
    </div>
</template>
