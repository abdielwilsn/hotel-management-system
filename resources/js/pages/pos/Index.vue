<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, ShoppingCart, Store, Wine, Utensils } from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormatters } from '@/lib/format';
import { index, menu, reports, terminal } from '@/routes/pos';

type Outlet = {
    id: number;
    name: string;
    type: string;
    status: string;
    today_orders: number;
    today_sales: number;
};

type Props = {
    outlets: Outlet[];
    team: { id: number; slug: string; name: string };
};

defineProps<Props>();

const page = usePage();
const isAdmin = computed(() => {
    const role = page.props.currentTeam?.role;

    return role === 'owner' || role === 'admin';
});

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

const { formatCurrency } = useFormatters();
</script>

<template>
    <Head title="Point of Sale" />

    <div class="space-y-6">
        <Heading
            title="Point of Sale"
            description="Bar & Restaurant — take orders, sell, and print receipts"
        />

        <EmptyState
            v-if="outlets.length === 0"
            :icon="Store"
            title="No outlets assigned"
            description="You have not been assigned to any outlet yet. Ask a manager to assign you to a bar or restaurant."
        />

        <section v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="outlet in outlets"
                :key="outlet.id"
                class="bg-white/90"
            >
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Wine
                                v-if="outlet.type === 'bar'"
                                class="text-hotel-primary size-5"
                            />
                            <Utensils
                                v-else
                                class="text-hotel-primary size-5"
                            />
                            {{ outlet.name }}
                        </CardTitle>
                        <Badge class="rounded-full capitalize">{{
                            outlet.type
                        }}</Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">
                                Orders today
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ outlet.today_orders }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">
                                Sales today
                            </p>
                            <p class="text-lg font-semibold">
                                {{ formatCurrency(outlet.today_sales) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button class="gap-2" as-child>
                            <Link :href="terminal([team.slug, outlet.id]).url">
                                <ShoppingCart class="size-4" />
                                Sell
                            </Link>
                        </Button>
                        <Button variant="outline" class="gap-2" as-child>
                            <Link :href="reports([team.slug, outlet.id]).url">
                                <ClipboardList class="size-4" />
                                Reports
                            </Link>
                        </Button>
                        <Button
                            v-if="isAdmin"
                            variant="outline"
                            class="gap-2"
                            as-child
                        >
                            <Link :href="menu([team.slug, outlet.id]).url">
                                <BookOpen class="size-4" />
                                Menu
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
