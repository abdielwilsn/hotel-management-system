<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import { CreditCard, Edit, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, edit, index, store } from '@/routes/payments';
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

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(Number(value));

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
                                balance. Partial payments are not accepted.
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

                <div v-else class="space-y-2">
                    <div
                        v-for="payment in payments"
                        :key="payment.id"
                        class="border-hotel-primary/15 grid grid-cols-1 items-center gap-3 rounded-lg border bg-white px-3 py-2.5 sm:grid-cols-[130px_1fr_120px_100px_auto]"
                    >
                        <div>
                            <p
                                class="text-hotel-primary/80 text-xs font-semibold"
                            >
                                {{ payment.payment_number }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ payment.payment_date }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium">
                                {{
                                    payment.invoice?.invoice_number ?? 'Invoice'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ payment.invoice?.guest_name ?? 'Guest' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-semibold">
                                {{ formatCurrency(payment.amount) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ methodLabel(payment.method) }}
                            </p>
                        </div>

                        <Badge :class="statusColor(payment.status)">
                            {{ statusLabel(payment.status) }}
                        </Badge>

                        <div class="flex items-center justify-end gap-2">
                            <Button
                                v-if="isAdmin"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link :href="edit([team.slug, payment.id]).url">
                                    <Edit class="size-3.5" />
                                </Link>
                            </Button>
                            <Button
                                v-else
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled
                                title="Admin only action"
                            >
                                <Edit class="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="text-red-600 hover:text-red-700"
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
                            </Button>
                        </div>
                    </div>
                </div>
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
