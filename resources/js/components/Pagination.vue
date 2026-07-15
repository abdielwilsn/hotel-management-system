<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_url: string | null;
    next_url: string | null;
};

const props = defineProps<{
    pagination: PaginationMeta;
    /** Plural noun for the "Showing X–Y of N ___" label. */
    label?: string;
}>();

const goToPage = (url: string | null) => {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <div
        v-if="pagination.total > 0"
        class="flex flex-col items-center justify-between gap-3 px-1 sm:flex-row"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of
            {{ pagination.total }} {{ props.label ?? 'results' }}
        </p>
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.prev_url"
                @click="goToPage(pagination.prev_url)"
            >
                Previous
            </Button>
            <span class="text-sm text-muted-foreground">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.next_url"
                @click="goToPage(pagination.next_url)"
            >
                Next
            </Button>
        </div>
    </div>
</template>
