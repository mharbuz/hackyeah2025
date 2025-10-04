<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img 
        src="/zus-logo.svg" 
        alt="ZUS Logo" 
        :class="className"
        v-bind="$attrs"
        class="h-6 w-auto"
    />
</template>
