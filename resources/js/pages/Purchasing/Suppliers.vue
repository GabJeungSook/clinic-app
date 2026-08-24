<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Trash2, Search } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Suppliers', href: '/suppliers' }] } });

const props = defineProps<{
    suppliers: {
        data: Array<{ id: string; name: string; contact_name: string | null; phone: string | null; email: string | null; is_active: boolean }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/suppliers', { search: search.value }, { preserveState: true, replace: true });

const form = useForm({ name: '', contact_name: '', phone: '', email: '', is_active: true });
const add = () => form.post('/suppliers', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (id: string) => router.delete(`/suppliers/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Suppliers" />
    <div class="grid w-full gap-6 p-4 md:grid-cols-3 md:p-6">
        <Card class="md:col-span-2">
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Suppliers</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ suppliers.total }} total</p>
                </div>
                <div class="relative w-48">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search…" class="h-9 pl-8" @keyup.enter="submitSearch" />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-4 py-2 font-medium">Name</th><th class="px-4 py-2 font-medium">Contact</th><th class="px-4 py-2 font-medium">Phone</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in suppliers.data" :key="s.id" class="border-b last:border-0">
                            <td class="px-4 py-2 font-medium">{{ s.name }} <Badge v-if="!s.is_active" variant="outline">inactive</Badge></td>
                            <td class="px-4 py-2">{{ s.contact_name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ s.phone ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <ConfirmDialog title="Delete supplier?" description="This removes the supplier. Past purchases are kept." @confirm="remove(s.id)">
                                    <Button variant="ghost" size="sm"><Trash2 class="size-4 text-rose-600" /></Button>
                                </ConfirmDialog>
                            </td>
                        </tr>
                        <tr v-if="suppliers.data.length === 0"><td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No suppliers found.</td></tr>
                    </tbody>
                </table>
                <div v-if="suppliers.links.length > 3" class="p-3"><Pagination :links="suppliers.links" /></div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Add supplier</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="add">
                    <div class="grid gap-1.5"><Label>Name *</Label><Input v-model="form.name" /></div>
                    <div class="grid gap-1.5"><Label>Contact</Label><Input v-model="form.contact_name" /></div>
                    <div class="grid gap-1.5"><Label>Phone</Label><Input v-model="form.phone" /></div>
                    <div class="grid gap-1.5"><Label>Email</Label><Input type="email" v-model="form.email" /></div>
                    <Button type="submit" :disabled="form.processing || !form.name">Add</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
