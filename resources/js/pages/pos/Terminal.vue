<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { store } from '@/routes/pos/orders';

type MenuItem = {
    id: number;
    pos_category_id: number | null;
    name: string;
    price: number;
    unit: string;
    track_stock: boolean;
    stock_quantity: number;
};

type Category = { id: number; name: string };
type ActiveBooking = {
    id: number;
    guest_name: string;
    room_number: number | null;
};

type Props = {
    outlet: { id: number; name: string; type: string };
    categories: Category[];
    items: MenuItem[];
    activeBookings: ActiveBooking[];
    defaultServer: string;
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

const activeCategory = ref<number | 'all'>('all');

const visibleItems = computed(() =>
    activeCategory.value === 'all'
        ? props.items
        : props.items.filter((item) => item.pos_category_id === activeCategory.value),
);

type CartLine = { item: MenuItem; quantity: number };
const cart = ref<CartLine[]>([]);

const form = useForm<{
    charge_type: 'walk_in' | 'room';
    payment_mode: 'cash' | 'card' | 'transfer' | 'room';
    served_by: string;
    booking_id: string;
    items: { pos_menu_item_id: number; quantity: number }[];
}>({
    charge_type: 'walk_in',
    payment_mode: 'cash',
    served_by: props.defaultServer,
    booking_id: '',
    items: [],
});

const subtotal = computed(() =>
    cart.value.reduce(
        (sum, line) => sum + Number(line.item.price) * line.quantity,
        0,
    ),
);

const { formatCurrency } = useFormatters();

const isOutOfStock = (item: MenuItem) =>
    item.track_stock && item.stock_quantity <= 0;

// Units of an item already sitting in the cart.
const cartQuantity = (itemId: number) =>
    cart.value.find((line) => line.item.id === itemId)?.quantity ?? 0;

const canAddMore = (item: MenuItem) =>
    !item.track_stock || cartQuantity(item.id) < item.stock_quantity;

const addToCart = (item: MenuItem) => {
    if (isOutOfStock(item) || !canAddMore(item)) {
        return;
    }

    const existing = cart.value.find((line) => line.item.id === item.id);

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({ item, quantity: 1 });
    }
};

const changeQuantity = (line: CartLine, delta: number) => {
    if (delta > 0 && !canAddMore(line.item)) {
        return;
    }

    line.quantity += delta;

    if (line.quantity <= 0) {
        removeLine(line);
    }
};

const removeLine = (line: CartLine) => {
    cart.value = cart.value.filter((entry) => entry.item.id !== line.item.id);
};

const clearCart = () => {
    cart.value = [];
};

const setChargeType = (value: string) => {
    form.charge_type = value as 'walk_in' | 'room';

    if (value === 'room') {
        form.payment_mode = 'room';
    } else if (form.payment_mode === 'room') {
        form.payment_mode = 'cash';
    }
};

const checkout = () => {
    if (cart.value.length === 0) {
        return;
    }

    form.items = cart.value.map((line) => ({
        pos_menu_item_id: line.item.id,
        quantity: line.quantity,
    }));

    form.post(store([props.team.slug, props.outlet.id]).url, {
        onSuccess: () => {
            clearCart();
            form.reset('booking_id');
        },
    });
};
</script>

<template>
    <Head :title="`${outlet.name} — POS`" />

    <div class="space-y-6">
        <Heading
            :title="outlet.name"
            description="Tap items to build the order, then take payment and print"
        />

        <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            <!-- Menu -->
            <Card class="bg-white/90">
                <CardHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            :variant="activeCategory === 'all' ? 'default' : 'outline'"
                            @click="activeCategory = 'all'"
                        >
                            All
                        </Button>
                        <Button
                            v-for="category in categories"
                            :key="category.id"
                            size="sm"
                            :variant="activeCategory === category.id ? 'default' : 'outline'"
                            @click="activeCategory = category.id"
                        >
                            {{ category.name }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="visibleItems.length === 0"
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        No items in this section yet.
                    </div>
                    <div
                        v-else
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <button
                            v-for="item in visibleItems"
                            :key="item.id"
                            type="button"
                            :disabled="isOutOfStock(item)"
                            class="hover:border-hotel-primary flex flex-col rounded-lg border p-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-border"
                            @click="addToCart(item)"
                        >
                            <span class="font-medium">{{ item.name }}</span>
                            <span class="mt-1 text-sm text-muted-foreground">{{
                                formatCurrency(item.price)
                            }}</span>
                            <Badge
                                v-if="isOutOfStock(item)"
                                class="mt-2 w-fit rounded-full bg-red-100 text-[10px] text-red-700"
                            >
                                Out of stock
                            </Badge>
                            <Badge
                                v-else-if="item.track_stock"
                                variant="outline"
                                class="mt-2 w-fit rounded-full text-[10px]"
                            >
                                {{ item.stock_quantity }} in stock
                            </Badge>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Cart / checkout -->
            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ShoppingCart class="text-hotel-primary size-4" />
                        Current Order
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="cart.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No items added yet.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="line in cart"
                            :key="line.item.id"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">
                                    {{ line.item.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatCurrency(line.item.price) }} ×
                                    {{ line.quantity }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-7"
                                    :aria-label="`Reduce ${line.item.name} quantity`"
                                    @click="changeQuantity(line, -1)"
                                >
                                    <Minus class="size-3.5" />
                                </Button>
                                <span class="w-6 text-center text-sm">{{
                                    line.quantity
                                }}</span>
                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-7"
                                    :disabled="!canAddMore(line.item)"
                                    :aria-label="`Increase ${line.item.name} quantity`"
                                    @click="changeQuantity(line, 1)"
                                >
                                    <Plus class="size-3.5" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-7 text-destructive"
                                    :aria-label="`Remove ${line.item.name} from order`"
                                    @click="removeLine(line)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-t pt-3 text-lg font-semibold"
                    >
                        <span>Total</span>
                        <span>{{ formatCurrency(subtotal) }}</span>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <Label>Charge to</Label>
                            <Select
                                :model-value="form.charge_type"
                                @update:model-value="setChargeType($event as string)"
                            >
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="walk_in">
                                        Walk-in (pay now)
                                    </SelectItem>
                                    <SelectItem value="room">
                                        Guest room / booking
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="form.charge_type === 'room'">
                            <Label>Guest booking *</Label>
                            <Select v-model="form.booking_id">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Select booking" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="booking in activeBookings"
                                        :key="booking.id"
                                        :value="String(booking.id)"
                                    >
                                        {{ booking.guest_name }}
                                        <template v-if="booking.room_number">
                                            — Room {{ booking.room_number }}
                                        </template>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.booking_id"
                                class="mt-2"
                            />
                        </div>

                        <div v-else>
                            <Label>Payment mode</Label>
                            <Select v-model="form.payment_mode">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="card">Card</SelectItem>
                                    <SelectItem value="transfer">
                                        Transfer
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="served_by">Served by</Label>
                            <Input
                                id="served_by"
                                v-model="form.served_by"
                                class="mt-1"
                            />
                        </div>

                        <InputError :message="form.errors.items" />

                        <div class="flex gap-2">
                            <Button
                                class="flex-1"
                                :disabled="cart.length === 0 || form.processing"
                                @click="checkout"
                            >
                                Charge & print
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="cart.length === 0"
                                @click="clearCart"
                            >
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
