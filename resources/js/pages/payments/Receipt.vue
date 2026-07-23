<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useFormatters } from '@/lib/format';
import { index as bookingIndex } from '@/routes/bookings';
import { index as paymentIndex } from '@/routes/payments';

type ReceiptInvoice = {
    id: number;
    invoice_number: string;
    guest_name: string;
    guest_email: string | null;
    total_amount: number;
    paid_amount: number;
    status: string;
};

type ReceiptPayment = {
    id: number;
    payment_number: string;
    payment_date: string;
    amount: number;
    method: string;
    status: string;
    reference: string | null;
    notes: string | null;
    createdBy?: { id: number; name: string } | null;
    updatedBy?: { id: number; name: string } | null;
    invoice: ReceiptInvoice | null;
};

type Props = {
    payment: ReceiptPayment;
    team: { id: number; slug: string; name: string };
};

defineProps<Props>();

// This screen renders without app chrome — see the `payments/Receipt` case in app.ts.
const { formatCurrency } = useFormatters();

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

const formatLabel = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const userLabel = (user?: { name: string } | null) => user?.name ?? 'System';

const printReceipt = () => window.print();

onMounted(() => {
    // Give the DOM a tick to paint before opening the print dialog.
    window.setTimeout(() => window.print(), 300);
});
</script>

<template>
    <Head :title="`Receipt ${payment.payment_number}`" />

    <div class="receipt-screen">
        <div class="receipt-actions">
            <button type="button" class="action-button" @click="printReceipt">
                Print again
            </button>
            <Link :href="bookingIndex(team.slug).url" class="action-button">
                Back to bookings
            </Link>
            <Link :href="paymentIndex(team.slug).url" class="action-button">
                Payments
            </Link>
        </div>

        <div class="receipt">
            <div class="center">
                <p class="title">{{ team.name }}</p>
                <p class="muted uppercase">Payment Receipt</p>
            </div>

            <hr />

            <div class="row muted">
                <span>Receipt</span><span>{{ payment.payment_number }}</span>
            </div>
            <div class="row muted">
                <span>Date</span><span>{{ formatDate(payment.payment_date) }}</span>
            </div>
            <div class="row muted">
                <span>Guest</span>
                <span>{{ payment.invoice?.guest_name ?? 'Walk-in guest' }}</span>
            </div>
            <div v-if="payment.invoice" class="row muted">
                <span>Invoice</span><span>{{ payment.invoice.invoice_number }}</span>
            </div>

            <hr />

            <div class="row">
                <span>Method</span><span>{{ formatLabel(payment.method) }}</span>
            </div>
            <div v-if="payment.reference" class="row muted small">
                <span>Reference</span><span>{{ payment.reference }}</span>
            </div>
            <div class="row muted">
                <span>Status</span><span>{{ formatLabel(payment.status) }}</span>
            </div>

            <hr />

            <div class="row total">
                <span>AMOUNT PAID</span
                ><span>{{ formatCurrency(payment.amount) }}</span>
            </div>
            <div v-if="payment.invoice" class="row muted small">
                <span>Invoice balance</span>
                <span>{{
                    formatCurrency(
                        Math.max(
                            Number(payment.invoice.total_amount) -
                                Number(payment.invoice.paid_amount),
                            0,
                        ),
                    )
                }}</span>
            </div>

            <hr />

            <div class="row muted small">
                <span>Created by</span><span>{{ userLabel(payment.createdBy) }}</span>
            </div>
            <div class="row muted small">
                <span>Last action by</span>
                <span>{{ userLabel(payment.updatedBy ?? payment.createdBy) }}</span>
            </div>

            <hr />

            <p class="center muted small">Thank you for your payment.</p>
        </div>
    </div>
</template>

<style scoped>
.receipt-screen {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 1rem;
    background: #f3f4f6;
    min-height: 100vh;
}

.receipt-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-button {
    display: inline-flex;
    align-items: center;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    background: #fff;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.action-button:hover {
    background: #f3f4f6;
}

.receipt {
    width: 80mm;
    max-width: 100%;
    background: #fff;
    padding: 6mm 5mm;
    color: #000;
    font-family: 'Courier New', ui-monospace, monospace;
    font-size: 12px;
    line-height: 1.45;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}

.center {
    text-align: center;
}
.uppercase {
    text-transform: uppercase;
}
.title {
    font-size: 15px;
    font-weight: 700;
}
.muted {
    color: #333;
}
.small {
    font-size: 10px;
}
.row {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
}
.total {
    font-size: 14px;
    font-weight: 700;
}
hr {
    border: none;
    border-top: 1px dashed #000;
    margin: 6px 0;
}

@media print {
    .receipt-screen {
        background: #fff;
        padding: 0;
        min-height: auto;
    }
    .receipt-actions {
        display: none;
    }
    .receipt {
        width: auto;
        box-shadow: none;
        padding: 0;
    }
    @page {
        margin: 4mm;
    }
}
</style>
