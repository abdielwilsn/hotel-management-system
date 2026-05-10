<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ChevronLeft, Save, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
import { index, update, destroy } from '@/routes/invoices';

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
};

type Props = {
    invoice: Invoice;
    bookings: BookingOption[];
    statuses: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentTeam?: { slug: string } | null;
        invoice?: Invoice;
    }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.invoice?.invoice_number,
                href: '#',
            },
        ],
    }),
});

const showDeleteDialog = ref(false);

const form = useForm({
    booking_id: props.invoice.booking_id
        ? String(props.invoice.booking_id)
        : 'none',
    invoice_number: props.invoice.invoice_number,
    guest_name: props.invoice.guest_name,
    guest_email: props.invoice.guest_email,
    issue_date: props.invoice.issue_date,
    due_date: props.invoice.due_date || '',
    subtotal: String(props.invoice.subtotal),
    tax_amount: String(props.invoice.tax_amount),
    discount_amount: String(props.invoice.discount_amount),
    paid_amount: String(props.invoice.paid_amount),
    status: props.invoice.status,
    notes: props.invoice.notes || '',
});

const deleteForm = useForm({});

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

const submit = () => {
    form.transform((data) => ({
        ...data,
        booking_id: data.booking_id === 'none' ? null : data.booking_id,
    })).patch(update([props.team.slug, props.invoice.id]).url);
};

const deleteInvoice = () => {
    deleteForm.delete(destroy([props.team.slug, props.invoice.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
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
        <Link
            :href="index(props.team.slug).url"
            class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900"
        >
            <ChevronLeft class="h-4 w-4" />
            Back to Invoices
        </Link>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ invoice.invoice_number }}
                </h1>
                <p class="mt-1 text-gray-600">
                    {{ invoice.guest_name }} · {{ invoice.guest_email }}
                </p>
            </div>
            <Badge :class="statusColor(invoice.status)">
                {{ statusLabel(invoice.status) }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Edit Invoice</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
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
                            <Label for="invoice_number">Invoice Number *</Label>
                            <Input
                                id="invoice_number"
                                v-model="form.invoice_number"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.invoice_number"
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
                        <Input id="notes" v-model="form.notes" class="mt-1" />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div
                        class="flex flex-wrap justify-between gap-3 border-t pt-4"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="text-red-600 hover:bg-red-50 hover:text-red-700"
                            @click="showDeleteDialog = true"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete Invoice
                        </Button>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-hotel-primary hover:bg-hotel-primary/90"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
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
                        This will permanently remove
                        {{ invoice.invoice_number }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="deleteForm.processing"
                        @click="deleteInvoice"
                    >
                        {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
