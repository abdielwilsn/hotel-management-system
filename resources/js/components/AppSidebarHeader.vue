<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
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
