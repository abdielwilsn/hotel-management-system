<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    BedDouble,
    Check,
    ChevronLeft,
    ChevronRight,
    Loader2,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { store } from '@/routes/bookings';
import { availability } from '@/routes/rooms';

type Room = {
    id: number;
    room_number: string;
    room_type: string;
    capacity: number;
    price_per_night: number;
};

type Props = {
    open: boolean;
    teamSlug: string;
    /** Preselect this room once availability confirms it is free. */
    preselectRoomId?: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    preselectRoomId: null,
});

const emit = defineEmits<{
    close: [];
    submit: [];
}>();

const { formatCurrency, formatDate } = useFormatters();

const steps = [
    { number: 1, label: 'Stay' },
    { number: 2, label: 'Room' },
    { number: 3, label: 'Guest' },
    { number: 4, label: 'Confirm' },
];
const totalSteps = steps.length;
const step = ref(1);

const today = () => new Date().toISOString().split('T')[0];
const tomorrow = () =>
    new Date(Date.now() + 86400000).toISOString().split('T')[0];

const form = useForm({
    room_id: '',
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    number_of_guests: '1',
    check_in_date: today(),
    check_out_date: tomorrow(),
    status: 'pending',
    notes: '',
    discount_type: '',
    discount_value: '',
    discount_reason: '',
    process_payment: false,
    payment_amount: '',
    payment_method: 'cash',
    payment_date: today(),
    payment_reference: '',
    payment_notes: '',
});

/* ------------------------------------------------------------------ *
 * Availability — the room list always reflects the chosen dates.
 * ------------------------------------------------------------------ */
const availableRooms = ref<Room[]>([]);
const loadingRooms = ref(false);
const availabilityError = ref<string | null>(null);
const roomSearch = ref('');

const nightsCount = computed(() => {
    if (!form.check_in_date || !form.check_out_date) {
        return 0;
    }

    const ms =
        new Date(form.check_out_date).getTime() -
        new Date(form.check_in_date).getTime();

    return Math.ceil(ms / 86400000);
});

const hasValidRange = computed(
    () =>
        Boolean(form.check_in_date && form.check_out_date) &&
        nightsCount.value > 0,
);

const fetchAvailability = async () => {
    if (!hasValidRange.value) {
        availableRooms.value = [];

        return;
    }

    loadingRooms.value = true;
    availabilityError.value = null;

    try {
        const url = availability.url(props.teamSlug, {
            query: {
                check_in_date: form.check_in_date,
                check_out_date: form.check_out_date,
            },
        });

        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Availability lookup failed');
        }

        const data = await response.json();
        availableRooms.value = data.rooms ?? [];

        // A room chosen for older dates may no longer be free.
        if (
            form.room_id &&
            !availableRooms.value.some((r) => String(r.id) === form.room_id)
        ) {
            form.room_id = '';
        }

        if (
            !form.room_id &&
            props.preselectRoomId &&
            availableRooms.value.some((r) => r.id === props.preselectRoomId)
        ) {
            form.room_id = String(props.preselectRoomId);
        }
    } catch {
        availabilityError.value =
            'Could not check room availability. Please try again.';
        availableRooms.value = [];
    } finally {
        loadingRooms.value = false;
    }
};

watch(() => [form.check_in_date, form.check_out_date], fetchAvailability);

const filteredRooms = computed(() => {
    const term = roomSearch.value.trim().toLowerCase();

    if (!term) {
        return availableRooms.value;
    }

    return availableRooms.value.filter(
        (room) =>
            room.room_number.toLowerCase().includes(term) ||
            room.room_type.toLowerCase().includes(term),
    );
});

const selectedRoom = computed(() =>
    availableRooms.value.find((r) => String(r.id) === form.room_id),
);

const totalAmount = computed(
    () => nightsCount.value * (selectedRoom.value?.price_per_night ?? 0),
);

const hasDiscount = computed(
    () => form.discount_type === 'percentage' || form.discount_type === 'fixed',
);

const discountAmount = computed(() => {
    if (!hasDiscount.value || !form.discount_value) {
        return 0;
    }

    const value = Number(form.discount_value);

    if (!Number.isFinite(value) || value <= 0) {
        return 0;
    }

    return form.discount_type === 'percentage'
        ? Math.min((totalAmount.value * value) / 100, totalAmount.value)
        : Math.min(value, totalAmount.value);
});

const payableAmount = computed(() => totalAmount.value - discountAmount.value);

/* ------------------------------------------------------------------ *
 * Step gating — always say *why* you can't continue.
 * ------------------------------------------------------------------ */
