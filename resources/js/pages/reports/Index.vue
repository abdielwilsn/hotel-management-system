<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarClock,
    Wallet,
    DoorOpen,
    Hotel,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/reports';

type Summary = {
    occupancy_rate: number;
    active_bookings: number;
    bookings_this_month: number;
    gross_revenue: number;
    collected_revenue: number;
    outstanding_revenue: number;
    paid_expenses: number;
    net_profit: number;
    average_daily_rate: number;
    upcoming_check_ins: number;
    upcoming_check_outs: number;
    occupied_rooms: number;
    total_rooms: number;
};

type MonthlyTrend = {
    label: string;
    invoiced: number;
    collected: number;
};

type PaymentMethod = {
    method: string;
    total: number;
};

type Props = {
    summary: Summary;
    monthlyTrend: MonthlyTrend[];
    paymentMethods: PaymentMethod[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Reports',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value);

const formatMethod = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const maxMonthlyValue = Math.max(
    ...props.monthlyTrend.flatMap((month) => [month.invoiced, month.collected]),
    1,
);

const maxMethodValue = Math.max(
    ...props.paymentMethods.map((item) => item.total),
    1,
);
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-6">
        <Heading
            title="Reports & Analytics"
            description="Business performance overview with revenue, occupancy, and collection trends"
        />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Occupancy
                        <Hotel class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ summary.occupancy_rate }}%
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.occupied_rooms }} of
                        {{ summary.total_rooms }} rooms occupied
                    </p>
                </CardContent>
            </Card>

            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Active bookings
                        <CalendarClock class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ summary.active_bookings }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.bookings_this_month }} check-ins this month
                    </p>
                </CardContent>
            </Card>

            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Gross revenue
                        <Wallet class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ formatCurrency(summary.gross_revenue) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        ADR {{ formatCurrency(summary.average_daily_rate) }}
                    </p>
                </CardContent>
            </Card>

            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Collections
                        <Wallet class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ formatCurrency(summary.collected_revenue) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Outstanding
                        {{ formatCurrency(summary.outstanding_revenue) }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader>
                    <CardTitle
                        class="flex items-center justify-between text-base"
                    >
                        Invoiced vs Collected (6 months)
                        <Badge
                            class="bg-hotel-primary/10 text-hotel-primary rounded-full"
                        >
                            Monthly
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="month in monthlyTrend"
                            :key="month.label"
                            class="grid grid-cols-[40px_1fr_72px_72px] items-center gap-2"
                        >
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >{{ month.label }}</span
                            >
                            <div class="space-y-1">
                                <div class="h-2 rounded-full bg-muted/60">
                                    <div
                                        class="h-2 rounded-full bg-cyan-500"
                                        :style="{
                                            width: `${(month.invoiced / maxMonthlyValue) * 100}%`,
                                        }"
                                    />
                                </div>
                                <div class="h-2 rounded-full bg-muted/60">
                                    <div
                                        class="h-2 rounded-full bg-emerald-500"
                                        :style="{
                                            width: `${(month.collected / maxMonthlyValue) * 100}%`,
                                        }"
                                    />
                                </div>
                            </div>
                            <span
                                class="text-right text-xs text-muted-foreground"
                                >{{ formatCurrency(month.invoiced) }}</span
                            >
                            <span
                                class="text-right text-xs font-semibold text-foreground"
                                >{{ formatCurrency(month.collected) }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center gap-4 text-xs text-muted-foreground"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-cyan-500" />
                            Invoiced
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-500" />
                            Collected
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader>
                    <CardTitle class="text-base">Operations outlook</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        class="border-hotel-primary/15 bg-hotel-primary/5 rounded-lg border p-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Upcoming check-ins (7 days)
                        </p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ summary.upcoming_check_ins }}
                        </p>
                    </div>
                    <div
                        class="border-hotel-primary/15 rounded-lg border bg-white p-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Upcoming check-outs (7 days)
                        </p>
                        <p class="mt-1 text-2xl font-semibold">
                            {{ summary.upcoming_check_outs }}
                        </p>
                    </div>

                    <div class="pt-1">
                        <p
                            class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Collection by method
                        </p>
                        <div
                            v-if="paymentMethods.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No completed payments yet.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="method in paymentMethods"
                                :key="method.method"
                                class="space-y-1"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-medium text-foreground/90"
                                        >{{ formatMethod(method.method) }}</span
                                    >
                                    <span class="text-muted-foreground">{{
                                        formatCurrency(method.total)
                                    }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-muted/60">
                                    <div
                                        class="bg-hotel-primary h-2 rounded-full"
                                        :style="{
                                            width: `${(method.total / maxMethodValue) * 100}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800"
                    >
                        <DoorOpen class="size-4 shrink-0" />
                        Keep outstanding invoices low to stabilize cash flow.
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader>
                    <CardTitle class="text-base"
                        >Collection efficiency</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="h-3 rounded-full bg-muted/60">
                        <div
                            class="h-3 rounded-full bg-emerald-500"
                            :style="{
                                width: `${Math.min(
                                    summary.gross_revenue > 0
                                        ? (summary.collected_revenue /
                                              summary.gross_revenue) *
                                              100
                                        : 0,
                                    100,
                                )}%`,
                            }"
                        />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{
                            summary.gross_revenue > 0
                                ? `${((summary.collected_revenue / summary.gross_revenue) * 100).toFixed(1)}% of invoiced revenue collected`
                                : 'No invoices yet for efficiency calculation'
                        }}
                    </p>
                </CardContent>
            </Card>

            <Card class="border-hotel-primary/15 bg-white/90 shadow-sm">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <BarChart3 class="text-hotel-primary size-4" />
                        Revenue health
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 text-xs text-muted-foreground">
                    <p>
                        Gross revenue:
                        <span class="font-semibold text-foreground">{{
                            formatCurrency(summary.gross_revenue)
                        }}</span>
                    </p>
                    <p>
                        Collected:
                        <span class="font-semibold text-foreground">{{
                            formatCurrency(summary.collected_revenue)
                        }}</span>
                    </p>
                    <p>
                        Outstanding:
                        <span class="font-semibold text-foreground">{{
                            formatCurrency(summary.outstanding_revenue)
                        }}</span>
                    </p>
                    <p>
                        Paid expenses:
                        <span class="font-semibold text-foreground">{{
                            formatCurrency(summary.paid_expenses)
                        }}</span>
                    </p>
                    <p>
                        Net profit:
                        <span
                            class="font-semibold"
                            :class="
                                summary.net_profit < 0
                                    ? 'text-red-600'
                                    : 'text-foreground'
                            "
                            >{{ formatCurrency(summary.net_profit) }}</span
                        >
                    </p>
                    <p>
                        Average daily rate:
                        <span class="font-semibold text-foreground">{{
                            formatCurrency(summary.average_daily_rate)
                        }}</span>
                    </p>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
