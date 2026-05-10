<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ChevronLeft, Save, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
import { destroy, index, update } from '@/routes/expenses';

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
    expense: Expense;
    categories: string[];
    statuses: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();

const showDeleteDialog = ref(false);

defineOptions({
    layout: (props: {
        currentTeam?: { slug: string } | null;
        expense?: Expense;
    }) => ({
        breadcrumbs: [
            {
                title: 'Expenses',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.expense?.title,
                href: '#',
            },
        ],
    }),
});

const form = useForm({
    title: props.expense.title,
    category: props.expense.category,
    amount: String(props.expense.amount),
    incurred_date: props.expense.incurred_date,
    vendor: props.expense.vendor || '',
    status: props.expense.status,
    description: props.expense.description || '',
});

const deleteForm = useForm({});

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
    form.patch(update([props.team.slug, props.expense.id]).url);
};

const deleteExpense = () => {
    deleteForm.delete(destroy([props.team.slug, props.expense.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Link
            :href="index(props.team.slug).url"
            class="inline-flex items-center gap-2 text-muted-foreground hover:text-foreground"
        >
            <ChevronLeft class="size-4" />
            Back to Expenses
        </Link>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold">{{ expense.title }}</h1>
                <p class="text-muted-foreground">{{ expense.incurred_date }}</p>
            </div>
            <Badge :class="statusColor(expense.status)">
                {{ labelize(expense.status) }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Edit Expense</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
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

                    <div class="flex items-center justify-between">
                        <Button
                            type="button"
                            variant="destructive"
                            class="gap-2"
                            @click="showDeleteDialog = true"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="gap-2"
                        >
                            <Save class="size-4" />
                            Save changes
                        </Button>
                    </div>
                </form>
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
                        This will permanently remove this expense.
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
                        Delete expense
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
