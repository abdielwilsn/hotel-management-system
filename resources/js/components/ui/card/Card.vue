<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    accentClass?: HTMLAttributes['class'];
}>();

const baseShellClass =
    'overflow-hidden rounded-xl border border-slate-200/80 bg-card text-card-foreground flex flex-col gap-6 py-6 shadow-sm transition-shadow hover:shadow-md';

const baseAccentClass = 'from-slate-500 via-sky-400 to-cyan-400';
</script>

<template>
    <div data-slot="card" :class="cn(baseShellClass, props.class)">
        <div class="h-1 bg-linear-to-r" :class="props.accentClass ?? baseAccentClass" />
        <slot />
    </div>
</template>
