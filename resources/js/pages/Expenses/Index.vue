<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { fmt } from '@/lib/datetime';
import { Trash2, Wallet } from '@lucide/vue';

interface ExpenseRow { id: string; description: string; category: string | null; amount: number; spent_at: string | null; by: string | null }

const props = defineProps<{
    expenses: ExpenseRow[];
    monthTotal: number;
    monthLabel: string;
    currency: string;
    categories: string[];
    canManage: boolean;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Expenses', href: '/expenses' }] } });

const money = (n: number) => `${props.currency}${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const today = new Date().toISOString().slice(0, 10);

const form = useForm({ description: '', category: '', amount: null as number | null, spent_at: today, notes: '' });

const OTHER = '__other__';
const catChoice = ref('');
watch(catChoice, (v) => { form.category = v === OTHER ? '' : v; });

const selectClass = 'h-9 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25';

function save() {
    form.post('/expenses', {
        preserveScroll: true,
        onSuccess: () => { form.reset('description', 'category', 'amount', 'notes'); catChoice.value = ''; },
    });
}
const remove = (id: string) => router.delete(`/expenses/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Expenses" />

    <div class="flex w-full flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Expenses</h1>
                <p class="text-sm text-muted-foreground">Track cash taken from the register to spend outside.</p>
            </div>
            <Card class="border-none">
                <CardContent class="flex items-center gap-3 p-4">
                    <Wallet class="size-5 text-muted-foreground" />
                    <div>
                        <p class="text-xs text-muted-foreground">{{ monthLabel }} total</p>
                        <p class="text-xl font-semibold">{{ money(monthTotal) }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <!-- Add expense -->
            <Card v-if="canManage" class="h-fit">
                <CardHeader><CardTitle class="text-base">Record an expense</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-3" @submit.prevent="save">
                        <div class="grid gap-1.5">
                            <Label for="amount">Amount *</Label>
                            <Input id="amount" type="number" step="0.01" min="0.01" v-model="form.amount" />
                            <p v-if="form.errors.amount" class="text-xs text-rose-600">{{ form.errors.amount }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="description">What for? *</Label>
                            <Input id="description" v-model="form.description" placeholder="e.g. Bought alcohol & gauze" />
                            <p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="category">Category</Label>
                            <select id="category" v-model="catChoice" :class="selectClass">
                                <option value="">—</option>
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                <option :value="OTHER">Other…</option>
                            </select>
                            <Input v-if="catChoice === OTHER" v-model="form.category" placeholder="Custom category" class="mt-2" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="spent_at">Date *</Label>
                            <Input id="spent_at" type="date" v-model="form.spent_at" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="notes">Notes</Label>
                            <Input id="notes" v-model="form.notes" />
                        </div>
                        <Button type="submit" :disabled="form.processing || !form.description || !form.amount">Record expense</Button>
                    </form>
                </CardContent>
            </Card>

            <!-- List -->
            <Card class="border-none">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Date</th>
                                    <th class="px-4 py-2 font-medium">Description</th>
                                    <th class="px-4 py-2 font-medium">Category</th>
                                    <th class="px-4 py-2 font-medium">Recorded by</th>
                                    <th class="px-4 py-2 text-right font-medium">Amount</th>
                                    <th v-if="canManage"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in expenses" :key="e.id" class="border-b last:border-0">
                                    <td class="px-4 py-2 whitespace-nowrap">{{ fmt(e.spent_at) }}</td>
                                    <td class="px-4 py-2">{{ e.description }}</td>
                                    <td class="px-4 py-2">{{ e.category ?? '—' }}</td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ e.by ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ money(e.amount) }}</td>
                                    <td v-if="canManage" class="px-4 py-2 text-right">
                                        <ConfirmDialog title="Remove expense?" description="This expense record will be deleted." confirm-text="Remove" @confirm="remove(e.id)">
                                            <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-rose-600" /></Button>
                                        </ConfirmDialog>
                                    </td>
                                </tr>
                                <tr v-if="expenses.length === 0">
                                    <td :colspan="canManage ? 6 : 5" class="px-4 py-10 text-center text-muted-foreground">No expenses recorded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
