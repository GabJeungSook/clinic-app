<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Trash2, Check, Pencil, ArrowLeft, Search } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Services', href: '/services' }, { title: 'Categories', href: '/services/categories' }] } });

const props = defineProps<{
    categories: {
        data: Array<{ id: string; name: string; sort_order: number; services: number }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/services/categories', { search: search.value }, { preserveState: true, replace: true });

const form = useForm({ name: '', sort_order: 0 });
const add = () => form.post('/services/categories', { preserveScroll: true, onSuccess: () => form.reset() });

const editingId = ref<string | null>(null);
const draft = ref('');
const startEdit = (c: { id: string; name: string }) => { editingId.value = c.id; draft.value = c.name; };
const saveEdit = (id: string) => router.put(`/services/categories/${id}`, { name: draft.value }, { preserveScroll: true, onSuccess: () => (editingId.value = null) });
const remove = (id: string) => router.delete(`/services/categories/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Service categories" />
    <div class=" grid w-full gap-6 p-4 md:grid-cols-3 md:p-6">
        <Card class="border-none md:col-span-2">
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Service categories</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ categories.total }} total</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative w-40">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input v-model="search" placeholder="Search…" class="h-9 pl-8" @keyup.enter="submitSearch" />
                    </div>
                    <Button as-child variant="ghost" size="sm"><Link href="/services"><ArrowLeft class="size-4" /> Back</Link></Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                        <tr><th class="px-4 py-2 font-medium">Name</th><th class="px-4 py-2 text-center font-medium">Services</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in categories.data" :key="c.id" class="border-b last:border-0">
                            <td class="px-4 py-2">
                                <div v-if="editingId === c.id" class="flex items-center gap-2">
                                    <Input v-model="draft" class="h-9" @keyup.enter="saveEdit(c.id)" />
                                    <Button size="icon-sm" @click="saveEdit(c.id)"><Check class="size-4" /></Button>
                                </div>
                                <span v-else class="font-medium">{{ c.name }}</span>
                            </td>
                            <td class="px-4 py-2 text-center text-muted-foreground">{{ c.services }}</td>
                            <td class="px-4 py-2 text-right">
                                <Button v-if="editingId !== c.id" variant="ghost" size="icon-sm" @click="startEdit(c)"><Pencil class="size-4" /></Button>
                                <ConfirmDialog title="Delete category?" description="Services in this category will become uncategorised." @confirm="remove(c.id)">
                                    <Button variant="ghost" size="icon-sm"><Trash2 class="size-4 text-rose-600" /></Button>
                                </ConfirmDialog>
                            </td>
                        </tr>
                        <tr v-if="categories.data.length === 0"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">No categories found.</td></tr>
                    </tbody>
                </table>
                <div v-if="categories.links.length > 3" class="p-3"><Pagination :links="categories.links" /></div>
            </CardContent>
        </Card>

        <Card class="border-none">
            <CardHeader><CardTitle>Add category</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="add">
                    <div class="grid gap-1.5"><Label>Name</Label><Input v-model="form.name" /></div>
                    <Button type="submit" :disabled="form.processing || !form.name">Add</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
