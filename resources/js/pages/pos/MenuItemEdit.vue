<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, menu } from '@/routes/pos';
import { update } from '@/routes/pos/items';

type Category = { id: number; name: string };
type Item = {
    id: number;
    pos_category_id: number | null;
    name: string;
    price: number;
    unit: string;
    track_stock: boolean;
    is_active: boolean;
};

type Props = {
    outlet: { id: number; name: string; type: string };
    item: Item;
    categories: Category[];
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

const form = useForm({
    pos_category_id: props.item.pos_category_id
        ? String(props.item.pos_category_id)
        : '',
    name: props.item.name,
    price: String(props.item.price),
    unit: props.item.unit,
    track_stock: props.item.track_stock ? '1' : '0',
    is_active: props.item.is_active ? '1' : '0',
});

const submit = () => {
    form.patch(update([props.team.slug, props.outlet.id, props.item.id]).url);
};
</script>

<template>
    <Head :title="`Edit ${item.name}`" />

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                :title="`Edit ${item.name}`"
                :description="`${outlet.name} menu item`"
            />
            <Button variant="outline" size="sm" class="gap-2" as-child>
                <Link :href="menu([team.slug, outlet.id]).url">
                    <ArrowLeft class="size-4" />
                    Back
                </Link>
            </Button>
        </div>

        <Card class="bg-white/90">
            <CardContent class="pt-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label>Category</Label>
                            <Select v-model="form.pos_category_id">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Select category" />
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
                                :message="form.errors.pos_category_id"
                                class="mt-2"
                            />
                        </div>
                        <div>
                            <Label for="name">Name *</Label>
                            <Input id="name" v-model="form.name" class="mt-1" />
                            <InputError
                                :message="form.errors.name"
                                class="mt-2"
                            />
                        </div>
                        <div>
                            <Label for="price">Price *</Label>
                            <Input
                                id="price"
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.price"
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
                            <Label>Track stock *</Label>
                            <Select v-model="form.track_stock">
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
                            <Select v-model="form.is_active">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            Save changes
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="menu([team.slug, outlet.id]).url">
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
