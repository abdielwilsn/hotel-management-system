<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ChevronLeft, Save, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
import { destroy, index, update } from '@/routes/payments';

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
    payment: Payment;
    invoices: InvoiceOption[];
    methods: string[];
    statuses: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentTeam?: { slug: string } | null;
        payment?: Payment;
    }) => ({
        breadcrumbs: [
            {
                title: 'Payments',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.payment?.payment_number,
                href: '#',
            },
        ],
    }),
});

const showDeleteDialog = ref(false);

const form = useForm({
    invoice_id: String(props.payment.invoice_id),
    payment_number: props.payment.payment_number,
    payment_date: props.payment.payment_date,
    amount: String(props.payment.amount),
    method: props.payment.method,
    status: props.payment.status,
    reference: props.payment.reference || '',
    notes: props.payment.notes || '',
});

const deleteForm = useForm({});

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

const submit = () => {
    form.patch(update([props.team.slug, props.payment.id]).url);
};

const deletePayment = () => {
    deleteForm.delete(destroy([props.team.slug, props.payment.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Link
            :href="index(props.team.slug).url"
            class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900"
        >
            <ChevronLeft class="h-4 w-4" />
            Back to Payments
        </Link>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ payment.payment_number }}
                </h1>
                <p class="mt-1 text-gray-600">
                    {{ payment.invoice?.invoice_number }} ·
                    {{ payment.invoice?.guest_name }}
                </p>
            </div>
            <Badge :class="statusColor(payment.status)">
                {{ statusLabel(payment.status) }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Edit Payment</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="invoice_id">Invoice *</Label>
                            <Select v-model="form.invoice_id">
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
                        </div>

                        <div>
                            <Label for="payment_number">Payment Number *</Label>
                            <Input
                                id="payment_number"
                                v-model="form.payment_number"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.payment_number"
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
                            <Label for="amount">Amount *</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="mt-1"
                            />
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
                            />
                            <InputError
                                :message="form.errors.notes"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="text-red-600 hover:text-red-700"
                            @click="showDeleteDialog = true"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Delete Payment
                        </Button>

                        <Button type="submit" :disabled="form.processing">
                            <Save class="mr-2 size-4" />
                            Save Changes
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Payment</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. Payment
                        {{ payment.payment_number }} will be permanently
                        removed.
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
