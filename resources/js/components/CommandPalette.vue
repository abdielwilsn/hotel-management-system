<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { search as searchRoute } from '@/routes';

type SearchResult = {
    type: string;
    label: string;
    sublabel: string | null;
    href: string;
};

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? null);

const open = ref(false);
const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const onKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = !open.value;
    }
};

const openFromEvent = () => {
    open.value = true;
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('open-command-palette', openFromEvent);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('open-command-palette', openFromEvent);
});

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
        results.value = [];
        activeIndex.value = 0;
    }
});

const runSearch = async () => {
    const term = query.value.trim();

    if (!teamSlug.value || term.length < 2) {
        results.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const url = `${searchRoute(teamSlug.value).url}?q=${encodeURIComponent(term)}`;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        results.value = data.results ?? [];
        activeIndex.value = 0;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(runSearch, 200);
});

const select = (result: SearchResult | undefined) => {
    if (!result) {
        return;
    }

    open.value = false;
    router.visit(result.href);
};

const onListKeydown = (event: KeyboardEvent) => {
    if (results.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + results.value.length) %
            results.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        select(results.value[activeIndex.value]);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-xl gap-0 overflow-hidden p-0">
            <DialogHeader class="sr-only">
                <DialogTitle>Search</DialogTitle>
                <DialogDescription>
                    Search guests, bookings, and rooms
                </DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2 border-b px-4">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search guests, bookings, rooms…"
                    class="h-12 w-full bg-transparent text-sm outline-none"
                    autofocus
                    @keydown="onListKeydown"
                />
            </div>

            <div class="max-h-80 overflow-y-auto p-2">
                <p
                    v-if="loading"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Searching…
                </p>
                <p
                    v-else-if="query.trim().length >= 2 && results.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    No matches for “{{ query }}”.
                </p>
                <p
                    v-else-if="query.trim().length < 2"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Type at least 2 characters to search.
                </p>
                <button
                    v-for="(result, i) in results"
                    :key="`${result.type}-${i}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm"
                    :class="
                        i === activeIndex ? 'bg-accent' : 'hover:bg-accent/60'
                    "
                    @mouseenter="activeIndex = i"
                    @click="select(result)"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{
                            result.label
                        }}</span>
                        <span
                            v-if="result.sublabel"
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ result.sublabel }}
                        </span>
                    </span>
                    <span
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                        {{ result.type }}
                    </span>
                </button>
            </div>

            <div
                class="flex items-center justify-between border-t px-4 py-2 text-[11px] text-muted-foreground"
            >
                <span>↑↓ to navigate · ↵ to open</span>
                <span>⌘K / Ctrl-K</span>
            </div>
        </DialogContent>
    </Dialog>
</template>
