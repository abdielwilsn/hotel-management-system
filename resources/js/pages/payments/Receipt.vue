<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
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
    invoice: ReceiptInvoice | null;
};

type Props = {
    payment: ReceiptPayment;
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: null,
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(Number(value));

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

const formatLabel = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

onMounted(() => {
    window.print();
});
</script>

<template>
    <Head :title="`Receipt ${payment.payment_number}`" />

    <main
        class="mx-auto max-w-3xl bg-slate-50 p-4 sm:p-8 print:max-w-none print:bg-white print:p-0"
    >
        <div class="mb-4 flex items-center justify-end gap-2 print:hidden">
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                @click="window.print()"
            >
                Print Again
            </button>
            <Link
                :href="bookingIndex(team.slug).url"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Back to Bookings
            </Link>
            <Link
                :href="paymentIndex(team.slug).url"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                Payments
            </Link>
        </div>

        <section
            class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none"
        >
            <header class="border-b border-slate-200 pb-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs tracking-[0.25em] text-slate-500 uppercase"
                        >
                            Receipt
                        </p>
                        <h1 class="mt-2 text-2xl font-bold text-slate-900">
                            {{ team.name }}
                        </h1>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 uppercase">
                            Receipt Number
                        </p>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ payment.payment_number }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500 uppercase">
                            Payment Date
                        </p>
                        <p class="text-sm font-medium text-slate-800">
                            {{ formatDate(payment.payment_date) }}
                        </p>
                    </div>
                </div>
            </header>

            <div
                class="grid gap-4 border-b border-slate-200 py-5 sm:grid-cols-2"
            >
                <div>
                    <p class="text-xs text-slate-500 uppercase">
                        Received From
                    </p>
                    <p class="mt-1 text-base font-semibold text-slate-900">
                        {{ payment.invoice?.guest_name ?? 'Walk-in guest' }}
                    </p>
                    <p class="text-sm text-slate-600">
                        {{
                            payment.invoice?.guest_email ?? 'No email provided'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">Invoice</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">
                        {{
                            payment.invoice?.invoice_number ??
                            'Unlinked payment'
                        }}
                    </p>
                    <p class="text-sm text-slate-600">
                        Status: {{ formatLabel(payment.status) }}
                    </p>
                </div>
            </div>

            <div class="space-y-3 py-5">
                <div
                    class="flex items-center justify-between text-sm text-slate-700"
                >
                    <span>Payment Method</span>
                    <span class="font-medium text-slate-900">{{
                        formatLabel(payment.method)
                    }}</span>
                </div>
                <div
                    class="flex items-center justify-between text-sm text-slate-700"
                >
                    <span>Amount Paid</span>
                    <span class="text-lg font-bold text-emerald-700">{{
                        formatCurrency(payment.amount)
                    }}</span>
                </div>
                <div
                    class="flex items-center justify-between text-sm text-slate-700"
                >
                    <span>Reference</span>
                    <span class="font-medium text-slate-900">{{
                        payment.reference ?? 'N/A'
                    }}</span>
                </div>
                <div
                    v-if="payment.invoice"
                    class="flex items-center justify-between text-sm text-slate-700"
                >
                    <span>Invoice Balance</span>
                    <span class="font-medium text-slate-900">
                        {{
                            formatCurrency(
                                Math.max(
                                    Number(payment.invoice.total_amount) -
                                        Number(payment.invoice.paid_amount),
                                    0,
                                ),
                            )
                        }}
                    </span>
                </div>
            </div>

            <footer class="border-t border-slate-200 pt-5">
                <p class="text-xs text-slate-500">
                    Thank you for your payment.
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    Generated by {{ team.name }} Hotel Management System
                </p>
            </footer>
        </section>
    </main>
</template>
