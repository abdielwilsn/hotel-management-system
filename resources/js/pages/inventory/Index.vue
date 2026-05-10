<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import {
    Boxes,
    ChartColumn,
    ClipboardList,
    Edit,
    Package,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { destroy, edit, index, store } from '@/routes/inventory';
import {
    destroy as destroyCategory,
    edit as editCategory,
    store as storeCategory,
} from '@/routes/inventory/categories';
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

type Summary = {
    categories: number;
    items: number;
    sales_records: number;
    stock_records: number;
    sales_value: number;
};

type Category = {
    id: number;
    type: string | null;
    name: string;
    description: string | null;
    items_count: number;
};

type Item = {
    id: number;
    name: string;
    unit_price: number;
    unit: string;
    is_active: boolean;
    category: {
        id: number;
        name: string;
    };
};

type Sale = {
    id: number;
    business_date: string;
    quantity: number;
    total_amount: number;
    payment_mode: string;
    guest_name: string | null;
    item: {
        id: number;
        name: string;
    };
};

type StockRecord = {
    id: number;
    business_date: string;
    opening_stock: number;
    new_stock: number;
    closing_stock: number;
    sales_qty: number;
    item: {
        id: number;
        name: string;
        unit: string;
    };
};

type Props = {
    summary: Summary;
    categories: Category[];
    items: Item[];
    recentSales: Sale[];
    recentStock: StockRecord[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const showCreateForm = ref(false);
const showCreateCategoryForm = ref(false);
const showDeleteDialog = ref(false);
const showDeleteCategoryDialog = ref(false);
const itemToDelete = ref<Item | null>(null);
const categoryToDelete = ref<Category | null>(null);

const form = useForm({
    inventory_category_id: props.categories[0]?.id
        ? String(props.categories[0].id)
        : '',
    name: '',
    unit_price: '0',
    unit: 'piece',
    is_active: '1',
});

const deleteForm = useForm({});
const categoryForm = useForm({
    name: '',
    type: '',
    description: '',
});
const deleteCategoryForm = useForm({});

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Inventory',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(Number(value));

const labelize = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.inventory_category_id = props.categories[0]?.id
                ? String(props.categories[0].id)
                : '';
            form.unit = 'piece';
            form.unit_price = '0';
            form.is_active = '1';
        },
    });
};

const submitCategory = () => {
    categoryForm.post(storeCategory(props.team.slug).url, {
        onSuccess: () => {
            showCreateCategoryForm.value = false;
            categoryForm.reset();
        },
    });
};

const deleteItem = () => {
    if (!itemToDelete.value) {
        return;
    }

    deleteForm.delete(destroy([props.team.slug, itemToDelete.value.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            itemToDelete.value = null;
        },
    });
};

