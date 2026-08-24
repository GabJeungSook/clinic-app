<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        data: Array<{ label: string; value: number }>;
        color?: string;
    }>(),
    { color: 'bg-primary' },
);

const maxV = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
const first = computed(() => props.data[0]?.label ?? '');
const last = computed(() => props.data[props.data.length - 1]?.label ?? '');
</script>

<template>
    <div class="w-full">
        <div class="flex h-44 items-end gap-[3px]">
            <div
                v-for="(d, i) in data"
                :key="i"
                class="group relative flex-1 rounded-t transition-colors"
                :class="color"
                :style="{ height: `${Math.max(2, (d.value / maxV) * 100)}%`, opacity: d.value === 0 ? 0.25 : 1 }"
                :title="`${d.label}: ${d.value}`"
            />
        </div>
        <div class="mt-1 flex justify-between px-1 text-[11px] text-muted-foreground">
            <span>{{ first }}</span>
            <span>peak {{ maxV }}</span>
            <span>{{ last }}</span>
        </div>
    </div>
</template>
