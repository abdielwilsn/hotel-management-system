<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3';
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
    } | null;
};

type Props = {
    rooms: Room[];
    roomTypes: string[];
    statuses: string[];
    occupancySummary: {
        occupied_rooms: number;
        checked_in_bookings: number;
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
        maintenance: 'bg-orange-100 text-orange-800',
        cleaning: 'bg-purple-100 text-purple-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
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

        <div class="grid gap-4 sm:grid-cols-2">
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
                        >Checked-in Bookings</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ occupancySummary.checked_in_bookings }}
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
                                        {{ roomTypeLabel(s) }}
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
                <CardContent class="flex-grow space-y-3 pb-3">
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
