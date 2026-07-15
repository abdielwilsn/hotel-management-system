<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Booking = {
    id: number;
    room_id: number;
    guest_name: string;
    check_in_date: string;
    check_out_date: string;
    status: string;
    room?: {
        id: number;
        room_number: string;
        room_type: string;
    };
};

type Props = {
    bookings: Booking[];
    rooms: Array<{
        id: number;
        room_number: string;
        room_type: string;
        capacity: number;
        price_per_night: number;
    }>;
    startDate: string;
    endDate: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    viewBooking: [bookingId: number];
}>();

const dayRange = computed(() => {
    const start = new Date(props.startDate);
    const end = new Date(props.endDate);
    const days: Date[] = [];

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        days.push(new Date(d));
    }

    return days;
});

const roomsWithBookings = computed(() => {
    return (props.rooms || []).map((room) => {
        const roomBookings = (props.bookings || []).filter(
            (b) => b.room_id === room.id,
        );

        return { ...room, bookings: roomBookings };
    });
});

const isBookingOnDay = (booking: Booking, date: Date) => {
    const checkIn = new Date(booking.check_in_date);
    const checkOut = new Date(booking.check_out_date);

    return date >= checkIn && date < checkOut;
};

const getBookingForDay = (roomId: number, date: Date) => {
    return roomsWithBookings.value
        .find((r) => r.id === roomId)
        ?.bookings.find((b) => isBookingOnDay(b, date));
};

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800 border-yellow-300',
        confirmed: 'bg-blue-100 text-blue-800 border-blue-300',
        checked_in: 'bg-green-100 text-green-800 border-green-300',
        checked_out: 'bg-gray-100 text-gray-800 border-gray-300',
        cancelled: 'bg-red-100 text-red-800 border-red-300',
    };

    return colors[status] || 'bg-gray-100 text-gray-800';
};

const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatDayName = (date: Date) => {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
};
</script>

<template>
    <Card class="mb-6">
        <CardHeader>
            <CardTitle>14-Day Occupancy Calendar</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="w-20 px-2 py-2 text-left font-semibold">
                                Room
                            </th>
                            <th
                                v-for="date in dayRange"
                                :key="date.toISOString()"
                                class="px-1 py-2 text-center text-xs"
                            >
                                <div class="font-semibold">
                                    {{ formatDayName(date) }}
                                </div>
                                <div class="text-gray-600">
                                    {{ formatDate(date) }}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="room in roomsWithBookings"
                            :key="room.id"
                            class="border-b hover:bg-gray-50"
                        >
                            <td
                                class="bg-gray-50 px-2 py-3 font-medium text-gray-700"
                            >
                                {{ room.room_number }}
                                <div class="text-xs text-gray-500">
                                    {{ room.room_type }}
                                </div>
                            </td>
                            <td
                                v-for="date in dayRange"
                                :key="date.toISOString()"
                                class="px-1 py-3 text-center text-xs"
                            >
                                <template
                                    v-if="
                                        getBookingForDay(room.id, date) as
                                            | Booking
                                            | undefined as any
                                    "
                                >
                                    <button
                                        class="w-full cursor-pointer rounded border px-1 py-1 font-medium transition hover:shadow-md"
                                        :class="
                                            statusColor(
                                                getBookingForDay(room.id, date)!
                                                    .status,
                                            )
                                        "
                                        @click="
                                            emit(
                                                'viewBooking',
                                                getBookingForDay(room.id, date)!
                                                    .id,
                                            )
                                        "
                                    >
                                        {{
                                            getBookingForDay(
                                                room.id,
                                                date,
                                            )!.guest_name.split(' ')[0]
                                        }}
                                    </button>
                                </template>
                                <template v-else>
                                    <div
                                        class="w-full rounded border border-green-200 bg-green-50 px-1 py-1 font-medium text-green-600"
                                    >
                                        ✓
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex gap-2 text-xs">
                <div class="flex items-center gap-1">
                    <div
                        class="h-4 w-4 rounded border border-yellow-300 bg-yellow-100"
                    ></div>
                    <span>Pending</span>
                </div>
                <div class="flex items-center gap-1">
                    <div
                        class="h-4 w-4 rounded border border-blue-300 bg-blue-100"
                    ></div>
                    <span>Confirmed</span>
                </div>
                <div class="flex items-center gap-1">
                    <div
                        class="h-4 w-4 rounded border border-green-300 bg-green-100"
                    ></div>
                    <span>Checked In</span>
                </div>
                <div class="flex items-center gap-1">
                    <div
                        class="h-4 w-4 rounded border border-green-200 bg-green-50"
                    ></div>
                    <span>Available</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
