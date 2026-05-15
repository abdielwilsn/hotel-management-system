<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BedDouble,
    Building2,
    CalendarClock,
    Wallet,
    Command,
    DoorOpen,
    Plus,
    Search,
    TrendingUp,
    Users,
    CreditCard,
    LogIn,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as bookings } from '@/routes/bookings';
import { index as payments } from '@/routes/payments';
import { index as rooms } from '@/routes/rooms';
import { index as staff } from '@/routes/staff';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

type MetricCard = {
    title: string;
    value: number;
    delta: string;
    hint: string;
    currency?: boolean;
};

type Props = {
    metricCards: MetricCard[];
};

const props = defineProps<Props>();

const metricCards = props.metricCards;

const metricIcon = (title: string) => {
    if (title === 'New Bookings') {
        return CalendarClock;
    }

    if (title === 'Current Check-ins') {
        return Users;
    }

    if (title === 'Check-outs Today') {
        return DoorOpen;
    }

    return Wallet;
};

const quickActions = [
    {
        title: 'New Booking',
        description: 'Create a new reservation',
        icon: Plus,
        color: 'bg-[rgb(var(--primary-brand-rgb)/0.12)] text-(--primary-brand)',
        route: (team: Team) => bookings(team.slug).url,
    },
    {
        title: 'Process Payment',
        description: 'Record guest payment',
        icon: CreditCard,
        color: 'bg-[rgb(var(--primary-brand-rgb)/0.16)] text-(--primary-brand)',
        route: (team: Team) => payments(team.slug).url,
    },
    {
        title: 'Check-in Guest',
        description: 'Mark arrival at desk',
        icon: LogIn,
        color: 'bg-[rgb(var(--accent-color-rgb)/0.18)] text-(--primary-brand)',
        route: (team: Team) => bookings(team.slug).url,
    },
    {
        title: 'View Rooms',
        description: 'Manage room inventory',
        icon: BedDouble,
        color: 'bg-[rgb(var(--accent-color-rgb)/0.24)] text-(--primary-brand)',
        route: (team: Team) => rooms(team.slug).url,
    },
];

const weekLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const occupancySeries = [34, 62, 58, 46, 74, 81, 77];

const channelMix = [
    { name: 'Direct Booking', percent: 41 },
    { name: 'Booking.com', percent: 25 },
    { name: 'Travel Agencies', percent: 18 },
    { name: 'Walk-ins', percent: 10 },
    { name: 'Other', percent: 6 },
];

const todaySchedule = [
    { day: 'Mon', date: '20', count: 3, note: 'Suite arrivals' },
    { day: 'Tue', date: '21', count: 2, note: 'VIP check-ins' },
    { day: 'Wed', date: '22', count: 5, note: 'Corporate group' },
    { day: 'Thu', date: '23', count: 2, note: 'Late departures' },
    { day: 'Fri', date: '24', count: 4, note: 'Weekend demand' },
];

const recentReservations = [
    {
        id: '#1234',
        guest: 'Cristian',
        stay: '1 night',
        action: 'Check-in at 15:00',
        room: 'Suite 203',
    },
    {
        id: '#1230',
        guest: 'Ronesecure',
        stay: '4 nights',
        action: 'Airport pickup requested',
        room: 'Deluxe 408',
    },
    {
        id: '#1231',
        guest: 'Sobir',
        stay: '2 nights',
        action: 'Breakfast included',
        room: 'Classic 109',
    },
];

const occupancyPoints = computed(() =>
    occupancySeries.map((value, index) => {
        const x = (index / (occupancySeries.length - 1)) * 700;
        const y = 220 - (value / 100) * 160;

        return {
            value,
            label: weekLabels[index],
            x,
            y,
        };
    }),
);

