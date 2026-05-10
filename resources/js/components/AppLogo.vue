<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-to-br from-teal-600 to-teal-800 text-white shadow-md"
    >
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span
            class="truncate bg-gradient-to-r from-teal-600 to-teal-700 bg-clip-text leading-tight font-bold text-transparent"
            >HMS</span
        >
        <span class="text-xs leading-tight text-muted-foreground"
            >Hotel Management</span
        >
    </div>
</template>
