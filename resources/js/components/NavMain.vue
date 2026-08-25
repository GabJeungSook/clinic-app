<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    label?: string;
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

// Solid brand pill for the active item; soft accent on hover for the rest.
const activeClasses =
    'data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground data-[active=true]:font-semibold data-[active=true]:shadow-sm data-[active=true]:hover:bg-sidebar-primary data-[active=true]:hover:text-sidebar-primary-foreground';
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel
            v-if="label"
            class="px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/45"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    :class="['h-9 rounded-lg', activeClasses]"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
