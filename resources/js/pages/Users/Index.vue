<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';
import { Pencil, UserX, Search } from '@lucide/vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import Pagination from '@/components/Pagination.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Staff', href: '/users' }] } });

interface StaffRow {
    id: number;
    name: string;
    username: string;
    job_title: string | null;
    role: string | null;
    is_active: boolean;
}

const props = defineProps<{
    users: {
        data: StaffRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    roles: Array<{ value: string; label: string }>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/users', { search: search.value }, { preserveState: true, replace: true });

const roleLabel = (v: string | null) => props.roles.find((r) => r.value === v)?.label ?? v ?? '—';

const create = useForm({ name: '', username: '', job_title: '', role: 'receptionist', password: '', password_confirmation: '', is_active: true });
const addUser = () => create.post('/users', { preserveScroll: true, onSuccess: () => create.reset() });

const editingId = ref<number | null>(null);
const edit = useForm({ name: '', job_title: '', role: '', is_active: true, password: '', password_confirmation: '' });
const startEdit = (u: StaffRow) => {
    editingId.value = u.id;
    edit.defaults({ name: u.name, job_title: u.job_title ?? '', role: u.role ?? 'receptionist', is_active: u.is_active, password: '', password_confirmation: '' });
    edit.reset();
};
const saveEdit = () => edit.put(`/users/${editingId.value}`, { preserveScroll: true, onSuccess: () => (editingId.value = null) });
const deactivate = (id: number) => router.delete(`/users/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Staff accounts" />
    <div class=" grid w-full gap-6 p-4 lg:grid-cols-5 md:p-6">
        <Card class="border-none lg:col-span-3">
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Staff accounts</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ users.total }} total</p>
                </div>
                <div class="relative w-48">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search…" class="h-9 pl-8" @keyup.enter="submitSearch" />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-4 py-2 font-medium">Name</th><th class="px-4 py-2 font-medium">Username</th><th class="px-4 py-2 font-medium">Role</th><th class="px-4 py-2 font-medium">Status</th><th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users.data" :key="u.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ u.name }}</div>
                                    <div v-if="u.job_title" class="text-xs text-muted-foreground">{{ u.job_title }}</div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ u.username }}</td>
                                <td class="px-4 py-2">{{ roleLabel(u.role) }}</td>
                                <td class="px-4 py-2"><Badge :variant="u.is_active ? 'secondary' : 'outline'">{{ u.is_active ? 'Active' : 'Inactive' }}</Badge></td>
                                <td class="px-4 py-2 text-right">
                                    <Button variant="ghost" size="icon-sm" @click="startEdit(u)"><Pencil class="size-4" /></Button>
                                    <ConfirmDialog v-if="u.is_active" title="Deactivate account?" :description="`${u.name} will no longer be able to sign in.`" confirm-text="Deactivate" @confirm="deactivate(u.id)">
                                        <Button variant="ghost" size="icon-sm"><UserX class="size-4 text-rose-600" /></Button>
                                    </ConfirmDialog>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No staff found.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="users.links.length > 3" class="p-3"><Pagination :links="users.links" /></div>
            </CardContent>
        </Card>

        <!-- Edit panel -->
        <Card v-if="editingId" class="border-none lg:col-span-2">
            <CardHeader><CardTitle>Edit staff</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="saveEdit">
                    <div class="grid gap-1.5"><Label>Name</Label><Input v-model="edit.name" /></div>
                    <div class="grid gap-1.5"><Label>Job title</Label><Input v-model="edit.job_title" /></div>
                    <div class="grid gap-1.5">
                        <Label>Role</Label>
                        <SearchSelect v-model="edit.role" :options="roles" :sort="false" />
                    </div>
                    <div class="grid gap-1.5"><Label>New password <span class="text-muted-foreground">(optional)</span></Label><Input type="password" v-model="edit.password" /></div>
                    <div v-if="edit.password" class="grid gap-1.5"><Label>Confirm password</Label><Input type="password" v-model="edit.password_confirmation" /></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="edit.is_active" /> Active</label>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="edit.processing">Save</Button>
                        <Button type="button" variant="ghost" @click="editingId = null">Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Add panel -->
        <Card v-else class="border-none lg:col-span-2">
            <CardHeader><CardTitle>Add staff</CardTitle></CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="addUser">
                    <div class="grid gap-1.5"><Label>Name</Label><Input v-model="create.name" /><InputError :message="create.errors.name" /></div>
                    <div class="grid gap-1.5"><Label>Username</Label><Input v-model="create.username" /><InputError :message="create.errors.username" /></div>
                    <div class="grid gap-1.5"><Label>Job title</Label><Input v-model="create.job_title" /></div>
                    <div class="grid gap-1.5">
                        <Label>Role</Label>
                        <SearchSelect v-model="create.role" :options="roles" :sort="false" />
                    </div>
                    <div class="grid gap-1.5"><Label>Password</Label><Input type="password" v-model="create.password" /><InputError :message="create.errors.password" /></div>
                    <div class="grid gap-1.5"><Label>Confirm password</Label><Input type="password" v-model="create.password_confirmation" /></div>
                    <Button type="submit" :disabled="create.processing || !create.name || !create.username">Create account</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
