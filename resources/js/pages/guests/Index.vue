<script setup lang="ts">
import { useForm, Link, usePage, router } from '@inertiajs/vue3';
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
    filters: {
        search?: string | null;
        loyalty_tier?: string | null;
        last_stay_from?: string | null;
        last_stay_to?: string | null;
        min_loyalty_points?: number | null;
        max_loyalty_points?: number | null;
        has_email?: string | null;
        has_phone?: string | null;
    };
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

const filtersForm = useForm({
    search: props.filters.search ?? '',
    loyalty_tier: props.filters.loyalty_tier ?? 'all',
    last_stay_from: props.filters.last_stay_from ?? '',
    last_stay_to: props.filters.last_stay_to ?? '',
    min_loyalty_points:
        props.filters.min_loyalty_points !== null &&
        props.filters.min_loyalty_points !== undefined
            ? String(props.filters.min_loyalty_points)
            : '',
    max_loyalty_points:
        props.filters.max_loyalty_points !== null &&
        props.filters.max_loyalty_points !== undefined
            ? String(props.filters.max_loyalty_points)
            : '',
    has_email: props.filters.has_email ?? 'all',
    has_phone: props.filters.has_phone ?? 'all',
});

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

const hasActiveFilters = computed(() =>
    Boolean(
        filtersForm.search ||
        filtersForm.loyalty_tier !== 'all' ||
        filtersForm.last_stay_from ||
        filtersForm.last_stay_to ||
        filtersForm.min_loyalty_points ||
        filtersForm.max_loyalty_points ||
        filtersForm.has_email !== 'all' ||
        filtersForm.has_phone !== 'all',
    ),
);

