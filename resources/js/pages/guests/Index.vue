<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { Edit, Plus, Star, Trash2, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { destroy, edit, index, store } from '@/routes/guests';
import type { Team } from '@/types';

type Guest = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    loyalty_tier: string;
    loyalty_points: number;
    last_stay_date: string | null;
};

type Props = {
    guests: Guest[];
    tiers: string[];
    team: { id: number; slug: string; name: string };
};

const props = defineProps<Props>();
const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);
const isAdmin = computed(() => {
    const role = currentTeam.value?.role;

    return role === 'owner' || role === 'admin';
});

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Guests',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const showCreateForm = ref(false);
const showDeleteDialog = ref(false);
const guestToDelete = ref<Guest | null>(null);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    loyalty_tier: props.tiers[0] ?? 'standard',
    loyalty_points: '0',
    last_stay_date: '',
    preferences: '',
    notes: '',
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

const tierCounts = computed(() => {
    return props.guests.reduce(
        (acc, guest) => {
            acc[guest.loyalty_tier] = (acc[guest.loyalty_tier] || 0) + 1;

            return acc;
        },
        {} as Record<string, number>,
    );
});

const submit = () => {
    form.post(store(props.team.slug).url, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.reset();
            form.loyalty_tier = props.tiers[0] ?? 'standard';
            form.loyalty_points = '0';
        },
    });
};

const deleteGuest = () => {
    if (!guestToDelete.value) {
        return;
    }

    deleteForm.delete(destroy([props.team.slug, guestToDelete.value.id]).url, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            guestToDelete.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            title="Guests"
            description="Manage profiles, loyalty levels, and repeat stays"
        />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground"
                        >Total guests</CardTitle
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold">{{
                    guests.length
                }}</CardContent>
            </Card>
            <Card v-for="tier in tiers" :key="tier" class="bg-white/90">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        {{ labelize(tier) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-3xl font-semibold">
                    {{ tierCounts[tier] || 0 }}
                </CardContent>
            </Card>
        </section>

        <Card v-if="showCreateForm" class="border-hotel-primary/20">
            <CardHeader>
                <CardTitle>Add Guest</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
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

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing"
                            >Save guest</Button
                        >
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
                    <UserRound class="size-5" />
                    Guest Directory
                </CardTitle>
                <Button
                    v-if="!showCreateForm"
                    size="sm"
                    class="gap-2"
                    @click="showCreateForm = true"
                >
                    <Plus class="size-4" />
                    Add guest
                </Button>
            </CardHeader>
            <CardContent>
                <div
                    v-if="guests.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    No guests yet.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="guest in guests"
                        :key="guest.id"
                        class="rounded-lg border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{ guest.full_name }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ guest.email || 'No email' }}
                                    <span v-if="guest.phone">
                                        · {{ guest.phone }}</span
                                    >
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Last stay:
                                    {{ guest.last_stay_date || 'N/A' }}
                                </p>
                            </div>

                            <div class="text-right">
                                <Badge :class="tierColor(guest.loyalty_tier)">
                                    <Star class="mr-1 size-3" />
                                    {{ labelize(guest.loyalty_tier) }}
                                </Badge>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ guest.loyalty_points }} pts
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="edit([team.slug, guest.id]).url"
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
                                :disabled="!isAdmin"
                                :title="
                                    isAdmin
                                        ? 'Delete guest'
                                        : 'Admin only action'
                                "
                                @click="
                                    guestToDelete = guest;
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
                        Delete
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
