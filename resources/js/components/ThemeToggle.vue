<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="flex size-9 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
            aria-label="Toggle theme"
        >
            <Moon v-if="resolvedAppearance === 'dark'" class="size-4.5" />
            <Sun v-else class="size-4.5" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-40">
            <DropdownMenuItem
                v-for="o in options"
                :key="o.value"
                class="cursor-pointer gap-2"
                :class="appearance === o.value ? 'bg-accent text-accent-foreground font-medium' : ''"
                @click="updateAppearance(o.value)"
            >
                <component :is="o.Icon" class="size-4" />
                {{ o.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
