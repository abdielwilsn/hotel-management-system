<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
        fill="currentColor"
    >
        <!-- Hotel building icon -->
        <path
            d="M3 3h18v2H3V3zm0 3h18v13H3V6zm2 2v9h3V8H5zm5 0v9h3V8h-3zm5 0v9h3V8h-3zM3 20h18v1H3v-1z"
        />
    </svg>
</template>
