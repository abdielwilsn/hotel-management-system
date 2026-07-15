<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import { CreditCard, Edit, Plus, Printer, Trash2 } from 'lucide-vue-next';
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
import { destroy, edit, index, receipt, store } from '@/routes/payments';
import type { Team } from '@/types';

type InvoiceOption = {
    id: number;
    invoice_number: string;
    guest_name: string;
    total_amount: number;
    paid_amount: number;
    status: string;
};

type Payment = {
    id: number;
    invoice_id: number;
    payment_number: string;
    payment_date: string;
    amount: number;
    method: string;
    status: string;
    reference: string | null;
    notes: string | null;
    createdBy?: { id: number; name: string } | null;
    updatedBy?: { id: number; name: string } | null;
    invoice?: {
        id: number;
        invoice_number: string;
        guest_name: string;
        total_amount: number;
        paid_amount: number;
    } | null;
};

type Props = {
    payments: Payment[];
    pagination: PaginationMeta;
    invoices: InvoiceOption[];
    methods: string[];
    statuses: string[];
    filters: {
        search?: string | null;
        status?: string | null;
        method?: string | null;
        invoice_id?: number | null;
        payment_date_from?: string | null;
        payment_date_to?: string | null;
        amount_min?: number | null;
        amount_max?: number | null;
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
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Payments',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const paymentToDelete = ref<Payment | null>(null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    method: props.filters.method ?? 'all',
    invoice_id: props.filters.invoice_id
        ? String(props.filters.invoice_id)
        : 'all',
    payment_date_from: props.filters.payment_date_from ?? '',
    payment_date_to: props.filters.payment_date_to ?? '',
    amount_min:
        props.filters.amount_min !== null &&
        props.filters.amount_min !== undefined
            ? String(props.filters.amount_min)
            : '',
    amount_max:
        props.filters.amount_max !== null &&
        props.filters.amount_max !== undefined
            ? String(props.filters.amount_max)
            : '',
});

const form = useForm({
    invoice_id: '',
    payment_date: '',
    amount: '0',
    method: 'cash',
    status: 'completed',
    reference: '',
    notes: '',
});

const deleteForm = useForm({});

const hasActiveFilters = computed(() =>
    Boolean(
        filtersForm.search ||
        filtersForm.status !== 'all' ||
        filtersForm.method !== 'all' ||
        filtersForm.invoice_id !== 'all' ||
        filtersForm.payment_date_from ||
        filtersForm.payment_date_to ||
        filtersForm.amount_min ||
        filtersForm.amount_max,
    ),
);

const applyFilters = () => {
    router.get(
        index(props.team.slug).url,
        {
            search: filtersForm.search || undefined,
            status:
                filtersForm.status !== 'all' ? filtersForm.status : undefined,
            method:
                filtersForm.method !== 'all' ? filtersForm.method : undefined,
            invoice_id:
                filtersForm.invoice_id !== 'all'
                    ? Number(filtersForm.invoice_id)
                    : undefined,
            payment_date_from: filtersForm.payment_date_from || undefined,
            payment_date_to: filtersForm.payment_date_to || undefined,
            amount_min: filtersForm.amount_min
                ? Number(filtersForm.amount_min)
                : undefined,
            amount_max: filtersForm.amount_max
                ? Number(filtersForm.amount_max)
                : undefined,
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
    filtersForm.method = 'all';
    filtersForm.invoice_id = 'all';
    filtersForm.payment_date_from = '';
    filtersForm.payment_date_to = '';
    filtersForm.amount_min = '';
    filtersForm.amount_max = '';
    applyFilters();
};

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
        refunded: 'bg-slate-100 text-slate-800',
    };

    return colors[status] ?? 'bg-gray-100 text-gray-800';
};

const statusLabel = (status: string) =>
    status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const methodLabel = (method: string) =>
    method.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const { formatCurrency } = useFormatters();

const paymentAccentClass = (payment: Payment) => {
    if (payment.status === 'completed') {
        return 'from-emerald-500 via-emerald-400 to-teal-400';
    }

    if (payment.status === 'pending') {
        return 'from-amber-500 via-orange-400 to-yellow-400';
    }

    if (payment.status === 'failed') {
        return 'from-rose-500 via-red-400 to-orange-400';
    }

    if (payment.status === 'refunded') {
        return 'from-slate-500 via-gray-400 to-zinc-400';
    }

    return 'from-blue-500 via-sky-400 to-cyan-400';
};

const paymentBalance = (payment: Payment) =>
    Math.max(
        0,
        Number(payment.invoice?.total_amount ?? 0) -
            Number(payment.invoice?.paid_amount ?? 0),
    );

const paymentToneClass = (payment: Payment) => {
    const balance = paymentBalance(payment);

    if (balance <= 0 || payment.status === 'completed') {
        return 'text-emerald-700 bg-emerald-50 ring-emerald-100';
    }

    if (payment.status === 'pending') {
        return 'text-amber-700 bg-amber-50 ring-amber-100';
    }

    return 'text-slate-700 bg-slate-50 ring-slate-200';
};

