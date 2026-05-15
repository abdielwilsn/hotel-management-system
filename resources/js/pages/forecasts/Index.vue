<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarRange,
    Wallet,
    TrendingUp,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/forecasts';

type Forecast = {
    projected_occupancy_rate: number;
    baseline_occupancy_rate: number;
    projected_revenue_30_days: number;
    projected_net_profit_30_days: number;
    outstanding_revenue: number;
    recent_expenses_30_days: number;
    upcoming_bookings_30_days: number;
    total_rooms: number;
};

type Alert = {
    level: 'warning' | 'critical';
    title: string;
    message: string;
};

type Props = {
    forecast: Forecast;
    alerts: Alert[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Forecasts',
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
</script>

<template>
    <Head title="Forecasting & Alerts" />

    <div class="space-y-6">
        <Heading
            title="Forecasting & Alerts"
            description="30-day occupancy, revenue, and risk outlook"
        />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Projected occupancy
                        <CalendarRange class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ forecast.projected_occupancy_rate }}%
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Baseline {{ forecast.baseline_occupancy_rate }}%
                    </p>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Projected revenue
                        <Wallet class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ formatCurrency(forecast.projected_revenue_30_days) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ forecast.upcoming_bookings_30_days }} bookings
                        expected
                    </p>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Recent expenses
                        <TrendingUp class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold">
                        {{ formatCurrency(forecast.recent_expenses_30_days) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Paid expenses, last 30 days
                    </p>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        Projected net profit
                        <TrendingUp class="text-hotel-primary size-4" />
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-3xl font-semibold"
                        :class="
                            forecast.projected_net_profit_30_days < 0
                                ? 'text-red-600'
                                : ''
                        "
                    >
                        {{
                            formatCurrency(
                                forecast.projected_net_profit_30_days,
                            )
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Outstanding
                        {{ formatCurrency(forecast.outstanding_revenue) }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <Card class="bg-white/90">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <AlertTriangle class="size-4 text-amber-500" />
                    Active Alerts
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="alerts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No active risks detected for this period.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(alert, index) in alerts"
                        :key="`${alert.title}-${index}`"
                        class="rounded-lg border p-3"
                        :class="
                            alert.level === 'critical'
                                ? 'border-red-200 bg-red-50'
                                : 'border-amber-200 bg-amber-50'
                        "
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <p class="text-sm font-semibold">
                                {{ alert.title }}
                            </p>
                            <Badge
                                :class="
                                    alert.level === 'critical'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-amber-100 text-amber-800'
                                "
                            >
                                {{ alert.level }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ alert.message }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
