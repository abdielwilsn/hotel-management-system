<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import { FileText, Plus, Edit, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import type {PaginationMeta} from '@/components/Pagination.vue';
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
import { useFormatters } from '@/lib/format';
import { index, store, edit, destroy } from '@/routes/invoices';
import type { Team } from '@/types';

type BookingOption = {
    id: number;
    guest_name: string;
    guest_email: string;
};

type Invoice = {
    id: number;
    booking_id: number | null;
    invoice_number: string;
    guest_name: string;
    guest_email: string;
    issue_date: string;
    due_date: string | null;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    paid_amount: number;
    status: string;
    notes: string | null;
    booking?: { id: number; guest_name: string } | null;
};

type Props = {
    invoices: Invoice[];
    pagination: PaginationMeta;
    bookings: BookingOption[];
    statuses: string[];
    paymentStatuses: string[];
    filters: {
        search?: string | null;
        status?: string | null;
        payment_status?: string | null;
        booking_id?: number | null;
        issue_date_from?: string | null;
        issue_date_to?: string | null;
        due_date_from?: string | null;
        due_date_to?: string | null;
    };
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const invoiceToDelete = ref<Invoice | null>(null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    payment_status: props.filters.payment_status ?? 'all',
    booking_id: props.filters.booking_id
        ? String(props.filters.booking_id)
        : 'all',
    issue_date_from: props.filters.issue_date_from ?? '',
    issue_date_to: props.filters.issue_date_to ?? '',
    due_date_from: props.filters.due_date_from ?? '',
    due_date_to: props.filters.due_date_to ?? '',
});

const form = useForm({
    booking_id: 'none',
    guest_name: '',
    guest_email: '',
    issue_date: '',
    due_date: '',
    subtotal: '0',
    tax_amount: '0',
    discount_amount: '0',
    paid_amount: '0',
    status: 'draft',
    notes: '',
});

const deleteForm = useForm({});

const hasActiveFilters = computed(() =>
    Boolean(
        filtersForm.search ||
        filtersForm.status !== 'all' ||
        filtersForm.payment_status !== 'all' ||
        filtersForm.booking_id !== 'all' ||
        filtersForm.issue_date_from ||
        filtersForm.issue_date_to ||
        filtersForm.due_date_from ||
        filtersForm.due_date_to,
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
            booking_id:
                filtersForm.booking_id !== 'all'
                    ? Number(filtersForm.booking_id)
                    : undefined,
            issue_date_from: filtersForm.issue_date_from || undefined,
            issue_date_to: filtersForm.issue_date_to || undefined,
            due_date_from: filtersForm.due_date_from || undefined,
            due_date_to: filtersForm.due_date_to || undefined,
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
    filtersForm.booking_id = 'all';
    filtersForm.issue_date_from = '';
    filtersForm.issue_date_to = '';
    filtersForm.due_date_from = '';
    filtersForm.due_date_to = '';
    applyFilters();
};

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        issued: 'bg-blue-100 text-blue-800',
        partially_paid: 'bg-amber-100 text-amber-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
        void: 'bg-zinc-100 text-zinc-800',
    };

    return colors[status] ?? 'bg-gray-100 text-gray-800';
};

const statusLabel = (status: string) =>
    status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const { formatCurrency } = useFormatters();

const invoiceAccentClass = (invoice: Invoice) => {
    if (invoice.status === 'paid') {
        return 'from-emerald-500 via-emerald-400 to-teal-400';
    }

    if (invoice.status === 'partially_paid') {
        return 'from-amber-500 via-orange-400 to-yellow-400';
    }

    if (invoice.status === 'overdue') {
        return 'from-rose-500 via-red-400 to-orange-400';
    }

    if (invoice.status === 'void') {
        return 'from-zinc-500 via-slate-400 to-gray-400';
    }

    return 'from-blue-500 via-sky-400 to-cyan-400';
};

const invoiceBalance = (invoice: Invoice) =>
    Math.max(0, Number(invoice.total_amount) - Number(invoice.paid_amount));

const invoicePaymentTone = (invoice: Invoice) => {
    const balance = invoiceBalance(invoice);

    if (balance <= 0) {
        return 'text-emerald-700 bg-emerald-50 ring-emerald-100';
    }

    if (balance < Number(invoice.total_amount)) {
        return 'text-amber-700 bg-amber-50 ring-amber-100';
    }

    return 'text-slate-700 bg-slate-50 ring-slate-200';
};

const invoiceBillingLabel = (invoice: Invoice) =>
    invoice.booking ? `Booking ${invoice.booking.id}` : 'Standalone invoice';

