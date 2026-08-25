<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Printer, Ban, Undo2, TriangleAlert } from '@lucide/vue';
import SearchSelect from '@/components/SearchSelect.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Billing', href: '/invoices' }, { title: 'Invoice', href: '#' }] } });

const props = defineProps<{
    invoice: { id: string; invoice_no: string; patient: string | null; status: string; subtotal: number; discount_total: number; tax_total: number; grand_total: number; amount_paid: number; amount_due: number; tax_enabled: boolean; tax_rate: number; notes: string | null; issued_at: string | null; void_reason: string | null; voided_at: string | null; voided_by: string | null };
    items: Array<{ description: string; quantity: number; unit_price: number; discount: number; tax: number; line_total: number }>;
    payments: Array<{ method: string; amount: number; received_by: string | null; paid_at: string | null }>;
    receipts: Array<{ id: string; receipt_no: string }>;
    methods: Array<{ value: string; label: string }>;
    can: { manage: boolean };
    currency: string;
}>();

const money = (n: number) => `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const isTerminal = computed(() => ['void', 'refunded'].includes(props.invoice.status));
const canVoid = computed(() => props.can.manage && !isTerminal.value && props.invoice.amount_paid === 0);
const canRefund = computed(() => props.can.manage && !isTerminal.value && props.invoice.amount_paid > 0);

const pay = useForm({ amount: props.invoice.amount_due, method: 'cash', reference: '' });
const recordPayment = () => pay.post(`/invoices/${props.invoice.id}/payments`, { preserveScroll: true, onSuccess: () => pay.reset('reference') });
const makeReceipt = () => router.post(`/invoices/${props.invoice.id}/receipt`);

// Void
const voidOpen = ref(false);
const voidForm = useForm({ reason: '' });
const submitVoid = () => voidForm.post(`/invoices/${props.invoice.id}/void`, { preserveScroll: true, onSuccess: () => { voidOpen.value = false; voidForm.reset(); } });

// Refund
const refundOpen = ref(false);
const refundForm = useForm({ amount: props.invoice.amount_paid, method: 'cash', reason: '', restock: false });
const submitRefund = () => refundForm.post(`/invoices/${props.invoice.id}/refund`, { preserveScroll: true, onSuccess: () => { refundOpen.value = false; refundForm.reset(); } });

const tone: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    unpaid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    partially_paid: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    void: 'bg-muted text-muted-foreground line-through',
    refunded: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400',
};
</script>

<template>
    <Head :title="invoice.invoice_no" />
    <div class=" flex w-full flex-col gap-6 p-4 md:p-6">
        <!-- Voided banner -->
        <Card v-if="invoice.status === 'void'" class="border-muted-foreground/30 bg-muted/40">
            <CardContent class="flex items-start gap-3 p-4">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted-foreground/15 text-muted-foreground"><Ban class="size-5" /></div>
                <div class="text-sm">
                    <p class="font-medium">This invoice was voided.</p>
                    <p class="text-muted-foreground">{{ invoice.void_reason }}<template v-if="invoice.voided_by"> · by {{ invoice.voided_by }}</template><template v-if="invoice.voided_at"> · {{ fmt(invoice.voided_at) }}</template></p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>{{ invoice.invoice_no }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ invoice.patient ?? 'Walk-in' }} · {{ fmt(invoice.issued_at) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Manager actions -->
                    <Button v-if="canRefund" variant="outline" size="sm" @click="refundOpen = true"><Undo2 class="size-4" /> Refund</Button>
                    <Button v-if="canVoid" variant="outline" size="sm" class="text-destructive hover:text-destructive" @click="voidOpen = true"><Ban class="size-4" /> Void</Button>
                    <Badge :class="tone[invoice.status]">{{ invoice.status.replace('_', ' ') }}</Badge>
                </div>
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
                        <div class="flex justify-between text-emerald-600"><span>{{ invoice.amount_paid < 0 ? 'Refunded' : 'Paid' }}</span><span>{{ money(invoice.amount_paid) }}</span></div>
                        <div v-if="invoice.amount_due > 0" class="flex justify-between font-medium text-amber-600"><span>Due</span><span>{{ money(invoice.amount_due) }}</span></div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader><CardTitle class="text-base">Record payment</CardTitle></CardHeader>
                <CardContent>
                    <p v-if="isTerminal" class="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                        This invoice is {{ invoice.status.replace('_', ' ') }} — no further payments can be taken.
                    </p>
                    <form v-else class="grid gap-3" @submit.prevent="recordPayment">
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
                            <span :class="p.amount < 0 ? 'text-rose-600' : ''">{{ money(p.amount) }}</span>
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

        <!-- Void dialog -->
        <Dialog v-model:open="voidOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><TriangleAlert class="size-5 text-destructive" /> Void this invoice?</DialogTitle>
                    <DialogDescription>
                        Voiding cancels {{ invoice.invoice_no }} and returns any retail stock it sold. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label>Reason</Label>
                    <Input v-model="voidForm.reason" placeholder="e.g. duplicate invoice, wrong item" />
                    <p v-if="voidForm.errors.reason" class="text-xs text-destructive">{{ voidForm.errors.reason }}</p>
                    <p v-if="voidForm.errors.status" class="text-xs text-destructive">{{ voidForm.errors.status }}</p>
                </div>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="ghost" @click="voidOpen = false">Cancel</Button>
                    <Button variant="destructive" :disabled="voidForm.processing || !voidForm.reason" @click="submitVoid">Void invoice</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Refund dialog -->
        <Dialog v-model:open="refundOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><Undo2 class="size-5" /> Refund payment</DialogTitle>
                    <DialogDescription>
                        Return money received on {{ invoice.invoice_no }}. Up to {{ money(invoice.amount_paid) }} can be refunded.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5"><Label>Amount</Label><Input type="number" step="0.01" v-model="refundForm.amount" /></div>
                        <div class="grid gap-1.5"><Label>Method</Label><SearchSelect v-model="refundForm.method" :options="methods" :sort="false" /></div>
                    </div>
                    <div class="grid gap-1.5"><Label>Reason</Label><Input v-model="refundForm.reason" placeholder="Optional note" /></div>
                    <Label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="refundForm.restock" /> Return retail items to stock
                    </Label>
                    <p v-if="refundForm.errors.amount" class="text-xs text-destructive">{{ refundForm.errors.amount }}</p>
                </div>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button variant="ghost" @click="refundOpen = false">Cancel</Button>
                    <Button :disabled="refundForm.processing" @click="submitRefund">Record refund</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
