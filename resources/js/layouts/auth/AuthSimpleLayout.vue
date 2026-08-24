<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const name = usePage().props.name as string;
</script>

<template>
    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-6 md:p-10">
        <!-- Animated patterned background -->
        <div class="auth-bg">
            <span class="auth-shape auth-shape--a"></span>
            <span class="auth-shape auth-shape--b"></span>
            <span class="auth-shape auth-shape--c"></span>
        </div>

        <div class="relative z-10 flex w-full max-w-md flex-col gap-6">
            <Link :href="home()" class="flex flex-col items-center gap-3 self-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-md">
                    <AppLogoIcon class="size-7" />
                </div>
                <span class="text-lg font-semibold tracking-tight">{{ name }}</span>
            </Link>

            <Card class="card-elevated rounded-2xl border-none">
                <CardHeader class="px-8 pt-8 pb-0 text-center">
                    <CardTitle class="text-xl">{{ title }}</CardTitle>
                    <CardDescription>{{ description }}</CardDescription>
                </CardHeader>
                <CardContent class="px-8 py-8">
                    <slot />
                </CardContent>
            </Card>

            <p class="text-center text-xs text-muted-foreground">
                Aesthetic clinic management · secure &amp; offline
            </p>
        </div>
    </div>
</template>
