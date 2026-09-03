<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowUpCircle,
    CalendarDays,
    ChartColumn,
    CreditCard,
    LayoutGrid,
    Package,
    Receipt,
    Settings,
    Wallet,
    ShoppingCart,
    Sparkles,
    Syringe,
    UserCog,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type GatedNavItem = NavItem & { permission?: string };
type NavGroup = { label?: string; items: GatedNavItem[] };

// Grouped navigation — operational items first, admin at the bottom.
const navGroups: NavGroup[] = [
    {
        label: 'Menu',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Appointments', href: '/appointments', icon: CalendarDays, permission: 'appointments.view' },
            { title: 'Checkout', href: '/checkout', icon: CreditCard, permission: 'pos.use' },
            { title: 'Patients', href: '/patients', icon: Users, permission: 'patients.view' },
            { title: 'Services', href: '/services', icon: Sparkles, permission: 'services.manage' },
            { title: 'Treatments', href: '/treatments', icon: Syringe, permission: 'treatments.view' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { title: 'Inventory', href: '/inventory', icon: Package, permission: 'inventory.view' },
            { title: 'Purchasing', href: '/purchases', icon: ShoppingCart, permission: 'purchasing.view' },
            { title: 'Billing', href: '/invoices', icon: Receipt, permission: 'billing.view' },
            { title: 'Expenses', href: '/expenses', icon: Wallet, permission: 'expenses.view' },
            { title: 'Reports', href: '/reports/revenue', icon: ChartColumn, permission: 'reports.view' },
        ],
    },
    {
        label: 'Setting',
        items: [
            { title: 'Staff', href: '/users', icon: UserCog, permission: 'users.manage' },
            { title: 'Settings', href: '/clinic-settings', icon: Settings, permission: 'settings.manage' },
        ],
    },
];

const permissions = computed<string[]>(
    () => ((usePage().props.auth as { permissions?: string[] })?.permissions) ?? [],
);

// "Update available" badge — shown once the launch check (or a manual check)
// finds a newer release, and while one is downloaded and waiting to install.
const updateReady = computed(() => {
    const state = (usePage().props.update as { status?: { state?: string } })?.status?.state;
    return state === 'available' || state === 'downloaded';
});

const visibleGroups = computed(() =>
    navGroups
        .map((group) => ({
            label: group.label,
            items: group.items.filter((i) => !i.permission || permissions.value.includes(i.permission)),
        }))
        .filter((group) => group.items.length > 0),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-transparent active:bg-transparent">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain v-for="group in visibleGroups" :key="group.label" :label="group.label" :items="group.items" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu v-if="updateReady">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Update available" class="text-primary">
                        <Link href="/clinic-settings">
                            <ArrowUpCircle />
                            <span>Update available</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
