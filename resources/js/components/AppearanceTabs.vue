<script setup lang="ts">
import { Sun } from 'lucide-vue-next';
</script>

<template>
    <div class="space-y-4">
        <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1">
            <span
                class="flex items-center rounded-md bg-white px-3.5 py-1.5 text-sm text-neutral-900 shadow-xs"
            >
                <Sun class="-ml-1 h-4 w-4" />
                <span class="ml-1.5">Light</span>
            </span>
        </div>
        <p class="text-sm text-muted-foreground">
            Dark mode is disabled. Brand colors are standardized across the app
            for consistency.
        </p>
    </div>
</template>
