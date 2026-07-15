<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BellRing,
    BookOpen,
    Building2,
    CalendarDays,
    CreditCard,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as bookingsIndex } from '@/routes/bookings';
import { index as paymentsIndex } from '@/routes/payments';
import { index as reportsIndex } from '@/routes/reports';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const teamSlug = computed(() => page.props.currentTeam?.slug ?? null);

const consoleActions = computed(() => {
    if (!teamSlug.value) {
        return [];
    }

    return [
        {
            icon: CalendarDays,
            title: 'Bookings',
            description: 'Open reservations, check-ins, and room changes.',
            href: bookingsIndex(teamSlug.value).url,
        },
        {
            icon: CreditCard,
            title: 'Payments',
            description: 'Record payments and print receipts instantly.',
            href: paymentsIndex(teamSlug.value).url,
        },
        {
            icon: BarChart3,
            title: 'Reports',
            description: 'Review revenue, occupancy, and outstanding balances.',
            href: reportsIndex(teamSlug.value).url,
        },
    ];
});

const operations = [
    {
        icon: BellRing,
        title: 'Front Desk Ready',
        description: 'One screen for arrivals, walk-ins, and nightly handoff.',
    },
    {
        icon: Wallet,
        title: 'Naira First',
        description: 'Every booking, folio, and receipt is displayed in NGN.',
    },
    {
        icon: Users,
        title: 'Team Accountability',
        description:
            'Created by and last action by are visible on every record.',
    },
    {
        icon: ShieldCheck,
        title: 'Controlled Access',
        description:
            'Staff and admins can move fast without losing traceability.',
    },
];
</script>