const linePath = computed(() =>
    occupancyPoints.value
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`,
        )
        .join(' '),
);

const areaPath = computed(() => `${linePath.value} L 700 220 L 0 220 Z`);

const today = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
}).format(new Date());

const formatNaira = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 0,
    }).format(value);

const brandRgb = ref<[number, number, number]>([15, 118, 110]);
const positivePillTextColor = ref('#0f172a');

const luminance = (rgb: [number, number, number]) => {
    const mapped = rgb.map((value) => {
        const channel = value / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * mapped[0] + 0.7152 * mapped[1] + 0.0722 * mapped[2];
};

onMounted(() => {
    const rootStyles = getComputedStyle(document.documentElement);
    const rgbToken = rootStyles.getPropertyValue('--primary-brand-rgb').trim();

    if (rgbToken) {
        const values = rgbToken
            .split(/\s+/)
            .map((value) => Number.parseInt(value, 10))
            .filter((value) => Number.isFinite(value));

        if (values.length === 3) {
            brandRgb.value = [values[0], values[1], values[2]];
        }
    }

    positivePillTextColor.value =
        luminance(brandRgb.value) > 0.48 ? '#0f172a' : '#ffffff';
});

const deltaStyle = (delta: string) => {
    if (delta.startsWith('-')) {
        return {
            backgroundColor: 'rgba(239, 68, 68, 0.14)',
            color: '#991b1b',
        };
    }

    return {
        backgroundColor: `rgba(${brandRgb.value.join(',')}, 0.14)`,
        color: positivePillTextColor.value,
    };
};

const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="dashboard-page relative flex min-h-full flex-col gap-6 overflow-x-hidden px-6 py-6 text-slate-900 lg:px-8 dark:text-slate-100"
    >
        <div
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_8%_0%,rgb(var(--primary-brand-rgb)/0.14),transparent_32%),radial-gradient(circle_at_100%_8%,rgb(var(--accent-color-rgb)/0.10),transparent_30%),linear-gradient(180deg,#f9fafb_0%,#f4f6f8_100%)] dark:bg-[radial-gradient(circle_at_8%_0%,rgb(var(--primary-brand-rgb)/0.26),transparent_34%),radial-gradient(circle_at_100%_8%,rgb(var(--accent-color-rgb)/0.16),transparent_34%),linear-gradient(180deg,#020617_0%,#0b1120_100%)]"
        />

        <Card class="dash-reveal elevated-card border border-white/50">
            <CardContent
                class="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between xl:p-8"
            >
                <div class="space-y-2">
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-(--primary-brand) uppercase"
                    >
                        Hotel command center
                    </p>
                    <h1
                        class="headline-font text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl"
                    >
                        Daily Operations Dashboard
                    </h1>
                    <p class="text-sm text-slate-600">
                        {{ today }} • Real-time visibility across reservations,
                        occupancy, and revenue
                    </p>
                </div>

                <div class="flex w-full flex-col gap-3 lg:w-auto lg:min-w-115">
                    <div
                        class="inset-search flex items-center gap-2 rounded-2xl px-4 py-3"
                    >
                        <Search class="size-4 text-slate-500" />
                        <input
                            type="text"
                            placeholder="Search reservations, guests, invoices"
                            class="w-full bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none"
                        />
                        <span
                            class="headline-font rounded-lg border border-slate-300/80 bg-white px-2.5 py-1 text-xs font-bold text-slate-700"
                        >
                            <Command class="mr-1 inline size-3" />K
                        </span>
                    </div>

                    <div
                        v-if="currentTeam"
                        class="flex flex-wrap justify-end gap-2"
                    >
                        <Button
                            as-child
                            variant="outline"
                            class="rounded-xl border-slate-300/80 bg-white/90"
                        >
                            <Link :href="rooms(currentTeam.slug).url">
                                Room board
                                <ArrowUpRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            class="rounded-xl border-slate-300/80 bg-white/90"
                        >
                            <Link :href="staff(currentTeam.slug).url">
                                Team schedule
                                <ArrowUpRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            class="rounded-xl bg-(--primary-brand) text-white hover:opacity-95"
                        >
                            <Link :href="bookings(currentTeam.slug).url">
                                Open reservations
                                <ArrowUpRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <section v-if="currentTeam" class="grid grid-cols-12 gap-4 sm:gap-6">
            <div class="col-span-12 mb-2">
                <h2 class="headline-font text-lg font-extrabold text-slate-900">
                    Quick Actions
                </h2>
            </div>
            <Link
                v-for="(action, index) in quickActions"
                :key="action.title"
                :href="action.route(currentTeam)"
                class="col-span-12 sm:col-span-6 lg:col-span-3"
            >
                <Card
                    class="dash-reveal elevated-card h-full cursor-pointer border border-white/55 transition-all hover:border-white/80 hover:shadow-lg"
                    :style="{ animationDelay: `${(index + 4) * 70}ms` }"
                >
                    <CardContent class="p-6">
                        <div
                            class="flex flex-col items-center gap-4 text-center"
                        >
                            <div :class="`rounded-2xl p-3 ${action.color}`">
                                <component :is="action.icon" class="size-6" />
                            </div>
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ action.title }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ action.description }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </section>

        <section class="grid grid-cols-12 gap-6">
            <Card
                v-for="(metric, index) in metricCards"
                :key="metric.title"
                class="dash-reveal elevated-card col-span-12 border border-white/55 sm:col-span-6 xl:col-span-3"
                :style="{ animationDelay: `${index * 70}ms` }"
            >
                <CardHeader class="px-5 pt-5 pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm font-semibold text-slate-500"
                    >
                        {{ metric.title }}
                        <component
                            :is="metricIcon(metric.title)"
                            class="size-4 text-(--primary-brand)"
                        />
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 px-5 pb-5">
                    <p
                        class="headline-font text-3xl font-extrabold tracking-tight text-slate-900"
                    >
                        {{
                            metric.currency
                                ? formatNaira(metric.value)
                                : metric.value
                        }}
                    </p>
                    <div class="flex items-center gap-2 text-xs">
                        <Badge
                            class="rounded-full px-2.5 py-1 font-semibold"
                            :style="deltaStyle(metric.delta)"
                        >
                            {{ metric.delta }}
                        </Badge>
                        <span class="text-slate-500">{{ metric.hint }}</span>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid grid-cols-12 gap-6">
            <Card
                class="dash-reveal elevated-card col-span-12 border border-white/55 xl:col-span-8"
                style="animation-delay: 220ms"
            >
                <CardHeader class="px-6 pt-6 pb-2">
                    <CardTitle
                        class="headline-font flex items-center justify-between text-base font-extrabold text-slate-900"
                    >
                        Occupancy pulse
                        <Badge
                            class="rounded-full bg-[rgb(var(--primary-brand-rgb)/0.12)] px-2.5 py-1 text-(--primary-brand)"
                            >Week view</Badge
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent class="px-6 pb-6">
                    <div
                        class="relative h-72 overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4"
                    >
                        <div
                            class="area-fill absolute inset-x-4 top-4 bottom-8 rounded-xl"
                        />

                        <svg
                            class="relative z-10 h-full w-full"
                            viewBox="0 0 700 260"
                            fill="none"
                            preserveAspectRatio="none"
                        >
                            <path :d="areaPath" fill="url(#occupancyFill)" />
                            <path
                                :d="linePath"
                                stroke="var(--primary-brand)"
                                stroke-width="3"
                                stroke-linecap="round"
                            />

                            <circle
                                v-for="point in occupancyPoints"
                                :key="point.label"
                                :cx="point.x"
                                :cy="point.y"
                                r="5"
                                fill="white"
                                stroke="var(--primary-brand)"
                                stroke-width="2"
                            />

                            <defs>
                                <linearGradient
                                    id="occupancyFill"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        :stop-color="`rgb(${brandRgb.join(' ')})`"
                                        stop-opacity="0.40"
                                    />
                                    <stop
                                        offset="100%"
                                        :stop-color="`rgb(${brandRgb.join(' ')})`"
                                        stop-opacity="0"
                                    />
                                </linearGradient>
                            </defs>
                        </svg>

                        <div
                            class="absolute inset-x-4 bottom-2 z-20 flex justify-between text-xs font-semibold text-slate-500"
                        >
                            <span v-for="day in weekLabels" :key="day">{{
                                day
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card
                class="dash-reveal elevated-card col-span-12 border border-white/55 xl:col-span-4"
                style="animation-delay: 280ms"
            >
                <CardHeader class="px-6 pt-6 pb-2">
                    <CardTitle
                        class="headline-font text-base font-extrabold text-slate-900"
                        >Bookings by channel</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-4 px-6 pb-6">
                    <div class="space-y-3">
                        <div
                            v-for="channel in channelMix"
                            :key="channel.name"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span class="text-slate-700">{{
                                    channel.name
                                }}</span>
                                <span class="text-slate-500"
                                    >{{ channel.percent }}%</span
                                >
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div
                                    class="h-2 rounded-full"
                                    :style="{
                                        width: `${channel.percent}%`,
                                        background:
                                            channel.name === 'Booking.com'
                                                ? 'rgb(var(--accent-color-rgb) / 0.85)'
                                                : channel.name ===
                                                    'Travel Agencies'
                                                  ? 'rgb(var(--secondary-brand-rgb) / 0.85)'
                                                  : 'rgb(var(--primary-brand-rgb) / 0.82)',
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
                    >
                        Direct and web channels account for 66% of this week's
                        check-ins.
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid grid-cols-12 gap-6">
            <Card
                class="dash-reveal elevated-card col-span-12 border border-white/55 xl:col-span-8"
                style="animation-delay: 340ms"
            >
                <CardHeader class="px-6 pt-6 pb-2">
                    <CardTitle
                        class="headline-font flex items-center justify-between text-base font-extrabold text-slate-900"
                    >
                        Revenue overview
                        <div
                            class="flex items-center gap-1 text-xs font-semibold text-emerald-700"
                        >
                            <TrendingUp class="size-3.5" />
                            7-day uplift
                        </div>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 px-6 pb-6">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p class="text-xs text-slate-500">Total revenue</p>
                            <p
                                class="headline-font mt-1 text-lg font-extrabold text-slate-900"
                            >
                                {{ formatNaira(41243000) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <p class="text-xs text-slate-500">
                                Offline bookings
                            </p>
                            <p
                                class="headline-font mt-1 text-lg font-extrabold text-slate-900"
                            >
                                {{ formatNaira(31243000) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <p class="text-xs text-slate-500">
                                Platform channels
                            </p>
                            <p
                                class="headline-font mt-1 text-lg font-extrabold text-slate-900"
                            >
                                {{ formatNaira(10000000) }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="channel in channelMix"
                            :key="`rev-${channel.name}`"
                            class="flex items-center gap-3"
                        >
                            <div
                                class="w-24 text-xs font-semibold text-slate-500 sm:w-32"
                            >
                                {{ channel.name }}
                            </div>
                            <div class="h-2 flex-1 rounded-full bg-slate-100">
                                <div
                                    class="h-2 rounded-full"
                                    :style="{
                                        width: `${Math.max(channel.percent - 8, 10)}%`,
                                        background:
                                            'rgb(var(--primary-brand-rgb) / 0.78)',
                                    }"
                                />
                            </div>
                            <div
                                class="w-10 text-right text-xs font-semibold text-slate-700"
                            >
                                {{ Math.max(channel.percent - 8, 10) }}%
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card
                class="dash-reveal elevated-card col-span-12 border border-white/55 xl:col-span-4"
                style="animation-delay: 400ms"
            >
                <CardHeader class="px-6 pt-6 pb-2">
                    <CardTitle
                        class="headline-font text-base font-extrabold text-slate-900"
                        >Front desk calendar</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-3 px-6 pb-6">
                    <div
                        v-for="day in todaySchedule"
                        :key="`${day.day}-${day.date}`"
                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-3 py-2"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-10 w-10 place-items-center rounded-lg bg-[rgb(var(--primary-brand-rgb)/0.12)] text-sm font-bold text-(--primary-brand)"
                            >
                                {{ day.date }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ day.day }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ day.note }}
                                </p>
                            </div>
                        </div>
                        <Badge class="rounded-full bg-slate-100 text-slate-600"
                            >{{ day.count }} bookings</Badge
                        >
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card
            class="dash-reveal elevated-card border border-white/55"
            style="animation-delay: 460ms"
        >
            <CardHeader class="px-6 pt-6 pb-2">
                <CardTitle
                    class="headline-font flex items-center justify-between text-base font-extrabold text-slate-900"
                >
                    New arrivals queue
                    <Badge class="rounded-full bg-slate-100 text-slate-700"
                        >Live</Badge
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 px-6 pb-6">
                <div
                    v-for="reservation in recentReservations"
                    :key="reservation.id"
                    class="grid grid-cols-[auto_1fr] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 sm:grid-cols-[90px_1fr_110px_1fr_130px]"
                >
                    <p class="text-xs font-bold text-(--primary-brand)">
                        {{ reservation.id }}
                    </p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ reservation.guest }}
                    </p>
                    <div class="flex items-center gap-1 text-xs text-slate-500">
                        <BedDouble class="size-3.5" />
                        {{ reservation.stay }}
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ reservation.action }}
                    </p>
                    <p class="text-xs font-semibold text-slate-600">
                        {{ reservation.room }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600"
                >
                    <Building2 class="mr-1 inline size-3.5" />
                    Front desk is currently processing 3 VIP arrivals.
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.headline-font {
    font-family:
        'Montserrat', 'Inter', 'Instrument Sans', ui-sans-serif, system-ui,
        sans-serif;
}

.dash-reveal {
    animation: dash-reveal 480ms ease-out both;
}

.elevated-card {
    border-radius: 24px;
    background: #ffffff;
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.05),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.inset-search {
    background: #f3f4f6;
    box-shadow: inset 2px 2px 5px rgba(0, 0, 0, 0.05);
}

.area-fill {
    background: linear-gradient(
        180deg,
        rgb(var(--primary-brand-rgb) / 0.4) 0%,
        rgb(var(--primary-brand-rgb) / 0) 100%
    );
}

:global(.dark) .dashboard-page .elevated-card {
    background: rgba(15, 23, 42, 0.92);
    border-color: rgba(100, 116, 139, 0.38);
    box-shadow:
        0 18px 28px -18px rgba(2, 6, 23, 0.85),
        0 8px 18px -12px rgba(2, 6, 23, 0.65);
}

:global(.dark) .dashboard-page .inset-search {
    background: rgba(30, 41, 59, 0.88);
    border: 1px solid rgba(100, 116, 139, 0.28);
    box-shadow: inset 1px 1px 4px rgba(0, 0, 0, 0.35);
}

:global(.dark) .dashboard-page .text-slate-900 {
    color: rgb(241 245 249);
}

:global(.dark) .dashboard-page .text-slate-800 {
    color: rgb(226 232 240);
}

:global(.dark) .dashboard-page .text-slate-700 {
    color: rgb(203 213 225);
}

:global(.dark) .dashboard-page .text-slate-600,
:global(.dark) .dashboard-page .text-slate-500 {
    color: rgb(156 163 175);
}

:global(.dark) .dashboard-page .bg-white,
:global(.dark) .dashboard-page .bg-white\/90 {
    background-color: rgba(17, 24, 39, 0.94);
}

:global(.dark) .dashboard-page .bg-slate-50,
:global(.dark) .dashboard-page .bg-slate-100 {
    background-color: rgba(30, 41, 59, 0.88);
}

:global(.dark) .dashboard-page .border-slate-200,
:global(.dark) .dashboard-page .border-slate-300\/80,
:global(.dark) .dashboard-page .border-slate-200\/80 {
    border-color: rgba(100, 116, 139, 0.35);
}

@keyframes dash-reveal {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
