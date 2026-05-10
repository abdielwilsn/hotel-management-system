<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { ChevronLeft, Save, Star, Trash2 } from 'lucide-vue-next';
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
import { destroy, index, update } from '@/routes/guests';

type Guest = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    loyalty_tier: string;
    loyalty_points: number;
    last_stay_date: string | null;
    preferences: string | null;
    notes: string | null;
};

type Props = {
    guest: Guest;
    tiers: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const showDeleteDialog = ref(false);

defineOptions({
    layout: (props: {
        currentTeam?: { slug: string } | null;
        guest?: Guest;
    }) => ({
        breadcrumbs: [
            {
                title: 'Guests',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.guest?.full_name,
                href: '#',
            },
        ],
    }),
});

const form = useForm({
    first_name: props.guest.first_name,
    last_name: props.guest.last_name,
    email: props.guest.email || '',
    phone: props.guest.phone || '',
    date_of_birth: props.guest.date_of_birth || '',
    loyalty_tier: props.guest.loyalty_tier,
    loyalty_points: String(props.guest.loyalty_points),
    last_stay_date: props.guest.last_stay_date || '',
    preferences: props.guest.preferences || '',
    notes: props.guest.notes || '',
});

const deleteForm = useForm({});

const tierColor = (tier: string) => {
    const colors: Record<string, string> = {
        standard: 'bg-slate-100 text-slate-800',
        silver: 'bg-zinc-200 text-zinc-900',
        gold: 'bg-amber-100 text-amber-800',
        platinum: 'bg-cyan-100 text-cyan-800',
    };

    return colors[tier] ?? 'bg-gray-100 text-gray-800';
};

const labelize = (value: string) =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const submit = () => {
    form.patch(update([props.team.slug, props.guest.id]).url);
};

const deleteGuest = () => {
    deleteForm.delete(destroy([props.team.slug, props.guest.id]).url, {
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
            Back to Guests
        </Link>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold">{{ guest.full_name }}</h1>
                <p class="text-muted-foreground">
                    {{ guest.email || 'No email on file' }}
                </p>
            </div>
            <Badge :class="tierColor(guest.loyalty_tier)">
                <Star class="mr-1 size-3" />
                {{ labelize(guest.loyalty_tier) }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Edit Guest</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="first_name">First name *</Label>
                            <Input
                                id="first_name"
                                v-model="form.first_name"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.first_name"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="last_name">Last name *</Label>
                            <Input
                                id="last_name"
                                v-model="form.last_name"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.last_name"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                class="mt-1"
                                type="email"
                            />
                            <InputError
                                :message="form.errors.email"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                class="mt-1"
                            />
                            <InputError
                                :message="form.errors.phone"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="date_of_birth">Date of birth</Label>
                            <Input
                                id="date_of_birth"
                                v-model="form.date_of_birth"
                                class="mt-1"
                                type="date"
                            />
                            <InputError
                                :message="form.errors.date_of_birth"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="last_stay_date">Last stay date</Label>
                            <Input
                                id="last_stay_date"
                                v-model="form.last_stay_date"
                                class="mt-1"
                                type="date"
                            />
                            <InputError
                                :message="form.errors.last_stay_date"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="loyalty_tier">Loyalty tier *</Label>
                            <Select v-model="form.loyalty_tier">
                                <SelectTrigger id="loyalty_tier" class="mt-1">
                                    <SelectValue placeholder="Select tier" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tier in tiers"
                                        :key="tier"
                                        :value="tier"
                                    >
                                        {{ labelize(tier) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.loyalty_tier"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <Label for="loyalty_points">Loyalty points *</Label>
                            <Input
                                id="loyalty_points"
                                v-model="form.loyalty_points"
                                class="mt-1"
                                type="number"
                                min="0"
                                step="1"
                            />
                            <InputError
                                :message="form.errors.loyalty_points"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <div>
                        <Label for="preferences">Preferences</Label>
                        <textarea
                            id="preferences"
                            v-model="form.preferences"
                            class="mt-1 flex min-h-[70px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError
                            :message="form.errors.preferences"
                            class="mt-2"
                        />
                    </div>

                    <div>
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="mt-1 flex min-h-[70px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.notes" class="mt-2" />
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
                    <DialogTitle>Delete guest?</DialogTitle>
                    <DialogDescription>
                        This will permanently remove this guest profile.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteGuest"
                    >
                        Delete guest
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
