<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PackageCheck } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Purchasing', href: '/purchases' }, { title: 'Detail', href: '#' }] } });

const props = defineProps<{
    purchase: { id: string; reference_no: string | null; supplier: string | null; status: string; total_cost: number; notes: string | null; received_at: string | null; can_receive: boolean };
    lines: Array<{ item: string | null; quantity: number; unit: string | null; unit_cost: number; batch_number: string | null; expiry_date: string | null }>;
}>();

const receive = () => router.post(`/purchases/${props.purchase.id}/receive`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="purchase.reference_no ?? 'Purchase'" />
    <div class=" w-full p-4 md:p-6">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>{{ purchase.reference_no }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ purchase.supplier ?? 'No supplier' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge>{{ purchase.status }}</Badge>
                    <Button v-if="purchase.can_receive" @click="receive"><PackageCheck class="size-4" /> Receive stock</Button>
                </div>
            </CardHeader>
            <CardContent>
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-3 py-2 font-medium">Item</th><th class="px-3 py-2 text-right font-medium">Qty</th><th class="px-3 py-2 font-medium">Unit</th><th class="px-3 py-2 text-right font-medium">Cost</th><th class="px-3 py-2 font-medium">Batch</th><th class="px-3 py-2 font-medium">Expiry</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(l, i) in lines" :key="i" class="border-b last:border-0">
                            <td class="px-3 py-2">{{ l.item }}</td>
                            <td class="px-3 py-2 text-right">{{ l.quantity }}</td>
                            <td class="px-3 py-2">{{ l.unit }}</td>
                            <td class="px-3 py-2 text-right">{{ l.unit_cost.toLocaleString() }}</td>
                            <td class="px-3 py-2">{{ l.batch_number ?? '—' }}</td>
                            <td class="px-3 py-2">{{ l.expiry_date ? fmt(l.expiry_date) : '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-medium"><td colspan="3" class="px-3 py-2">Total</td><td class="px-3 py-2 text-right" colspan="3">{{ purchase.total_cost.toLocaleString() }}</td></tr>
                    </tfoot>
                </table>
                <p v-if="purchase.received_at" class="mt-3 text-sm text-emerald-600">Received on {{ fmt(purchase.received_at) }} — stock added to inventory.</p>
                <Link href="/purchases" class="mt-3 inline-block text-sm text-primary hover:underline">← Back to purchases</Link>
            </CardContent>
        </Card>
    </div>
</template>
