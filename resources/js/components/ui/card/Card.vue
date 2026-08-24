<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground flex flex-col gap-6 rounded-2xl border border-border/60 py-6 shadow-[0_1px_2px_hsl(320_30%_20%/0.04),0_10px_30px_-16px_hsl(320_40%_30%/0.14)]',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
