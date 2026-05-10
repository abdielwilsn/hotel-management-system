<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/inventory';
import {
    destroy as destroyCategory,
    update as updateCategory,
} from '@/routes/inventory/categories';

const props = defineProps<{
    category: {
        id: number;
        name: string;
        type: string | null;
        description: string | null;
    };
    team: {
        id: number;
        slug: string;
        name: string;
    };
}>();

const form = useForm({
    name: props.category.name,
    type: props.category.type ?? '',
    description: props.category.description ?? '',
});

const deleteForm = useForm({});

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Inventory',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: 'Edit Category',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const submit = () => {
    form.patch(updateCategory([props.team.slug, props.category.id]).url);
};

const deleteCategoryRecord = () => {
    deleteForm.delete(destroyCategory([props.team.slug, props.category.id]).url);
};
</script>

<template>
    <Head :title="`Edit Category - ${props.category.name}`" />

    <div class="space-y-6">
        <Heading
            title="Edit Category"
            description="Update inventory category details"
        />

        <Card class="max-w-2xl bg-white/90">
            <CardHeader>
                <CardTitle>{{ props.category.name }}</CardTitle>
                <CardDescription>Category details and metadata</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" class="mt-1" />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <Label for="type">Type</Label>
                        <Input id="type" v-model="form.type" class="mt-1" />
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>

                    <div>
                        <Label for="description">Description</Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            class="mt-1"
                        />
                        <InputError
                            :message="form.errors.description"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            Save changes
                        </Button>
                    </div>
                </form>
            </CardContent>
            <CardFooter>
                <Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="deleteCategoryRecord"
                >
                    Delete category
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
