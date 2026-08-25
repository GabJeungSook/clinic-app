<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import clinicPhoto from '@/assets/clinic-login.png';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const name = usePage().props.name as string;
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-[2fr_3fr]">
        <!-- Brand panel: let the clinic photo (with its own signage) do the branding.
             One tagline over a light bottom-only scrim — nothing competing with it. -->
        <div class="relative hidden flex-col justify-end overflow-hidden bg-neutral-200 p-12 text-white lg:flex">
            <img :src="clinicPhoto" alt="Skinthera Medical Aesthetic Clinic" class="absolute inset-0 h-full w-full object-cover object-[62%_50%]" />
            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-[linear-gradient(to_top,hsl(20_16%_8%/0.82)_0%,hsl(20_16%_8%/0.35)_45%,transparent_100%)]"></div>

            <div class="relative z-10 max-w-md">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight drop-shadow-sm">
                    Beautiful care,<br />beautifully managed.
                </h2>
                <p class="mt-2.5 text-sm text-white/75">Skinthera Medical Aesthetic · Tacurong</p>
            </div>
        </div>

        <!-- Form panel with a subtle floating petal motif -->
        <div class="relative flex items-center justify-center overflow-hidden bg-background p-6 sm:p-10">
            <div class="petal-field" aria-hidden="true">
                <span class="petal petal--1"></span>
                <span class="petal petal--fill petal--2"></span>
                <span class="petal petal--3"></span>
                <span class="petal petal--fill petal--4"></span>
                <span class="petal petal--5"></span>
            </div>

            <div class="relative z-10 w-full max-w-sm">
                <!-- App identity lives with the form, not over the photo -->
                <Link :href="home()" class="mb-9 inline-flex items-center gap-2.5">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                        <AppLogoIcon class="size-5" />
                    </div>
                    <span class="text-base font-semibold tracking-tight">{{ name }}</span>
                </Link>

                <div class="mb-6">
                    <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="mt-1.5 text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />

                <p class="mt-8 text-xs text-muted-foreground">
                    Aesthetic clinic management · secure &amp; offline
                </p>
            </div>
        </div>
    </div>
</template>