const applyFilters = () => {
    router.get(
        index(props.team.slug).url,
        {
            search: filtersForm.search || undefined,
            loyalty_tier:
                filtersForm.loyalty_tier !== 'all'
                    ? filtersForm.loyalty_tier
                    : undefined,
            last_stay_from: filtersForm.last_stay_from || undefined,
            last_stay_to: filtersForm.last_stay_to || undefined,
            min_loyalty_points: filtersForm.min_loyalty_points
                ? Number(filtersForm.min_loyalty_points)
                : undefined,
            max_loyalty_points: filtersForm.max_loyalty_points
                ? Number(filtersForm.max_loyalty_points)
                : undefined,
            has_email:
                filtersForm.has_email !== 'all'
                    ? filtersForm.has_email
                    : undefined,
            has_phone:
                filtersForm.has_phone !== 'all'
                    ? filtersForm.has_phone
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    filtersForm.search = '';
    filtersForm.loyalty_tier = 'all';
    filtersForm.last_stay_from = '';
    filtersForm.last_stay_to = '';
    filtersForm.min_loyalty_points = '';
    filtersForm.max_loyalty_points = '';
    filtersForm.has_email = 'all';
    filtersForm.has_phone = 'all';
    applyFilters();
};

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

const guestAccentClass = (guest: Guest) => {
    if (guest.loyalty_tier === 'platinum') {
        return 'from-cyan-500 via-sky-400 to-blue-400';
    }

    if (guest.loyalty_tier === 'gold') {
        return 'from-amber-500 via-orange-400 to-yellow-400';
    }

    if (guest.loyalty_tier === 'silver') {
        return 'from-slate-500 via-zinc-400 to-gray-400';
    }

    return 'from-violet-500 via-fuchsia-400 to-pink-400';
};

const guestPointsTone = (guest: Guest) => {
    if (guest.loyalty_tier === 'platinum') {
        return 'text-cyan-700 bg-cyan-50 ring-cyan-100';
    }

    if (guest.loyalty_tier === 'gold') {
        return 'text-amber-700 bg-amber-50 ring-amber-100';
    }

    if (guest.loyalty_tier === 'silver') {
        return 'text-slate-700 bg-slate-50 ring-slate-200';
    }

    return 'text-violet-700 bg-violet-50 ring-violet-100';
};

const guestContactLabel = (guest: Guest) =>
    guest.email || guest.phone || 'No contact details';

const guestStayLabel = (guest: Guest) =>
    guest.last_stay_date ?? 'No recorded stay';

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

        <Card>
            <CardHeader>
                <CardTitle>Filter Guests</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="applyFilters" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <Label for="guest_filter_search">Search</Label>
                            <Input
                                id="guest_filter_search"
                                v-model="filtersForm.search"
                                class="mt-1"
                                placeholder="Name, email, phone"
                            />
                        </div>

                        <div>
                            <Label for="guest_filter_tier">Tier</Label>
                            <Select v-model="filtersForm.loyalty_tier">
                                <SelectTrigger
                                    id="guest_filter_tier"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any tier" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Any tier</SelectItem
                                    >
                                    <SelectItem
                                        v-for="tier in tiers"
                                        :key="tier"
                                        :value="tier"
                                    >
                                        {{ labelize(tier) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="guest_filter_has_email"
                                >Has Email</Label
                            >
                            <Select v-model="filtersForm.has_email">
                                <SelectTrigger
                                    id="guest_filter_has_email"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Any</SelectItem>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="guest_filter_last_stay_from"
                                >Last Stay From</Label
                            >
                            <Input
                                id="guest_filter_last_stay_from"
                                v-model="filtersForm.last_stay_from"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="guest_filter_last_stay_to"
                                >Last Stay To</Label
                            >
                            <Input
                                id="guest_filter_last_stay_to"
                                v-model="filtersForm.last_stay_to"
                                type="date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="guest_filter_min_points"
                                >Min Loyalty Points</Label
                            >
                            <Input
                                id="guest_filter_min_points"
                                v-model="filtersForm.min_loyalty_points"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="guest_filter_max_points"
                                >Max Loyalty Points</Label
                            >
                            <Input
                                id="guest_filter_max_points"
                                v-model="filtersForm.max_loyalty_points"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="guest_filter_has_phone"
                                >Has Phone</Label
                            >
                            <Select v-model="filtersForm.has_phone">
                                <SelectTrigger
                                    id="guest_filter_has_phone"
                                    class="mt-1"
                                >
                                    <SelectValue placeholder="Any" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Any</SelectItem>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button type="submit">Apply Filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="!hasActiveFilters"
                            @click="clearFilters"
                        >
                            Clear
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

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
                            class="mt-1 flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                            class="mt-1 flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                    {{
                        hasActiveFilters
                            ? 'No guests match these filters.'
                            : 'No guests yet.'
                    }}
                </div>

                <div v-else class="space-y-4">
                    <Card
                        v-for="guest in guests"
                        :key="guest.id"
                        :accent-class="guestAccentClass(guest)"
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
                                                    {{ guest.full_name }}
                                                </h3>
                                                <Badge
                                                    :class="
                                                        tierColor(
                                                            guest.loyalty_tier,
                                                        )
                                                    "
                                                >
                                                    <Star class="mr-1 size-3" />
                                                    {{
                                                        labelize(
                                                            guest.loyalty_tier,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="text-sm break-words text-gray-500"
                                            >
                                                {{ guestContactLabel(guest) }}
                                            </p>
                                        </div>

                                        <div
                                            class="max-w-56 truncate rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                                        >
                                            {{ guest.loyalty_points }} points
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
                                                Contact
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{ guest.email || 'No email' }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                {{ guest.phone || 'No phone' }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Last Stay
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold break-words text-slate-900"
                                            >
                                                {{ guestStayLabel(guest) }}
                                            </p>
                                            <p
                                                class="text-xs break-words text-slate-500"
                                            >
                                                Loyalty tier:
                                                {{
                                                    labelize(guest.loyalty_tier)
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl p-3 ring-1"
                                            :class="guestPointsTone(guest)"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Loyalty
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold"
                                            >
                                                {{ guest.loyalty_points }}
                                                points
                                            </p>
                                            <p class="text-xs">
                                                {{
                                                    labelize(guest.loyalty_tier)
                                                }}
                                                member
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{
                                                guest.email ||
                                                'No email recorded'
                                            }}
                                        </span>
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{
                                                guest.phone ||
                                                'No phone recorded'
                                            }}
                                        </span>
                                        <span
                                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                                        >
                                            {{
                                                guest.last_stay_date ||
                                                'No stay recorded'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="border-t border-slate-200 bg-slate-50/70 p-5 lg:border-t-0 lg:border-l lg:p-6"
                                >
                                    <div class="space-y-3">
                                        <div
                                            class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Guest Record
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold text-slate-900"
                                            >
                                                {{ guest.full_name }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ guest.email || 'No email' }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-white p-3 ring-1 ring-slate-200"
                                        >
                                            <p
                                                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                                            >
                                                Recent Stay
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    guest.last_stay_date ||
                                                    'No recorded stay'
                                                }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ guest.phone || 'No phone' }}
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
                                                    edit([team.slug, guest.id])
                                                        .url
                                                "
                                            >
                                                <Edit class="size-3.5" />
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            class="h-10 w-full justify-start gap-2 rounded-xl"
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
