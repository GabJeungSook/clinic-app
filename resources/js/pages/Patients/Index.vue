<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { fmt } from '@/lib/datetime';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserPlus, Search } from '@lucide/vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Patients', href: '/patients' }] },
});

interface PatientRow {
    id: string;
    code: string;
    full_name: string;
    phone: string | null;
    sex: string | null;
    created_at: string | null;
}

const props = defineProps<{
    patients: {
        data: PatientRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

function submitSearch() {
    router.get('/patients', { search: search.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Patients" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Patients</h1>
                <p class="text-sm text-muted-foreground">{{ patients.total }} total</p>
            </div>
            <Button as-child>
                <Link href="/patients/create"><UserPlus class="size-4" /> New patient</Link>
            </Button>
        </div>

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <div class="relative flex-1">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search name, code, phone…" class="pl-8" />
            </div>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Code</th>
                                <th class="px-4 py-2 font-medium">Name</th>
                                <th class="px-4 py-2 font-medium">Phone</th>
                                <th class="px-4 py-2 font-medium">Sex</th>
                                <th class="px-4 py-2 font-medium">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in patients.data"
                                :key="p.id"
                                class="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                                @click="router.visit(`/patients/${p.id}`)"
                            >
                                <td class="px-4 py-2 font-mono text-xs">{{ p.code }}</td>
                                <td class="px-4 py-2 font-medium">{{ p.full_name }}</td>
                                <td class="px-4 py-2">{{ p.phone ?? '—' }}</td>
                                <td class="px-4 py-2 capitalize">{{ p.sex ?? '—' }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ p.created_at ? fmt(p.created_at) : '—' }}</td>
                            </tr>
                            <tr v-if="patients.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                    No patients found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div v-if="patients.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, i) in patients.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    v-html="link.label"
                />
                <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
