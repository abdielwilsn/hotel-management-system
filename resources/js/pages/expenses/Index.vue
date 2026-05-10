<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { Edit, Plus, ReceiptText, Trash2 } from 'lucide-vue-next';
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

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(Number(value));

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

    deleteForm.delete(destroy([props.team.slug, expenseToDelete.value.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            expenseToDelete.value = null;
        },
    });
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
                            class="mt-1 flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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

                <div v-else class="space-y-3">
                    <div
                        v-for="expense in expenses"
                        :key="expense.id"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold">{{ expense.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ labelize(expense.category) }} ·
                                    {{ expense.incurred_date }}
                                    <span v-if="expense.vendor"
                                        >· {{ expense.vendor }}</span
                                    >
                                </p>
                                <p
                                    v-if="expense.description"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ expense.description }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-lg font-semibold">
                                    {{ formatCurrency(expense.amount) }}
                                </p>
                                <Badge :class="statusColor(expense.status)">
                                    {{ labelize(expense.status) }}
                                </Badge>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="edit([team.slug, expense.id]).url"
                                    class="gap-1.5"
                                >
                                    <Edit class="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                class="gap-1.5"
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
