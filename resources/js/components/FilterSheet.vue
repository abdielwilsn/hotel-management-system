<script setup lang="ts">
import { SlidersHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

withDefaults(
    defineProps<{
        activeCount?: number;
        title?: string;
        description?: string;
    }>(),
    {
        activeCount: 0,
        title: 'Filters',
        description: 'Refine the results below.',
    },
);

const emit = defineEmits<{
    apply: [];
    clear: [];
}>();

const open = ref(false);

const apply = () => {
    emit('apply');
    open.value = false;
};

const clear = () => {
    emit('clear');
};
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="min-w-0 flex-1">
            <slot name="search" />
        </div>

        <Sheet v-model:open="open">
            <SheetTrigger as-child>
                <Button type="button" variant="outline" class="shrink-0 gap-2">
                    <SlidersHorizontal class="h-4 w-4" />
                    <span class="hidden sm:inline">Filters</span>
                    <Badge
                        v-if="activeCount > 0"
                        class="ml-0.5 h-5 min-w-5 justify-center rounded-full px-1.5 tabular-nums"
                    >
                        {{ activeCount }}
                    </Badge>
                </Button>
            </SheetTrigger>

            <SheetContent
                side="right"
                class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
            >
                <SheetHeader class="border-b">
                    <SheetTitle>{{ title }}</SheetTitle>
                    <SheetDescription>{{ description }}</SheetDescription>
                </SheetHeader>

                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <slot />
                </div>

                <SheetFooter class="flex-row gap-2 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1"
                        @click="clear"
                    >
                        Clear
                    </Button>
                    <Button type="button" class="flex-1" @click="apply">
                        Apply filters
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>
</template>
