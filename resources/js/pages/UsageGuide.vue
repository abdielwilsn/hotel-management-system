<script setup lang="ts">
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usageGuide } from '@/routes';
import type { Team } from '@/types';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Usage Guide',
                href: props.currentTeam
                    ? usageGuide(props.currentTeam.slug).url
                    : '/',
            },
        ],
    }),
});

const quickLinks = computed(() => [
    {
        title: 'Front Desk Flow',
        steps: [
            'Create a booking from the Bookings page.',
            'Set booking to checked_in when the guest arrives.',
            'Record payment from Bookings or Payments.',
            'Set booking to checked_out at departure.',
        ],
    },
    {
        title: 'Revenue Tracking',
        steps: [
            'Use Invoices to confirm billing balances.',
            'Use Payments filters to find pending or failed transactions.',
            'Use date-range filters for daily and monthly reconciliation.',
        ],
    },
    {
        title: 'Rooms and Occupancy',
        steps: [
            'Room cards show active guest occupancy when a checked-in booking exists.',
            'Occupancy summary is computed from live checked-in bookings.',
            'Use room status for maintenance and cleaning workflow management.',
        ],
    },
]);
</script>

<template>
    <div class="space-y-6">
        <Heading
            title="Usage Guide"
            description="How to use key pages for operations, revenue, and occupancy tracking"
        />

        <section class="grid gap-4 md:grid-cols-3">
            <Card v-for="section in quickLinks" :key="section.title">
                <CardHeader>
                    <CardTitle>{{ section.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol
                        class="list-inside list-decimal space-y-2 text-sm text-muted-foreground"
                    >
                        <li v-for="step in section.steps" :key="step">
                            {{ step }}
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>Filter Tips</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-muted-foreground">
                <p>
                    Use combined filters such as payment status plus date range
                    to quickly narrow high-volume lists.
                </p>
                <p>
                    If a list is empty, clear filters first before assuming
                    records are missing.
                </p>
                <p>
                    Occupancy metrics are based on checked-in bookings, so they
                    remain accurate even if room status was not manually
                    updated.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
