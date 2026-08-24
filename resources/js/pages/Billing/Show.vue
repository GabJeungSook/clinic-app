<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Printer } from '@lucide/vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Billing', href: '/invoices' }, { title: 'Invoice', href: '#' }] } });

const props = defineProps<{
    invoice: { id: string; invoice_no: string; patient: string | null; status: string; subtotal: number; discount_total: number; tax_total: number; grand_total: number; amount_paid: number; amount_due: number; tax_enabled: boolean; tax_rate: number; notes: string | null; issued_at: string | null };
    items: Array<{ description: string; quantity: number; unit_price: number; discount: number; tax: number; line_total: number }>;
    payments: Array<{ method: string; amount: number; received_by: string | null; paid_at: string | null }>;
    receipts: Array<{ id: string; receipt_no: string }>;
    methods: Array<{ value: string; label: string }>;
    currency: string;
}>();

const money = (n: number) => `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const pay = useForm({ amount: props.invoice.amount_due, method: 'cash', reference: '' });
const recordPayment = () => pay.post(`/invoices/${props.invoice.id}/payments`, { preserveScroll: true, onSuccess: () => pay.reset('reference') });
const makeReceipt = () => router.post(`/invoices/${props.invoice.id}/receipt`);

const tone: Record<string, string> = { paid: 'bg-emerald-100 text-emerald-700', unpaid: 'bg-amber-100 text-amber-700', partially_paid: 'bg-amber-100 text-amber-700' };
</script>

<template>
    <Head :title="invoice.invoice_no" />
    <div class=" flex w-full flex-col gap-6 p-4 md:p-6">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>{{ invoice.invoice_no }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ invoice.patient ?? 'Walk-in' }} · {{ fmt(invoice.issued_at) }}</p>
                </div>
                <Badge :class="tone[invoice.status]">{{ invoice.status.replace('_', ' ') }}</Badge>
            </CardHeader>
            <CardContent>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-muted-foreground">
                        <tr><th class="py-2 font-medium">Description</th><th class="py-2 text-right font-medium">Qty</th><th class="py-2 text-right font-medium">Price</th><th class="py-2 text-right font-medium">Disc</th><th class="py-2 text-right font-medium">Total</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(it, i) in items" :key="i" class="border-b last:border-0">
                            <td class="py-2">{{ it.description }}</td>
                            <td class="py-2 text-right">{{ it.quantity }}</td>
                            <td class="py-2 text-right">{{ money(it.unit_price) }}</td>
                            <td class="py-2 text-right">{{ money(it.discount) }}</td>
                            <td class="py-2 text-right">{{ money(it.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-4 flex justify-end">
                    <div class="w-64 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-muted-foreground">Subtotal</span><span>{{ money(invoice.subtotal) }}</span></div>
                        <div v-if="invoice.discount_total > 0" class="flex justify-between"><span class="text-muted-foreground">Discount</span><span>-{{ money(invoice.discount_total) }}</span></div>
                        <div v-if="invoice.tax_enabled" class="flex justify-between"><span class="text-muted-foreground">Tax ({{ invoice.tax_rate }}%)</span><span>{{ money(invoice.tax_total) }}</span></div>
                        <div class="flex justify-between border-t pt-1 font-semibold"><span>Total</span><span>{{ money(invoice.grand_total) }}</span></div>
                        <div class="flex justify-between text-emerald-600"><span>Paid</span><span>{{ money(invoice.amount_paid) }}</span></div>
                        <div v-if="invoice.amount_due > 0" class="flex justify-between font-medium text-amber-600"><span>Due</span><span>{{ money(invoice.amount_due) }}</span></div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader><CardTitle class="text-base">Record payment</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-3" @submit.prevent="recordPayment">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5"><Label>Amount</Label><Input type="number" step="0.01" v-model="pay.amount" /></div>
                            <div class="grid gap-1.5">
                                <Label>Method</Label>
                                <SearchSelect v-model="pay.method" :options="methods" :sort="false" />
                            </div>
                        </div>
                        <div class="grid gap-1.5"><Label>Reference</Label><Input v-model="pay.reference" /></div>
                        <Button type="submit" :disabled="pay.processing">Record payment</Button>
                    </form>
                    <ul class="mt-4 divide-y divide-border text-sm">
                        <li v-for="(p, i) in payments" :key="i" class="flex justify-between py-1.5">
                            <span class="capitalize">{{ p.method }} <span class="text-muted-foreground">· {{ fmt(p.paid_at) }}</span></span>
                            <span>{{ money(p.amount) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle class="text-base">Receipt</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <Button variant="secondary" @click="makeReceipt"><Printer class="size-4" /> Generate receipt</Button>
                    <ul class="divide-y divide-border text-sm">
                        <li v-for="r in receipts" :key="r.id" class="flex items-center justify-between py-1.5">
                            <span class="font-mono text-xs">{{ r.receipt_no }}</span>
                            <Link :href="`/receipts/${r.id}`" class="text-primary hover:underline">View / print →</Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
