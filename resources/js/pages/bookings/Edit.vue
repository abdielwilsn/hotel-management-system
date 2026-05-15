<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, Trash2, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, update, destroy } from '@/routes/bookings';
import type { Team } from '@/types';

type RoomOption = {
    id: number;
    room_number: string;
    room_type: string;
    capacity: number;
    price_per_night: number;
};

type Booking = {
    id: number;
    guest_name: string;
    guest_email: string;
    guest_phone: string | null;
    number_of_guests: number;
    check_in_date: string;
    check_out_date: string;
    price_per_night: number;
    total_amount: number;
    status: string;
    notes: string | null;
    room: RoomOption | null;
    createdBy?: { id: number; name: string } | null;
    updatedBy?: { id: number; name: string } | null;
};

type Props = {
    booking: Booking;
    rooms: RoomOption[];
    statuses: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Bookings',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.booking?.guest_name,
                href: '#',
            },
        ],
    }),
});

const showDeleteDialog = ref(false);

const form = useForm({
    room_id: String(props.booking.room_id ?? props.booking.room?.id ?? ''),
    guest_name: props.booking.guest_name,
    guest_email: props.booking.guest_email,
    guest_phone: props.booking.guest_phone || '',
    number_of_guests: String(props.booking.number_of_guests),
    check_in_date: props.booking.check_in_date,
    check_out_date: props.booking.check_out_date,
    status: props.booking.status,
    notes: props.booking.notes || '',
});

const deleteForm = useForm({});

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        checked_in: 'bg-green-100 text-green-800',
        checked_out: 'bg-gray-100 text-gray-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const statusLabel = (status: string) =>
    status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const userLabel = (user?: { name: string } | null) => user?.name ?? 'System';

const nights = (checkIn: string, checkOut: string) => {
    const ms = new Date(checkOut).getTime() - new Date(checkIn).getTime();
    return Math.round(ms / (1000 * 60 * 60 * 24));
};

const selectedRoom = computed(() => {
    const roomId = Number(form.room_id);
    return props.rooms.find((r) => r.id === roomId) ?? null;
});

const calculatedNights = computed(() => {
    if (!form.check_in_date || !form.check_out_date) return 0;
    return Math.max(1, nights(form.check_in_date, form.check_out_date));
});

const calculatedTotal = computed(() => {
    if (!selectedRoom.value || !calculatedNights.value) return 0;
    return selectedRoom.value.price_per_night * calculatedNights.value;
});

const submit = () => {
    form.patch(update([props.team.slug, props.booking.id]).url);
};

const deleteBooking = () => {
    deleteForm.delete(destroy([props.team.slug, props.booking.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Back Link -->
        <Link
            :href="index(props.team.slug).url"
            class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900"
        >
            <ChevronLeft class="h-4 w-4" />
            Back to Bookings
        </Link>

        <!-- Status Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ booking.guest_name }}
                </h1>
                <p class="mt-1 text-gray-600">{{ booking.guest_email }}</p>
                <p class="mt-1 text-sm text-gray-500">
                    Created by {{ userLabel(booking.createdBy) }}
                    <span v-if="booking.updatedBy">
                        · Last action by {{ userLabel(booking.updatedBy) }}
                    </span>
                </p>
            </div>
            <Badge :class="statusColor(booking.status)">{{
                statusLabel(booking.status)
            }}</Badge>
        </div>

        <!-- Edit useForm -->
        <Card>
            <CardHeader>
                <CardTitle>Edit Booking</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Guest Details -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Guest Details
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="guest_name">Guest Name *</Label>
                                <Input
                                    id="guest_name"
                                    v-model="form.guest_name"
                                    type="text"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.guest_name"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="guest_email">Guest Email *</Label>
                                <Input
                                    id="guest_email"
                                    v-model="form.guest_email"
                                    type="email"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.guest_email"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="guest_phone">Guest Phone</Label>
                                <Input
                                    id="guest_phone"
                                    v-model="form.guest_phone"
                                    type="text"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.guest_phone"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="number_of_guests">Guests *</Label>
                                <Input
                                    id="number_of_guests"
                                    v-model="form.number_of_guests"
                                    type="number"
                                    class="mt-1"
                                    min="1"
                                />
                                <InputError
                                    :message="form.errors.number_of_guests"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Reservation Details -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Reservation Details
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="room_id">Room *</Label>
                                <Select v-model="form.room_id">
                                    <SelectTrigger id="room_id" class="mt-1">
                                        <SelectValue
                                            placeholder="Select a room"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="room in rooms"
                                            :key="room.id"
                                            :value="String(room.id)"
                                        >
                                            Room {{ room.room_number }} —
                                            {{ room.room_type }} · ₦{{
                                                room.price_per_night
                                            }}/night
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.room_id"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="status">Status *</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="status" class="mt-1">
                                        <SelectValue
                                            placeholder="Select status"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="s in statuses"
                                            :key="s"
                                            :value="s"
                                        >
                                            {{ statusLabel(s) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.status"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="check_in_date"
                                    >Check-in Date *</Label
                                >
                                <Input
                                    id="check_in_date"
                                    v-model="form.check_in_date"
                                    type="date"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.check_in_date"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="check_out_date"
                                    >Check-out Date *</Label
                                >
                                <Input
                                    id="check_out_date"
                                    v-model="form.check_out_date"
                                    type="date"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.check_out_date"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Additional Notes
                        </h3>
                        <div>
                            <Label for="notes">Notes</Label>
                            <Input
                                id="notes"
                                v-model="form.notes"
                                type="text"
                                class="mt-1"
                                placeholder="Special requests..."
                            />
                            <InputError
                                :message="form.errors.notes"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <!-- Pricing Summary -->
                    <div class="rounded-lg bg-gray-50 p-4">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">
                            Pricing Summary
                        </h3>
                        <p v-if="selectedRoom" class="text-sm text-gray-600">
                            ₦{{
                                Number(selectedRoom.price_per_night).toFixed(2)
                            }}
                            / night
                            <span v-if="calculatedNights > 0">
                                · {{ calculatedNights }} nights · Total:
                                <strong
                                    >₦{{ calculatedTotal.toFixed(2) }}</strong
                                >
                            </span>
                        </p>
                        <p v-else class="text-sm text-gray-500">
                            Select a room and dates to calculate total
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Total is recalculated based on selected room and
                            dates.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-hotel-primary hover:bg-hotel-primary/90 gap-2"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                        <Link
                            :href="index(props.team.slug).url"
                            class="inline-flex"
                        >
                            <Button type="button" variant="outline"
                                >Cancel</Button
                            >
                        </Link>
                        <Button
                            type="button"
                            @click="showDeleteDialog = true"
                            class="ml-auto gap-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                            variant="ghost"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete Booking
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Delete Dialog -->
        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Booking?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the booking for
                        <strong>{{ booking.guest_name }}</strong
                        >? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        @click="deleteBooking"
                        :disabled="deleteForm.processing"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
