<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { Edit, Plus, ReceiptText, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import type {PaginationMeta} from '@/components/Pagination.vue';
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
import { useFormatters } from '@/lib/format';
import { destroy, edit, index, store } from '@/routes/expenses';

type Expense = {
    id: number;
    title: string;
    category: string;
    amount: number;
    incurred_date: string;
    vendor: string | null;
    status: string;
    description: string | null;
};

type Props = {
    expenses: Expense[];
    pagination: PaginationMeta;
    categories: string[];
    statuses: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Expenses',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const expenseToDelete = ref<Expense | null>(null);

const form = useForm({
    title: '',
    category: props.categories[0] ?? 'other',
    amount: '0',
    incurred_date: '',
    vendor: '',
    status: props.statuses[0] ?? 'paid',
    description: '',
});

const deleteForm = useForm({});

const { formatCurrency } = useFormatters();

const labelize = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        paid: 'bg-emerald-100 text-emerald-800',
        cancelled: 'bg-slate-100 text-slate-800',
    };

    return colors[status] ?? 'bg-gray-100 text-gray-800';
};

const expenseAccentClass = (expense: Expense) => {
    if (expense.status === 'paid') {
        return 'from-emerald-500 via-emerald-400 to-teal-400';
    }

    if (expense.status === 'pending') {
        return 'from-amber-500 via-orange-400 to-yellow-400';
    }

    if (expense.status === 'cancelled') {
        return 'from-slate-500 via-gray-400 to-zinc-400';
    }

    return 'from-blue-500 via-sky-400 to-cyan-400';
};

const expenseToneClass = (expense: Expense) => {
    if (expense.status === 'paid') {
        return 'text-emerald-700 bg-emerald-50 ring-emerald-100';
    }

    if (expense.status === 'pending') {
        return 'text-amber-700 bg-amber-50 ring-amber-100';
    }

    return 'text-slate-700 bg-slate-50 ring-slate-200';
};

const expenseSummaryLabel = (expense: Expense) =>
    expense.vendor ? expense.vendor : 'No vendor recorded';

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.category = props.categories[0] ?? 'other';
            form.status = props.statuses[0] ?? 'paid';
            form.amount = '0';
        },
    });
};

const deleteExpense = () => {
    if (!expenseToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroy([props.team.slug, expenseToDelete.value.id]).url,
        {
            onSuccess: () => {
                showDeleteDialog.value = false;
                expenseToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            title="Expenses"
            description="Track costs by category and keep profitability visible"
        />

        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Record Expense</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="title">Title *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.title"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="amount">Amount *</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                class="mt-1"
                                type="number"
                                min="0.01"
                                step="0.01"
                            />
                            <InputError
                                :message="form.errors.amount"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="category">Category *</Label>
                            <Select v-model="form.category">
                                <SelectTrigger id="category" class="mt-1">
                                    <SelectValue
                                        placeholder="Select category"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category"
                                        :value="category"
                                    >
                                        {{ labelize(category) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.category"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="incurred_date">Incurred Date *</Label>
                            <Input
                                id="incurred_date"
                                v-model="form.incurred_date"
                                class="mt-1"
                                type="date"
                            />
                            <InputError
                                :message="form.errors.incurred_date"
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
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ labelize(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.status"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="vendor">Vendor</Label>
                            <Input
                                id="vendor"
                                v-model="form.vendor"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.vendor"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div>
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="mt-1 flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError
                            :message="form.errors.description"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            Save expense
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateForm = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <ReceiptText class="size-5" />
                    Expense Log
                </CardTitle>
                <Button
                    v-if="!showCreateForm"
                    size="sm"
                    class="gap-2"
                    @click="showCreateForm = true"
                >
                    <Plus class="size-4" />
                    Add expense
                </Button>
            </CardHeader>
            <CardContent>
                <div
                    v-if="expenses.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    No expenses yet.
                </div>

                <div v-else class="space-y-4">
                    <Card
                        v-for="expense in expenses"
                        :key="expense.id"
                        :accent-class="expenseAccentClass(expense)"
                    >
                        <CardContent class="p-0">
                            <div
                                class="grid gap-0 lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.9fr)_auto]"
                            >
                                <div class="space-y-4 p-5 lg:p-6">
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 space-y-2">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <h3
                                                    class="text-lg font-semibold tracking-tight break-words text-gray-900"
                                                >
                                                    {{ expense.title }}
                                                </h3>
                                                <Badge
                                                    :class="
                                                        statusColor(
                                                            expense.status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        labelize(expense.status)
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="text-sm break-words text-gray-500"
                                            >
                                                {{ labelize(expense.category) }}
                                                · {{ expense.incurred_date }}
                                            </p>
                                        </div>

                                        <div
                                            class="max-w-56 truncate rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                                        >
                                            {{ expenseSummaryLabel(expense) }}
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                    >
                                        <div
                                            class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Amount
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{
                                                    formatCurrency(
                                                        expense.amount,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                {{ labelize(expense.category) }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Incurred On
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{ expense.incurred_date }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                {{
                                                    expense.vendor ??
                                                    'No vendor'
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl p-3 ring-1"
                                            :class="expenseToneClass(expense)"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Status
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold"
                                            >
                                                {{ labelize(expense.status) }}
                                            </p>
                                            <p class="text-xs">
                                                {{
                                                    expense.description
                                                        ? 'Description included'
                                                        : 'No description'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{ labelize(expense.category) }}
                                        </span>
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{
                                                expense.vendor ??
                                                'No vendor recorded'
                                            }}
                                        </span>
                                        <span
                                            v-if="expense.description"
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            Notes added
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="border-t border-slate-200 bg-slate-50/70 p-5 lg:border-t-0 lg:border-l lg:p-6"
                                >
                                    <div class="space-y-3">
                                        <div
                                            v-if="expense.description"
                                            class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Description
                                            </p>
                                            <p
                                                class="mt-1 line-clamp-3 text-sm text-slate-600"
                                            >
                                                {{ expense.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-stretch border-t border-slate-200 p-5 lg:border-t-0 lg:border-l lg:p-6"
                                >
                                    <div
                                        class="flex w-full flex-col gap-2 sm:w-auto"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                            class="h-10 w-full justify-start gap-2 rounded-xl"
                                        >
                                            <Link
                                                :href="
                                                    edit([
                                                        team.slug,
                                                        expense.id,
                                                    ]).url
                                                "
                                            >
                                                <Edit class="size-3.5" />
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-10 w-full justify-start gap-2 rounded-xl border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            @click="
                                                expenseToDelete = expense;
                                                showDeleteDialog = true;
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Pagination :pagination="pagination" label="expenses" />
            </CardContent>
        </Card>

        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete expense?</DialogTitle>
                    <DialogDescription>
                        This will permanently remove the expense entry.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteExpense"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
