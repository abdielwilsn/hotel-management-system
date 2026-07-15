<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-12 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="mb-4 size-10 text-muted-foreground"
        />
        <h3 class="text-lg font-semibold">{{ title }}</h3>
        <p
            v-if="description"
            class="mt-1 max-w-md text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
