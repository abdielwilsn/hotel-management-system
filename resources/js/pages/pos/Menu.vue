<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Boxes, Edit, Plus, Trash2 } from 'lucide-vue-next';
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
import { useFormatters } from '@/lib/format';
import { index } from '@/routes/pos';
import {
    destroy as destroyCategory,
    store as storeCategory,
} from '@/routes/pos/categories';
import {
    destroy as destroyItem,
    edit as editItem,
    store as storeItem,
} from '@/routes/pos/items';

type Category = { id: number; name: string };
type Item = {
    id: number;
    pos_category_id: number | null;
    name: string;
    price: number;
    unit: string;
    track_stock: boolean;
    stock_quantity: number;
    is_active: boolean;
    category: { id: number; name: string } | null;
};

type Props = {
    outlet: { id: number; name: string; type: string };
    categories: Category[];
    items: Item[];
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

const showCategoryForm = ref(false);
const itemToDelete = ref<Item | null>(null);
const categoryToDelete = ref<Category | null>(null);

const categoryForm = useForm({ name: '' });
const itemForm = useForm({
    pos_category_id: props.categories[0]?.id
        ? String(props.categories[0].id)
        : '',
    name: '',
    price: '0',
    unit: 'piece',
    track_stock: '0',
    is_active: '1',
});
const deleteForm = useForm({});

const { formatCurrency } = useFormatters();

const submitCategory = () => {
    categoryForm.post(storeCategory([props.team.slug, props.outlet.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
            showCategoryForm.value = false;
        },
    });
};

const submitItem = () => {
    itemForm.post(storeItem([props.team.slug, props.outlet.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            itemForm.reset('name', 'price');
            itemForm.price = '0';
        },
    });
};

const deleteItem = () => {
    if (!itemToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroyItem([props.team.slug, props.outlet.id, itemToDelete.value.id])
            .url,
        {
            preserveScroll: true,
            onSuccess: () => {
                itemToDelete.value = null;
            },
        },
    );
};

const deleteCategory = () => {
    if (!categoryToDelete.value) {
        return;
    }

    deleteForm.delete(
        destroyCategory([
            props.team.slug,
            props.outlet.id,
            categoryToDelete.value.id,
        ]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                categoryToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`${outlet.name} — Menu`" />

    <div class="space-y-6">
        <Heading
            :title="`${outlet.name} — Menu`"
            description="Add the items and categories this outlet sells"
        />

        <section class="grid gap-4 xl:grid-cols-[1fr_1.4fr]">
            <Card class="bg-white/90">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Boxes class="text-hotel-primary size-4" />
                            Categories
                        </CardTitle>
                        <Button
                            v-if="!showCategoryForm"
                            size="sm"
                            class="gap-2"
                            @click="showCategoryForm = true"
                        >
                            <Plus class="size-4" />
                            Add
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <form
                        v-if="showCategoryForm"
                        class="space-y-2 rounded-lg border p-3"
                        @submit.prevent="submitCategory"
                    >
                        <Label for="category_name">Name *</Label>
                        <Input
                            id="category_name"
                            v-model="categoryForm.name"
                        />
                        <InputError :message="categoryForm.errors.name" />
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="categoryForm.processing"
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="showCategoryForm = false"
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>

                    <div
                        v-if="categories.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No categories yet.
                    </div>
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="flex items-center justify-between rounded-lg border px-3 py-2"
                    >
                        <span class="font-medium">{{ category.name }}</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 text-destructive"
                            :aria-label="`Delete ${category.name} category`"
                            @click="categoryToDelete = category"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-white/90">
                <CardHeader>
                    <CardTitle class="text-base">Add Item</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitItem">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Category</Label>
                                <Select v-model="itemForm.pos_category_id">
                                    <SelectTrigger class="mt-1">
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
                                    :message="itemForm.errors.pos_category_id"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="item_name">Name *</Label>
                                <Input
                                    id="item_name"
                                    v-model="itemForm.name"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="itemForm.errors.name"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="item_price">Price *</Label>
                                <Input
                                    id="item_price"
                                    v-model="itemForm.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="itemForm.errors.price"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label for="item_unit">Unit *</Label>
                                <Input
                                    id="item_unit"
                                    v-model="itemForm.unit"
                                    class="mt-1"
                                />
                                <InputError
                                    :message="itemForm.errors.unit"
                                    class="mt-2"
                                />
                            </div>
                            <div>
                                <Label>Track stock *</Label>
                                <Select v-model="itemForm.track_stock">
                                    <SelectTrigger class="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Yes</SelectItem>
                                        <SelectItem value="0">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Status *</Label>
                                <Select v-model="itemForm.is_active">
                                    <SelectTrigger class="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Active</SelectItem>
                                        <SelectItem value="0">
                                            Inactive
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <Button type="submit" :disabled="itemForm.processing">
                            Save item
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </section>

        <Card class="bg-white/90">
            <CardHeader>
                <CardTitle class="text-base">Menu Items</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="items.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No items yet.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 rounded-lg border px-3 py-2"
                    >
                        <div>
                            <p class="font-medium">
                                {{ item.name }}
                                <Badge
                                    v-if="!item.is_active"
                                    variant="outline"
                                    class="ml-1 rounded-full text-[10px]"
                                >
                                    inactive
                                </Badge>
                                <Badge
                                    v-if="item.track_stock"
                                    variant="outline"
                                    class="ml-1 rounded-full text-[10px]"
                                    :class="
                                        item.stock_quantity <= 0
                                            ? 'border-red-200 text-red-700'
                                            : ''
                                    "
                                >
                                    {{ item.stock_quantity }} in stock
                                </Badge>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.category?.name || 'Uncategorised' }}
                            </p>
                        </div>
                        <span class="text-sm text-muted-foreground">{{
                            item.unit
                        }}</span>
                        <span class="text-sm font-semibold">{{
                            formatCurrency(item.price)
                        }}</span>
                        <div class="flex justify-end gap-1.5">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="
                                        editItem([team.slug, outlet.id, item.id])
                                            .url
                                    "
                                    :aria-label="`Edit ${item.name}`"
                                >
                                    <Edit class="size-3.5" />
                                </Link>
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                :aria-label="`Delete ${item.name}`"
                                @click="itemToDelete = item"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog
            :open="itemToDelete !== null"
            @update:open="itemToDelete = $event ? itemToDelete : null"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete item?</DialogTitle>
                    <DialogDescription>
                        This permanently removes {{ itemToDelete?.name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="itemToDelete = null">
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
            :open="categoryToDelete !== null"
            @update:open="categoryToDelete = $event ? categoryToDelete : null"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete category?</DialogTitle>
                    <DialogDescription>
                        A category with items cannot be deleted.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="categoryToDelete = null">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteCategory"
                    >
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
