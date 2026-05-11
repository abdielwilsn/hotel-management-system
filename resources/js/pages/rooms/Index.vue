<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
import { Plus, Home, Trash2, Edit } from 'lucide-vue-next';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, store, edit, destroy } from '@/routes/rooms';
import type { Team } from '@/types';

type Room = {
    id: number;
    room_number: string;
    floor: number;
    room_type: string;
    capacity: number;
    price_per_night: number;
    status: string;
    description: string | null;
    active_booking: {
        id: number;
        guest_name: string;
        check_in_date: string | null;
        check_out_date: string | null;
        status: string;
    } | null;
};

type Props = {
    rooms: Room[];
    roomTypes: string[];
    statuses: string[];
    filters: {
        search?: string | null;
        room_type?: string | null;
        status?: string | null;
        floor?: number | null;
        min_capacity?: number | null;
        max_price?: number | null;
    };
    occupancySummary: {
        occupied_rooms: number;
        reserved_rooms: number;
        checked_in_bookings: number;
        active_reservations: number;
    };
    team: {
        id: number;
        slug: string;
        name: string;
    };
};

const props = defineProps<Props>();

const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Rooms',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const roomToDelete = ref<Room | null>(null);

const filtersForm = useForm({
    search: props.filters.search ?? '',
    room_type: props.filters.room_type ?? 'all',
    status: props.filters.status ?? 'all',
    floor:
        props.filters.floor !== null && props.filters.floor !== undefined
            ? String(props.filters.floor)
            : '',
    min_capacity:
        props.filters.min_capacity !== null &&
        props.filters.min_capacity !== undefined
            ? String(props.filters.min_capacity)
            : '',
    max_price:
        props.filters.max_price !== null &&
        props.filters.max_price !== undefined
            ? String(props.filters.max_price)
            : '',
});

const form = useForm({
    room_number: '',
    floor: '',
    room_type: 'double',
    capacity: '',
    price_per_night: '',
    status: 'available',
    description: '',
});

const deleteForm = useForm({});

const filterStatuses = computed(() => [
    'available',
    'reserved',
    'occupied',
    'maintenance',
    'cleaning',
]);

const hasActiveFilters = computed(() =>
    Boolean(
        filtersForm.search ||
        filtersForm.room_type !== 'all' ||
        filtersForm.status !== 'all' ||
        filtersForm.floor ||
        filtersForm.min_capacity ||
        filtersForm.max_price,
    ),
);

const roomTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        single: 'Single',
        double: 'Double',
        suite: 'Suite',
        deluxe: 'Deluxe',
        penthouse: 'Penthouse',
    };
    return labels[type] || type;
};

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        available: 'bg-green-100 text-green-800',
        occupied: 'bg-blue-100 text-blue-800',
        reserved: 'bg-amber-100 text-amber-800',
        maintenance: 'bg-orange-100 text-orange-800',
        cleaning: 'bg-purple-100 text-purple-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const statusLabel = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const applyFilters = () => {
    router.get(
        index(props.team.slug).url,
        {
            search: filtersForm.search || undefined,
            room_type:
                filtersForm.room_type !== 'all'
                    ? filtersForm.room_type
                    : undefined,
            status:
                filtersForm.status !== 'all' ? filtersForm.status : undefined,
            floor: filtersForm.floor ? Number(filtersForm.floor) : undefined,
            min_capacity: filtersForm.min_capacity
                ? Number(filtersForm.min_capacity)
                : undefined,
            max_price: filtersForm.max_price
                ? Number(filtersForm.max_price)
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
    filtersForm.room_type = 'all';
    filtersForm.status = 'all';
    filtersForm.floor = '';
    filtersForm.min_capacity = '';
    filtersForm.max_price = '';
    applyFilters();
};

const formatDate = (date: string | null) => {
    if (!date) {
        return 'N/A';
    }

    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
        },
    });
};

