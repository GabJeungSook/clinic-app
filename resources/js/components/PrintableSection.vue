<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Printer } from '@lucide/vue';
import { usePrint } from '@/lib/print';

const props = defineProps<{
    sectionKey: string;
    title: string;
    meta: { clinic: string | null; generated_at: string; currency: string };
    subtitle?: string;
}>();

const { activeSection, printSection } = usePrint();

// Hidden from the printout when a *different* section is being printed alone.
const hidden = computed(() => activeSection.value !== null && activeSection.value !== props.sectionKey);
// This section is the sole print target → show its standalone report header.
const soleTarget = computed(() => activeSection.value === props.sectionKey);
</script>

<template>
    <Card :class="{ 'print-hidden': hidden }">
        <!-- Standalone report header (only when this list is printed by itself) -->
        <div v-if="soleTarget" class="print-only border-b px-6 pb-3 pt-4">
            <h1 class="text-lg font-bold">{{ meta.clinic || 'Clinic' }}</h1>
            <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
            <p class="text-xs text-muted-foreground">Generated {{ meta.generated_at }}</p>
        </div>

        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <div>
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <p v-if="subtitle" class="text-xs text-muted-foreground no-print">{{ subtitle }}</p>
            </div>
            <Button variant="outline" size="sm" class="no-print" @click="printSection(sectionKey)">
                <Printer class="mr-1.5 size-4" /> Print
            </Button>
        </CardHeader>

        <CardContent class="p-0">
            <slot />
        </CardContent>
    </Card>
</template>
