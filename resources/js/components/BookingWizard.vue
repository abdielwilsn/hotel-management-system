<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ChevronRight, ChevronLeft, Search } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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

type Room = {
    id: number;
    room_number: string;
    room_type: string;
    capacity: number;
    price_per_night: number;
};

type Props = {
    open: boolean;
    rooms: Room[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    submit: [];
}>();

const step = ref(1);
const totalSteps = 3;
const roomSearchDraft = ref('');
const roomSearchTerm = ref('');

const form = useForm({
    room_id: '',
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    number_of_guests: '1',
    check_in_date: new Date().toISOString().split('T')[0],
    check_out_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
    status: 'pending',
    notes: '',
    process_payment: false,
    payment_amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    payment_reference: '',
    payment_notes: '',
});

const selectedRoom = computed(() =>
    props.rooms.find((r) => Number(form.room_id) === r.id),
);

const nightsCount = computed(() => {
    if (!form.check_in_date || !form.check_out_date) return 0;
    const checkIn = new Date(form.check_in_date);
    const checkOut = new Date(form.check_out_date);
    return Math.ceil(
        (checkOut.getTime() - checkIn.getTime()) / (1000 * 60 * 60 * 24),
    );
});

const totalAmount = computed(
    () => nightsCount.value * (selectedRoom.value?.price_per_night || 0),
);

const filteredRooms = computed(() => {
    if (!roomSearchTerm.value.trim()) {
        return props.rooms;
    }

    const searchTerm = roomSearchTerm.value.toLowerCase();

    return props.rooms.filter((room) => {
        return (
            room.room_number.toLowerCase().includes(searchTerm) ||
            room.room_type.toLowerCase().includes(searchTerm)
        );
    });
});

const canGoNext = computed(() => {
    if (step.value === 1) {
        return form.guest_name && form.guest_email && form.number_of_guests;
    }
    if (step.value === 2) {
        return form.room_id && form.check_in_date && form.check_out_date;
    }
    return true;
});

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

const applyRoomSearch = () => {
    roomSearchTerm.value = roomSearchDraft.value.trim();
};

const submitForm = () => {
    form.post(route('bookings.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            step.value = 1;
            emit('close');
            emit('submit');
        },
    });
};

const handleOpenChange = (newOpen: boolean) => {
    if (!newOpen) {
        emit('close');
        form.reset();
        step.value = 1;
        roomSearchDraft.value = '';
        roomSearchTerm.value = '';
    }
};

