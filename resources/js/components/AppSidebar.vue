<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    CalendarDays,
    ChartColumn,
    CreditCard,
    LayoutGrid,
    Package,
    Receipt,
    Settings,
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

const allNavItems: GatedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Appointments', href: '/appointments', icon: CalendarDays, permission: 'appointments.view' },
    { title: 'Checkout', href: '/checkout', icon: CreditCard, permission: 'pos.use' },
    { title: 'Patients', href: '/patients', icon: Users, permission: 'patients.view' },
    { title: 'Services', href: '/services', icon: Sparkles, permission: 'services.manage' },
    { title: 'Treatments', href: '/treatments', icon: Syringe, permission: 'treatments.view' },
    { title: 'Inventory', href: '/inventory', icon: Package, permission: 'inventory.view' },
    { title: 'Purchasing', href: '/purchases', icon: ShoppingCart, permission: 'purchasing.view' },
    { title: 'Billing', href: '/invoices', icon: Receipt, permission: 'billing.view' },
    { title: 'Reports', href: '/reports/revenue', icon: ChartColumn, permission: 'reports.view' },
    { title: 'Staff', href: '/users', icon: UserCog, permission: 'users.manage' },
    { title: 'Settings', href: '/clinic-settings', icon: Settings, permission: 'settings.manage' },
];

const permissions = computed<string[]>(
    () => ((usePage().props.auth as { permissions?: string[] })?.permissions) ?? [],
);

const mainNavItems = computed<NavItem[]>(() =>
    allNavItems.filter((i) => !i.permission || permissions.value.includes(i.permission)),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
