<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    ChartColumn,
    ClipboardList,
    Package,
    Truck,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
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
import { useFormatters } from '@/lib/format';
import { index } from '@/routes/pos';
import { receive as receiveStock, store as storeStock } from '@/routes/pos/stock';

type Summary = { orders: number; sales_value: number; items: number };
type Order = {
    id: number;
    order_number: string;
    business_date: string;
    total: number;
    payment_mode: string;
    charge_type: string;
    guest_name: string | null;
    items_count: number;
};
type StockRecord = {
    id: number;
    business_date: string;
    opening_stock: number;
    new_stock: number;
    sales_qty: number;
    closing_stock: number;
    shortage: number;
    excess: number;
    is_closed: boolean;
    menu_item: { id: number; name: string; unit: string };
};
type TrackedItem = {
    id: number;
    name: string;
    unit: string;
    stock_quantity: number;
};
type Movement = {
    id: number;
    type: string;
    quantity: number;
    balance_after: number;
    unit_cost: number | null;
    supplier: string | null;
    business_date: string;
    menu_item: { id: number; name: string; unit: string } | null;
};

type Props = {
    outlet: { id: number; name: string; type: string };
    summary: Summary;
    recentOrders: Order[];
    stockRecords: StockRecord[];
    trackedItems: TrackedItem[];
    recentMovements: Movement[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Point of Sale',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const today = new Date().toISOString().slice(0, 10);

const stockForm = useForm({
    pos_menu_item_id: props.trackedItems[0]?.id
        ? String(props.trackedItems[0].id)
        : '',
    business_date: today,
    opening_stock: '0',
    new_stock: '0',
    closing_stock: '0',
    damaged: '0',
    recorded_by: '',
    notes: '',
    is_closed: '0',
});

const receiveForm = useForm({
    pos_menu_item_id: props.trackedItems[0]?.id
        ? String(props.trackedItems[0].id)
        : '',
    quantity: '1',
    unit_cost: '',
    supplier: '',
    business_date: today,
    notes: '',
});

const { formatCurrency } = useFormatters();

const labelize = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const submitStock = () => {
    stockForm.post(storeStock([props.team.slug, props.outlet.id]).url, {
        preserveScroll: true,
    });
};

const submitReceive = () => {
    receiveForm.post(receiveStock([props.team.slug, props.outlet.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            receiveForm.reset('quantity', 'unit_cost', 'supplier', 'notes');
            receiveForm.quantity = '1';
        },
    });
};

const movementTone = (type: string) =>
    type === 'received'
        ? 'text-green-700'
        : type === 'sold' || type === 'damaged'
          ? 'text-red-600'
          : 'text-amber-600';
</script>

<template>
    <Head :title="`${outlet.name} — Reports`" />

    <div class="space-y-6">
        <Heading
            :title="`${outlet.name} — Reports`"
            description="Sales history and daily stock reconciliation"
        />

        <section class="grid gap-4 sm:grid-cols-3">
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Total orders
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ summary.orders }}
                </CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Sales value
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">
                    {{ formatCurrency(summary.sales_value) }}
                </CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Menu items
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ summary.items }}
                </CardContent>
            </Card>
        </section>

        <section
            v-if="trackedItems.length > 0"
            class="grid gap-4 xl:grid-cols-[1fr_1.1fr]"
        >
            <!-- Current on-hand -->
            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Boxes class="text-hotel-primary size-4" />
                        Current Stock
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div
                            v-for="item in trackedItems"
                            :key="item.id"
                            class="flex items-center justify-between rounded-lg border px-3 py-2"
                        >
                            <span class="text-sm font-medium">{{
                                item.name
                            }}</span>
                            <Badge
                                :class="
                                    item.stock_quantity <= 0
                                        ? 'bg-red-100 text-red-700'
                                        : 'bg-green-100 text-green-800'
                                "
                                class="rounded-full"
                            >
                                {{ item.stock_quantity }} {{ item.unit }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Receive stock -->
            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Truck class="text-hotel-primary size-4" />
                        Receive Stock
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submitReceive">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <Label>Item *</Label>
                                <Select v-model="receiveForm.pos_menu_item_id">
                                    <SelectTrigger class="mt-1">
                                        <SelectValue placeholder="Select item" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in trackedItems"
                                            :key="item.id"
                                            :value="String(item.id)"
                                        >
                                            {{ item.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="receiveForm.errors.pos_menu_item_id"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="rcv_qty">Quantity *</Label>
                                <Input
                                    id="rcv_qty"
                                    v-model="receiveForm.quantity"
                                    type="number"
                                    min="1"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="receiveForm.errors.quantity"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="rcv_cost">Unit cost</Label>
                                <Input
                                    id="rcv_cost"
                                    v-model="receiveForm.unit_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="receiveForm.errors.unit_cost"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="rcv_supplier">Supplier</Label>
                                <Input
                                    id="rcv_supplier"
                                    v-model="receiveForm.supplier"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="rcv_date">Date *</Label>
                                <Input
                                    id="rcv_date"
                                    v-model="receiveForm.business_date"
                                    type="date"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                        <Button type="submit" :disabled="receiveForm.processing">
                            Receive into stock
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ChartColumn class="text-hotel-primary size-4" />
                        Recent Orders
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="recentOrders.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No orders recorded yet.
                    </div>
                    <div
                        v-else
                        class="max-h-[430px] space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="order in recentOrders"
                            :key="order.id"
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold">
                                    {{ order.order_number }}
                                </p>
                                <span class="text-xs text-muted-foreground">{{
                                    order.business_date
                                }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ order.items_count }} items ·
                                {{ labelize(order.payment_mode) }} ·
                                {{ formatCurrency(order.total) }}
                            </p>
                            <p
                                v-if="order.guest_name"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Guest: {{ order.guest_name }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Package class="text-hotel-primary size-4" />
                        Record Daily Stock
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="trackedItems.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No stock-tracked items in this outlet.
                    </div>
                    <form
                        v-else
                        class="space-y-3"
                        @submit.prevent="submitStock"
                    >
                        <div>
                            <Label>Item *</Label>
                            <Select v-model="stockForm.pos_menu_item_id">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Select item" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="item in trackedItems"
                                        :key="item.id"
                                        :value="String(item.id)"
                                    >
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="stockForm.errors.pos_menu_item_id"
                                class="mt-2"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="business_date">Date *</Label>
                                <Input
                                    id="business_date"
                                    v-model="stockForm.business_date"
                                    type="date"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="opening_stock">Opening *</Label>
                                <Input
                                    id="opening_stock"
                                    v-model="stockForm.opening_stock"
                                    type="number"
                                    min="0"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="new_stock">New stock *</Label>
                                <Input
                                    id="new_stock"
                                    v-model="stockForm.new_stock"
                                    type="number"
                                    min="0"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="closing_stock">Closing *</Label>
                                <Input
                                    id="closing_stock"
                                    v-model="stockForm.closing_stock"
                                    type="number"
                                    min="0"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="damaged">Damaged *</Label>
                                <Input
                                    id="damaged"
                                    v-model="stockForm.damaged"
                                    type="number"
                                    min="0"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="is_closed">Day status *</Label>
                                <Select v-model="stockForm.is_closed">
                                    <SelectTrigger id="is_closed" class="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Open</SelectItem>
                                        <SelectItem value="1">Closed</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div>
                            <Label for="notes">Notes</Label>
                            <Input
                                id="notes"
                                v-model="stockForm.notes"
                                class="mt-1"
                            />
                        </div>

                        <Button type="submit" :disabled="stockForm.processing">
                            Save stock record
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </section>

        <Card class="bg-white/90">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <ClipboardList class="text-hotel-primary size-4" />
                    Stock Reconciliation
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="stockRecords.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No stock records yet.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="record in stockRecords"
                        :key="record.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">
                                {{ record.menu_item.name }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Badge
                                    v-if="record.shortage > 0"
                                    variant="destructive"
                                    class="rounded-full text-[10px]"
                                >
                                    short {{ record.shortage }}
                                </Badge>
                                <Badge
                                    v-if="record.excess > 0"
                                    class="rounded-full text-[10px]"
                                >
                                    excess {{ record.excess }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">{{
                                    record.business_date
                                }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            O: {{ record.opening_stock }} · N:
                            {{ record.new_stock }} · S: {{ record.sales_qty }} ·
                            C: {{ record.closing_stock }}
                            {{ record.menu_item.unit }}
                            <span v-if="record.is_closed"> · closed</span>
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="recentMovements.length > 0" class="bg-white/90">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Package class="text-hotel-primary size-4" />
                    Stock Movements
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <div
                        v-for="movement in recentMovements"
                        :key="movement.id"
                        class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ movement.menu_item?.name ?? 'Item' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ labelize(movement.type) }}
                                <template v-if="movement.supplier">
                                    · {{ movement.supplier }}
                                </template>
                                <template v-if="movement.unit_cost">
                                    · {{ formatCurrency(movement.unit_cost) }}/unit
                                </template>
                                · {{ movement.business_date }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-sm font-semibold"
                                :class="movementTone(movement.type)"
                            >
                                {{ movement.quantity > 0 ? '+' : ''
                                }}{{ movement.quantity }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                bal {{ movement.balance_after }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
