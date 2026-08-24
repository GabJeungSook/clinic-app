<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        data: Array<{ label: string; value: number }>;
        formatValue?: (n: number) => string;
    }>(),
    { formatValue: (n: number) => String(n) },
);

// Internal coordinate system; the SVG scales responsively via viewBox.
const W = 640;
const H = 240;
const padL = 6;
const padR = 6;
const padT = 16;
const padB = 26;

const maxV = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
const innerW = W - padL - padR;
const innerH = H - padT - padB;

const points = computed(() => {
    const n = props.data.length;
    return props.data.map((d, i) => ({
        x: padL + (n <= 1 ? innerW / 2 : (i / (n - 1)) * innerW),
        y: padT + innerH - (d.value / maxV.value) * innerH,
        d,
    }));
});

const linePath = computed(() =>
    points.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
);

const areaPath = computed(() => {
    const pts = points.value;
    if (!pts.length) return '';
    const base = H - padB;
    return `${linePath.value} L${pts[pts.length - 1].x.toFixed(1)},${base} L${pts[0].x.toFixed(1)},${base} Z`;
});

const gridYs = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => padT + innerH * f));

const first = computed(() => props.data[0]?.label ?? '');
const last = computed(() => props.data[props.data.length - 1]?.label ?? '');
</script>

<template>
    <div class="w-full">
        <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="h-44 w-full text-primary">
            <!-- gridlines -->
            <line
                v-for="(gy, i) in gridYs"
                :key="i"
                :x1="padL"
                :x2="W - padR"
                :y1="gy"
                :y2="gy"
                class="stroke-border"
                stroke-width="1"
                vector-effect="non-scaling-stroke"
            />
            <!-- area + line -->
            <path :d="areaPath" fill="currentColor" fill-opacity="0.10" />
            <path
                :d="linePath"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
            />
            <!-- last point marker -->
            <circle
                v-if="points.length"
                :cx="points[points.length - 1].x"
                :cy="points[points.length - 1].y"
                r="3.5"
                fill="currentColor"
                vector-effect="non-scaling-stroke"
            />
        </svg>
        <div class="mt-1 flex justify-between px-1 text-[11px] text-muted-foreground">
            <span>{{ first }}</span>
            <span>peak {{ formatValue(maxV) }}</span>
            <span>{{ last }}</span>
        </div>
    </div>
</template>
