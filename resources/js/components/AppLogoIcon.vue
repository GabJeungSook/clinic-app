<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

type Props = { className?: HTMLAttributes['class'] };
defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Lotus / petal mark for an aesthetic clinic -->
        <path d="M12 21c-4.5 0-8-2.6-8-6 2.4 0 4.4.9 5.7 2.3" />
        <path d="M12 21c4.5 0 8-2.6 8-6-2.4 0-4.4.9-5.7 2.3" />
        <path d="M12 21c-2.2 0-3.8-2.4-3.8-5.4 0-2.7 1.5-5.3 3.8-7.6 2.3 2.3 3.8 4.9 3.8 7.6 0 3-1.6 5.4-3.8 5.4Z" />
        <path d="M12 8V3" opacity="0.9" />
        <circle cx="12" cy="3" r="0.9" fill="currentColor" stroke="none" />
    </svg>
</template>
