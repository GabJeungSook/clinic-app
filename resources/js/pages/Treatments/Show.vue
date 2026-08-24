<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { fmt } from '@/lib/datetime';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CreditCard } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Treatments', href: '/treatments' }, { title: 'Package', href: '#' }] } });

const props = defineProps<{
    course: { id: string; patient: string | null; patient_id: string; service: string | null; name: string; status: string; total_sessions: number; sessions_completed: number; sessions_remaining: number };
    sessions: Array<{ id: string; session_number: number | null; status: string; performed_at: string | null; performed_by: string | null; notes: string | null; items: Array<{ name: string | null; qty: number; unit: string | null }> }>;
}>();

const canRecord = props.course.status === 'active' && props.course.sessions_remaining > 0;
</script>

<template>
    <Head :title="course.name" />
    <div class="flex w-full flex-col gap-6 p-4 md:p-6">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>{{ course.name }}</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        <Link :href="`/patients/${course.patient_id}`" class="text-primary hover:underline">{{ course.patient }}</Link>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge>{{ course.status }}</Badge>
                    <Button v-if="canRecord" as-child size="sm">
                        <Link :href="`/checkout?patient=${course.patient_id}`"><CreditCard class="size-4" /> Checkout</Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex items-center gap-3">
                    <div class="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary" :style="{ width: `${(course.sessions_completed / course.total_sessions) * 100}%` }" />
                    </div>
                    <span class="text-sm font-medium">{{ course.sessions_completed }}/{{ course.total_sessions }} · {{ course.sessions_remaining }} left</span>
                </div>
            </CardContent>
        </Card>

        <!-- Session history -->
        <Card>
            <CardHeader><CardTitle class="text-base">Session history</CardTitle></CardHeader>
            <CardContent>
                <ol v-if="sessions.length" class="relative flex flex-col gap-4 border-l border-border pl-5">
                    <li v-for="s in sessions" :key="s.id" class="relative">
                        <span class="absolute -left-[23px] top-1 size-2.5 rounded-full bg-primary ring-4 ring-background"></span>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-medium">
                                Session {{ s.session_number ?? '—' }}
                                <Badge variant="outline" class="ml-1 capitalize">{{ s.status }}</Badge>
                            </span>
                            <span class="text-xs text-muted-foreground">{{ fmt(s.performed_at) }}<span v-if="s.performed_by"> · {{ s.performed_by }}</span></span>
                        </div>
                        <p v-if="s.notes" class="mt-0.5 text-sm text-muted-foreground">{{ s.notes }}</p>
                        <div v-if="s.items.length" class="mt-1.5 flex flex-wrap gap-1.5">
                            <span v-for="(it, k) in s.items" :key="k" class="rounded-full bg-muted px-2 py-0.5 text-xs">
                                {{ it.name }} · {{ it.qty }}{{ it.unit ? ' ' + it.unit : '' }}
                            </span>
                        </div>
                    </li>
                </ol>
                <p v-else class="py-6 text-center text-sm text-muted-foreground">No sessions performed yet.</p>
            </CardContent>
        </Card>
    </div>
</template>