const submit = () => {
    form.transform((data) => ({
        ...data,
        booking_id: data.booking_id === 'none' ? null : data.booking_id,
    })).post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.booking_id = 'none';
            form.status = 'draft';
            form.subtotal = '0';
            form.tax_amount = '0';
            form.discount_amount = '0';
            form.paid_amount = '0';
        },
    });
};

const deleteInvoice = () => {
    if (!invoiceToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroy([props.team.slug, invoiceToDelete.value.id]).url,
        {
            onSuccess: () => {
                showDeleteDialog.value = false;
                invoiceToDelete.value = null;
            },
        },
    );
};

const applyBookingDetails = (bookingId: string) => {
    if (bookingId === 'none') {
        return;
    }

    const selected = props.bookings.find(
        (booking) => String(booking.id) === bookingId,
    );

    if (!selected) {
        return;
    }

    form.guest_name = selected.guest_name;
    form.guest_email = selected.guest_email;
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            title="Invoices"
            description="Generate and track billing for reservations"
        />

        <Card>
            <CardHeader>
                <CardTitle>Filter Invoices</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <Label for="invoice_filter_search">Search</Label>
                            <Input
                                id="invoice_filter_search"
                                v-model="filtersForm.search"
                                class="mt-1"
                                placeholder="Invoice no., guest name, email"
                            />
                        </div>

                        <div>
                            <Label for="invoice_filter_status">Status</Label>
                            <Select v-model="filtersForm.status">
                                <SelectTrigger
                                    id="invoice_filter_status"
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
                                        {{ statusLabel(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="invoice_filter_payment_status"
                                >Payment Status</Label
                            >
                            <Select v-model="filtersForm.payment_status">
                                <SelectTrigger
                                    id="invoice_filter_payment_status"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any payment" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any payment</SelectItem
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
                            <Label for="invoice_filter_booking"
                                >Linked Booking</Label
                            >
                            <Select v-model="filtersForm.booking_id">
                                <SelectTrigger
                                    id="invoice_filter_booking"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any booking" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any booking</SelectItem
                                    >
                                    <SelectItem
                                        v-for="booking in bookings"
                                        :key="booking.id"
                                        :value="String(booking.id)"
                                    >
                                        {{ booking.guest_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="invoice_filter_issue_date_from"
                                >Issue Date From</Label
                            >
                            <Input
                                id="invoice_filter_issue_date_from"
                                v-model="filtersForm.issue_date_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="invoice_filter_issue_date_to"
                                >Issue Date To</Label
                            >
                            <Input
                                id="invoice_filter_issue_date_to"
                                v-model="filtersForm.issue_date_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="invoice_filter_due_date_from"
                                >Due Date From</Label
                            >
                            <Input
                                id="invoice_filter_due_date_from"
                                v-model="filtersForm.due_date_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="invoice_filter_due_date_to"
                                >Due Date To</Label
                            >
                            <Input
                                id="invoice_filter_due_date_to"
                                v-model="filtersForm.due_date_to"
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

        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Create Invoice</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="booking_id">Linked Booking</Label>
                            <Select
                                v-model="form.booking_id"
                                @update:model-value="
                                    (value) =>
                                        applyBookingDetails(String(value))
                                "
                            >
                                <SelectTrigger id="booking_id" class="mt-1">
                                    <SelectValue
                                        placeholder="Select booking (optional)"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >No booking</SelectItem
                                    >
                                    <SelectItem
                                        v-for="booking in bookings"
                                        :key="booking.id"
                                        :value="String(booking.id)"
                                    >
                                        {{ booking.guest_name }} ·
                                        {{ booking.guest_email }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.booking_id"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="guest_name">Guest Name *</Label>
                            <Input
                                id="guest_name"
                                v-model="form.guest_name"
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
                            <Label for="issue_date">Issue Date *</Label>
                            <Input
                                id="issue_date"
                                v-model="form.issue_date"
                                type="date"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.issue_date"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="due_date">Due Date</Label>
                            <Input
                                id="due_date"
                                v-model="form.due_date"
                                type="date"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.due_date"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="subtotal">Subtotal *</Label>
                            <Input
                                id="subtotal"
                                v-model="form.subtotal"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.subtotal"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="tax_amount">Tax *</Label>
                            <Input
                                id="tax_amount"
                                v-model="form.tax_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.tax_amount"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="discount_amount">Discount *</Label>
                            <Input
                                id="discount_amount"
                                v-model="form.discount_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.discount_amount"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="paid_amount">Paid Amount *</Label>
                            <Input
                                id="paid_amount"
                                v-model="form.paid_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.paid_amount"
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
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ statusLabel(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.status"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div>
                        <Label for="notes">Notes</Label>
                        <Input
                            id="notes"
                            v-model="form.notes"
                            class="mt-1"
                            placeholder="Payment terms or remarks"
                        />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-hotel-primary hover:bg-hotel-primary/90"
                        >
                            {{
                                form.processing
                                    ? 'Creating...'
                                    : 'Create Invoice'
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

        <div v-else class="flex justify-end">
            <Button @click="showCreateForm = true" class="gap-2">
                <Plus class="h-4 w-4" />
                New Invoice
            </Button>
        </div>

        <div v-if="invoices.length > 0" class="space-y-4">
            <Card
                v-for="invoice in invoices"
                :key="invoice.id"
                :accent-class="invoiceAccentClass(invoice)"
            >
                <CardContent class="p-0">
                    <div
                        class="grid gap-0 lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.9fr)_auto]"
                    >
                        <div class="space-y-4 p-5 lg:p-6">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="text-lg font-semibold tracking-tight break-words text-gray-900"
                                        >
                                            {{ invoice.invoice_number }}
                                        </h3>
                                        <Badge
                                            :class="statusColor(invoice.status)"
                                        >
                                            {{ statusLabel(invoice.status) }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm break-words text-gray-500"
                                    >
                                        {{ invoice.guest_name }} ·
                                        {{ invoice.guest_email }}
                                    </p>
                                </div>

                                <div
                                    class="max-w-56 truncate rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                                >
                                    {{ invoiceBillingLabel(invoice) }}
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
                                        Issue / Due
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold break-words text-slate-900"
                                    >
                                        {{ invoice.issue_date }}
                                    </p>
                                    <p
                                        class="text-xs break-words text-slate-500"
                                    >
                                        {{ invoice.due_date ?? 'No due date' }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Total
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold break-words text-slate-900"
                                    >
                                        {{
                                            formatCurrency(invoice.total_amount)
                                        }}
                                    </p>
                                    <p
                                        class="text-xs break-words text-slate-500"
                                    >
                                        {{
                                            invoice.booking
                                                ? `Linked to booking ${invoice.booking.id}`
                                                : 'Unlinked invoice'
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl p-3 ring-1"
                                    :class="invoicePaymentTone(invoice)"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Balance
                                    </p>
                                    <p class="mt-1 text-sm font-semibold">
                                        {{
                                            formatCurrency(
                                                invoiceBalance(invoice),
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs">
                                        {{ statusLabel(invoice.status) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    Paid
                                    {{ formatCurrency(invoice.paid_amount) }}
                                </span>
                                <span
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    {{
                                        invoice.booking
                                            ? 'Booking-backed'
                                            : 'Standalone billing'
                                    }}
                                </span>
                                <span
                                    v-if="invoice.notes"
                                    class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                >
                                    Notes added
                                </span>
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 bg-slate-50/70 p-5 lg:border-t-0 lg:border-l lg:p-6"
                        >
                            <div class="space-y-3">
                                <div
                                    class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Payment Progress
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            formatCurrency(invoice.paid_amount)
                                        }}
                                        paid
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            formatCurrency(
                                                invoiceBalance(invoice),
                                            )
                                        }}
                                        remaining
                                    </p>
                                </div>

                                <div
                                    v-if="invoice.notes"
                                    class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                    >
                                        Notes
                                    </p>
                                    <p
                                        class="mt-1 line-clamp-3 text-sm text-slate-600"
                                    >
                                        {{ invoice.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-stretch border-t border-slate-200 p-5 lg:border-t-0 lg:border-l lg:p-6"
                        >
                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <Link
                                    :href="
                                        edit([props.team.slug, invoice.id]).url
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
                                    class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                    @click="
                                        invoiceToDelete = invoice;
                                        showDeleteDialog = true;
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Pagination :pagination="pagination" label="invoices" />
        </div>

        <Card v-else class="border-dashed">
            <CardContent class="pt-12 pb-12 text-center">
                <FileText class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    {{
                        hasActiveFilters
                            ? 'No invoices match these filters'
                            : 'No invoices yet'
                    }}
                </h3>
                <p class="mb-4 text-gray-600">
                    {{
                        hasActiveFilters
                            ? 'Try adjusting your filter criteria.'
                            : 'Create your first invoice to track payments and balances.'
                    }}
                </p>
                <Button @click="showCreateForm = true" class="gap-2">
                    <Plus class="h-4 w-4" />
                    New Invoice
                </Button>
            </CardContent>
        </Card>

        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Invoice?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong>{{ invoiceToDelete?.invoice_number }}</strong
                        >? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        @click="deleteInvoice"
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
