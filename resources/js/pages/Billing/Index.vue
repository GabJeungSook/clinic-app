<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Pagination.vue';
import { Plus, Tag, Search } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Billing', href: '/invoices' }] } });

const props = defineProps<{
    invoices: {
        data: Array<{ id: string; invoice_no: string; patient: string | null; status: string; grand_total: number; amount_paid: number; issued_at: string | null }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
    currency: string;
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/invoices', { search: search.value }, { preserveState: true, replace: true });

const tone: Record<string, string> = { paid: 'bg-emerald-100 text-emerald-700', unpaid: 'bg-amber-100 text-amber-700', partially_paid: 'bg-amber-100 text-amber-700', void: 'bg-muted', refunded: 'bg-rose-100 text-rose-700', draft: 'bg-muted' };
const money = (n: number) => `${props.currency}${n.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
</script>

<template>
    <Head title="Billing" />
    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Invoices</h1>
                <p class="text-sm text-muted-foreground">{{ invoices.total }} total</p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="secondary"><Link href="/promotions"><Tag class="size-4" /> Promotions</Link></Button>
                <Button as-child><Link href="/checkout"><Plus class="size-4" /> New sale</Link></Button>
            </div>
        </div>

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search invoice no. or patient…" class="pl-8" />
            </div>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-4 py-2 font-medium">Invoice</th><th class="px-4 py-2 font-medium">Patient</th><th class="px-4 py-2 text-right font-medium">Total</th><th class="px-4 py-2 text-right font-medium">Paid</th><th class="px-4 py-2 font-medium">Status</th><th class="px-4 py-2 font-medium">Date</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in invoices.data" :key="inv.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/40" @click="router.visit(`/invoices/${inv.id}`)">
                                <td class="px-4 py-2 font-mono text-xs">{{ inv.invoice_no }}</td>
                                <td class="px-4 py-2">{{ inv.patient ?? 'Walk-in' }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ money(inv.grand_total) }}</td>
                                <td class="px-4 py-2 text-right text-muted-foreground">{{ money(inv.amount_paid) }}</td>
                                <td class="px-4 py-2"><Badge :class="tone[inv.status]">{{ inv.status.replace('_', ' ') }}</Badge></td>
                                <td class="px-4 py-2 text-muted-foreground">{{ fmt(inv.issued_at) }}</td>
                            </tr>
                            <tr v-if="invoices.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No invoices found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Pagination :links="invoices.links" />
    </div>
</template>
