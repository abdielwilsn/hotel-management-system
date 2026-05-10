<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    Building2,
    CalendarDays,
    CircleDollarSign,
    CreditCard,
    Home,
    Package,
    LayoutGrid,
    ReceiptText,
    UserRound,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { index as departments } from '@/routes/departments';
import { index as staff } from '@/routes/staff';
import { index as rooms } from '@/routes/rooms';
import { index as bookings } from '@/routes/bookings';
import { index as invoices } from '@/routes/invoices';
import { index as payments } from '@/routes/payments';
import { index as expenses } from '@/routes/expenses';
import { index as guests } from '@/routes/guests';
import { index as inventory } from '@/routes/inventory';
import { index as reports } from '@/routes/reports';
import { index as forecasts } from '@/routes/forecasts';
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

const page = usePage();
const isAdmin = computed(() => {
    const role = page.props.currentTeam?.role;

    return role === 'owner' || role === 'admin';
});

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

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
        icon: CircleDollarSign,
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

const operationsNavItems = computed<NavItem[]>(() =>
    mainNavItems.value.filter((item) =>
        [
            'Dashboard',
            'Departments',
            'Staff',
            'Rooms',
            'Guests',
            'Inventory',
        ].includes(item.title),
    ),
);

const revenueNavItems = computed<NavItem[]>(() =>
    mainNavItems.value.filter((item) =>
        ['Bookings', 'Invoices', 'Payments', 'Expenses'].includes(item.title),
    ),
);

const insightsNavItems = computed<NavItem[]>(() =>
    isAdmin.value
        ? mainNavItems.value.filter((item) =>
              ['Reports', 'Forecasts'].includes(item.title),
          )
        : [],
);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="[--sidebar-width-icon:70px] [--sidebar-width:260px] max-lg:[--sidebar-width:70px]"
    >
        <SidebarHeader
            class="border-r border-sidebar-border/70 bg-sidebar/80 px-4 py-4 backdrop-blur-xl"
        >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="rounded-xl transition-colors duration-200 hover:bg-white/20 max-lg:justify-center"
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
            <NavMain label="Operations" :items="operationsNavItems" />
            <NavMain label="Revenue" :items="revenueNavItems" />
            <NavMain
                v-if="insightsNavItems.length > 0"
                label="Insights"
                :items="insightsNavItems"
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
