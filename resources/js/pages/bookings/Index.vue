<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import {
    BadgePercent,
    Calendar,
    CalendarClock,
    CalendarDays,
    Check,
    Edit,
    FileText,
    LayoutList,
    Plus,
    Printer,
    Trash2,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BookingCalendar from '@/components/BookingCalendar.vue';
import BookingWizard from '@/components/BookingWizard.vue';
import FilterSheet from '@/components/FilterSheet.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OperationsDashboard from '@/components/OperationsDashboard.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { useFormatters } from '@/lib/format';
import {
    checkout,
    destroy,
    edit,
    extendStay,
    index,
    processPayment as processBookingPayment,
} from '@/routes/bookings';
import {
    approve as approveDiscount,
    reject as rejectDiscount,
    store as storeDiscount,
} from '@/routes/bookings/discounts';
import {
    approve as approveStayAdjustment,
    reject as rejectStayAdjustment,
    store as storeStayAdjustment,
} from '@/routes/bookings/stay-adjustments';
import { receipt as paymentReceipt } from '@/routes/payments';
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
    invoice?: {
        id: number;
        invoice_number: string;
        issue_date: string;
        due_date: string;
        total_amount: number;
        paid_amount: number;
        status: string;
        payments: Array<{
            id: number;
            payment_number: string;
            payment_date: string;
            amount: number;
            method: string;
            status: string;
            reference: string | null;
            createdBy?: { id: number; name: string } | null;
            updatedBy?: { id: number; name: string } | null;
        }>;
    } | null;
    pos_orders?: Array<{
        id: number;
        order_number: string;
        status: string;
        total: number;
        served_by: string | null;
        business_date: string;
        opened_at: string | null;
        outlet: { id: number; name: string } | null;
        items: Array<{
            id: number;
            name: string;
            unit_price: number;
            quantity: number;
            line_total: number;
        }>;
    }>;
    extension_history?: Array<{
        label: string;
    }>;
    active_discount?: {
        id: number;
        type: 'percentage' | 'fixed';
        value: number;
        amount: number;
        reason: string | null;
        status: string;
        requested_by?: { id: number; name: string } | null;
    } | null;
    check_out_at?: string | null;
    chargeable_nights?: number | null;
    nights_basis?: string | null;
    active_stay_adjustment?: {
        id: number;
        computed_nights: number;
        requested_nights: number;
        reason: string | null;
        status: string;
        requested_by?: { id: number; name: string } | null;
    } | null;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_url: string | null;
    next_url: string | null;
};