const paymentSourceLabel = (payment: Payment) =>
    payment.invoice?.invoice_number ?? 'Standalone payment';

const paymentGuestLabel = (payment: Payment) =>
    payment.invoice?.guest_name ?? 'Guest';

const userLabel = (user?: { name: string } | null) => user?.name ?? 'System';

const selectedInvoice = computed(() =>
    props.invoices.find((invoice) => String(invoice.id) === form.invoice_id),
);

const selectedInvoiceBalance = computed(() => {
    if (!selectedInvoice.value) {
        return 0;
    }

    return Math.max(
        Number(selectedInvoice.value.total_amount) -
            Number(selectedInvoice.value.paid_amount),
        0,
    );
});

const fillFullBalance = () => {
    form.amount = selectedInvoiceBalance.value.toFixed(2);
    form.status = 'completed';
};

const applyInvoiceDefaults = (invoiceId: string) => {
    const invoice = props.invoices.find(
        (item) => String(item.id) === invoiceId,
    );

    if (!invoice) {
        return;
    }

    const balance = Math.max(
        Number(invoice.total_amount) - Number(invoice.paid_amount),
        0,
    );

    form.amount = balance.toFixed(2);
    form.status = 'completed';
};

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.method = 'cash';
            form.status = 'completed';
            form.amount = '0';
        },
    });
};

