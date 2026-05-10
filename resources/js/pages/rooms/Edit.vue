<script setup lang="ts">
import { useForm, Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, Trash2, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { edit, index, update, destroy } from '@/routes/rooms';
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
};

type Props = {
    room: Room;
    roomTypes: string[];
    statuses: string[];
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
            {
                title: `Room ${props.room?.room_number}`,
                href: '#',
            },
        ],
    }),
});

const showDeleteDialog = ref(false);

const form = useForm({
    room_number: props.room.room_number,
    floor: String(props.room.floor),
    room_type: props.room.room_type,
    capacity: String(props.room.capacity),
    price_per_night: String(props.room.price_per_night),
    status: props.room.status,
    description: props.room.description || '',
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

const submit = () => {
    const payload = {
        ...form.data(),
        floor: Number(form.floor),
        capacity: Number(form.capacity),
        price_per_night: Number(form.price_per_night),
    };
    form.patch(update([props.team.slug, props.room.id]).url);
};

const deleteRoom = () => {
    deleteForm.delete(destroy([props.team.slug, props.room.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Back Link -->
        <Link
            :href="index(props.team.slug).url"
            class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900"
        >
            <ChevronLeft class="h-4 w-4" />
            Back to Rooms
        </Link>

        <!-- Room Status -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    Room {{ room.room_number }}
                </h1>
                <p class="mt-1 text-gray-600">Floor {{ room.floor }}</p>
            </div>
            <Badge :class="statusColor(room.status)">
                {{ room.status }}
            </Badge>
        </div>

        <!-- Edit useForm -->
        <Card>
            <CardHeader>
                <CardTitle>Edit Room Details</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Basic Information
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="room_number">Room Number *</Label>
                                <Input
                                    id="room_number"
                                    v-model="form.room_number"
                                    type="text"
                                    class="mt-1"
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
                                    min="1"
                                />
                                <InputError
                                    :message="form.errors.capacity"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Status -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Pricing & Status
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="price_per_night"
                                    >Price Per Night *</Label
                                >
                                <Input
                                    id="price_per_night"
                                    v-model="form.price_per_night"
                                    type="number"
                                    class="mt-1"
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
                                        <SelectValue
                                            placeholder="Select status"
                                        />
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
                    </div>

                    <!-- Description -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Description</h3>
                        <div>
                            <Label for="description">Room Description</Label>
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
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-hotel-primary hover:bg-hotel-primary/90 gap-2"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                        <Link
                            :href="index(props.team.slug).url"
                            class="inline-flex"
                        >
                            <Button type="button" variant="outline"
                                >Cancel</Button
                            >
                        </Link>
                        <Button
                            type="button"
                            @click="showDeleteDialog = true"
                            class="ml-auto gap-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                            variant="ghost"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete Room
                        </Button>
                    </div>
                </form>
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
                        <strong>Room {{ room.room_number }}</strong
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
