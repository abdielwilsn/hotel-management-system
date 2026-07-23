<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notificationsIndex } from '@/routes/notifications';
import type { BreadcrumbItem, Team } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const currentTeam = computed<Team | null>(() => page.props.currentTeam ?? null);
const unreadNotificationsCount = computed(
    () => page.props.unreadNotificationsCount ?? 0,
);

const openSearch = () => {
    window.dispatchEvent(new CustomEvent('open-command-palette'));
};
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6 lg:px-8"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <Link
            v-if="currentTeam"
            :href="notificationsIndex(currentTeam.slug).url"
            class="relative flex items-center justify-center rounded-lg border border-sidebar-border/70 p-2 text-muted-foreground transition-colors hover:bg-accent"
            aria-label="Notifications"
        >
            <Bell class="size-4" />
            <span
                v-if="unreadNotificationsCount > 0"
                class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-medium text-white"
            >
                {{
                    unreadNotificationsCount > 99
                        ? '99+'
                        : unreadNotificationsCount
                }}
            </span>
        </Link>

        <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent"
            aria-label="Search guests, bookings, and rooms"
            @click="openSearch"
        >
            <Search class="size-4" />
            <span class="hidden sm:inline">Search…</span>
            <span
                class="hidden rounded border px-1.5 text-[10px] tracking-wide sm:inline"
            >
                ⌘K
            </span>
        </button>
    </header>
</template>
