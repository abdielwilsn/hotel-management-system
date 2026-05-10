<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleDollarSign,
    Plus,
    Trash2,
    Edit,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, edit, destroy } from '@/routes/bookings';
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
    invoice?: {
        id: number;
        invoice_number: string;
        total_amount: number;
        paid_amount: number;
        status: string;
    } | null;
};

type Props = {
    bookings: Booking[];
    rooms: RoomOption[];
    statuses: string[];
    paymentStatuses: string[];
    filters: {
        search?: string | null;
        status?: string | null;
        payment_status?: string | null;
        room_id?: number | null;
        check_in_from?: string | null;
        check_in_to?: string | null;
        check_out_from?: string | null;
        check_out_to?: string | null;
    };
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);
const isAdmin = computed(() => {
    const role = currentTeam.value?.role;

    return role === 'owner' || role === 'admin';
});

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Bookings',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const showProcessPaymentDialog = ref(false);
const bookingToDelete = ref<Booking | null>(null);
const bookingToProcessPayment = ref<Booking | null>(null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    payment_status: props.filters.payment_status ?? 'all',
    room_id: props.filters.room_id ? String(props.filters.room_id) : 'all',
    check_in_from: props.filters.check_in_from ?? '',
    check_in_to: props.filters.check_in_to ?? '',
    check_out_from: props.filters.check_out_from ?? '',
    check_out_to: props.filters.check_out_to ?? '',
});

const form = useForm({
    room_id: '',
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    number_of_guests: '1',
    check_in_date: '',
    check_out_date: '',
    status: 'pending',
    notes: '',
    process_payment: false,
    payment_amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    payment_reference: '',
    payment_notes: '',
});

const deleteForm = useForm({});
const processPaymentForm = useForm({
    amount: '',
    method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    status: 'completed',
    reference: '',
    notes: '',
});

const hasActiveFilters = computed(() =>
    Boolean(
        filtersForm.search ||
        filtersForm.status !== 'all' ||
        filtersForm.payment_status !== 'all' ||
        filtersForm.room_id !== 'all' ||
        filtersForm.check_in_from ||
        filtersForm.check_in_to ||
        filtersForm.check_out_from ||
        filtersForm.check_out_to,
    ),
);

const applyFilters = () => {
    router.get(
        index(props.team.slug).url,
        {
            search: filtersForm.search || undefined,
            status:
                filtersForm.status !== 'all' ? filtersForm.status : undefined,
            payment_status:
                filtersForm.payment_status !== 'all'
                    ? filtersForm.payment_status
                    : undefined,
            room_id:
                filtersForm.room_id !== 'all'
                    ? Number(filtersForm.room_id)
                    : undefined,
            check_in_from: filtersForm.check_in_from || undefined,
            check_in_to: filtersForm.check_in_to || undefined,
            check_out_from: filtersForm.check_out_from || undefined,
            check_out_to: filtersForm.check_out_to || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    filtersForm.search = '';
    filtersForm.status = 'all';
    filtersForm.payment_status = 'all';
    filtersForm.room_id = 'all';
    filtersForm.check_in_from = '';
    filtersForm.check_in_to = '';
    filtersForm.check_out_from = '';
    filtersForm.check_out_to = '';
    applyFilters();
};

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

const isReservation = (booking: Booking) =>
    booking.status === 'pending' && bookingBalance(booking) > 0;

const bookingStatusLabel = (booking: Booking) =>
    isReservation(booking) ? 'Reservation' : statusLabel(booking.status);

const bookingStatusColor = (booking: Booking) =>
    isReservation(booking)
        ? 'bg-violet-100 text-violet-800'
        : statusColor(booking.status);

const optionStatusLabel = (status: string) =>
    status === 'pending' ? 'Reservation' : statusLabel(status);

const formatDate = (d: string) =>
    new Date(d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const nights = (checkIn: string, checkOut: string) => {
    const ms = new Date(checkOut).getTime() - new Date(checkIn).getTime();

    return Math.round(ms / (1000 * 60 * 60 * 24));
};

const selectedRoom = computed(() => {
    const roomId = Number(form.room_id);

    return props.rooms.find((r) => r.id === roomId) ?? null;
});

const calculatedNights = computed(() => {
    if (!form.check_in_date || !form.check_out_date) {
        return 0;
    }

    return Math.max(1, nights(form.check_in_date, form.check_out_date));
});

const calculatedTotal = computed(() => {
    if (!selectedRoom.value || !calculatedNights.value) {
        return 0;
    }

    return selectedRoom.value.price_per_night * calculatedNights.value;
});

watch(
    [() => form.process_payment, calculatedTotal],
    ([isProcessing, total]) => {
        if (isProcessing && total > 0) {
            form.payment_amount = total.toFixed(2);
        }
    },
);

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.status = 'pending';
            form.number_of_guests = '1';
            form.process_payment = false;
            form.payment_method = 'cash';
            form.payment_date = new Date().toISOString().split('T')[0];
        },
    });
};

