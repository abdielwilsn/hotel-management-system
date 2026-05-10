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
import { edit, index, update, destroy } from '@/routes/staff';
import type { Staff, StaffRole, StaffStatus, Team } from '@/types';

type Props = {
    staff: Staff;
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
            {
                title: props.staff?.full_name,
                href: '#',
            },
        ],
    }),
});

const showDeleteDialog = ref(false);

const form = useForm({
    full_name: props.staff.full_name,
    email: props.staff.email,
    phone: props.staff.phone || '',
    address: props.staff.address || '',
    gender: props.staff.gender || undefined,
    department_id: String(props.staff.department_id),
    role: props.staff.role,
    employment_date: props.staff.employment_date,
    salary: props.staff.salary ? String(props.staff.salary) : '',
    emergency_contact_name: props.staff.emergency_contact_name || '',
    emergency_contact_phone: props.staff.emergency_contact_phone || '',
    status: props.staff.status,
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

const submit = () => {
    const payload = {
        ...form.data(),
        salary: form.salary ? Number(form.salary) : null,
    };
    form.patch(update([props.team.slug, props.staff.id]).url);
};

const deleteStaff = () => {
    deleteForm.delete(destroy([props.team.slug, props.staff.id]).url, {
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
            Back to Staff
        </Link>

        <!-- Edit useForm -->
        <Card>
            <CardHeader>
                <CardTitle>Edit Staff Member</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Personal Information -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Personal Information
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="full_name">Full Name *</Label>
                                <Input
                                    id="full_name"
                                    v-model="form.full_name"
                                    type="text"
                                    class="mt-1"
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
                                <Label for="gender">Gender</Label>
                                <Select v-model="form.gender">
                                    <SelectTrigger id="gender" class="mt-1">
                                        <SelectValue
                                            placeholder="Select gender"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male"
                                            >Male</SelectItem
                                        >
                                        <SelectItem value="female"
                                            >Female</SelectItem
                                        >
                                        <SelectItem value="other"
                                            >Other</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.gender"
                                    class="mt-2"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <Label for="address">Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    type="text"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="form.errors.address"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Employment Information
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="department_id">Department *</Label>
                                <Select v-model="form.department_id">
                                    <SelectTrigger
                                        id="department_id"
                                        class="mt-1"
                                    >
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
                                        <SelectValue
                                            placeholder="Select a role"
                                        />
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
                                <Label for="salary">Salary</Label>
                                <Input
                                    id="salary"
                                    v-model="form.salary"
                                    type="number"
                                    step="0.01"
                                    class="mt-1"
                                    placeholder="0.00"
                                />
                                <InputError
                                    :message="form.errors.salary"
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
                    </div>

                    <!-- Emergency Contact -->
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">
                            Emergency Contact
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="emergency_contact_name">Name</Label>
                                <Input
                                    id="emergency_contact_name"
                                    v-model="form.emergency_contact_name"
                                    type="text"
                                    class="mt-1"
                                    placeholder="Contact name"
                                />
                                <InputError
                                    :message="
                                        form.errors.emergency_contact_name
                                    "
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <Label for="emergency_contact_phone"
                                    >Phone</Label
                                >
                                <Input
                                    id="emergency_contact_phone"
                                    v-model="form.emergency_contact_phone"
                                    type="text"
                                    class="mt-1"
                                    placeholder="+1234567890"
                                />
                                <InputError
                                    :message="
                                        form.errors.emergency_contact_phone
                                    "
                                    class="mt-2"
                                />
                            </div>
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
                            Delete Staff Member
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
                    <DialogTitle>Remove Staff Member?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove
                        <strong>{{ staff.full_name }}</strong> from your staff?
                        This action cannot be undone.
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
