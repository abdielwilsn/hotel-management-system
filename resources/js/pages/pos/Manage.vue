<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookOpen, Plus, Trash2, UserPlus, Wine, Utensils } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index, menu } from '@/routes/pos';
import {
    destroy as destroyOutlet,
    store as storeOutlet,
} from '@/routes/pos/outlets';
import {
    destroy as unassignStaff,
    store as assignStaff,
} from '@/routes/pos/outlets/staff';

type Staff = { id: number; name: string; email: string };
type Outlet = {
    id: number;
    name: string;
    type: string;
    status: string;
    menu_items_count: number;
    categories_count: number;
    staff: Staff[];
};
type Member = { id: number; name: string; email: string; role: string };

type Props = {
    outlets: Outlet[];
    members: Member[];
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

const showOutletForm = ref(false);
const outletToDelete = ref<Outlet | null>(null);
const assignTarget = ref<Outlet | null>(null);

const outletForm = useForm({ name: '', type: 'bar', status: 'active' });
const assignForm = useForm({ user_id: '' });
const deleteForm = useForm({});

const submitOutlet = () => {
    outletForm.post(storeOutlet(props.team.slug).url, {
        preserveScroll: true,
        onSuccess: () => {
            outletForm.reset();
            showOutletForm.value = false;
        },
    });
};

const submitAssign = () => {
    if (!assignTarget.value) {
        return;
    }

    assignForm.post(
        assignStaff([props.team.slug, assignTarget.value.id]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                assignForm.reset();
                assignTarget.value = null;
            },
        },
    );
};

const removeStaff = (outlet: Outlet, staff: Staff) => {
    deleteForm.delete(
        unassignStaff([props.team.slug, outlet.id, staff.id]).url,
        { preserveScroll: true },
    );
};

const deleteOutlet = () => {
    if (!outletToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroyOutlet([props.team.slug, outletToDelete.value.id]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                outletToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="POS Outlets" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <Heading
                title="POS Outlets"
                description="Manage bar & restaurant outlets and their staff"
            />
            <Button
                v-if="!showOutletForm"
                class="gap-2"
                @click="showOutletForm = true"
            >
                <Plus class="size-4" />
                New outlet
            </Button>
        </div>

        <Card v-if="showOutletForm" class="bg-white/90">
            <CardHeader>
                <CardTitle class="text-base">Create Outlet</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                    @submit.prevent="submitOutlet"
                >
                    <div>
                        <Label for="outlet_name">Name *</Label>
                        <Input
                            id="outlet_name"
                            v-model="outletForm.name"
                            class="mt-1"
                        />
                        <InputError
                            :message="outletForm.errors.name"
                            class="mt-2"
                        />
                    </div>
                    <div>
                        <Label>Type *</Label>
                        <Select v-model="outletForm.type">
                            <SelectTrigger class="mt-1">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="bar">Bar</SelectItem>
                                <SelectItem value="restaurant">
                                    Restaurant
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Status *</Label>
                        <Select v-model="outletForm.status">
                            <SelectTrigger class="mt-1">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex gap-2 md:col-span-3">
                        <Button type="submit" :disabled="outletForm.processing">
                            Save outlet
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showOutletForm = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div
            v-if="outlets.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            No outlets yet. Create your Bar and Restaurant to start selling.
        </div>

        <section v-else class="grid gap-4 lg:grid-cols-2">
            <Card
                v-for="outlet in outlets"
                :key="outlet.id"
                class="bg-white/90"
            >
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Wine
                                v-if="outlet.type === 'bar'"
                                class="text-hotel-primary size-5"
                            />
                            <Utensils v-else class="text-hotel-primary size-5" />
                            {{ outlet.name }}
                        </CardTitle>
                        <div class="flex items-center gap-2">
                            <Badge class="rounded-full capitalize">{{
                                outlet.type
                            }}</Badge>
                            <Badge
                                v-if="outlet.status !== 'active'"
                                variant="outline"
                                class="rounded-full"
                            >
                                {{ outlet.status }}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        {{ outlet.categories_count }} categories ·
                        {{ outlet.menu_items_count }} items
                    </p>

                    <div>
                        <p class="mb-2 text-sm font-medium">Assigned staff</p>
                        <div
                            v-if="outlet.staff.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No staff assigned.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <span
                                v-for="staff in outlet.staff"
                                :key="staff.id"
                                class="flex items-center gap-1 rounded-full border px-2 py-1 text-xs"
                            >
                                {{ staff.name }}
                                <button
                                    type="button"
                                    class="text-destructive"
                                    :aria-label="`Remove ${staff.name} from ${outlet.name}`"
                                    @click="removeStaff(outlet, staff)"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="gap-2"
                            @click="assignTarget = outlet"
                        >
                            <UserPlus class="size-4" />
                            Assign staff
                        </Button>
                        <Button size="sm" variant="outline" class="gap-2" as-child>
                            <Link :href="menu([team.slug, outlet.id]).url">
                                <BookOpen class="size-4" />
                                Menu
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="gap-2"
                            @click="outletToDelete = outlet"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Assign staff dialog -->
        <Dialog
            :open="assignTarget !== null"
            @update:open="assignTarget = $event ? assignTarget : null"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Assign staff to {{ assignTarget?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        POS staff can only operate the outlets they are assigned
                        to.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitAssign">
                    <div>
                        <Label>Team member *</Label>
                        <Select v-model="assignForm.user_id">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Select member" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="member in members"
                                    :key="member.id"
                                    :value="String(member.id)"
                                >
                                    {{ member.name }} ({{ member.role }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="assignForm.errors.user_id"
                            class="mt-2"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="assignTarget = null"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="assignForm.processing">
                            Assign
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete outlet dialog -->
        <Dialog
            :open="outletToDelete !== null"
            @update:open="outletToDelete = $event ? outletToDelete : null"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete outlet?</DialogTitle>
                    <DialogDescription>
                        This removes {{ outletToDelete?.name }} and all its
                        menu, orders, and stock records.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="outletToDelete = null">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteOutlet"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