const nextBlockedReason = computed<string | null>(() => {
    if (step.value === 1) {
        if (!form.check_in_date || !form.check_out_date) {
            return 'Choose both a check-in and a check-out date.';
        }

        if (nightsCount.value < 1) {
            return 'Check-out must be after check-in.';
        }

        return null;
    }

    if (step.value === 2) {
        if (loadingRooms.value) {
            return 'Checking which rooms are free…';
        }

        if (!form.room_id) {
            return 'Select an available room to continue.';
        }

        return null;
    }

    if (step.value === 3) {
        if (!form.guest_name.trim()) {
            return 'Enter the guest name.';
        }

        if (!form.guest_email.trim()) {
            return 'Enter the guest email.';
        }

        return null;
    }

    return null;
});

const canGoNext = computed(() => nextBlockedReason.value === null);

const goNext = () => {
    if (step.value < totalSteps && canGoNext.value) {
        step.value++;
    }
};

const goBack = () => {
    if (step.value > 1) {
        step.value--;
    }
};

const resetWizard = () => {
    form.reset();
    form.check_in_date = today();
    form.check_out_date = tomorrow();
    form.payment_date = today();
    step.value = 1;
    roomSearch.value = '';
    availabilityError.value = null;
};

const submitForm = () => {
    // "none" is a UI-only sentinel (reka-ui forbids empty SelectItem values);
    // the backend treats a blank discount_type as "no discount".
    form.transform((data) => ({
        ...data,
        discount_type: hasDiscount.value ? data.discount_type : '',
    })).post(store(props.teamSlug).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetWizard();
            emit('close');
            emit('submit');
        },
    });
};

const handleOpenChange = (newOpen: boolean) => {
    if (!newOpen) {
        emit('close');
        resetWizard();
    }
};

