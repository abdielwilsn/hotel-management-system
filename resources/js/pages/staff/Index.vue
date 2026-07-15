<script setup lang="ts">
import { useForm, Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Users, Trash2, Edit } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { index, store, edit, destroy } from '@/routes/staff';
import type { Staff, StaffRole, StaffStatus, Team } from '@/types';

type Props = {
    staff: Staff[];
    departments: Array<{ id: number; name: string }>;
    roles: StaffRole[];
    statuses: StaffStatus[];
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
                title: 'Staff',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const staffToDelete = ref<Staff | null>(null);

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    department_id: '',
    role: 'receptionist' as StaffRole,
    employment_date: '',
    status: 'active' as StaffStatus,
});

const deleteForm = useForm({});

const roleLabel = (role: StaffRole) => {
    const labels: Record<StaffRole, string> = {
        receptionist: 'Receptionist',
        housekeeping: 'Housekeeping',
        accountant: 'Accountant',
        manager: 'Manager',
        admin: 'Admin',
    };

    return labels[role] || role;
};

const statusColor = (status: StaffStatus) => {
    const colors: Record<StaffStatus, string> = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        on_leave: 'bg-yellow-100 text-yellow-800',
    };

    return colors[status] || 'bg-gray-100 text-gray-800';
};

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
        },
    });
};

const deleteStaff = () => {
    if (!staffToDelete.value) {
return;
}

    deleteForm.delete(destroy([props.team.slug, staffToDelete.value.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            staffToDelete.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            icon="Users"
            title="Staff Management"
            description="Manage your team members and staff"
        />

        <!-- Create useForm -->
        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Add New Staff Member</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="full_name">Full Name *</Label>
                            <Input
                                id="full_name"
                                v-model="form.full_name"
                                type="text"
                                class="mt-1"
                                placeholder="John Doe"
                            />
                            <InputError
                                :message="form.errors.full_name"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="email">Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1"
                                placeholder="john@example.com"
                            />
                            <InputError
                                :message="form.errors.email"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-1"
                                placeholder="+1234567890"
                            />
                            <InputError
                                :message="form.errors.phone"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="department_id">Department *</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger id="department_id" class="mt-1">
                                    <SelectValue
                                        placeholder="Select a department"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.department_id"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="role">Role *</Label>
                            <Select v-model="form.role">
                                <SelectTrigger id="role" class="mt-1">
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="r in roles"
                                        :key="r"
                                        :value="r"
                                    >
                                        {{ roleLabel(r) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.role"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="employment_date"
                                >Employment Date *</Label
                            >
                            <Input
                                id="employment_date"
                                v-model="form.employment_date"
                                type="date"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.employment_date"
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
                                        {{
                                            s.charAt(0).toUpperCase() +
                                            s.slice(1).replace('_', ' ')
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.status"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-4">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-black hover:bg-hotel-primary/90"
                        >
                            {{
                                form.processing
                                    ? 'Creating...'
                                    : 'Create Staff Member'
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
                Add Staff Member
            </Button>
        </div>

        <!-- Staff List -->
        <div
            v-if="staff.length > 0"
            class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <Card v-for="s in staff" :key="s.id" class="flex flex-col">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle class="text-lg">{{
                                s.full_name
                            }}</CardTitle>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ s.email }}
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="flex-grow space-y-3 pb-3">
                    <div class="flex gap-2">
                        <Badge :class="statusColor(s.status)">
                            {{ s.status.replace('_', ' ') }}
                        </Badge>
                        <Badge variant="outline">{{ roleLabel(s.role) }}</Badge>
                    </div>
                    <div class="space-y-1 text-sm text-gray-600">
                        <p>
                            <strong>Department:</strong>
                            {{ s.department?.name }}
                        </p>
                        <p><strong>Phone:</strong> {{ s.phone || 'N/A' }}</p>
                        <p>
                            <strong>Start Date:</strong>
                            {{
                                new Date(s.employment_date).toLocaleDateString()
                            }}
                        </p>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Link
                            :href="edit([props.team.slug, s.id]).url"
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
                                staffToDelete = s;
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
                <Users class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <h3 class="mb-1 text-lg font-semibold text-gray-900">
                    No staff members yet
                </h3>
                <p class="mb-4 text-gray-600">
                    Start by adding your first team member
                </p>
                <Button @click="showCreateForm = true" class="gap-2">
                    <Plus class="h-4 w-4" />
                    Add Your First Staff Member
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
                    <DialogTitle>Remove Staff Member?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove
                        <strong>{{ staffToDelete?.full_name }}</strong> from
                        your staff? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        @click="deleteStaff"
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
