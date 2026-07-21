<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-white ring-1 ring-black/5 dark:bg-white/95"
    >
        <AppLogoIcon class="size-6" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-semibold">Ann's Haven</span>
        <span class="text-xs leading-tight text-muted-foreground">
            Hotel &amp; Suites
        </span>
    </div>
</template>