<template>
    <Head title="Ann's Haven | Front Desk Console" />

    <div class="min-h-screen bg-[#07131f] text-white">
        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-32 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-[#0ea5a0]/20 blur-3xl"
            ></div>
            <div
                class="absolute right-0 bottom-0 h-96 w-96 translate-x-1/3 translate-y-1/4 rounded-full bg-[#f59e0b]/15 blur-3xl"
            ></div>
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.08),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.05),transparent_30%)]"
            ></div>
        </div>

        <div
            class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 lg:px-8"
        >
            <nav
                class="flex items-center justify-between rounded-3xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur-xl"
            >
                <Link :href="'/'" class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0ea5a0] to-[#155e75] shadow-lg shadow-cyan-950/30"
                    >
                        <Building2 class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <p
                            class="text-xs tracking-[0.35em] text-white/50 uppercase"
                        >
                            Ann's Haven
                        </p>
                        <p class="text-lg font-semibold">Front Desk Console</p>
                    </div>
                </Link>

                <div class="flex items-center gap-3">
                    <Link v-if="$page.props.auth.user" :href="dashboardUrl">
                        <Button
                            class="bg-white text-slate-950 hover:bg-slate-100"
                            >Open Dashboard</Button
                        >
                    </Link>
                    <template v-else>
                        <Link :href="login()">
                            <Button
                                variant="ghost"
                                class="text-white hover:bg-white/10 hover:text-white"
                                >Log in</Button
                            >
                        </Link>
                        <Link v-if="canRegister" :href="register()">
                            <Button
                                class="bg-[#0ea5a0] text-white hover:bg-[#0b8a87]"
                                >Register</Button
                            >
                        </Link>
                    </template>
                </div>
            </nav>

            <main
                class="grid flex-1 gap-8 py-8 lg:grid-cols-[1.2fr_0.8fr] lg:py-12"
            >
                <section
                    class="flex flex-col justify-between gap-8 rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 backdrop-blur-xl lg:p-10"
                >
                    <div class="space-y-6">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f59e0b]/30 bg-[#f59e0b]/10 px-4 py-2 text-sm text-[#fde68a]"
                        >
                            <BellRing class="h-4 w-4" />
                            Ready for check-ins, folios, and payments
                        </div>

                        <div class="max-w-3xl space-y-4">
                            <h1
                                class="text-5xl font-semibold tracking-tight text-white sm:text-6xl"
                            >
                                Ann's Haven's
                                <span
                                    class="bg-gradient-to-r from-[#67e8f9] via-white to-[#fbbf24] bg-clip-text text-transparent"
                                >
                                    front desk home
                                </span>
                            </h1>
                            <p
                                class="max-w-2xl text-lg leading-8 text-white/70"
                            >
                                A focused entry point for Ann's Haven's front
                                desk and back office. Open bookings, record
                                payments, print receipts, and review performance
                                without the noise of a public landing page.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboardUrl"
                            >
                                <Button
                                    size="lg"
                                    class="bg-white px-6 text-slate-950 hover:bg-slate-100"
                                    >Open Dashboard</Button
                                >
                            </Link>
                            <Link v-else :href="login()">
                                <Button
                                    size="lg"
                                    class="bg-[#0ea5a0] px-6 text-white hover:bg-[#0b8a87]"
                                    >Log in to continue</Button
                                >
                            </Link>
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                            >
                                <Button
                                    size="lg"
                                    variant="outline"
                                    class="border-white/20 bg-white/5 px-6 text-white hover:bg-white/10"
                                    >Create account</Button
                                >
                            </Link>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="module in operations"
                            :key="module.title"
                            class="rounded-3xl border border-white/10 bg-slate-950/25 p-5"
                        >
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10"
                            >
                                <component
                                    :is="module.icon"
                                    class="h-6 w-6 text-[#67e8f9]"
                                />
                            </div>
                            <h2 class="text-base font-semibold text-white">
                                {{ module.title }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-white/65">
                                {{ module.description }}
                            </p>
                        </article>
                    </div>
                </section>

                <aside
                    class="space-y-5 rounded-[2rem] border border-white/10 bg-slate-950/40 p-6 shadow-2xl shadow-black/20 backdrop-blur-xl lg:p-8"
                >
                    <div
                        class="rounded-3xl border border-white/10 bg-white/5 p-5"
                    >
                        <p
                            class="text-xs tracking-[0.35em] text-white/45 uppercase"
                        >
                            Quick access
                        </p>
                        <div
                            v-if="consoleActions.length > 0"
                            class="mt-4 space-y-3"
                        >
                            <Link
                                v-for="action in consoleActions"
                                :key="action.title"
                                :href="action.href"
                                class="flex items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 hover:bg-white/10"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0ea5a0]/15 text-[#67e8f9]"
                                >
                                    <component
                                        :is="action.icon"
                                        class="h-5 w-5"
                                    />
                                </div>
                                <div>
                                    <p class="font-semibold text-white">
                                        {{ action.title }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-white/60"
                                    >
                                        {{ action.description }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                        <div
                            v-else
                            class="mt-4 rounded-2xl border border-dashed border-white/15 p-5 text-sm leading-7 text-white/60"
                        >
                            Sign in to open the operational console. The system
                            will route you directly to Ann's Haven's dashboard,
                            bookings, and payment tools.
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#f59e0b]/15 text-[#fde68a]"
                                >
                                    <BookOpen class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">
                                        Reception flow
                                    </p>
                                    <p
                                        class="text-xs tracking-[0.25em] text-white/35 uppercase"
                                    >
                                        Bookings, folios, receipts
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#67e8f9]/15 text-[#67e8f9]"
                                >
                                    <Users class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">
                                        Traceable actions
                                    </p>
                                    <p
                                        class="text-xs tracking-[0.25em] text-white/35 uppercase"
                                    >
                                        Created by and updated by
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-3xl border border-white/10 bg-white/5 p-5 sm:col-span-2 lg:col-span-1"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#22c55e]/15 text-[#86efac]"
                                >
                                    <BarChart3 class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">
                                        Revenue visibility
                                    </p>
                                    <p
                                        class="text-xs tracking-[0.25em] text-white/35 uppercase"
                                    >
                                        Naira-based reporting
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>
</template>
