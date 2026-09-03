<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Pagination.vue';
import { CheckCircle2, CreditCard, Search } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Treatments', href: '/treatments' }] } });

const props = defineProps<{
    courses: {
        data: Array<{ id: string; patient: string | null; patient_id: string | null; service: string | null; status: string; total_sessions: number; sessions_completed: number; sessions_remaining: number }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { status: string; search: string };
}>();

const search = ref(props.filters.search ?? '');
const setStatus = (s: string) => router.get('/treatments', { status: s, search: search.value }, { preserveState: true, replace: true });
const submitSearch = () => router.get('/treatments', { status: props.filters.status, search: search.value }, { preserveState: true, replace: true });
const tone: Record<string, string> = { active: 'bg-sky-100 text-sky-700', completed: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-rose-100 text-rose-700', expired: 'bg-amber-100 text-amber-700' };

// A package is done when marked completed or all sessions are used up.
const isDone = (c: { status: string; sessions_completed: number; total_sessions: number }) =>
    c.status === 'completed' || c.sessions_completed >= c.total_sessions;
// Rows are ordered by patient — only show the name on the first row of each group.
const isNewPatient = (i: number) => i === 0 || props.courses.data[i - 1].patient_id !== props.courses.data[i].patient_id;
</script>

<template>
    <Head title="Treatments" />
    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Treatment packages</h1>
                <p class="text-sm text-muted-foreground">{{ courses.total }} total · progress per patient. Packages start when a multi-session service is availed at Checkout.</p>
            </div>
            <Button as-child><Link href="/checkout"><CreditCard class="size-4" /> Checkout</Link></Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex gap-1">
                <Button v-for="s in ['active', 'completed', 'all']" :key="s" size="sm" :variant="filters.status === s ? 'default' : 'outline'" class="capitalize" @click="setStatus(s)">{{ s }}</Button>
            </div>
            <form class="relative max-w-xs flex-1" @submit.prevent="submitSearch">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search patient or service…" class="pl-8" @keyup.enter="submitSearch" />
            </form>
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr><th class="px-4 py-2 font-medium">Patient</th><th class="px-4 py-2 font-medium">Service</th><th class="px-4 py-2 font-medium">Progress</th><th class="px-4 py-2 font-medium">Status</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(c, i) in courses.data" :key="c.id" class="cursor-pointer hover:bg-muted/40" :class="isNewPatient(i) && i !== 0 ? 'border-t' : ''" @click="router.visit(`/treatments/${c.id}`)">
                                <td class="px-4 py-2 font-medium">{{ isNewPatient(i) ? c.patient : '' }}</td>
                                <td class="px-4 py-2">{{ c.service }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-28 overflow-hidden rounded-full bg-muted">
                                            <div class="h-full" :class="isDone(c) ? 'bg-emerald-500' : 'bg-primary'" :style="{ width: `${Math.min(100, (c.sessions_completed / c.total_sessions) * 100)}%` }" />
                                        </div>
                                        <span class="text-xs" :class="isDone(c) ? 'font-medium text-emerald-600' : 'text-muted-foreground'">{{ c.sessions_completed }}/{{ c.total_sessions }}</span>
                                        <span v-if="isDone(c)" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><CheckCircle2 class="size-3.5" /> Completed</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2"><Badge :class="tone[c.status]">{{ c.status }}</Badge></td>
                            </tr>
                            <tr v-if="courses.data.length === 0"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No packages found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Pagination :links="courses.links" />
    </div>
</template>
