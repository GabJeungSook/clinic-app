<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{ url: string | null; label: string; active: boolean }>;
}>();
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap gap-1">
        <template v-for="(l, i) in links" :key="i">
            <Link
                v-if="l.url"
                :href="l.url"
                class="rounded-md border px-3 py-1 text-sm"
                :class="l.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                v-html="l.label"
            />
            <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50" v-html="l.label" />
        </template>
    </div>
</template>
