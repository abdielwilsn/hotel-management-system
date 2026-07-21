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
    <!--
        The emblem only, on a transparent background. The wordmark in the full
        lockup is black, so it is rendered as live text instead of baked into
        the image; that keeps it readable in dark mode.
    -->
    <img
        src="/images/annshaven-mark.png"
        alt="Ann's Haven"
        :class="className"
        v-bind="$attrs"
    />
</template>
