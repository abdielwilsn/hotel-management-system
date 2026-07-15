<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, store } from '@/routes/departments';
import type {
    Department,
    DepartmentMember,
    DepartmentStatus,
    Team,
} from '@/types';

type Props = {
    departments: Department[];
    members: DepartmentMember[];
    statuses: DepartmentStatus[];
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
                title: 'Departments',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const statusLabel = (status: DepartmentStatus) =>
    status.charAt(0).toUpperCase() + status.slice(1);

const showCreateDialog = ref(false);
</script>

<template>
    <Head title="Departments" />

    <h1 class="sr-only">Departments</h1>

    <div class="space-y-6 p-0 sm:space-y-8 sm:p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                title="Departments"
                description="Organize hotel operations across teams and managers"
            />

            <Dialog v-model:open="showCreateDialog">
                <DialogTrigger as-child>
                    <Button class="w-full sm:w-auto">
                        <Plus class="h-4 w-4" />
                        New department
                    </Button>
                </DialogTrigger>

                <DialogContent
                    class="max-h-[90dvh] overflow-y-auto sm:max-w-lg"
                >
                    <DialogHeader>
                        <DialogTitle>New department</DialogTitle>
                        <DialogDescription>
                            Create a department and optionally assign a manager.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="store.form(props.team.slug)"
                        reset-on-success
                        class="grid gap-4 sm:grid-cols-2"
                        @success="showCreateDialog = false"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autocomplete="off"
                                placeholder="Housekeeping"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                class="h-10 rounded-md border bg-background px-3 py-2 text-sm"
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

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows="3"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                                placeholder="Daily cleaning operations and room turnaround"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="manager_id">Manager</Label>
                            <select
                                id="manager_id"
                                name="manager_id"
                                class="h-10 rounded-md border bg-background px-3 py-2 text-sm"
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
                            class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="showCreateDialog = false"
                            >
                                Cancel
                            </Button>
                            <Button :disabled="processing" type="submit">
                                Create department
                            </Button>
                        </div>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div class="space-y-3">
            <Card
                v-for="department in departments"
                :key="department.id"
                class="border-sidebar-border/70"
            >
                <CardContent
                    class="flex flex-col items-start justify-between gap-3 py-5 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Building2
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <p class="font-medium">{{ department.name }}</p>
                            <Badge
                                :variant="
                                    department.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ statusLabel(department.status) }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{
                                department.description ||
                                'No description added yet.'
                            }}
                        </p>
                        <p
                            class="text-xs wrap-break-word text-muted-foreground"
                        >
                            Manager:
                            {{
                                department.manager
                                    ? `${department.manager.name} (${department.manager.email})`
                                    : 'Not assigned'
                            }}
                        </p>
                    </div>

                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="w-full sm:w-auto"
                    >
                        <Link
                            :href="
                                edit([
                                    currentTeam?.slug ?? props.team.slug,
                                    department.id,
                                ])
                            "
                        >
                            Manage
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <p
                v-if="departments.length === 0"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No departments created yet.
            </p>
        </div>
    </div>
</template>
