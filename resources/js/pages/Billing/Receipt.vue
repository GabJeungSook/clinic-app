<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Button } from '@/components/ui/button';
import { Printer } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Billing', href: '/invoices' }, { title: 'Receipt', href: '#' }] } });

interface Snapshot {
    clinic: { name: string; address: string; phone: string; footer: string };
    currency: string;
    invoice_no: string;
    issued_at: string | null;
    patient: string | null;
    items: Array<{ description: string; quantity: number; unit_price: number; discount: number; tax: number; line_total: number }>;
    totals: { subtotal: number; discount_total: number; tax_total: number; grand_total: number; amount_paid: number; tax_enabled: boolean; tax_rate: number };
    payments: Array<{ method: string; amount: number; paid_at: string | null }>;
}

const props = defineProps<{ receipt: { receipt_no: string; printed_at: string | null; snapshot: Snapshot } }>();
const s = props.receipt.snapshot;
const money = (n: number) => `${s.currency ?? ''}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
</script>

<template>
    <Head title="Receipt" />
    <div class="mx-auto w-full max-w-md p-4 md:p-6">
        <div class="mb-3 flex justify-end print:hidden">
            <Button size="sm" @click="() => window.print()"><Printer class="size-4" /> Print</Button>
        </div>

        <div class="rounded-lg border bg-white p-6 text-sm text-black shadow-sm print:border-0 print:shadow-none">
            <div class="text-center">
                <h2 class="text-lg font-bold">{{ s.clinic.name }}</h2>
                <p v-if="s.clinic.address" class="text-xs">{{ s.clinic.address }}</p>
                <p v-if="s.clinic.phone" class="text-xs">{{ s.clinic.phone }}</p>
            </div>
            <hr class="my-3 border-dashed" />
            <div class="flex justify-between text-xs">
                <span>Receipt: {{ receipt.receipt_no }}</span>
                <span>{{ fmt(s.issued_at) }}</span>
            </div>
            <div class="text-xs">Invoice: {{ s.invoice_no }}</div>
            <div v-if="s.patient" class="text-xs">Patient: {{ s.patient }}</div>
            <hr class="my-3 border-dashed" />
            <table class="w-full text-xs">
                <tbody>
                    <tr v-for="(it, i) in s.items" :key="i">
                        <td class="py-0.5">{{ it.description }} <span class="text-[10px]">×{{ it.quantity }}</span></td>
                        <td class="py-0.5 text-right">{{ money(it.line_total) }}</td>
                    </tr>
                </tbody>
            </table>
            <hr class="my-3 border-dashed" />
            <div class="space-y-0.5 text-xs">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ money(s.totals.subtotal) }}</span></div>
                <div v-if="s.totals.discount_total > 0" class="flex justify-between"><span>Discount</span><span>-{{ money(s.totals.discount_total) }}</span></div>
                <div v-if="s.totals.tax_enabled" class="flex justify-between"><span>Tax ({{ s.totals.tax_rate }}%)</span><span>{{ money(s.totals.tax_total) }}</span></div>
                <div class="flex justify-between text-sm font-bold"><span>TOTAL</span><span>{{ money(s.totals.grand_total) }}</span></div>
                <div class="flex justify-between"><span>Paid</span><span>{{ money(s.totals.amount_paid) }}</span></div>
            </div>
            <hr class="my-3 border-dashed" />
            <p class="text-center text-xs">{{ s.clinic.footer }}</p>
        </div>
    </div>
</template>

<style>
@media print {
    body { background: white; }
    aside, header, nav { display: none !important; }
}
</style>
