<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    CreditCard,
    Home,
    LayoutGrid,
    Package,
    ReceiptText,
    Store,
    TriangleAlert,
    UserRound,
    Users,
    Wallet,
    Wine,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
import { usageGuide } from '@/routes';
import { dashboard } from '@/routes';
import { index as bookings } from '@/routes/bookings';
import { index as departments } from '@/routes/departments';
import { index as expenses } from '@/routes/expenses';
import { index as forecasts } from '@/routes/forecasts';
import { index as guests } from '@/routes/guests';
import { index as incidents } from '@/routes/incidents';
import { index as inventory } from '@/routes/inventory';
import { index as invoices } from '@/routes/invoices';
import { index as payments } from '@/routes/payments';
import { index as pos, manage as posManage } from '@/routes/pos';
import { index as reports } from '@/routes/reports';
import { index as rooms } from '@/routes/rooms';
import { index as staff } from '@/routes/staff';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => {
    const role = page.props.currentTeam?.role;

    return role === 'owner' || role === 'admin';
});

// POS-only staff see nothing but the point of sale.
const isPosStaff = computed(() => page.props.currentTeam?.role === 'pos');

const dashboardUrl = computed(() => {
    if (!page.props.currentTeam) {
        return '/';
    }

    // POS-only staff can't open the dashboard, so their logo/home link goes to the POS.
    return isPosStaff.value
        ? pos(page.props.currentTeam.slug).url
        : dashboard(page.props.currentTeam.slug).url;
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'Departments',
        href: page.props.currentTeam
            ? departments(page.props.currentTeam.slug).url
            : '/',
        icon: Building2,
    },
    {
        title: 'Staff',
        href: page.props.currentTeam
            ? staff(page.props.currentTeam.slug).url
            : '/',
        icon: Users,
    },
    {
        title: 'Incidents',
        href: page.props.currentTeam
            ? incidents(page.props.currentTeam.slug).url
            : '/',
        icon: TriangleAlert,
    },
    {
        title: 'Rooms',
        href: page.props.currentTeam
            ? rooms(page.props.currentTeam.slug).url
            : '/',
        icon: Home,
    },
    {
        title: 'Bookings',
        href: page.props.currentTeam
            ? bookings(page.props.currentTeam.slug).url
            : '/',
        icon: CalendarDays,
    },
    {
        title: 'Invoices',
        href: page.props.currentTeam
            ? invoices(page.props.currentTeam.slug).url
            : '/',
        icon: Wallet,
    },
    {
        title: 'Payments',
        href: page.props.currentTeam
            ? payments(page.props.currentTeam.slug).url
            : '/',
        icon: CreditCard,
    },
    {
        title: 'Expenses',
        href: page.props.currentTeam
            ? expenses(page.props.currentTeam.slug).url
            : '/',
        icon: ReceiptText,
    },
    {
        title: 'Guests',
        href: page.props.currentTeam
            ? guests(page.props.currentTeam.slug).url
            : '/',
        icon: UserRound,
    },
    {
        title: 'Inventory',
        href: page.props.currentTeam
            ? inventory(page.props.currentTeam.slug).url
            : '/',
        icon: Package,
    },
    {
        title: 'Usage Guide',
        href: page.props.currentTeam
            ? usageGuide(page.props.currentTeam.slug).url
            : '/',
        icon: BookOpen,
    },
    {
        title: 'Reports',
        href: page.props.currentTeam
            ? reports(page.props.currentTeam.slug).url
            : '/',
        icon: BarChart3,
    },
    {
        title: 'Forecasts',
        href: page.props.currentTeam
            ? forecasts(page.props.currentTeam.slug).url
            : '/',
        icon: Activity,
    },
]);

// Manager-only modules. Receptionists (the Member role) don't work with these,
// and the backend routes are gated to admins too, so hide them from the nav.
const adminOnlyTitles = ['Departments', 'Staff', 'Inventory', 'Expenses'];

const canViewIncidents = computed(() =>
    (page.props.abilities ?? []).includes('incidents.view'),
);

const isVisibleNavItem = (item: NavItem) => {
    if (item.title === 'Incidents') {
        return canViewIncidents.value;
    }

    return isAdmin.value || !adminOnlyTitles.includes(item.title);
};

const operationsNavItems = computed<NavItem[]>(() =>
    mainNavItems.value.filter(
        (item) =>
            [
                'Dashboard',
                'Departments',
                'Staff',
                'Incidents',
                'Rooms',
                'Guests',
                'Inventory',
                'Usage Guide',
            ].includes(item.title) && isVisibleNavItem(item),
    ),
);

const revenueNavItems = computed<NavItem[]>(() =>
    mainNavItems.value.filter(
        (item) =>
            ['Bookings', 'Invoices', 'Payments', 'Expenses'].includes(
                item.title,
            ) && isVisibleNavItem(item),
    ),
);

const insightsNavItems = computed<NavItem[]>(() =>
    isAdmin.value
        ? mainNavItems.value.filter((item) =>
              ['Reports', 'Forecasts'].includes(item.title),
          )
        : [],
);

const posNavItems = computed<NavItem[]>(() => {
    if (!page.props.currentTeam) {
        return [];
    }

    const slug = page.props.currentTeam.slug;

    const items: NavItem[] = [
        {
            title: 'Point of Sale',
            href: pos(slug).url,
            icon: Wine,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: 'POS Outlets',
            href: posManage(slug).url,
            icon: Store,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="[--sidebar-width-icon:70px] [--sidebar-width:260px]"
    >
        <SidebarHeader
            class="border-r border-sidebar-border/70 bg-sidebar/80 px-4 py-4 backdrop-blur-xl"
        >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="rounded-xl transition-colors duration-200 hover:bg-white/20"
                    >
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent
            class="flex-1 overflow-y-auto border-r border-sidebar-border/70 bg-sidebar/75 py-4 backdrop-blur-xl"
        >
            <template v-if="!isPosStaff">
                <NavMain label="Operations" :items="operationsNavItems" />
                <NavMain label="Revenue" :items="revenueNavItems" />
                <NavMain
                    v-if="insightsNavItems.length > 0"
                    label="Insights"
                    :items="insightsNavItems"
                />
            </template>
            <NavMain
                v-if="posNavItems.length > 0"
                label="Bar & Restaurant"
                :items="posNavItems"
            />
        </SidebarContent>

        <SidebarFooter
            class="border-r border-sidebar-border/70 bg-sidebar/80 px-3 py-3 backdrop-blur-xl"
        >
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