const deleteCategory = () => {
    if (!categoryToDelete.value) {
        return;
    }

    deleteCategoryForm.delete(
        destroyCategory([props.team.slug, categoryToDelete.value.id]).url,
        {
            onSuccess: () => {
                showDeleteCategoryDialog.value = false;
                categoryToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Inventory" />

    <div class="space-y-6">
        <Heading
            title="Inventory Ledger"
            description="Imported Bar, Kitchen, Store, and Mini-Shop seeded data"
        />

        <Card v-if="showCreateCategoryForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Add Category</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="category_name">Name *</Label>
                            <Input
                                id="category_name"
                                v-model="categoryForm.name"
                                class="mt-1"
                            />
                            <InputError
                                :message="categoryForm.errors.name"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="category_type">Type</Label>
                            <Input
                                id="category_type"
                                v-model="categoryForm.type"
                                class="mt-1"
                                placeholder="BAR, KITCHEN, STORE, MINI_SHOP"
                            />
                            <InputError
                                :message="categoryForm.errors.type"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div>
                        <Label for="category_description">Description</Label>
                        <Input
                            id="category_description"
                            v-model="categoryForm.description"
                            class="mt-1"
                        />
                        <InputError
                            :message="categoryForm.errors.description"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            :disabled="categoryForm.processing"
                        >
                            Save category
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateCategoryForm = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Add Inventory Item</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="inventory_category_id"
                                >Category *</Label
                            >
                            <Select v-model="form.inventory_category_id">
                                <SelectTrigger
                                    id="inventory_category_id"
                                    class="mt-1"
                                >
                                    <SelectValue
                                        placeholder="Select category"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.inventory_category_id"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="name">Item Name *</Label>
                            <Input id="name" v-model="form.name" class="mt-1" />
                            <InputError
                                :message="form.errors.name"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="unit_price">Unit Price *</Label>
                            <Input
                                id="unit_price"
                                v-model="form.unit_price"
                                class="mt-1"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError
                                :message="form.errors.unit_price"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="unit">Unit *</Label>
                            <Input id="unit" v-model="form.unit" class="mt-1" />
                            <InputError
                                :message="form.errors.unit"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="is_active">Status *</Label>
                            <Select v-model="form.is_active">
                                <SelectTrigger id="is_active" class="mt-1">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.is_active"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            Save item
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

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Categories</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    summary.categories
                }}</CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Items</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    summary.items
                }}</CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Stock records</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    summary.stock_records
                }}</CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Sales records</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    summary.sales_records
                }}</CardContent>
            </Card>
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Total sales value</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{
                    formatCurrency(summary.sales_value)
                }}</CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_1.2fr]">
            <Card class="bg-white/90">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Boxes class="text-hotel-primary size-4" />
                            Categories
                        </CardTitle>
                        <Button
                            v-if="!showCreateCategoryForm"
                            size="sm"
                            class="gap-2"
                            @click="showCreateCategoryForm = true"
                        >
                            <Plus class="size-4" />
                            Add category
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="categories.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No inventory categories imported yet.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            class="rounded-lg border p-3"
                        >
                            <div
                                class="mb-1 flex items-center justify-between gap-2"
                            >
                                <p class="font-semibold">{{ category.name }}</p>
                                <Badge
                                    class="bg-hotel-primary/10 text-hotel-primary rounded-full"
                                >
                                    {{ category.items_count }} items
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    category.description || 'Imported category'
                                }}
                            </p>
                            <p
                                v-if="category.type"
                                class="mt-1 text-xs text-muted-foreground/80"
                            >
                                Type: {{ labelize(category.type) }}
                            </p>
                            <div class="mt-3 flex justify-end gap-1.5">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            editCategory([
                                                team.slug,
                                                category.id,
                                            ]).url
                                        "
                                    >
                                        <Edit class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="
                                        categoryToDelete = category;
                                        showDeleteCategoryDialog = true;
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Package class="text-hotel-primary size-4" />
                            Item Catalog
                        </CardTitle>
                        <Button
                            v-if="!showCreateForm"
                            size="sm"
                            class="gap-2"
                            @click="showCreateForm = true"
                        >
                            <Plus class="size-4" />
                            Add item
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="items.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No inventory items imported yet.
                    </div>
                    <div
                        v-else
                        class="max-h-[430px] space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 rounded-lg border px-3 py-2"
                        >
                            <div>
                                <p class="font-medium">{{ item.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.category.name }}
                                </p>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ item.unit }}
                            </p>
                            <p class="text-sm font-semibold">
                                {{ formatCurrency(item.unit_price) }}
                            </p>
                            <div class="flex justify-end gap-1.5">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="edit([team.slug, item.id]).url"
                                    >
                                        <Edit class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="
                                        itemToDelete = item;
                                        showDeleteDialog = true;
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ClipboardList class="text-hotel-primary size-4" />
                        Recent Stock Activity
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="recentStock.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No stock records found.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="record in recentStock"
                            :key="record.id"
                            class="rounded-lg border p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="text-sm font-semibold">
                                    {{ record.item.name }}
                                </p>
                                <span class="text-xs text-muted-foreground">{{
                                    record.business_date
                                }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                O: {{ record.opening_stock }} · N:
                                {{ record.new_stock }} · S:
                                {{ record.sales_qty }} · C:
                                {{ record.closing_stock }}
                                {{ record.item.unit }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ChartColumn class="text-hotel-primary size-4" />
                        Recent Sales
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="recentSales.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No sales records found.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="sale in recentSales"
                            :key="sale.id"
                            class="rounded-lg border p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="text-sm font-semibold">
                                    {{ sale.item.name }}
                                </p>
                                <span class="text-xs text-muted-foreground">{{
                                    sale.business_date
                                }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Qty {{ sale.quantity }} ·
                                {{ labelize(sale.payment_mode) }} ·
                                {{ formatCurrency(sale.total_amount) }}
                            </p>
                            <p
                                v-if="sale.guest_name"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Guest: {{ sale.guest_name }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Dialog
            :open="showDeleteDialog"
            @update:open="showDeleteDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete inventory item?</DialogTitle>
                    <DialogDescription>
                        This permanently removes the selected item.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteItem"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showDeleteCategoryDialog"
            @update:open="showDeleteCategoryDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete category?</DialogTitle>
                    <DialogDescription>
                        You cannot delete a category that still contains items.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        @click="showDeleteCategoryDialog = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteCategoryForm.processing"
                        @click="deleteCategory"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
