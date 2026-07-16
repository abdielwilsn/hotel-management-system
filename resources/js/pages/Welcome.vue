<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    BedDouble,
    Building2,
    CalendarDays,
    CreditCard,
    LogIn,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import { index as bookingsIndex } from '@/routes/bookings';
import { index as paymentsIndex } from '@/routes/payments';
import { index as roomsIndex } from '@/routes/rooms';

const page = usePage();
const isAuthed = computed(() => Boolean(page.props.auth.user));
const teamSlug = computed(() => page.props.currentTeam?.slug ?? null);
const dashboardUrl = computed(() =>
    teamSlug.value ? dashboard(teamSlug.value).url : '/',
);

const quickActions = computed(() => {
    if (!teamSlug.value) {
        return [];
    }

    return [
        {
            icon: CalendarDays,
            title: 'Bookings',
            description: 'Reservations, check-ins, and room moves.',
            href: bookingsIndex(teamSlug.value).url,
        },
        {
            icon: BedDouble,
            title: 'Rooms',
            description: 'Availability and the live room board.',
            href: roomsIndex(teamSlug.value).url,
        },
        {
            icon: CreditCard,
            title: 'Payments',
            description: 'Record payments and print receipts.',
            href: paymentsIndex(teamSlug.value).url,
        },
    ];
});

