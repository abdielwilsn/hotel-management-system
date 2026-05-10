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
import { destroy, index, update } from '@/routes/inventory';

type Category = {
    id: number;
    name: string;
    type: string | null;
};

type Item = {
    id: number;
    name: string;
    unit_price: number;
    unit: string;
    is_active: boolean;
    inventory_category_id: number;
    category?: {
        id: number;
        name: string;
        type: string | null;
    };
};

type Props = {
    item: Item;
    categories: Category[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const showDeleteDialog = ref(false);

defineOptions({
    layout: (props: {
        currentTeam?: { slug: string } | null;
        item?: Item;
    }) => ({
        breadcrumbs: [
            {
                title: 'Inventory',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.item?.name,
                href: '#',
            },
        ],
    }),
});

const form = useForm({
    inventory_category_id: String(props.item.inventory_category_id),
    name: props.item.name,
    unit_price: String(props.item.unit_price),
    unit: props.item.unit,
    is_active: props.item.is_active ? '1' : '0',
});

const deleteForm = useForm({});

const submit = () => {
    form.patch(update([props.team.slug, props.item.id]).url);
};

const deleteItem = () => {
    deleteForm.delete(destroy([props.team.slug, props.item.id]).url, {
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
            Back to Inventory
        </Link>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold">{{ item.name }}</h1>
                <p class="text-muted-foreground">{{ item.category?.name }}</p>
            </div>
            <Badge
                :class="
                    item.is_active
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-slate-100 text-slate-800'
                "
            >
                {{ item.is_active ? 'Active' : 'Inactive' }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Edit Inventory Item</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
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
                    <DialogTitle>Delete inventory item?</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
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
    </div>
</template>