const bookingBalance = (booking: Booking) => {
    const total = Number(
        booking.invoice?.total_amount ?? booking.total_amount ?? 0,
    );
    const paid = Number(booking.invoice?.paid_amount ?? 0);

    return Math.max(0, total - paid);
};

const openProcessPaymentDialog = (booking: Booking) => {
    bookingToProcessPayment.value = booking;
    processPaymentForm.reset();
    processPaymentForm.amount = bookingBalance(booking).toFixed(2);
    processPaymentForm.method = 'cash';
    processPaymentForm.payment_date = new Date().toISOString().split('T')[0];
    processPaymentForm.status = 'completed';
    showProcessPaymentDialog.value = true;
};

const processPayment = () => {
    if (!bookingToProcessPayment.value) {
        return;
    }

    processPaymentForm.post(
        `/${props.team.slug}/bookings/${bookingToProcessPayment.value.id}/process-payment`,
        {
            onSuccess: () => {
                showProcessPaymentDialog.value = false;
                bookingToProcessPayment.value = null;
            },
        },
    );
};

const deleteBooking = () => {
    if (!bookingToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroy([props.team.slug, bookingToDelete.value.id]).url,
        {
            onSuccess: () => {
                showDeleteDialog.value = false;
                bookingToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            icon="CalendarDays"
            title="Bookings"
            description="Manage guest reservations and check-ins"
        />

        <div v-if="!showCreateForm" class="flex justify-end">
            <Button @click="showCreateForm = true" class="gap-2">
                <Plus class="h-4 w-4" />
                New Booking
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filter Bookings</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <Label for="booking_filter_search">Search</Label>
                            <Input
                                id="booking_filter_search"
                                v-model="filtersForm.search"
                                class="mt-1"
                                placeholder="Guest, email, phone, room"
                            />
                        </div>

                        <div>
                            <Label for="booking_filter_status">Status</Label>
                            <Select v-model="filtersForm.status">
                                <SelectTrigger
                                    id="booking_filter_status"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any status</SelectItem
                                    >
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ optionStatusLabel(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="booking_filter_payment_status"
                                >Payment Status</Label
                            >
                            <Select v-model="filtersForm.payment_status">
                                <SelectTrigger
                                    id="booking_filter_payment_status"
                                    class="mt-1"
                                >
                                    <SelectValue
                                        placeholder="Any payment status"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any payment status</SelectItem
                                    >
                                    <SelectItem
                                        v-for="paymentStatus in paymentStatuses"
                                        :key="paymentStatus"
                                        :value="paymentStatus"
                                    >
                                        {{ statusLabel(paymentStatus) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="booking_filter_room">Room</Label>
                            <Select v-model="filtersForm.room_id">
                                <SelectTrigger
                                    id="booking_filter_room"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any room" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any room</SelectItem
                                    >
                                    <SelectItem
                                        v-for="room in rooms"
                                        :key="room.id"
                                        :value="String(room.id)"
                                    >
                                        Room {{ room.room_number }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="booking_filter_check_in_from"
                                >Check-in From</Label
                            >
                            <Input
                                id="booking_filter_check_in_from"
                                v-model="filtersForm.check_in_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="booking_filter_check_in_to"
                                >Check-in To</Label
                            >
                            <Input
                                id="booking_filter_check_in_to"
                                v-model="filtersForm.check_in_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="booking_filter_check_out_from"
                                >Check-out From</Label
                            >
                            <Input
                                id="booking_filter_check_out_from"
                                v-model="filtersForm.check_out_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="booking_filter_check_out_to"
                                >Check-out To</Label
                            >
                            <Input
                                id="booking_filter_check_out_to"
                                v-model="filtersForm.check_out_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button type="submit">Apply Filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="!hasActiveFilters"
                            @click="clearFilters"
                        >
                            Clear
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Create useForm -->
        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>New Booking</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="guest_name">Guest Name *</Label>
                            <Input
                                id="guest_name"
                                v-model="form.guest_name"
                                type="text"
                                class="mt-1"
                                placeholder="Jane Smith"
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
                                placeholder="jane@example.com"
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
                                placeholder="+1234567890"
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

                        <div>
                            <Label for="room_id">Room *</Label>
                            <Select v-model="form.room_id">
                                <SelectTrigger id="room_id" class="mt-1">
                                    <SelectValue placeholder="Select a room" />
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
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="s in statuses"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{ optionStatusLabel(s) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.status"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="check_in_date">Check-in Date *</Label>
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
                            <Label for="check_out_date">Check-out Date *</Label>
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

                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <Label>Booking Total</Label>
                                <div
                                    class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900"
                                >
                                    {{
                                        calculatedNights > 0
                                            ? `₦${calculatedTotal.toFixed(2)} (${calculatedNights} nights)`
                                            : '—'
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label
                                class="flex items-center gap-2 text-sm font-medium text-gray-700"
                            >
                                <input
                                    v-model="form.process_payment"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                />
                                Process payment now
                            </label>
                            <p class="mt-1 text-xs text-gray-500">
                                If unchecked, this is saved as reservation and
                                payment can be processed from the list.
                            </p>
                        </div>

                        <template v-if="form.process_payment">
                            <div>
                                <Label for="payment_amount"
                                    >Payment Amount *</Label
                                >
                                <Input
                                    id="payment_amount"
                                    v-model="form.payment_amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.payment_amount"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <Label for="payment_method"
                                    >Payment Method *</Label
                                >
                                <Select v-model="form.payment_method">
                                    <SelectTrigger
                                        id="payment_method"
                                        class="mt-1"
                                    >
                                        <SelectValue
                                            placeholder="Select method"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash"
                                            >Cash</SelectItem
                                        >
                                        <SelectItem value="card"
                                            >Card</SelectItem
                                        >
                                        <SelectItem value="bank_transfer"
                                            >Bank Transfer</SelectItem
                                        >
                                        <SelectItem value="online"
                                            >Online</SelectItem
                                        >
                                        <SelectItem value="other"
                                            >Other</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.payment_method"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <Label for="payment_date">Payment Date *</Label>
                                <Input
                                    id="payment_date"
                                    v-model="form.payment_date"
                                    type="date"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.payment_date"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <Label for="payment_reference"
                                    >Payment Reference</Label
                                >
                                <Input
                                    id="payment_reference"
                                    v-model="form.payment_reference"
                                    type="text"
                                    class="mt-1"
                                    placeholder="Transaction reference"
                                />
                                <InputError
                                    :message="form.errors.payment_reference"
                                    class="mt-2"
                                />
                            </div>
                        </template>
                    </div>

                    <div>
                        <Label for="notes">Notes</Label>
                        <Input
                            id="notes"
                            v-model="form.notes"
                            type="text"
                            class="mt-1"
                            placeholder="Special requests..."
                        />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div class="flex gap-2 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="hover:bg-hotel-primary/90 bg-black"
                        >
                            {{
                                form.processing
                                    ? 'Creating...'
                                    : 'Create Booking'
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateForm = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Bookings List -->
        <div v-if="bookings.length > 0" class="space-y-3">
            <Card v-for="booking in bookings" :key="booking.id">
                <CardContent class="py-4">
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <!-- Guest Info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">
                                        {{ booking.guest_name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ booking.guest_email }}
                                    </p>
                                </div>
                                <Badge :class="bookingStatusColor(booking)">
                                    {{ bookingStatusLabel(booking) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Room & Dates -->
                        <div
                            class="flex flex-col gap-1 text-sm text-gray-600 md:text-right"
                        >
                            <p>
                                <strong
                                    >Room
                                    {{ booking.room?.room_number }}</strong
                                >
                                · {{ booking.room?.room_type }}
                            </p>
                            <p>
                                {{ formatDate(booking.check_in_date) }} →
                                {{ formatDate(booking.check_out_date) }}
                                ({{
                                    nights(
                                        booking.check_in_date,
                                        booking.check_out_date,
                                    )
                                }}
                                nights)
                            </p>
                            <p class="font-medium text-gray-900">
                                ₦{{ Number(booking.total_amount).toFixed(2) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Invoice:
                                {{
                                    booking.invoice?.invoice_number ??
                                    'Auto on save'
                                }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Balance: ₦{{
                                    bookingBalance(booking).toFixed(2)
                                }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <Link
                                :href="edit([props.team.slug, booking.id]).url"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="gap-2"
                                >
                                    <Edit class="h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Button
                                v-if="bookingBalance(booking) > 0"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                @click="openProcessPaymentDialog(booking)"
                            >
                                <CircleDollarSign class="h-4 w-4" />
                                Process Payment
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!isAdmin"
                                :title="
                                    isAdmin
                                        ? 'Delete booking'
                                        : 'Admin only action'
                                "
                                @click="
                                    bookingToDelete = booking;
                                    showDeleteDialog = true;
                                "
                                class="text-red-600 hover:bg-red-50 hover:text-red-700"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else class="border-dashed">
            <CardContent class="pt-12 pb-12 text-center">
                <CalendarDays class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    {{
                        hasActiveFilters
                            ? 'No bookings match these filters'
                            : 'No bookings yet'
                    }}
                </h3>
                <p class="mb-4 text-gray-600">
                    {{
                        hasActiveFilters
                            ? 'Try adjusting your filter criteria.'
                            : 'Create your first booking to track guest reservations'
                    }}
                </p>
                <Button @click="showCreateForm = true" class="gap-2">
                    <Plus class="h-4 w-4" />
                    New Booking
                </Button>
            </CardContent>
        </Card>

        <!-- Delete Dialog -->
        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Booking?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the booking for
                        <strong>{{ bookingToDelete?.guest_name }}</strong
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

        <Dialog
            :open="showProcessPaymentDialog"
            @update:open="showProcessPaymentDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Process Reservation Payment</DialogTitle>
                    <DialogDescription>
                        Record payment for
                        <strong>{{
                            bookingToProcessPayment?.guest_name
                        }}</strong>
                        and update invoice balance automatically.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="processPayment" class="space-y-3">
                    <div>
                        <Label for="process_amount">Amount *</Label>
                        <Input
                            id="process_amount"
                            v-model="processPaymentForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                        />
                        <InputError
                            :message="processPaymentForm.errors.amount"
                            class="mt-2"
                        />
                    </div>

                    <div>
                        <Label for="process_method">Method *</Label>
                        <Select v-model="processPaymentForm.method">
                            <SelectTrigger id="process_method" class="mt-1">
                                <SelectValue placeholder="Select method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="card">Card</SelectItem>
                                <SelectItem value="bank_transfer"
                                    >Bank Transfer</SelectItem
                                >
                                <SelectItem value="online">Online</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="processPaymentForm.errors.method"
                            class="mt-2"
                        />
                    </div>

                    <div>
                        <Label for="process_payment_date">Payment Date *</Label>
                        <Input
                            id="process_payment_date"
                            v-model="processPaymentForm.payment_date"
                            type="date"
                        />
                        <InputError
                            :message="processPaymentForm.errors.payment_date"
                            class="mt-2"
                        />
                    </div>

                    <div>
                        <Label for="process_reference">Reference</Label>
                        <Input
                            id="process_reference"
                            v-model="processPaymentForm.reference"
                            type="text"
                            placeholder="Transaction reference"
                        />
                        <InputError
                            :message="processPaymentForm.errors.reference"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showProcessPaymentDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="processPaymentForm.processing"
                        >
                            {{
                                processPaymentForm.processing
                                    ? 'Processing...'
                                    : 'Process Payment'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