const deleteRoom = () => {
    if (!roomToDelete.value) return;
    deleteForm.delete(destroy([props.team.slug, roomToDelete.value.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            roomToDelete.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            icon="Home"
            title="Room Management"
            description="Manage your hotel rooms and availability"
        />

        <Card>
            <CardHeader>
                <CardTitle>Filter Rooms</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <Label for="room_filter_search">Search</Label>
                            <Input
                                id="room_filter_search"
                                v-model="filtersForm.search"
                                class="mt-1"
                                placeholder="Room number, description, guest"
                            />
                        </div>

                        <div>
                            <Label for="room_filter_type">Room Type</Label>
                            <Select v-model="filtersForm.room_type">
                                <SelectTrigger
                                    id="room_filter_type"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any type</SelectItem
                                    >
                                    <SelectItem
                                        v-for="type in roomTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ roomTypeLabel(type) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="room_filter_status">Status</Label>
                            <Select v-model="filtersForm.status">
                                <SelectTrigger
                                    id="room_filter_status"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any status</SelectItem
                                    >
                                    <SelectItem
                                        v-for="status in filterStatuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ statusLabel(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="room_filter_floor">Floor</Label>
                            <Input
                                id="room_filter_floor"
                                v-model="filtersForm.floor"
                                type="number"
                                min="1"
                                step="1"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="room_filter_min_capacity"
                                >Min Capacity</Label
                            >
                            <Input
                                id="room_filter_min_capacity"
                                v-model="filtersForm.min_capacity"
                                type="number"
                                min="1"
                                step="1"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="room_filter_max_price"
                                >Max Price / Night</Label
                            >
                            <Input
                                id="room_filter_max_price"
                                v-model="filtersForm.max_price"
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Currently Occupied Rooms</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ occupancySummary.occupied_rooms }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Reserved Rooms (Today)</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ occupancySummary.reserved_rooms }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Checked-in Bookings</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ occupancySummary.checked_in_bookings }}
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Active Reservations (Today)</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ occupancySummary.active_reservations }}
                </CardContent>
            </Card>
        </div>

        <!-- Create useForm -->
        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Add New Room</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="room_number">Room Number *</Label>
                            <Input
                                id="room_number"
                                v-model="form.room_number"
                                type="text"
                                class="mt-1"
                                placeholder="101"
                            />
                            <InputError
                                :message="form.errors.room_number"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="floor">Floor *</Label>
                            <Input
                                id="floor"
                                v-model="form.floor"
                                type="number"
                                class="mt-1"
                                placeholder="1"
                                min="1"
                            />
                            <InputError
                                :message="form.errors.floor"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="room_type">Room Type *</Label>
                            <Select v-model="form.room_type">
                                <SelectTrigger id="room_type" class="mt-1">
                                    <SelectValue
                                        placeholder="Select room type"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="type in roomTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ roomTypeLabel(type) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.room_type"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="capacity">Capacity *</Label>
                            <Input
                                id="capacity"
                                v-model="form.capacity"
                                type="number"
                                class="mt-1"
                                placeholder="2"
                                min="1"
                            />
                            <InputError
                                :message="form.errors.capacity"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="price_per_night"
                                >Price Per Night *</Label
                            >
                            <Input
                                id="price_per_night"
                                v-model="form.price_per_night"
                                type="number"
                                class="mt-1"
                                placeholder="100.00"
                                step="0.01"
                                min="0"
                            />
                            <InputError
                                :message="form.errors.price_per_night"
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
                                        v-for="s in statuses"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{ statusLabel(s) }}
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
                        <Label for="description">Description</Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            type="text"
                            class="mt-1"
                            placeholder="Add room details..."
                        />
                        <InputError
                            :message="form.errors.description"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex gap-2 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-hotel-primary hover:bg-hotel-primary/90"
                        >
                            {{
                                form.processing ? 'Creating...' : 'Create Room'
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateForm = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Create Button -->
        <div v-else class="flex justify-end">
            <Button @click="showCreateForm = true" class="gap-2">
                <Plus class="h-4 w-4" />
                Add Room
            </Button>
        </div>

        <!-- Rooms Grid -->
        <div
            v-if="rooms.length > 0"
            class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <Card v-for="room in rooms" :key="room.id" class="flex flex-col">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle class="text-lg">
                                Room {{ room.room_number }}
                            </CardTitle>
                            <p class="mt-1 text-sm text-gray-600">
                                Floor {{ room.floor }}
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="grow space-y-3 pb-3">
                    <div class="flex gap-2">
                        <Badge :class="statusColor(room.status)">
                            {{ room.status }}
                        </Badge>
                        <Badge variant="outline">
                            {{ roomTypeLabel(room.room_type) }}
                        </Badge>
                    </div>
                    <div class="space-y-1 text-sm text-gray-600">
                        <p>
                            <strong>Capacity:</strong>
                            {{ room.capacity }} guest{{
                                room.capacity > 1 ? 's' : ''
                            }}
                        </p>
                        <p>
                            <strong>Price:</strong>
                            ₦{{ Number(room.price_per_night).toFixed(2) }} /
                            night
                        </p>
                        <p v-if="room.description">
                            <strong>Description:</strong>
                            {{ room.description }}
                        </p>
                        <template
                            v-if="
                                room.status === 'occupied' &&
                                room.active_booking
                            "
                        >
                            <p>
                                <strong>Occupied By:</strong>
                                {{ room.active_booking.guest_name }}
                            </p>
                            <p>
                                <strong>Stay:</strong>
                                {{
                                    formatDate(
                                        room.active_booking.check_in_date,
                                    )
                                }}
                                -
                                {{
                                    formatDate(
                                        room.active_booking.check_out_date,
                                    )
                                }}
                            </p>
                        </template>
                        <template
                            v-if="
                                room.status === 'reserved' &&
                                room.active_booking
                            "
                        >
                            <p>
                                <strong>Reserved For:</strong>
                                {{ room.active_booking.guest_name }}
                            </p>
                            <p>
                                <strong>Reservation:</strong>
                                {{
                                    formatDate(
                                        room.active_booking.check_in_date,
                                    )
                                }}
                                -
                                {{
                                    formatDate(
                                        room.active_booking.check_out_date,
                                    )
                                }}
                            </p>
                            <p>
                                <strong>Booking Status:</strong>
                                {{ room.active_booking.status }}
                            </p>
                        </template>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Link
                            :href="edit([props.team.slug, room.id]).url"
                            class="flex-1"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full gap-2"
                            >
                                <Edit class="h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="
                                roomToDelete = room;
                                showDeleteDialog = true;
                            "
                            class="text-red-600 hover:bg-red-50 hover:text-red-700"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else class="border-dashed">
            <CardContent class="pt-12 pb-12 text-center">
                <Home class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    No rooms yet
                </h3>
                <p class="mb-4 text-gray-600">
                    Start by adding your first room to manage availability
                </p>
                <Button @click="showCreateForm = true" class="gap-2">
                    <Plus class="h-4 w-4" />
                    Add Your First Room
                </Button>
            </CardContent>
        </Card>

        <!-- Delete Dialog -->
        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Room?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove
                        <strong>Room {{ roomToDelete?.room_number }}</strong
                        >? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        @click="deleteRoom"
                        :disabled="deleteForm.processing"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