type Props = {
    bookings: Booking[];
    pagination: Pagination;
    stayPolicy: {
        check_in_time: string;
        check_out_time: string;
        early_check_in_from: string;
    };
    canReviewStayAdjustments: boolean;
    statusLabels: Record<string, string>;
    creatableStatuses: Array<{
        value: string;
        label: string;
        hint: string | null;
    }>;
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
    dashboard: {
        upcomingCheckIns: Array<{
            id: number;
            room_id?: number;
            guest_name: string;
            check_in_date?: string;
            room?: { id: number; room_number: string };
        }>;
        pendingCheckOuts: Array<{
            id: number;
            room_id?: number;
            guest_name: string;
            check_out_date?: string;
            room?: { id: number; room_number: string };
        }>;
        pendingPayments: Array<{
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
        }>;
        calendarBookings: Array<{
            id: number;
            room_id: number;
            guest_name: string;
            check_in_date: string;
            check_out_date: string;
            status: string;
            room?: { id: number; room_number: string; room_type: string };
        }>;
        today: string;
        calendarStart: string;
        calendarEnd: string;
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

const showDeleteDialog = ref(false);
const showProcessPaymentDialog = ref(false);
const showCheckoutDialog = ref(false);
const showExtendStayDialog = ref(false);
const showFolioDialog = ref(false);
const showBookingWizard = ref(false);
const viewMode = ref<'list' | 'calendar'>('list');
const bookingToDelete = ref<Booking | null>(null);
const bookingToProcessPayment = ref<Booking | null>(null);
const bookingToCheckout = ref<Booking | null>(null);
const bookingToExtend = ref<Booking | null>(null);
const bookingToViewFolio = ref<Booking | null>(null);

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

const deleteForm = useForm({});
const processPaymentForm = useForm({
    amount: '',
    method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    status: 'completed',
    reference: '',
    notes: '',
});
const checkoutForm = useForm({
    checked_out_at: '',
    settlement_amount: '',
    settlement_method: 'cash',
    settlement_payment_date: new Date().toISOString().split('T')[0],
    settlement_reference: '',
    settlement_notes: '',
});
const extendStayForm = useForm({
    check_out_date: '',
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

// Advanced filters shown inside the drawer (search stays inline, so it is
// excluded from the badge count).
const activeFilterCount = computed(
    () =>
        (filtersForm.status !== 'all' ? 1 : 0) +
        (filtersForm.payment_status !== 'all' ? 1 : 0) +
        (filtersForm.room_id !== 'all' ? 1 : 0) +
        (filtersForm.check_in_from ? 1 : 0) +
        (filtersForm.check_in_to ? 1 : 0) +
        (filtersForm.check_out_from ? 1 : 0) +
        (filtersForm.check_out_to ? 1 : 0),
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
    props.statusLabels?.[status] ??
    status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const { formatCurrency } = useFormatters();

const paymentMethodLabel = (method: string) => statusLabel(method);

const userLabel = (user?: { name: string } | null) => user?.name ?? 'System';

const isReservation = (booking: Booking) =>
    booking.status === 'pending' && bookingBalance(booking) > 0;

const bookingStatusLabel = (booking: Booking) => statusLabel(booking.status);

const bookingStatusColor = (booking: Booking) =>
    isReservation(booking)
        ? 'bg-violet-100 text-violet-800'
        : statusColor(booking.status);

const bookingPaymentTone = (booking: Booking) => {
    const balance = bookingBalance(booking);

    if (balance <= 0) {
        return 'text-emerald-700 bg-emerald-50 ring-emerald-100';
    }

    if (balance < Number(booking.total_amount)) {
        return 'text-amber-700 bg-amber-50 ring-amber-100';
    }

    return 'text-slate-700 bg-slate-50 ring-slate-200';
};

const bookingMetaLabel = (booking: Booking) =>
    booking.room ? `Room ${booking.room.room_number}` : 'Room not assigned';

const bookingStayLabel = (booking: Booking) =>
    `${formatDate(booking.check_in_date)} → ${formatDate(booking.check_out_date)}`;

/**
 * Whole nights between the departure being recorded and the one booked. The
 * room becomes sellable again for these, but the folio still covers them.
 */
const unusedNights = computed(() => {
    const booking = bookingToCheckout.value;

    if (!booking?.check_out_at || !checkoutForm.checked_out_at) {
        return 0;
    }

    const booked = new Date(booking.check_out_at);
    const actual = new Date(checkoutForm.checked_out_at);

    if (actual >= booked) {
        return 0;
    }

    const startOfDay = (d: Date) =>
        new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();

    return Math.round((startOfDay(booked) - startOfDay(actual)) / 86400000);
});

// Any stay that has not already been closed can be checked out: guests leave
// whether or not anybody remembered to check them in.
const canCheckoutBooking = (booking: Booking) =>
    !['checked_out', 'cancelled'].includes(booking.status);

const canExtendBooking = (booking: Booking) =>
    booking.status === 'confirmed' || booking.status === 'checked_in';

const canRequestDiscount = (booking: Booking) =>
    !['checked_out', 'cancelled'].includes(booking.status);

const discountLabel = (discount: NonNullable<Booking['active_discount']>) =>
    discount.type === 'percentage'
        ? `${Number(discount.value)}%`
        : formatCurrency(discount.amount);

const optionStatusLabel = (status: string) => statusLabel(status);

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

const openCheckoutDialog = (booking: Booking) => {
    bookingToCheckout.value = booking;
    checkoutForm.reset();
    checkoutForm.settlement_amount = bookingBalance(booking).toFixed(2);
    checkoutForm.settlement_method = 'cash';
    checkoutForm.checked_out_at = new Date(
        Date.now() - new Date().getTimezoneOffset() * 60000,
    )
        .toISOString()
        .slice(0, 16);
    checkoutForm.settlement_payment_date = new Date()
        .toISOString()
        .split('T')[0];
    showCheckoutDialog.value = true;
};

const openExtendStayDialog = (booking: Booking) => {
    bookingToExtend.value = booking;
    extendStayForm.reset();
    extendStayForm.check_out_date = booking.check_out_date;
    showExtendStayDialog.value = true;
};

const openFolioDialog = (booking: Booking) => {
    bookingToViewFolio.value = booking;
    showFolioDialog.value = true;
};

const processPayment = () => {
    if (!bookingToProcessPayment.value) {
        return;
    }

    processPaymentForm.post(
        processBookingPayment([
            props.team.slug,
            bookingToProcessPayment.value.id,
        ]).url,
        {
            onSuccess: () => {
                showProcessPaymentDialog.value = false;
                bookingToProcessPayment.value = null;
            },
        },
    );
};

const checkoutBalance = computed(() => {
    if (!bookingToCheckout.value) {
        return 0;
    }

    return bookingBalance(bookingToCheckout.value);
});

const extensionPreview = computed(() => {
    if (!bookingToExtend.value || !extendStayForm.check_out_date) {
        return { extraNights: 0, extraAmount: 0 };
    }

    const currentCheckOut = new Date(bookingToExtend.value.check_out_date);
    const proposedCheckOut = new Date(extendStayForm.check_out_date);
    const diff = proposedCheckOut.getTime() - currentCheckOut.getTime();
    const extraNights = Math.max(0, Math.round(diff / (1000 * 60 * 60 * 24)));
    const extraAmount =
        extraNights * Number(bookingToExtend.value.price_per_night);

    return { extraNights, extraAmount };
});

const folioPayments = computed(
    () => bookingToViewFolio.value?.invoice?.payments ?? [],
);

const folioRoomCharges = computed(
    () => bookingToViewFolio.value?.pos_orders ?? [],
);

const folioExtensionHistory = computed(
    () => bookingToViewFolio.value?.extension_history ?? [],
);

// --- Discounts ---
const showDiscountDialog = ref(false);
const showRejectDiscountDialog = ref(false);
const bookingToDiscount = ref<Booking | null>(null);
const discountToReject = ref<Booking | null>(null);

const discountForm = useForm({
    type: 'percentage',
    value: '',
    reason: '',
});
const approveDiscountForm = useForm({});
const rejectDiscountForm = useForm({ review_notes: '' });

const openDiscountDialog = (booking: Booking) => {
    bookingToDiscount.value = booking;
    discountForm.reset();
    showDiscountDialog.value = true;
};

const submitDiscount = () => {
    if (!bookingToDiscount.value) {
        return;
    }

    discountForm.post(
        storeDiscount([props.team.slug, bookingToDiscount.value.id]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                showDiscountDialog.value = false;
                bookingToDiscount.value = null;
            },
        },
    );
};

/* ------------------------------------------------------------------ *
 * Night count — the desk asks for a different number of nights, a
 * manager decides. Mirrors the discount flow above deliberately.
 * ------------------------------------------------------------------ */
const showStayDialog = ref(false);
const bookingToAdjust = ref<Booking | null>(null);
const stayForm = useForm({ requested_nights: 1, reason: '' });
const stayReviewForm = useForm({ review_notes: '' });

const openStayDialog = (booking: Booking) => {
    bookingToAdjust.value = booking;
    stayForm.reset();
    stayForm.requested_nights = Math.max(
        1,
        (booking.chargeable_nights ?? 2) - 1,
    );
    showStayDialog.value = true;
};

const submitStayAdjustment = () => {
    if (!bookingToAdjust.value) {
        return;
    }

    stayForm.post(
        storeStayAdjustment([props.team.slug, bookingToAdjust.value.id]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                showStayDialog.value = false;
                bookingToAdjust.value = null;
            },
        },
    );
};

const approveBookingStay = (booking: Booking) => {
    if (!booking.active_stay_adjustment) {
        return;
    }

    stayReviewForm.post(
        approveStayAdjustment([
            props.team.slug,
            booking.id,
            booking.active_stay_adjustment.id,
        ]).url,
        { preserveScroll: true },
    );
};

const rejectBookingStay = (booking: Booking) => {
    if (!booking.active_stay_adjustment) {
        return;
    }

    stayReviewForm.post(
        rejectStayAdjustment([
            props.team.slug,
            booking.id,
            booking.active_stay_adjustment.id,
        ]).url,
        { preserveScroll: true },
    );
};

const approveBookingDiscount = (booking: Booking) => {
    if (!booking.active_discount) {
        return;
    }

    approveDiscountForm.post(
        approveDiscount([
            props.team.slug,
            booking.id,
            booking.active_discount.id,
        ]).url,
        { preserveScroll: true },
    );
};

const openRejectDiscountDialog = (booking: Booking) => {
    discountToReject.value = booking;
    rejectDiscountForm.reset();
    showRejectDiscountDialog.value = true;
};

const submitRejectDiscount = () => {
    if (!discountToReject.value?.active_discount) {
        return;
    }

    rejectDiscountForm.post(
        rejectDiscount([
            props.team.slug,
            discountToReject.value.id,
            discountToReject.value.active_discount.id,
        ]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                showRejectDiscountDialog.value = false;
                discountToReject.value = null;
            },
        },
    );
};

const checkoutBooking = () => {
    if (!bookingToCheckout.value) {
        return;
    }

    checkoutForm.post(
        checkout([props.team.slug, bookingToCheckout.value.id]).url,
        {
            onSuccess: () => {
                showCheckoutDialog.value = false;
                bookingToCheckout.value = null;
            },
        },
    );
};

const extendBookingStay = () => {
    if (!bookingToExtend.value) {
        return;
    }

    extendStayForm.post(
        extendStay([props.team.slug, bookingToExtend.value.id]).url,
        {
            onSuccess: () => {
                showExtendStayDialog.value = false;
                bookingToExtend.value = null;
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

        <!-- Operations Dashboard -->
        <OperationsDashboard
            :upcomingCheckIns="dashboard.upcomingCheckIns"
            :pendingCheckOuts="dashboard.pendingCheckOuts"
            :pendingPayments="dashboard.pendingPayments"
            :today="dashboard.today"
        />

        <!-- View Toggle & Create Button -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex gap-2">
                <Button
                    :variant="viewMode === 'list' ? 'default' : 'outline'"
                    @click="viewMode = 'list'"
                    class="flex-1 gap-2 sm:flex-none"
                >
                    <LayoutList class="h-4 w-4" />
                    List View
                </Button>
                <Button
                    :variant="viewMode === 'calendar' ? 'default' : 'outline'"
                    @click="viewMode = 'calendar'"
                    class="flex-1 gap-2 sm:flex-none"
                >
                    <Calendar class="h-4 w-4" />
                    Calendar View
                </Button>
            </div>

            <Button
                @click="showBookingWizard = true"
                class="w-full gap-2 sm:w-auto"
            >
                <Plus class="h-4 w-4" />
                New Booking
            </Button>
        </div>

        <!-- Booking Wizard -->
        <BookingWizard
            :open="showBookingWizard"
            :team-slug="props.team.slug"
            :policy="props.stayPolicy"
            :creatable-statuses="props.creatableStatuses"
            @close="showBookingWizard = false"
            @submit="
                () => {
                    showBookingWizard = false;
                }
            "
        />

        <!-- Calendar View -->
        <BookingCalendar
            v-if="viewMode === 'calendar'"
            :bookings="dashboard.calendarBookings"
            :rooms="rooms"
            :startDate="dashboard.calendarStart"
            :endDate="dashboard.calendarEnd"
            @viewBooking="(id) => {}"
        />

        <!-- Filters (List View Only) -->
        <FilterSheet
            v-if="viewMode === 'list'"
            title="Filter Bookings"
            description="Narrow reservations by status, payment, room, and dates."
            :active-count="activeFilterCount"
            @apply="applyFilters"
            @clear="clearFilters"
        >
            <template #search>
                <Input
                    id="booking_filter_search"
                    v-model="filtersForm.search"
                    placeholder="Search guest, email, phone, room…"
                    aria-label="Search bookings"
                    @keyup.enter="applyFilters"
                />
            </template>

            <div>
                <Label for="booking_filter_status">Status</Label>
                <Select v-model="filtersForm.status">
                    <SelectTrigger id="booking_filter_status" class="mt-1">
                        <SelectValue placeholder="Any status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Any status</SelectItem>
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
                        <SelectValue placeholder="Any payment status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Any payment status</SelectItem>
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
                    <SelectTrigger id="booking_filter_room" class="mt-1">
                        <SelectValue placeholder="Any room" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Any room</SelectItem>
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

            <div class="grid grid-cols-2 gap-3">
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
                    <Label for="booking_filter_check_in_to">Check-in To</Label>
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
        </FilterSheet>

        <!-- Bookings List -->
        <div
            v-if="viewMode === 'list' && bookings.length > 0"
            class="space-y-4"
        >
            <Card
                v-for="booking in bookings"
                :key="booking.id"
                :accent-class="
                    booking.status === 'checked_in'
                        ? 'from-emerald-500 via-emerald-400 to-teal-400'
                        : booking.status === 'confirmed'
                          ? 'from-blue-500 via-sky-400 to-cyan-400'
                          : booking.status === 'cancelled'
                            ? 'from-rose-500 via-red-400 to-orange-400'
                            : 'from-violet-500 via-fuchsia-400 to-pink-400'
                "
            >
                <CardContent class="p-0">
                    <div class="grid gap-0 lg:grid-cols-[1fr_auto]">
                        <div class="space-y-4 p-5 lg:p-6">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="text-lg font-semibold tracking-tight wrap-break-word text-gray-900"
                                        >
                                            {{ booking.guest_name }}
                                        </h3>
                                        <Badge
                                            :class="bookingStatusColor(booking)"
                                        >
                                            {{ bookingStatusLabel(booking) }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm wrap-break-word text-gray-500"
                                    >
                                        {{ booking.guest_email }}
                                        <span v-if="booking.guest_phone">
                                            · {{ booking.guest_phone }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Created by
                                        {{ userLabel(booking.createdBy) }}
                                        <span v-if="booking.updatedBy">
                                            · Last action by
                                            {{ userLabel(booking.updatedBy) }}
                                        </span>
                                    </p>
                                </div>

                                <div
                                    class="max-w-56 truncate rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                                >
                                    {{ bookingMetaLabel(booking) }}
                                </div>
                            </div>

                            <div
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <div
                                    class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Stay
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold wrap-break-word text-slate-900"
                                    >
                                        {{ bookingStayLabel(booking) }}
                                    </p>
                                    <p
                                        class="text-xs wrap-break-word text-slate-500"
                                    >
                                        {{
                                            nights(
                                                booking.check_in_date,
                                                booking.check_out_date,
                                            )
                                        }}
                                        nights
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Billing
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold wrap-break-word text-slate-900"
                                    >
                                        {{
                                            formatCurrency(
                                                Number(booking.total_amount),
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs wrap-break-word text-slate-500"
                                    >
                                        {{
                                            booking.invoice?.invoice_number ??
                                            'Auto on save'
                                        }}
                                    </p>

                                    <!--
                                        Why the guest is being charged this many
                                        nights, in the words the calculator used.
                                    -->
                                    <p
                                        v-if="booking.chargeable_nights"
                                        class="mt-2 text-xs text-slate-600"
                                        :title="booking.nights_basis ?? ''"
                                    >
                                        {{ booking.chargeable_nights }} night{{
                                            booking.chargeable_nights > 1
                                                ? 's'
                                                : ''
                                        }}
                                        billed
                                    </p>

                                    <Badge
                                        v-if="booking.active_stay_adjustment"
                                        class="mt-2 rounded-full bg-amber-100 text-amber-800"
                                    >
                                        Nights pending:
                                        {{
                                            booking.active_stay_adjustment
                                                .computed_nights
                                        }}
                                        →
                                        {{
                                            booking.active_stay_adjustment
                                                .requested_nights
                                        }}
                                    </Badge>

                                    <Badge
                                        v-if="
                                            booking.active_discount?.status ===
                                            'pending'
                                        "
                                        class="mt-2 rounded-full bg-amber-100 text-amber-800"
                                    >
                                        Discount pending:
                                        {{
                                            discountLabel(
                                                booking.active_discount,
                                            )
                                        }}
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            booking.active_discount?.status ===
                                            'approved'
                                        "
                                        class="mt-2 rounded-full bg-green-100 text-green-800"
                                    >
                                        −{{
                                            formatCurrency(
                                                booking.active_discount.amount,
                                            )
                                        }}
                                        discount
                                    </Badge>
                                </div>

                                <div
                                    class="rounded-2xl p-3 ring-1"
                                    :class="bookingPaymentTone(booking)"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Balance
                                    </p>
                                    <p class="mt-1 text-sm font-semibold">
                                        {{
                                            formatCurrency(
                                                bookingBalance(booking),
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs">
                                        {{
                                            statusLabel(
                                                booking.invoice?.status ??
                                                    'issued',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    {{
                                        booking.room?.room_type ??
                                        'Room type unavailable'
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    {{
                                        booking.room
                                            ? `Room ${booking.room.room_number}`
                                            : 'No room assigned'
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    {{ booking.number_of_guests }} guest{{
                                        booking.number_of_guests > 1 ? 's' : ''
                                    }}
                                </span>
                            </div>

                            <div
                                v-if="booking.notes"
                                class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                >
                                    Notes
                                </p>
                                <p
                                    class="mt-1 line-clamp-3 text-sm text-slate-600"
                                >
                                    {{ booking.notes }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-stretch border-t border-slate-200 p-5 lg:border-t-0 lg:border-l lg:p-6"
                        >
                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <Link
                                    :href="
                                        edit([props.team.slug, booking.id]).url
                                    "
                                    class="w-full"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 w-full justify-start gap-2 rounded-xl"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Edit
                                    </Button>
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openFolioDialog(booking)"
                                >
                                    <FileText class="h-4 w-4" />
                                    View Folio
                                </Button>
                                <Button
                                    v-if="canRequestDiscount(booking)"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openDiscountDialog(booking)"
                                >
                                    <BadgePercent class="h-4 w-4" />
                                    {{
                                        booking.active_discount
                                            ? 'Change Discount'
                                            : 'Request Discount'
                                    }}
                                </Button>
                                <Button
                                    v-if="!booking.active_stay_adjustment"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openStayDialog(booking)"
                                >
                                    <CalendarClock class="h-4 w-4" />
                                    Request Different Nights
                                </Button>
                                <template
                                    v-if="
                                        canReviewStayAdjustments &&
                                        booking.active_stay_adjustment
                                    "
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 w-full justify-start gap-2 rounded-xl border-green-200 text-green-700 hover:bg-green-50"
                                        @click="approveBookingStay(booking)"
                                    >
                                        <Check class="h-4 w-4" />
                                        Approve
                                        {{
                                            booking.active_stay_adjustment
                                                .requested_nights
                                        }}
                                        Night(s)
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50"
                                        @click="rejectBookingStay(booking)"
                                    >
                                        <X class="h-4 w-4" />
                                        Reject Nights
                                    </Button>
                                </template>
                                <template
                                    v-if="
                                        isAdmin &&
                                        booking.active_discount?.status ===
                                            'pending'
                                    "
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 w-full justify-start gap-2 rounded-xl border-green-200 text-green-700 hover:bg-green-50"
                                        @click="approveBookingDiscount(booking)"
                                    >
                                        <Check class="h-4 w-4" />
                                        Approve Discount
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50"
                                        @click="
                                            openRejectDiscountDialog(booking)
                                        "
                                    >
                                        <X class="h-4 w-4" />
                                        Reject Discount
                                    </Button>
                                </template>
                                <Button
                                    v-if="bookingBalance(booking) > 0"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openProcessPaymentDialog(booking)"
                                >
                                    <Wallet class="h-4 w-4" />
                                    Process Payment
                                </Button>
                                <Button
                                    v-if="canExtendBooking(booking)"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openExtendStayDialog(booking)"
                                >
                                    Extend Stay
                                </Button>
                                <Button
                                    v-if="canCheckoutBooking(booking)"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 w-full justify-start gap-2 rounded-xl"
                                    @click="openCheckoutDialog(booking)"
                                >
                                    Check Out
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
                                    class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Pagination :pagination="pagination" label="bookings" />
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
                <Button @click="showBookingWizard = true" class="gap-2">
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
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
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

        <!-- Request / change discount -->
        <Dialog
            :open="showDiscountDialog"
            @update:open="showDiscountDialog = $event"
        >
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Request Discount</DialogTitle>
                    <DialogDescription>
                        For
                        <strong>{{ bookingToDiscount?.guest_name }}</strong
                        >.
                        <template v-if="!isAdmin">
                            This will be sent to a manager for approval before
                            it reduces the bill.
                        </template>
                        <template v-else>
                            As a manager, this discount is applied immediately.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitDiscount">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Type *</Label>
                            <Select v-model="discountForm.type">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage">
                                        Percentage (%)
                                    </SelectItem>
                                    <SelectItem value="fixed">
                                        Fixed amount (₦)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="discountForm.errors.type" />
                        </div>
                        <div>
                            <Label>Value *</Label>
                            <Input
                                v-model="discountForm.value"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                                :placeholder="
                                    discountForm.type === 'percentage'
                                        ? 'e.g. 10'
                                        : 'e.g. 5000'
                                "
                            />
                            <InputError :message="discountForm.errors.value" />
                        </div>
                    </div>
                    <div>
                        <Label>Reason</Label>
                        <Input
                            v-model="discountForm.reason"
                            type="text"
                            class="mt-1"
                            placeholder="e.g. Corporate rate, loyalty"
                        />
                        <InputError :message="discountForm.errors.reason" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDiscountDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="discountForm.processing"
                        >
                            {{
                                isAdmin
                                    ? 'Apply discount'
                                    : 'Submit for approval'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reject discount -->
        <Dialog
            :open="showRejectDiscountDialog"
            @update:open="showRejectDiscountDialog = $event"
        >
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Reject Discount?</DialogTitle>
                    <DialogDescription>
                        The guest will continue to owe the full amount.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRejectDiscount">
                    <div>
                        <Label>Reason (optional)</Label>
                        <Input
                            v-model="rejectDiscountForm.review_notes"
                            type="text"
                            class="mt-1"
                            placeholder="Why is it being rejected?"
                        />
                        <InputError
                            :message="rejectDiscountForm.errors.review_notes"
                        />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showRejectDiscountDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="rejectDiscountForm.processing"
                            class="bg-red-600 hover:bg-red-700"
                        >
                            Reject
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showProcessPaymentDialog"
            @update:open="showProcessPaymentDialog = $event"
        >
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
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
                        <p class="mt-2 text-xs text-gray-500">
                            Remaining balance:
                            {{
                                formatCurrency(
                                    bookingToProcessPayment
                                        ? bookingBalance(
                                              bookingToProcessPayment,
                                          )
                                        : 0,
                                )
                            }}
                        </p>
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

        <Dialog :open="showFolioDialog" @update:open="showFolioDialog = $event">
            <DialogContent class="max-h-[90dvh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Guest Folio</DialogTitle>
                    <DialogDescription>
                        Review the stay summary, payment history, and extension
                        history for
                        <strong>{{ bookingToViewFolio?.guest_name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="bookingToViewFolio" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div
                            class="rounded border border-gray-200 bg-gray-50 p-3 text-sm"
                        >
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Stay
                            </p>
                            <p class="mt-1 font-medium text-gray-900">
                                {{
                                    formatDate(bookingToViewFolio.check_in_date)
                                }}
                                to
                                {{
                                    formatDate(
                                        bookingToViewFolio.check_out_date,
                                    )
                                }}
                            </p>
                            <p class="text-gray-600">
                                {{
                                    nights(
                                        bookingToViewFolio.check_in_date,
                                        bookingToViewFolio.check_out_date,
                                    )
                                }}
                                nights in room
                                {{ bookingToViewFolio.room?.room_number }}
                            </p>
                        </div>

                        <div
                            class="rounded border border-gray-200 bg-gray-50 p-3 text-sm"
                        >
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Invoice
                            </p>
                            <p class="mt-1 font-medium text-gray-900">
                                {{
                                    bookingToViewFolio.invoice
                                        ?.invoice_number ?? 'Pending'
                                }}
                            </p>
                            <p class="text-gray-600">
                                Issued
                                {{
                                    bookingToViewFolio.invoice?.issue_date
                                        ? formatDate(
                                              bookingToViewFolio.invoice
                                                  .issue_date,
                                          )
                                        : 'on booking save'
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded border border-gray-200 bg-gray-50 p-3 text-sm"
                        >
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Balance
                            </p>
                            <p class="mt-1 font-medium text-gray-900">
                                {{
                                    formatCurrency(
                                        bookingBalance(bookingToViewFolio),
                                    )
                                }}
                            </p>
                            <p class="text-gray-600">
                                {{
                                    statusLabel(
                                        bookingToViewFolio.invoice?.status ??
                                            'issued',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded border border-gray-200 p-3 text-sm">
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Total Charges
                            </p>
                            <p class="mt-1 font-semibold text-gray-900">
                                {{
                                    formatCurrency(
                                        Number(
                                            bookingToViewFolio.invoice
                                                ?.total_amount ??
                                                bookingToViewFolio.total_amount,
                                        ),
                                    )
                                }}
                            </p>
                        </div>

                        <div class="rounded border border-gray-200 p-3 text-sm">
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Paid So Far
                            </p>
                            <p class="mt-1 font-semibold text-gray-900">
                                {{
                                    formatCurrency(
                                        Number(
                                            bookingToViewFolio.invoice
                                                ?.paid_amount ?? 0,
                                        ),
                                    )
                                }}
                            </p>
                        </div>

                        <div class="rounded border border-gray-200 p-3 text-sm">
                            <p
                                class="text-xs tracking-wide text-gray-500 uppercase"
                            >
                                Nightly Rate
                            </p>
                            <p class="mt-1 font-semibold text-gray-900">
                                {{
                                    formatCurrency(
                                        Number(
                                            bookingToViewFolio.price_per_night,
                                        ),
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-[1.5fr_1fr]"
                    >
                        <div class="space-y-4">
                            <div class="rounded border border-gray-200">
                                <div class="border-b border-gray-200 px-4 py-3">
                                    <h3
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        Room Charges
                                    </h3>
                                </div>

                                <div
                                    v-if="folioRoomCharges.length > 0"
                                    class="divide-y divide-gray-200"
                                >
                                    <div
                                        v-for="order in folioRoomCharges"
                                        :key="order.id"
                                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                    >
                                        <div>
                                            <p
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    order.outlet?.name ??
                                                    'Outlet'
                                                }}
                                                ·
                                                {{ order.order_number }}
                                            </p>
                                            <p class="text-gray-500">
                                                {{
                                                    formatDate(
                                                        order.business_date,
                                                    )
                                                }}
                                                <span v-if="order.served_by">
                                                    · Served by
                                                    {{ order.served_by }}
                                                </span>
                                            </p>
                                            <p
                                                v-for="item in order.items"
                                                :key="item.id"
                                                class="text-xs text-gray-500"
                                            >
                                                {{ item.quantity }} ×
                                                {{ item.name }} —
                                                {{
                                                    formatCurrency(
                                                        Number(item.line_total),
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div class="text-right">
                                            <p
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    formatCurrency(
                                                        Number(order.total),
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ statusLabel(order.status) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="px-4 py-6 text-sm text-gray-500"
                                >
                                    No room charges have been posted from POS
                                    outlets for this stay.
                                </div>
                            </div>

                            <div class="rounded border border-gray-200">
                                <div class="border-b border-gray-200 px-4 py-3">
                                    <h3
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        Payment History
                                    </h3>
                                </div>

                                <div
                                    v-if="folioPayments.length > 0"
                                    class="divide-y divide-gray-200"
                                >
                                    <div
                                        v-for="payment in folioPayments"
                                        :key="payment.id"
                                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                    >
                                        <div>
                                            <p
                                                class="font-medium text-gray-900"
                                            >
                                                {{ payment.payment_number }}
                                            </p>
                                            <p class="text-gray-500">
                                                {{
                                                    formatDate(
                                                        payment.payment_date,
                                                    )
                                                }}
                                                ·
                                                {{
                                                    paymentMethodLabel(
                                                        payment.method,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="payment.reference"
                                                class="text-xs text-gray-500"
                                            >
                                                Ref: {{ payment.reference }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                Created by
                                                {{
                                                    userLabel(payment.createdBy)
                                                }}
                                                <span v-if="payment.updatedBy">
                                                    · Last action by
                                                    {{
                                                        userLabel(
                                                            payment.updatedBy,
                                                        )
                                                    }}
                                                </span>
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <div class="text-right">
                                                <p
                                                    class="font-medium text-gray-900"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            Number(
                                                                payment.amount,
                                                            ),
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{
                                                        statusLabel(
                                                            payment.status,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <Link
                                                :href="
                                                    paymentReceipt([
                                                        props.team.slug,
                                                        payment.id,
                                                    ]).url
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                            >
                                                <Printer class="h-3.5 w-3.5" />
                                                Reprint
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="px-4 py-6 text-sm text-gray-500"
                                >
                                    No payments have been recorded for this stay
                                    yet.
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded border border-gray-200 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Extension History
                                </h3>
                                <div
                                    v-if="folioExtensionHistory.length > 0"
                                    class="mt-3 space-y-2 text-sm text-gray-600"
                                >
                                    <div
                                        v-for="(
                                            entry, index
                                        ) in folioExtensionHistory"
                                        :key="`${entry.label}-${index}`"
                                        class="rounded bg-gray-50 px-3 py-2"
                                    >
                                        {{ entry.label }}
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-gray-500">
                                    No stay extensions have been recorded for
                                    this booking.
                                </p>
                            </div>

                            <div class="rounded border border-gray-200 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Front Desk Notes
                                </h3>
                                <p class="mt-3 text-sm text-gray-600">
                                    {{
                                        bookingToViewFolio.notes ||
                                        'No notes saved for this booking.'
                                    }}
                                </p>
                            </div>

                            <div class="rounded border border-gray-200 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    Booking Audit
                                </h3>
                                <div
                                    class="mt-3 space-y-1 text-sm text-gray-600"
                                >
                                    <p>
                                        Created by
                                        {{
                                            userLabel(
                                                bookingToViewFolio.createdBy,
                                            )
                                        }}
                                    </p>
                                    <p>
                                        Last action by
                                        {{
                                            userLabel(
                                                bookingToViewFolio.updatedBy ??
                                                    bookingToViewFolio.createdBy,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showFolioDialog = false"
                    >
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showCheckoutDialog"
            @update:open="showCheckoutDialog = $event"
        >
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Check Out Guest</DialogTitle>
                    <DialogDescription>
                        Close the stay for
                        <strong>{{ bookingToCheckout?.guest_name }}</strong>
                        and settle any balance that is still outstanding.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="checkoutBooking" class="space-y-3">
                    <div>
                        <Label for="checked_out_at">Departed At</Label>
                        <Input
                            id="checked_out_at"
                            v-model="checkoutForm.checked_out_at"
                            type="datetime-local"
                        />
                        <InputError
                            :message="checkoutForm.errors.checked_out_at"
                            class="mt-2"
                        />
                        <p class="mt-2 text-xs text-gray-500">
                            Defaults to now. Back-date it if the guest left
                            before anyone noticed.
                        </p>
                    </div>

                    <div
                        v-if="unusedNights > 0"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        <p class="font-medium">
                            Leaving {{ unusedNights }} night{{
                                unusedNights > 1 ? 's' : ''
                            }}
                            early
                        </p>
                        <p class="mt-1">
                            The room frees up straight away. The bill still
                            covers the whole stay — use
                            <strong>Request Different Nights</strong> if those
                            nights should be waived.
                        </p>
                    </div>

                    <div>
                        <Label for="checkout_amount">Remaining Balance</Label>
                        <Input
                            id="checkout_amount"
                            v-model="checkoutForm.settlement_amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :disabled="checkoutBalance <= 0"
                        />
                        <InputError
                            :message="checkoutForm.errors.settlement_amount"
                            class="mt-2"
                        />
                        <p class="mt-2 text-xs text-gray-500">
                            Outstanding now:
                            {{ formatCurrency(checkoutBalance) }}
                        </p>
                    </div>

                    <div
                        v-if="checkoutBalance > 0"
                        class="grid grid-cols-1 gap-3 md:grid-cols-2"
                    >
                        <div>
                            <Label for="checkout_method"
                                >Payment Method *</Label
                            >
                            <Select v-model="checkoutForm.settlement_method">
                                <SelectTrigger
                                    id="checkout_method"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Select method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="card">Card</SelectItem>
                                    <SelectItem value="bank_transfer"
                                        >Bank Transfer</SelectItem
                                    >
                                    <SelectItem value="online"
                                        >Online</SelectItem
                                    >
                                    <SelectItem value="other">Other</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="checkoutForm.errors.settlement_method"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="checkout_payment_date"
                                >Payment Date *</Label
                            >
                            <Input
                                id="checkout_payment_date"
                                v-model="checkoutForm.settlement_payment_date"
                                type="date"
                                class="mt-1"
                            />
                            <InputError
                                :message="
                                    checkoutForm.errors.settlement_payment_date
                                "
                                class="mt-2"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <Label for="checkout_reference">Reference</Label>
                            <Input
                                id="checkout_reference"
                                v-model="checkoutForm.settlement_reference"
                                type="text"
                                class="mt-1"
                                placeholder="Transaction reference"
                            />
                            <InputError
                                :message="
                                    checkoutForm.errors.settlement_reference
                                "
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCheckoutDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="checkoutForm.processing"
                        >
                            {{
                                checkoutForm.processing
                                    ? 'Checking Out...'
                                    : 'Confirm Checkout'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showExtendStayDialog"
            @update:open="showExtendStayDialog = $event"
        >
            <DialogContent class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Extend Stay</DialogTitle>
                    <DialogDescription>
                        Update the departure date for
                        <strong>{{ bookingToExtend?.guest_name }}</strong>
                        and add the extension amount to the invoice.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="extendBookingStay" class="space-y-3">
                    <div>
                        <Label for="extend_check_out_date"
                            >New Check-out Date *</Label
                        >
                        <Input
                            id="extend_check_out_date"
                            v-model="extendStayForm.check_out_date"
                            type="date"
                        />
                        <InputError
                            :message="extendStayForm.errors.check_out_date"
                            class="mt-2"
                        />
                    </div>

                    <div
                        class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
                    >
                        <p>
                            Additional nights:
                            {{ extensionPreview.extraNights }}
                        </p>
                        <p>
                            Additional amount:
                            {{ formatCurrency(extensionPreview.extraAmount) }}
                        </p>
                    </div>

                    <div>
                        <Label for="extend_notes">Extension Notes</Label>
                        <Input
                            id="extend_notes"
                            v-model="extendStayForm.notes"
                            type="text"
                            placeholder="Reason for the extension"
                        />
                        <InputError
                            :message="extendStayForm.errors.notes"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showExtendStayDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="extendStayForm.processing"
                        >
                            {{
                                extendStayForm.processing
                                    ? 'Saving...'
                                    : 'Extend Stay'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>

    <!-- Ask for a different number of nights -->
    <Dialog v-model:open="showStayDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Request a different night count</DialogTitle>
                <DialogDescription>
                    {{
                        bookingToAdjust?.nights_basis ??
                        'The policy worked this stay out from the arrival and departure times.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label for="requested_nights">Nights to charge</Label>
                    <Input
                        id="requested_nights"
                        v-model="stayForm.requested_nights"
                        type="number"
                        min="1"
                    />
                    <InputError :message="stayForm.errors.requested_nights" />
                </div>

                <div class="grid gap-2">
                    <Label for="stay_reason">Reason</Label>
                    <Input
                        id="stay_reason"
                        v-model="stayForm.reason"
                        placeholder="Guest arrived early, agreed one night"
                    />
                    <InputError :message="stayForm.errors.reason" />
                </div>

                <p
                    v-if="!canReviewStayAdjustments"
                    class="text-xs text-muted-foreground"
                >
                    This goes to a manager for approval. The bill does not
                    change until they agree.
                </p>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="showStayDialog = false">
                    Cancel
                </Button>
                <Button
                    :disabled="stayForm.processing"
                    @click="submitStayAdjustment"
                >
                    {{
                        canReviewStayAdjustments ? 'Apply' : 'Send for approval'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
