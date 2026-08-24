<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/Pagination.vue';
import { Plus, Tags, Search } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Services', href: '/services' }] } });

const props = defineProps<{
    services: {
        data: Array<{ id: string; name: string; category: string | null; sessions: number; price: number; consumables: number; is_active: boolean }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
const submitSearch = () => router.get('/services', { search: search.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Services" />
    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Services</h1>
                <p class="text-sm text-muted-foreground">{{ services.total }} total</p>
            </div>
            <div class="flex gap-2">
                <Button as-child variant="outline"><Link href="/services/categories"><Tags class="size-4" /> Categories</Link></Button>
                <Button as-child><Link href="/services/create"><Plus class="size-4" /> New service</Link></Button>
            </div>
        </div>

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search name or code…" class="pl-8" />
            </div>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Name</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-center font-medium">Sessions</th>
                                <th class="px-4 py-2 text-right font-medium">Price</th>
                                <th class="px-4 py-2 text-center font-medium">BoM items</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in services.data" :key="s.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                                @click="router.visit(`/services/${s.id}/edit`)">
                                <td class="px-4 py-2 font-medium">{{ s.name }}</td>
                                <td class="px-4 py-2">{{ s.category ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <Badge v-if="s.sessions > 1" variant="secondary">{{ s.sessions }}-session</Badge>
                                    <span v-else class="text-muted-foreground">Single</span>
                                </td>
                                <td class="px-4 py-2 text-right">{{ s.price.toLocaleString() }}</td>
                                <td class="px-4 py-2 text-center text-muted-foreground">{{ s.consumables }}</td>
                                <td class="px-4 py-2">
                                    <Badge :variant="s.is_active ? 'secondary' : 'outline'">{{ s.is_active ? 'Active' : 'Inactive' }}</Badge>
                                </td>
                            </tr>
                            <tr v-if="services.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No services found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Pagination :links="services.links" />
    </div>
</template>
