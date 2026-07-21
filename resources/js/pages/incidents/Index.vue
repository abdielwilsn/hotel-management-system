<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus } from 'lucide-vue-next';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, resolve, store } from '@/routes/incidents';

type Option = { value: string; label: string };

type Incident = {
    id: number;
    title: string;
    description: string;
    category_label: string;
    severity: string;
    severity_label: string;
    status: string;
    status_label: string;
    is_open: boolean;
    occurred_at: string;
    department: { id: number; name: string } | null;
    room_number: string | null;
    reported_by: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
};

type Props = {
    incidents: Incident[];
    departments: Array<{ id: number; name: string }>;
    categories: Option[];
    severities: Option[];
    statuses: Option[];
    filters: { status?: string | null; severity?: string | null };
    canReport: boolean;
    canResolve: boolean;
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: Props) => ({
        breadcrumbs: [{ title: 'Incidents', href: index(props.team.slug) }],
    }),
});

const reporting = ref(false);
const resolving = ref<Incident | null>(null);

const openCount = computed(
    () => props.incidents.filter((i) => i.is_open).length,
);

/** Worst first, so the eye lands on what matters. */
const severityTone = (severity: string) =>
    ({
        critical: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-amber-100 text-amber-800',
        low: 'bg-slate-100 text-slate-700',
    })[severity] ?? 'bg-slate-100 text-slate-700';

const statusTone = (status: string) =>
    ({
        open: 'bg-red-50 text-red-700',
        investigating: 'bg-blue-50 text-blue-700',
        resolved: 'bg-emerald-50 text-emerald-700',
        dismissed: 'bg-slate-50 text-slate-600',
    })[status] ?? 'bg-slate-50 text-slate-600';

const now = () => new Date().toISOString().slice(0, 16);

const formatWhen = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

const applyFilter = (key: string, value: string) => {
    router.get(
        index(props.team.slug).url,
        { ...props.filters, [key]: value || undefined },
        { preserveScroll: true, preserveState: true },
    );
};

const submitResolution = (event: Event) => {
    if (!resolving.value) {
        return;
    }

    const data = new FormData(event.target as HTMLFormElement);
    const status = String(data.get('status'));
    const notes = String(data.get('resolution_notes') ?? '');

    router.patch(
        resolve([props.team.slug, resolving.value.id]).url,
        { status, resolution_notes: notes },
        {
            preserveScroll: true,
            onSuccess: () => {
                resolving.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Incident reports" />

    <div class="space-y-6 p-0 sm:space-y-8 sm:p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Incident reports"
                :description="`${openCount} still open across your departments`"
            />

            <Button v-if="canReport" @click="reporting = true">
                <Plus class="h-4 w-4" />
                Report an incident
            </Button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3">
            <select
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                :value="filters.status ?? ''"
                @change="
                    applyFilter(
                        'status',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">
                    {{ s.label }}
                </option>
            </select>

            <select
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                :value="filters.severity ?? ''"
                @change="
                    applyFilter(
                        'severity',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All severities</option>
                <option v-for="s in severities" :key="s.value" :value="s.value">
                    {{ s.label }}
                </option>
            </select>
        </div>

        <!-- The log -->
        <div v-if="incidents.length === 0" class="rounded-lg border p-8">
            <p class="text-center text-sm text-muted-foreground">
                Nothing has been reported. That is good news.
            </p>
        </div>

        <div v-else class="space-y-3">
            <Card v-for="incident in incidents" :key="incident.id">
                <CardContent class="space-y-3 p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <AlertTriangle
                                    v-if="incident.severity === 'critical'"
                                    class="h-4 w-4 text-red-600"
                                />
                                <span class="font-medium">
                                    {{ incident.title }}
                                </span>
                                <Badge
                                    class="rounded-full"
                                    :class="severityTone(incident.severity)"
                                >
                                    {{ incident.severity_label }}
                                </Badge>
                                <Badge
                                    class="rounded-full"
                                    :class="statusTone(incident.status)"
                                >
                                    {{ incident.status_label }}
                                </Badge>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ incident.department?.name }} ·
                                {{ incident.category_label }} ·
                                {{ formatWhen(incident.occurred_at) }}
                                <template v-if="incident.room_number">
                                    · Room {{ incident.room_number }}
                                </template>
                            </p>
                        </div>

                        <Button
                            v-if="canResolve"
                            size="sm"
                            variant="outline"
                            @click="resolving = incident"
                        >
                            {{ incident.is_open ? 'Update' : 'Reopen' }}
                        </Button>
                    </div>

                    <p class="text-sm whitespace-pre-line">
                        {{ incident.description }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        Reported by {{ incident.reported_by ?? 'Unknown' }}
                        <template v-if="incident.resolved_by">
                            · closed by {{ incident.resolved_by }}
                        </template>
                    </p>

                    <p
                        v-if="incident.resolution_notes"
                        class="rounded-md bg-muted p-2 text-sm"
                    >
                        {{ incident.resolution_notes }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Report -->
    <Dialog v-model:open="reporting">
        <DialogContent class="max-h-[90dvh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Report an incident</DialogTitle>
                <DialogDescription>
                    Log what happened while it is fresh. Your department
                    managers pick it up from here.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="store.form(team.slug)"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="reporting = false"
            >
                <div class="grid gap-2">
                    <Label for="department_id">Department</Label>
                    <select
                        id="department_id"
                        name="department_id"
                        required
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option
                            v-for="d in departments"
                            :key="d.id"
                            :value="d.id"
                        >
                            {{ d.name }}
                        </option>
                    </select>
                    <InputError :message="errors.department_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="title">What happened</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        placeholder="Broken shower in 204"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Details</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        required
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="category">Category</Label>
                        <select
                            id="category"
                            name="category"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="c in categories"
                                :key="c.value"
                                :value="c.value"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                        <InputError :message="errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="severity">Severity</Label>
                        <select
                            id="severity"
                            name="severity"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="s in severities"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                        <InputError :message="errors.severity" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="occurred_at">When did it happen</Label>
                    <Input
                        id="occurred_at"
                        name="occurred_at"
                        type="datetime-local"
                        :default-value="now()"
                        required
                    />
                    <InputError :message="errors.occurred_at" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="reporting = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        File report
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Resolve -->
    <Dialog
        :open="resolving !== null"
        @update:open="(open) => !open && (resolving = null)"
    >
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ resolving?.title }}</DialogTitle>
                <DialogDescription>
                    Move this on, and say what was done about it.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitResolution">
                <div class="grid gap-2">
                    <Label for="resolve_status">Status</Label>
                    <select
                        id="resolve_status"
                        name="status"
                        :value="resolving?.status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="resolution_notes">What was done</Label>
                    <textarea
                        id="resolution_notes"
                        name="resolution_notes"
                        rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        :value="resolving?.resolution_notes ?? ''"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="resolving = null"
                    >
                        Cancel
                    </Button>
                    <Button type="submit">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