watch(
    () => props.open,
    (newOpen) => {
        if (newOpen) {
            step.value = 1;
            form.reset();
            roomSearchDraft.value = '';
            roomSearchTerm.value = '';
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Create Booking</DialogTitle>
                <DialogDescription>
                    Step {{ step }} of {{ totalSteps }}</DialogDescription
                >
            </DialogHeader>

            <!-- Progress Bar -->
            <div class="mb-6 flex gap-2">
                <div
                    v-for="s in totalSteps"
                    :key="s"
                    class="h-1 flex-1 rounded-full"
                    :class="s <= step ? 'bg-blue-600' : 'bg-gray-200'"
                />
            </div>

            <!-- Step 1: Guest Details -->
            <div v-show="step === 1" class="space-y-4">
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
                        placeholder="+1 (555) 000-0000"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.guest_phone" />
                </div>

                <div>
                    <Label for="number_of_guests">Number of Guests *</Label>
                    <Select v-model="form.number_of_guests">
                        <SelectTrigger id="number_of_guests" class="mt-1">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">1</SelectItem>
                            <SelectItem value="2">2</SelectItem>
                            <SelectItem value="3">3</SelectItem>
                            <SelectItem value="4">4</SelectItem>
                            <SelectItem value="5">5</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.number_of_guests" />
                </div>
            </div>

            <!-- Step 2: Room & Dates -->
            <div v-show="step === 2" class="space-y-4">
                <div>
                    <Label for="room_search">Select Room *</Label>
                    <div class="mt-1 flex gap-2">
                        <Input
                            id="room_search"
                            v-model="roomSearchDraft"
                            type="text"
                            placeholder="Search by room number or type"
                            @keydown.enter.prevent="applyRoomSearch"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="applyRoomSearch"
                        >
                            <Search class="h-4 w-4" />
                            Search
                        </Button>
                    </div>

                    <div
                        class="mt-3 grid max-h-60 grid-cols-1 gap-2 overflow-y-auto md:grid-cols-2"
                    >
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
                            <div class="font-semibold text-gray-900">
                                Room {{ room.room_number }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ room.room_type }} ·
                                {{ room.capacity }} guests
                            </div>
                            <div class="mt-1 text-sm font-medium text-gray-900">
                                ${{ room.price_per_night }}/night
                            </div>
                        </button>
                    </div>

                    <p
                        v-if="filteredRooms.length === 0"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        No rooms match your search.
                    </p>

                    <InputError :message="form.errors.room_id" />
                </div>

                <div
                    v-if="selectedRoom"
                    class="rounded border border-blue-200 bg-blue-50 p-3 text-sm"
                >
                    <div class="font-medium text-blue-900">
                        {{ selectedRoom.room_type }}
                    </div>
                    <div class="text-blue-700">
                        ${{ selectedRoom.price_per_night }}/night
                    </div>
                </div>

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
                        class="mt-1"
                    />
                    <InputError :message="form.errors.check_out_date" />
                </div>

                <div
                    v-if="nightsCount > 0"
                    class="rounded border border-amber-200 bg-amber-50 p-3 text-sm"
                >
                    <div class="font-medium text-amber-900">
                        Total: {{ nightsCount }} nights
                    </div>
                    <div class="text-amber-700">
                        ${{ totalAmount.toFixed(2) }}
                    </div>
                </div>

                <div>
                    <Label for="status">Status *</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="status" class="mt-1">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="confirmed">Confirmed</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>

            <!-- Step 3: Payment (Optional) -->
            <div v-show="step === 3" class="space-y-4">
                <div class="flex items-center gap-2">
                    <input
                        id="process_payment"
                        v-model="form.process_payment"
                        type="checkbox"
                        class="rounded border-gray-300"
                    />
                    <Label for="process_payment" class="cursor-pointer"
                        >Process payment now?</Label
                    >
                </div>

                <template v-if="form.process_payment">
                    <div
                        class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm"
                    >
                        <div class="font-medium text-green-900">
                            Booking Total
                        </div>
                        <div class="text-2xl font-bold text-green-700">
                            ${{ totalAmount.toFixed(2) }}
                        </div>
                    </div>

                    <div>
                        <Label for="payment_amount">Payment Amount *</Label>
                        <Input
                            id="payment_amount"
                            v-model="form.payment_amount"
                            type="number"
                            step="0.01"
                            :placeholder="String(totalAmount)"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.payment_amount" />
                    </div>

                    <div>
                        <Label for="payment_method">Payment Method *</Label>
                        <Select v-model="form.payment_method">
                            <SelectTrigger id="payment_method" class="mt-1">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="credit_card"
                                    >Credit Card</SelectItem
                                >
                                <SelectItem value="debit_card"
                                    >Debit Card</SelectItem
                                >
                                <SelectItem value="bank_transfer"
                                    >Bank Transfer</SelectItem
                                >
                                <SelectItem value="check">Check</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.payment_method" />
                    </div>

                    <div>
                        <Label for="payment_reference"
                            >Reference/Confirmation #</Label
                        >
                        <Input
                            id="payment_reference"
                            v-model="form.payment_reference"
                            type="text"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.payment_reference" />
                    </div>
                </template>

                <div>
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        rows="3"
                        placeholder="Special requests, preferences..."
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex gap-2 border-t pt-4">
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
                    {{ form.processing ? 'Creating...' : 'Create Booking' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