const highlights = [
    {
        icon: CalendarDays,
        title: 'Front desk, focused',
        description: 'Arrivals, walk-ins, and nightly handoff on one screen.',
    },
    {
        icon: ShieldCheck,
        title: 'Controlled access',
        description:
            'Role-aware tools — reception and managers each see their own.',
    },
    {
        icon: Users,
        title: 'Full accountability',
        description: 'Every record shows who created and last touched it.',
    },
    {
        icon: BarChart3,
        title: 'Naira-first reporting',
        description: 'Revenue, occupancy, and balances at a glance.',
    },
];

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Ann's Haven | Front Desk Console" />

    <div class="relative min-h-screen overflow-hidden bg-[#07131f] text-white">
        <!-- Ambient background -->
        <div class="pointer-events-none absolute inset-0">
            <div
                class="absolute -top-40 left-1/2 h-112 w-md -translate-x-1/2 rounded-full bg-[#0ea5a0]/20 blur-[120px]"
            ></div>
            <div
                class="absolute right-0 bottom-0 h-104 w-104 translate-x-1/4 translate-y-1/4 rounded-full bg-[#f59e0b]/12 blur-[120px]"
            ></div>
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.06),transparent_40%)]"
            ></div>
        </div>

        <div
            class="relative mx-auto flex min-h-screen max-w-6xl flex-col px-5 py-6 sm:px-6 lg:px-8"
        >
            <!-- Nav -->
            <nav class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0ea5a0] to-[#155e75] shadow-lg shadow-cyan-950/40"
                    >
                        <Building2 class="h-6 w-6 text-white" />
                    </div>
                    <div class="leading-tight">
                        <p
                            class="text-[10px] tracking-[0.35em] text-white/45 uppercase"
                        >
                            Ann's Haven
                        </p>
                        <p class="text-base font-semibold">
                            Front Desk Console
                        </p>
                    </div>
                </div>

                <Link v-if="isAuthed" :href="dashboardUrl">
                    <Button
                        class="gap-2 bg-white text-slate-950 hover:bg-slate-100"
                    >
                        Open Dashboard
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </Link>
                <Link v-else :href="login()">
                    <Button
                        variant="ghost"
                        class="gap-2 text-white hover:bg-white/10 hover:text-white"
                    >
                        <LogIn class="h-4 w-4" />
                        Staff Log in
                    </Button>
                </Link>
            </nav>

            <!-- Hero -->
            <main
                class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:py-16"
            >
                <section class="space-y-8">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-[#f59e0b]/25 bg-[#f59e0b]/10 px-4 py-1.5 text-sm text-[#fde68a]"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-[#f59e0b]"
                        ></span>
                        Private staff workspace
                    </div>

                    <div class="space-y-5">
                        <h1
                            class="text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl"
                        >
                            Run the front desk,
                            <span
                                class="bg-linear-to-r from-[#67e8f9] via-white to-[#fbbf24] bg-clip-text text-transparent"
                            >
                                calmly.
                            </span>
                        </h1>
                        <p
                            class="max-w-xl text-base leading-8 text-white/70 sm:text-lg"
                        >
                            Ann's Haven's operations console — bookings, folios,
                            payments, and reporting in one place. Sign in with
                            the account your manager set up for you.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link v-if="isAuthed" :href="dashboardUrl">
                            <Button
                                size="lg"
                                class="gap-2 bg-white px-6 text-slate-950 hover:bg-slate-100"
                            >
                                Open Dashboard
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link v-else :href="login()">
                            <Button
                                size="lg"
                                class="gap-2 bg-[#0ea5a0] px-6 text-white hover:bg-[#0b8a87]"
                            >
                                <LogIn class="h-4 w-4" />
                                Log in to continue
                            </Button>
                        </Link>
                    </div>

                    <!-- Highlights -->
                    <dl class="grid gap-x-8 gap-y-6 pt-2 sm:grid-cols-2">
                        <div
                            v-for="item in highlights"
                            :key="item.title"
                            class="flex gap-3"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/8 ring-1 ring-white/10"
                            >
                                <component
                                    :is="item.icon"
                                    class="h-5 w-5 text-[#67e8f9]"
                                />
                            </div>
                            <div>
                                <dt class="text-sm font-semibold text-white">
                                    {{ item.title }}
                                </dt>
                                <dd
                                    class="mt-1 text-sm leading-6 text-white/60"
                                >
                                    {{ item.description }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <!-- Console card -->
                <aside
                    class="rounded-4xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-8"
                >
                    <p
                        class="text-[10px] tracking-[0.35em] text-white/45 uppercase"
                    >
                        {{ isAuthed ? 'Quick access' : 'Inside the console' }}
                    </p>

                    <!-- Authenticated: jump straight to work -->
                    <div
                        v-if="isAuthed && quickActions.length"
                        class="mt-5 space-y-3"
                    >
                        <Link
                            v-for="action in quickActions"
                            :key="action.title"
                            :href="action.href"
                            class="group flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/25 hover:bg-white/10"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0ea5a0]/15 text-[#67e8f9]"
                            >
                                <component :is="action.icon" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white">
                                    {{ action.title }}
                                </p>
                                <p class="truncate text-sm text-white/55">
                                    {{ action.description }}
                                </p>
                            </div>
                            <ArrowRight
                                class="h-4 w-4 text-white/30 transition group-hover:translate-x-0.5 group-hover:text-white/70"
                            />
                        </Link>
                    </div>

                    <!-- Guest: what's behind the login -->
                    <div v-else class="mt-5 space-y-3">
                        <div
                            v-for="item in highlights.slice(0, 3)"
                            :key="item.title"
                            class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0ea5a0]/15 text-[#67e8f9]"
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                            </div>
                            <p class="text-sm leading-6 text-white/75">
                                {{ item.title }}
                            </p>
                        </div>

                        <Link :href="login()" class="block pt-1">
                            <Button
                                class="w-full gap-2 bg-white text-slate-950 hover:bg-slate-100"
                            >
                                <LogIn class="h-4 w-4" />
                                Staff sign in
                            </Button>
                        </Link>
                        <p class="pt-1 text-center text-xs text-white/40">
                            Accounts are provisioned by your administrator.
                        </p>
                    </div>
                </aside>
            </main>

            <!-- Footer -->
            <footer
                class="flex flex-col items-center justify-between gap-2 border-t border-white/10 py-6 text-xs text-white/40 sm:flex-row"
            >
                <p>© {{ year }} Ann's Haven. All rights reserved.</p>
                <p class="tracking-[0.25em] uppercase">Front Desk Console</p>
            </footer>
        </div>
    </div>
</template>
