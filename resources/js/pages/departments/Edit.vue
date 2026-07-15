<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, edit, index, update } from '@/routes/departments';
import type { DepartmentMember, DepartmentStatus, Team } from '@/types';

type Props = {
    department: {
        id: number;
        name: string;
        description: string | null;
        status: DepartmentStatus;
        manager_id: number | null;
    };
    members: DepartmentMember[];
    statuses: DepartmentStatus[];
    team: {
        id: number;
        slug: string;
        name: string;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: Props & { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Departments',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.department.name,
                href: edit([props.team.slug, props.department.id]),
            },
        ],
    }),
});

const statusLabel = (status: DepartmentStatus) =>
    status.charAt(0).toUpperCase() + status.slice(1);

const deleteDepartment = () => {
    router.visit(destroy([props.team.slug, props.department.id]), {
        method: 'delete',
    });
};
</script>

<template>
    <Head :title="`Department: ${department.name}`" />

    <h1 class="sr-only">Edit Department</h1>

    <div class="space-y-6 p-0 sm:space-y-8 sm:p-4">
        <Heading
            variant="small"
            title="Department settings"
            description="Update department details and manager assignment"
        />

        <Card>
            <CardHeader>
                <CardTitle>Edit department</CardTitle>
                <CardDescription>
                    Keep department information accurate for operations and
                    reporting.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <Form
                    v-bind="update.form([team.slug, department.id])"
                    class="grid gap-4 md:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="department.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            class="h-10 rounded-md border bg-background px-3 py-2 text-sm"
                            :default-value="department.status"
                            required
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ statusLabel(status) }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                            :default-value="department.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="manager_id">Manager</Label>
                        <select
                            id="manager_id"
                            name="manager_id"
                            class="h-10 rounded-md border bg-background px-3 py-2 text-sm"
                            :default-value="department.manager_id ?? ''"
                        >
                            <option value="">No manager assigned</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError :message="errors.manager_id" />
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-3 md:col-span-2"
                    >
                        <Button :disabled="processing" type="submit">
                            Save changes
                        </Button>

                        <Button
                            type="button"
                            variant="destructive"
                            @click="deleteDepartment"
                        >
                            Delete department
                        </Button>

                        <Button as-child type="button" variant="ghost">
                            <Link :href="index(team.slug)">Back</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
