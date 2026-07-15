<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: 'Navigation',
    },
);

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-2">
        <SidebarGroupLabel
            class="mb-3 px-3 text-[11px] font-semibold tracking-[0.16em] text-sidebar-foreground/60 uppercase"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
                class="mb-1"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative rounded-xl px-5 py-3 transition-all duration-200"
                    :class="[
                        isCurrentUrl(item.href)
                            ? 'bg-[rgb(var(--primary-brand-rgb)/0.14)] font-semibold text-(--primary-brand) shadow-[inset_0_1px_0_rgba(255,255,255,0.24),inset_0_-4px_10px_rgba(0,0,0,0.14)]'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-sidebar-foreground',
                    ]"
                >
                    <Link
                        :href="item.href"
                        class="flex w-full items-center gap-3"
                    >
                        <component
                            :is="item.icon"
                            :class="[
                                'h-5 w-5 shrink-0 transition-all duration-200',
                                isCurrentUrl(item.href)
                                    ? 'text-(--primary-brand)'
                                    : 'text-sidebar-foreground/70 group-hover:text-(--primary-brand)',
                            ]"
                        />
                        <span class="flex-1 text-sm font-medium">{{
                            item.title
                        }}</span>
                        <div
                            v-if="isCurrentUrl(item.href)"
                            class="h-1.5 w-1.5 rounded-full bg-(--primary-brand)"
                        />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