const deletePayment = () => {
    if (!paymentToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroy([props.team.slug, paymentToDelete.value.id]).url,
        {
            onSuccess: () => {
                showDeleteDialog.value = false;
                paymentToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            title="Payments"
            description="Track invoice payments and update balances automatically"
        />

        <Card>
            <CardHeader>
                <CardTitle>Filter Payments</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <Label for="payment_filter_search">Search</Label>
                            <Input
                                id="payment_filter_search"
                                v-model="filtersForm.search"
                                class="mt-1"
                                placeholder="Payment no., ref, invoice, guest"
                            />
                        </div>

                        <div>
                            <Label for="payment_filter_status">Status</Label>
                            <Select v-model="filtersForm.status">
                                <SelectTrigger
                                    id="payment_filter_status"
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
                            <Label for="payment_filter_method">Method</Label>
                            <Select v-model="filtersForm.method">
                                <SelectTrigger
                                    id="payment_filter_method"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any method</SelectItem
                                    >
                                    <SelectItem
                                        v-for="method in methods"
                                        :key="method"
                                        :value="method"
                                    >
                                        {{ methodLabel(method) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="payment_filter_invoice">Invoice</Label>
                            <Select v-model="filtersForm.invoice_id">
                                <SelectTrigger
                                    id="payment_filter_invoice"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any invoice" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any invoice</SelectItem
                                    >
                                    <SelectItem
                                        v-for="invoice in invoices"
                                        :key="invoice.id"
                                        :value="String(invoice.id)"
                                    >
                                        {{ invoice.invoice_number }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="payment_filter_date_from"
                                >Date From</Label
                            >
                            <Input
                                id="payment_filter_date_from"
                                v-model="filtersForm.payment_date_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="payment_filter_date_to">Date To</Label>
                            <Input
                                id="payment_filter_date_to"
                                v-model="filtersForm.payment_date_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="payment_filter_amount_min"
                                >Min Amount</Label
                            >
                            <Input
                                id="payment_filter_amount_min"
                                v-model="filtersForm.amount_min"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="payment_filter_amount_max"
                                >Max Amount</Label
                            >
                            <Input
                                id="payment_filter_amount_max"
                                v-model="filtersForm.amount_max"
                                type="number"
                                min="0"
                                step="0.01"
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
                <CardTitle>Record Payment</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="invoice_id">Invoice *</Label>
                            <Select
                                v-model="form.invoice_id"
                                @update:model-value="
                                    (value) =>
                                        applyInvoiceDefaults(String(value))
                                "
                            >
                                <SelectTrigger id="invoice_id" class="mt-1">
                                    <SelectValue placeholder="Select invoice" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="invoice in invoices"
                                        :key="invoice.id"
                                        :value="String(invoice.id)"
                                    >
                                        {{ invoice.invoice_number }} ·
                                        {{ invoice.guest_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.invoice_id"
                                class="mt-2"
                            />
                            <p class="mt-2 text-xs text-muted-foreground">
                                Selecting an invoice auto-fills the outstanding
                                balance. You can record a partial payment or use
                                Full Pay to settle the invoice.
                            </p>
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
                            <Label for="amount">Amount *</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="mt-1"
                            />
                            <div
                                class="mt-2 flex items-center justify-between gap-2"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Outstanding:
                                    {{ formatCurrency(selectedInvoiceBalance) }}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!form.invoice_id"
                                    @click="fillFullBalance"
                                >
                                    Full Pay
                                </Button>
                            </div>
                            <InputError
                                :message="form.errors.amount"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="method">Method *</Label>
                            <Select v-model="form.method">
                                <SelectTrigger id="method" class="mt-1">
                                    <SelectValue placeholder="Select method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="method in methods"
                                        :key="method"
                                        :value="method"
                                    >
                                        {{ methodLabel(method) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.method"
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

                        <div>
                            <Label for="reference">Reference</Label>
                            <Input
                                id="reference"
                                v-model="form.reference"
                                class="mt-1"
                                placeholder="Transaction reference"
                            />
                            <InputError
                                :message="form.errors.reference"
                                class="mt-2"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <Label for="notes">Notes</Label>
                            <Input
                                id="notes"
                                v-model="form.notes"
                                class="mt-1"
                                placeholder="Optional payment notes"
                            />
                            <InputError
                                :message="form.errors.notes"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateForm = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Save Payment
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <CreditCard class="text-hotel-primary size-5" />
                    Payment Records
                </CardTitle>
                <Button
                    v-if="!showCreateForm"
                    type="button"
                    @click="showCreateForm = true"
                >
                    <Plus class="mr-2 size-4" />
                    Record Payment
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="payments.length === 0" class="py-10 text-center">
                    <p class="text-sm text-muted-foreground">
                        {{
                            hasActiveFilters
                                ? 'No payments match these filters.'
                                : 'No payments recorded yet.'
                        }}
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <Card
                        v-for="payment in payments"
                        :key="payment.id"
                        :accent-class="paymentAccentClass(payment)"
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
                                                    {{ payment.payment_number }}
                                                </h3>
                                                <Badge
                                                    :class="
                                                        statusColor(
                                                            payment.status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        statusLabel(
                                                            payment.status,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="text-sm break-words text-gray-500"
                                            >
                                                {{ paymentGuestLabel(payment) }}
                                                ·
                                                {{
                                                    payment.invoice
                                                        ?.invoice_number ??
                                                    'Invoice'
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="max-w-56 truncate rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                                        >
                                            {{ paymentSourceLabel(payment) }}
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
                                                Paid On
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{ payment.payment_date }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                {{
                                                    methodLabel(payment.method)
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Amount
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{
                                                    formatCurrency(
                                                        payment.amount,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                {{
                                                    payment.reference ??
                                                    'No reference'
                                                }}
                                            </p>
                                            <p class="text-xs text-slate-500">
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

                                        <div
                                            class="rounded-2xl p-3 ring-1"
                                            :class="paymentToneClass(payment)"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Balance
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold"
                                            >
                                                {{
                                                    formatCurrency(
                                                        paymentBalance(payment),
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs">
                                                {{
                                                    statusLabel(payment.status)
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{ methodLabel(payment.method) }}
                                        </span>
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{
                                                payment.invoice
                                                    ?.invoice_number ??
                                                'Unlinked payment'
                                            }}
                                        </span>
                                        <span
                                            v-if="payment.notes"
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
                                                Linked Invoice
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    payment.invoice
                                                        ?.invoice_number ??
                                                    'None'
                                                }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    payment.invoice
                                                        ?.guest_name ?? 'Guest'
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Audit Trail
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold text-slate-900"
                                            >
                                                Created by
                                                {{
                                                    userLabel(payment.createdBy)
                                                }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Last action by
                                                {{
                                                    userLabel(
                                                        payment.updatedBy ??
                                                            payment.createdBy,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="payment.notes"
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
                                                {{ payment.notes }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-stretch border-t border-slate-200 p-5 lg:border-t-0 lg:border-l lg:p-6"
                                >
                                    <div
                                        class="flex w-full flex-col gap-2 sm:w-auto"
                                    >
                                        <Button
                                            as-child
                                            variant="outline"
                                            size="sm"
                                            class="h-10 w-full justify-start gap-2 rounded-xl"
                                        >
                                            <Link
                                                :href="
                                                    receipt([
                                                        team.slug,
                                                        payment.id,
                                                    ]).url
                                                "
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <Printer class="size-3.5" />
                                                Print
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="isAdmin"
                                            as-child
                                            variant="outline"
                                            size="sm"
                                            class="h-10 w-full justify-start gap-2 rounded-xl"
                                        >
                                            <Link
                                                :href="
                                                    edit([
                                                        team.slug,
                                                        payment.id,
                                                    ]).url
                                                "
                                            >
                                                <Edit class="size-3.5" />
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            v-else
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled
                                            title="Admin only action"
                                            class="h-10 w-full justify-start gap-2 rounded-xl"
                                        >
                                            <Edit class="size-3.5" />
                                            Edit
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            :disabled="!isAdmin"
                                            :title="
                                                isAdmin
                                                    ? 'Delete payment'
                                                    : 'Admin only action'
                                            "
                                            @click="
                                                paymentToDelete = payment;
                                                showDeleteDialog = true;
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Pagination :pagination="pagination" label="payments" />
            </CardContent>
        </Card>

        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Payment</DialogTitle>
                    <DialogDescription>
                        This will permanently remove payment
                        {{ paymentToDelete?.payment_number }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="deleteForm.processing"
                        @click="deletePayment"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