watch(
    () => props.open,
    (newOpen) => {
        if (newOpen) {
            resetWizard();
            fetchAvailability();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="flex max-h-[90dvh] max-w-3xl flex-col">
            <DialogHeader>
                <DialogTitle>Create Booking</DialogTitle>
                <DialogDescription>
                    Pick the dates first — we'll only offer rooms that are free
                    for them.
                </DialogDescription>
            </DialogHeader>

            <!-- Labelled progress -->
            <ol class="flex items-center gap-2">
                <li
                    v-for="s in steps"
                    :key="s.number"
                    class="flex flex-1 flex-col gap-1.5"
                >
                    <div
                        class="h-1 rounded-full transition-colors"
                        :class="
                            s.number <= step ? 'bg-blue-600' : 'bg-gray-200'
                        "
                    />
                    <span
                        class="flex items-center gap-1 text-xs font-medium transition-colors"
                        :class="
                            s.number === step
                                ? 'text-blue-600'
                                : s.number < step
                                  ? 'text-gray-500'
                                  : 'text-gray-400'
                        "
                    >
                        <Check v-if="s.number < step" class="h-3 w-3" />
                        {{ s.label }}
                    </span>
                </li>
            </ol>

            <!-- Scrollable step body (nav stays pinned below) -->
            <div class="-mx-1 flex-1 overflow-y-auto px-1">
                <!-- Step 1: Stay -->
                <div v-show="step === 1" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="check_in_date">Check-in Date *</Label>
                            <Input
                                id="check_in_date"
                                v-model="form.check_in_date"
                                type="date"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.check_in_date" />
                        </div>

                        <div>
                            <Label for="check_out_date">Check-out Date *</Label>
                            <Input
                                id="check_out_date"
                                v-model="form.check_out_date"
                                type="date"
                                :min="form.check_in_date"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.check_out_date" />
                        </div>
                    </div>

                    <div>
                        <Label for="number_of_guests">Number of Guests *</Label>
                        <Select v-model="form.number_of_guests">
                            <SelectTrigger id="number_of_guests" class="mt-1">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="n in 5"
                                    :key="n"
                                    :value="String(n)"
                                >
                                    {{ n }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.number_of_guests" />
                    </div>

                    <div
                        v-if="hasValidRange"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        {{ nightsCount }} night{{
                            nightsCount > 1 ? 's' : ''
                        }}
                        · {{ formatDate(form.check_in_date) }} →
                        {{ formatDate(form.check_out_date) }}
                    </div>
                </div>

                <!-- Step 2: Room -->
                <div v-show="step === 2" class="space-y-4">
                    <div
                        class="flex flex-col gap-1 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span>
                            Free for
                            {{ formatDate(form.check_in_date) }} →
                            {{ formatDate(form.check_out_date) }}
                        </span>
                        <button
                            type="button"
                            class="text-left font-medium underline underline-offset-2 sm:text-right"
                            @click="step = 1"
                        >
                            Change dates
                        </button>
                    </div>

                    <div>
                        <Label for="room_search">Select Room *</Label>
                        <div class="mt-1 flex gap-2">
                            <Input
                                id="room_search"
                                v-model="roomSearch"
                                type="text"
                                placeholder="Filter by room number or type"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                disabled
                            >
                                <Search class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="loadingRooms"
                        class="flex items-center justify-center gap-2 rounded-lg border border-dashed py-10 text-sm text-muted-foreground"
                    >
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Checking which rooms are free…
                    </div>

                    <div
                        v-else-if="availabilityError"
                        class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                    >
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        {{ availabilityError }}
                        <button
                            type="button"
                            class="ml-auto font-medium underline"
                            @click="fetchAvailability"
                        >
                            Retry
                        </button>
                    </div>

                    <div
                        v-else-if="availableRooms.length === 0"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        <BedDouble class="mx-auto mb-2 h-8 w-8 text-gray-400" />
                        No rooms are free for these dates. Try a different date
                        range.
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <button
                                v-for="room in filteredRooms"
                                :key="room.id"
                                type="button"
                                class="rounded-lg border p-3 text-left transition"
                                :class="
                                    form.room_id === String(room.id)
                                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-300'
                                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                "
                                @click="form.room_id = String(room.id)"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="font-semibold text-gray-900">
                                        Room {{ room.room_number }}
                                    </span>
                                    <Check
                                        v-if="form.room_id === String(room.id)"
                                        class="h-4 w-4 text-blue-600"
                                    />
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ room.room_type }} · sleeps
                                    {{ room.capacity }}
                                </div>
                                <div
                                    class="mt-1 text-sm font-medium text-gray-900"
                                >
                                    {{ formatCurrency(room.price_per_night) }}
                                    /night ·
                                    <span class="text-gray-600">
                                        {{
                                            formatCurrency(
                                                room.price_per_night *
                                                    nightsCount,
                                            )
                                        }}
                                        total
                                    </span>
                                </div>
                            </button>
                        </div>

                        <p
                            v-if="filteredRooms.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No free rooms match that search.
                        </p>
                    </template>

                    <InputError :message="form.errors.room_id" />
                </div>

                <!-- Step 3: Guest -->
                <div v-show="step === 3" class="space-y-4">
                    <div>
                        <Label for="guest_name">Guest Name *</Label>
                        <Input
                            id="guest_name"
                            v-model="form.guest_name"
                            type="text"
                            placeholder="John Doe"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.guest_name" />
                    </div>

                    <div>
                        <Label for="guest_email">Email *</Label>
                        <Input
                            id="guest_email"
                            v-model="form.guest_email"
                            type="email"
                            placeholder="john@example.com"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.guest_email" />
                    </div>

                    <div>
                        <Label for="guest_phone">Phone</Label>
                        <Input
                            id="guest_phone"
                            v-model="form.guest_phone"
                            type="tel"
                            placeholder="+234 800 000 0000"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.guest_phone" />
                    </div>

                    <div>
                        <Label for="status">Booking Status *</Label>
                        <Select v-model="form.status">
                            <SelectTrigger id="status" class="mt-1">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="confirmed">
                                    Confirmed
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div>
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            rows="3"
                            placeholder="Special requests, preferences…"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <!-- Step 4: Confirm -->
                <div v-show="step === 4" class="space-y-4">
                    <!-- Review summary -->
                    <div class="rounded-lg border bg-gray-50 p-4">
                        <p
                            class="mb-3 text-xs font-medium tracking-wide text-gray-500 uppercase"
                        >
                            Review
                        </p>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Guest</dt>
                                <dd
                                    class="text-right font-medium text-gray-900"
                                >
                                    {{ form.guest_name || '—' }}
                                    <span class="text-gray-500">
                                        · {{ form.number_of_guests }} guest{{
                                            Number(form.number_of_guests) > 1
                                                ? 's'
                                                : ''
                                        }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Room</dt>
                                <dd
                                    class="text-right font-medium text-gray-900"
                                >
                                    <template v-if="selectedRoom">
                                        Room {{ selectedRoom.room_number }} ·
                                        {{ selectedRoom.room_type }}
                                    </template>
                                    <template v-else>—</template>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Stay</dt>
                                <dd
                                    class="text-right font-medium text-gray-900"
                                >
                                    {{ formatDate(form.check_in_date) }} →
                                    {{ formatDate(form.check_out_date) }}
                                    <span class="text-gray-500">
                                        · {{ nightsCount }} night{{
                                            nightsCount > 1 ? 's' : ''
                                        }}
                                    </span>
                                </dd>
                            </div>
                            <div
                                v-if="discountAmount > 0"
                                class="flex justify-between gap-4"
                            >
                                <dt class="text-gray-500">Discount</dt>
                                <dd
                                    class="text-right font-medium text-amber-700"
                                >
                                    −{{ formatCurrency(discountAmount) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t pt-2"
                            >
                                <dt class="font-medium text-gray-700">Total</dt>
                                <dd
                                    class="text-right text-base font-bold text-gray-900"
                                >
                                    {{ formatCurrency(payableAmount) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Discount (optional) -->
                    <div class="rounded-lg border p-3">
                        <Label for="discount_type">Discount (optional)</Label>
                        <p class="mt-0.5 mb-2 text-xs text-muted-foreground">
                            A discount you add here is submitted for manager
                            approval before it reduces the guest's bill.
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Select v-model="form.discount_type">
                                    <SelectTrigger
                                        id="discount_type"
                                        class="mt-1"
                                    >
                                        <SelectValue
                                            placeholder="No discount"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No discount
                                        </SelectItem>
                                        <SelectItem value="percentage">
                                            Percentage (%)
                                        </SelectItem>
                                        <SelectItem value="fixed">
                                            Fixed amount
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.discount_type"
                                />
                            </div>
                            <div v-if="hasDiscount">
                                <Input
                                    v-model="form.discount_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1"
                                    :placeholder="
                                        form.discount_type === 'percentage'
                                            ? 'e.g. 10'
                                            : 'e.g. 5000'
                                    "
                                />
                                <InputError
                                    :message="form.errors.discount_value"
                                />
                            </div>
                        </div>
                        <div v-if="hasDiscount" class="mt-3">
                            <Input
                                v-model="form.discount_reason"
                                type="text"
                                placeholder="Reason (optional)"
                            />
                            <InputError
                                :message="form.errors.discount_reason"
                            />
                        </div>
                    </div>

                    <!-- Payment (optional) -->
                    <div class="rounded-lg border p-3">
                        <div class="flex items-center gap-2">
                            <input
                                id="process_payment"
                                v-model="form.process_payment"
                                type="checkbox"
                                class="rounded border-gray-300"
                            />
                            <Label for="process_payment" class="cursor-pointer">
                                Take a payment now
                            </Label>
                        </div>

                        <div
                            v-if="form.process_payment"
                            class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"
                        >
                            <div>
                                <Label for="payment_amount">Amount *</Label>
                                <Input
                                    id="payment_amount"
                                    v-model="form.payment_amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="mt-1"
                                    :placeholder="String(payableAmount)"
                                />
                                <InputError
                                    :message="form.errors.payment_amount"
                                />
                            </div>
                            <div>
                                <Label for="payment_method">Method *</Label>
                                <Select v-model="form.payment_method">
                                    <SelectTrigger
                                        id="payment_method"
                                        class="mt-1"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash"
                                            >Cash</SelectItem
                                        >
                                        <SelectItem value="card"
                                            >Card</SelectItem
                                        >
                                        <SelectItem value="bank_transfer">
                                            Bank Transfer
                                        </SelectItem>
                                        <SelectItem value="online">
                                            Online
                                        </SelectItem>
                                        <SelectItem value="other">
                                            Other
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.payment_method"
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
                                />
                            </div>
                            <div>
                                <Label for="payment_reference">Reference</Label>
                                <Input
                                    id="payment_reference"
                                    v-model="form.payment_reference"
                                    type="text"
                                    class="mt-1"
                                    placeholder="Transaction reference"
                                />
                                <InputError
                                    :message="form.errors.payment_reference"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation (pinned) -->
            <div class="space-y-2 border-t pt-4">
                <p
                    v-if="nextBlockedReason && step < totalSteps"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <AlertCircle class="h-3.5 w-3.5 shrink-0" />
                    {{ nextBlockedReason }}
                </p>

                <div class="flex gap-2">
                    <Button
                        v-if="step > 1"
                        variant="outline"
                        @click="goBack"
                        :disabled="form.processing"
                    >
                        <ChevronLeft class="mr-1 h-4 w-4" />
                        Back
                    </Button>

                    <div class="flex-1" />

                    <Button
                        v-if="step < totalSteps"
                        @click="goNext"
                        :disabled="!canGoNext || form.processing"
                    >
                        Next
                        <ChevronRight class="ml-1 h-4 w-4" />
                    </Button>

                    <Button
                        v-else
                        @click="submitForm"
                        :disabled="form.processing"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        {{ form.processing ? 'Creating…' : 'Create Booking' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
