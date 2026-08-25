<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm ring-1 ring-primary/20 ring-offset-1 ring-offset-sidebar"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span class="truncate text-sm font-semibold tracking-tight">{{ name }}</span>
        <span class="truncate text-[11px] font-medium text-muted-foreground">Clinic Suite</span>
    </div>
</template>
