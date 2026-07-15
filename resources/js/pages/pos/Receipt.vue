<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useFormatters } from '@/lib/format';
import { index, terminal } from '@/routes/pos';

type OrderItem = {
    id: number;
    name: string;
    unit_price: number;
    quantity: number;
    line_total: number;
};

type Order = {
    id: number;
    pos_outlet_id: number;
    order_number: string;
    status: string;
    charge_type: string;
    payment_mode: string;
    room_number: number | null;
    guest_name: string | null;
    subtotal: number;
    total: number;
    served_by: string | null;
    business_date: string;
    paid_at: string | null;
    items: OrderItem[];
};

type Props = {
    order: Order;
    outlet: { id: number; name: string; type: string };
    team: { id: number; slug: string; name: string };
};

defineProps<Props>();

// This screen renders without app chrome — see the `pos/Receipt` case in app.ts.
const printReceipt = () => window.print();

const { formatCurrency } = useFormatters();

const labelize = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

onMounted(() => {
    // Give the DOM a tick to paint before opening the print dialog.
    window.setTimeout(() => window.print(), 300);
});
</script>

<template>
    <Head :title="`Receipt ${order.order_number}`" />

    <div class="receipt-screen">
        <div class="receipt-actions">
            <Button size="sm" @click="printReceipt">Print again</Button>
            <Button size="sm" variant="outline" as-child>
                <Link :href="terminal([team.slug, outlet.id]).url">
                    New order
                </Link>
            </Button>
            <Button size="sm" variant="ghost" as-child>
                <Link :href="index(team.slug).url">Done</Link>
            </Button>
        </div>

        <div class="receipt">
            <div class="center">
                <p class="title">{{ team.name }}</p>
                <p class="muted">{{ outlet.name }}</p>
                <p class="muted uppercase">{{ outlet.type }}</p>
            </div>

            <hr />

            <div class="row muted">
                <span>Order</span><span>{{ order.order_number }}</span>
            </div>
            <div class="row muted">
                <span>Date</span><span>{{ order.business_date }}</span>
            </div>
            <div class="row muted">
                <span>Served by</span><span>{{ order.served_by || '—' }}</span>
            </div>
            <div v-if="order.guest_name" class="row muted">
                <span>Guest</span><span>{{ order.guest_name }}</span>
            </div>
            <div v-if="order.room_number" class="row muted">
                <span>Room</span><span>{{ order.room_number }}</span>
            </div>

            <hr />

            <div v-for="item in order.items" :key="item.id" class="line">
                <div class="row">
                    <span>{{ item.quantity }} × {{ item.name }}</span>
                    <span>{{ formatCurrency(item.line_total) }}</span>
                </div>
                <p class="muted small">
                    @ {{ formatCurrency(item.unit_price) }}
                </p>
            </div>

            <hr />

            <div class="row total">
                <span>TOTAL</span><span>{{ formatCurrency(order.total) }}</span>
            </div>
            <div class="row muted">
                <span>Payment</span>
                <span>{{ labelize(order.payment_mode) }}</span>
            </div>
            <p v-if="order.charge_type === 'room'" class="center muted small">
                Charged to guest room folio
            </p>

            <hr />

            <p class="center muted small">Thank you! Please come again.</p>
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
.line {
    margin-bottom: 3px;
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
