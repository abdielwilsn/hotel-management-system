<script setup lang="ts">
import { LogIn, LogOut, CreditCard, AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormatters } from '@/lib/format';

type Booking = {
    id: number;
    room_id?: number;
    guest_name: string;
    check_in_date?: string;
    check_out_date?: string;
    room?: {
        id: number;
        room_number: string;
    };
};

type PaymentBooking = {
    id: number;
    guest_name: string;
    check_in_date: string;
    invoice?: {
        id: number;
        booking_id: number;
        total_amount: number;
        paid_amount: number;
        status: string;
    };
};

type Props = {
    upcomingCheckIns: Booking[];
    pendingCheckOuts: Booking[];
    pendingPayments: PaymentBooking[];
    today: string;
};

const props = defineProps<Props>();

defineEmits<{
    checkIn: [bookingId: number];
    checkOut: [bookingId: number];
    viewPayment: [bookingId: number];
}>();

const checkInCount = computed(() => props.upcomingCheckIns.length);
const checkOutCount = computed(() => props.pendingCheckOuts.length);
const paymentCount = computed(() => props.pendingPayments.length);
const totalTasks = computed(
    () => checkInCount.value + checkOutCount.value + paymentCount.value,
);

const { formatCurrency } = useFormatters();
</script>

<template>
    <div
        v-if="totalTasks > 0"
        class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
        <!-- Upcoming Check-ins -->
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Today's Check-ins</CardTitle
                    >
                    <LogIn class="h-4 w-4 text-blue-600" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ checkInCount }}</div>
                <p class="text-xs text-gray-600">Pending arrivals</p>
                <div v-if="checkInCount > 0" class="mt-3 space-y-2">
                    <div
                        v-for="booking in upcomingCheckIns.slice(0, 2)"
                        :key="booking.id"
                        class="rounded bg-blue-50 p-2 text-xs"
                    >
                        <div class="font-medium">{{ booking.guest_name }}</div>
                        <div class="text-gray-600">
                            Room {{ booking.room?.room_number }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Pending Check-outs -->
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Today's Check-outs</CardTitle
                    >
                    <LogOut class="h-4 w-4 text-amber-600" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ checkOutCount }}</div>
                <p class="text-xs text-gray-600">Pending departures</p>
                <div v-if="checkOutCount > 0" class="mt-3 space-y-2">
                    <div
                        v-for="booking in pendingCheckOuts.slice(0, 2)"
                        :key="booking.id"
                        class="rounded bg-amber-50 p-2 text-xs"
                    >
                        <div class="font-medium">{{ booking.guest_name }}</div>
                        <div class="text-gray-600">
                            Room {{ booking.room?.room_number }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Pending Payments -->
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Pending Payments</CardTitle
                    >
                    <CreditCard class="h-4 w-4 text-green-600" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ paymentCount }}</div>
                <p class="text-xs text-gray-600">Unpaid/partial</p>
                <div v-if="paymentCount > 0" class="mt-3 space-y-2">
                    <div
                        v-for="booking in pendingPayments.slice(0, 2)"
                        :key="booking.id"
                        class="rounded bg-green-50 p-2 text-xs"
                    >
                        <div class="font-medium">{{ booking.guest_name }}</div>
                        <div v-if="booking.invoice" class="text-gray-600">
                            {{
                                formatCurrency(
                                    booking.invoice.total_amount -
                                        booking.invoice.paid_amount,
                                )
                            }}
                            due
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Alert -->
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Active Tasks</CardTitle
                    >
                    <AlertCircle class="h-4 w-4 text-red-600" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ totalTasks }}</div>
                <p class="text-xs text-gray-600">Actions needed today</p>
                <Badge
                    :variant="totalTasks > 3 ? 'destructive' : 'secondary'"
                    class="mt-3"
                >
                    {{ totalTasks > 3 ? 'High Priority' : 'In Progress' }}
                </Badge>
            </CardContent>
        </Card>
    </div>
</template>
