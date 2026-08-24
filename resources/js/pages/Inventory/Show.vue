<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Pencil, Trash2, PackagePlus, SlidersHorizontal } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }, { title: 'Item', href: '#' }] } });

const props = defineProps<{
    item: {
        id: string; name: string; sku: string | null; type_label: string; category: string | null;
        unit: string | null; on_hand: number; reorder_level: number; is_low: boolean;
        is_batch_tracked: boolean; track_expiry: boolean; is_active: boolean;
    };
    batches: Array<{ id: string; batch_number: string | null; expiry_date: string | null; remaining: number; is_expired: boolean }>;
    movements: Array<{ id: string; type: string; type_label: string; quantity: number; reason: string | null; occurred_at: string | null; by: string | null }>;
}>();

const receive = useForm({ quantity: null as number | null, unit_cost: null as number | null, batch_number: '', expiry_date: '' });
const adjust = useForm({ quantity: null as number | null, reason: '' });

const doReceive = () => receive.post(`/inventory/${props.item.id}/receive`, { preserveScroll: true, onSuccess: () => receive.reset() });
const doAdjust = () => adjust.post(`/inventory/${props.item.id}/adjust`, { preserveScroll: true, onSuccess: () => adjust.reset() });
const writeOff = (batchId: string) => router.post(`/inventory/${props.item.id}/batches/${batchId}/write-off`, {}, { preserveScroll: true });
const removeItem = () => router.delete(`/inventory/${props.item.id}`);
</script>

<template>
    <Head :title="item.name" />
    <div class=" flex w-full flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ item.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ item.type_label }}
                    <span v-if="item.category"> · {{ item.category }}</span>
                    <span v-if="item.sku"> · <span class="font-mono">{{ item.sku }}</span></span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="secondary"><Link :href="`/inventory/${item.id}/edit`"><Pencil class="size-4" /> Edit</Link></Button>
                <ConfirmDialog title="Delete item?" description="This removes the item from the catalogue. Its stock history is kept." @confirm="removeItem">
                    <Button variant="ghost" size="icon"><Trash2 class="size-4 text-rose-600" /></Button>
                </ConfirmDialog>
            </div>
        </div>

        <!-- Stock level -->
        <Card class="border-none">
            <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <p class="text-xs text-muted-foreground">On hand</p>
                    <p class="text-3xl font-bold tracking-tight">{{ Number(item.on_hand) }} <span class="text-lg font-normal text-muted-foreground">{{ item.unit }}</span></p>
                </div>
                <div class="text-right text-sm text-muted-foreground">
                    <p>Reorder level: {{ Number(item.reorder_level) }}</p>
                    <Badge v-if="item.is_low" variant="destructive" class="mt-1">Low stock</Badge>
                    <Badge v-else variant="secondary" class="mt-1">OK</Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Stock actions -->
        <div class="grid gap-6 md:grid-cols-2">
            <Card class="border-none">
                <CardHeader><CardTitle class="flex items-center gap-2 text-base"><PackagePlus class="size-4" /> Add stock</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="doReceive">
                        <div class="grid gap-1.5"><Label>Quantity ({{ item.unit }})</Label><Input type="number" step="0.001" min="0.001" v-model="receive.quantity" /></div>
                        <div class="grid gap-1.5"><Label>Unit cost</Label><Input type="number" step="0.01" min="0" v-model="receive.unit_cost" /></div>
                        <div class="grid gap-1.5"><Label>Batch no.</Label><Input v-model="receive.batch_number" /></div>
                        <div class="grid gap-1.5"><Label>Expiry</Label><Input type="date" v-model="receive.expiry_date" /></div>
                        <Button type="submit" class="sm:col-span-2" :disabled="receive.processing || !receive.quantity">Add to stock</Button>
                    </form>
                </CardContent>
            </Card>

            <Card class="border-none">
                <CardHeader><CardTitle class="flex items-center gap-2 text-base"><SlidersHorizontal class="size-4" /> Adjust stock</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-3" @submit.prevent="doAdjust">
                        <div class="grid gap-1.5">
                            <Label>Quantity (+ to add, − to remove)</Label>
                            <Input type="number" step="0.001" v-model="adjust.quantity" placeholder="e.g. -5" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Reason</Label>
                            <Input v-model="adjust.reason" placeholder="Stock take, breakage…" />
                        </div>
                        <Button type="submit" variant="secondary" :disabled="adjust.processing || !adjust.quantity || !adjust.reason">Apply adjustment</Button>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Batches -->
        <Card v-if="item.is_batch_tracked" class="border-none">
            <CardHeader><CardTitle class="text-base">Batches</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-4 py-2 font-medium">Batch</th><th class="px-4 py-2 font-medium">Expiry</th><th class="px-4 py-2 text-right font-medium">Remaining</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in batches" :key="b.id" class="border-b last:border-0">
                            <td class="px-4 py-2 font-mono text-xs">{{ b.batch_number ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <Badge v-if="b.expiry_date" :variant="b.is_expired ? 'destructive' : 'secondary'">{{ fmt(b.expiry_date) }}</Badge>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2 text-right font-medium">{{ Number(b.remaining) }} {{ item.unit }}</td>
                            <td class="px-4 py-2 text-right">
                                <ConfirmDialog v-if="b.remaining > 0" title="Write off batch?" :description="`This removes the remaining ${b.remaining} ${item.unit} from stock as a write-off.`" confirm-text="Write off" @confirm="writeOff(b.id)">
                                    <Button variant="ghost" size="sm">Write off</Button>
                                </ConfirmDialog>
                            </td>
                        </tr>
                        <tr v-if="batches.length === 0"><td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No active batches.</td></tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <!-- Movement ledger -->
        <Card class="border-none">
            <CardHeader><CardTitle class="text-base">Stock movements</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-4 py-2 font-medium">When</th><th class="px-4 py-2 font-medium">Type</th><th class="px-4 py-2 text-right font-medium">Qty</th><th class="px-4 py-2 font-medium">Note</th><th class="px-4 py-2 font-medium">By</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in movements" :key="m.id" class="border-b last:border-0">
                            <td class="px-4 py-2 whitespace-nowrap text-muted-foreground">{{ fmt(m.occurred_at) }}</td>
                            <td class="px-4 py-2">{{ m.type_label }}</td>
                            <td class="px-4 py-2 text-right font-medium" :class="m.quantity >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ m.quantity >= 0 ? '+' : '' }}{{ Number(m.quantity) }}
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">{{ m.reason ?? '—' }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ m.by ?? '—' }}</td>
                        </tr>
                        <tr v-if="movements.length === 0"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No movements yet.</td></tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
