<script setup lang="ts">
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Printer, Download } from '@lucide/vue';
import { usePrint } from '@/lib/print';
import { downloadElementPdf, pdfDateStamp } from '@/lib/pdf';

const props = defineProps<{
    sectionKey: string;
    title: string;
    meta: { clinic: string | null; generated_at: string; currency: string };
    subtitle?: string;
}>();

const { activeSection, printSection } = usePrint();

const cardEl = ref<{ $el: HTMLElement } | null>(null);
const downloading = ref(false);
const downloadPdf = async () => {
    downloading.value = true;
    try {
        await downloadElementPdf(cardEl.value?.$el, [props.meta.clinic, props.title, pdfDateStamp()]);
    } finally {
        downloading.value = false;
    }
};

// Hidden from the printout when a *different* section is being printed alone.
const hidden = computed(() => activeSection.value !== null && activeSection.value !== props.sectionKey);
// This section is the sole print target → show its standalone report header.
const soleTarget = computed(() => activeSection.value === props.sectionKey);
</script>

<template>
    <Card ref="cardEl" :class="{ 'print-hidden': hidden }">
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
            <div class="flex items-center gap-2 no-print">
                <Button variant="outline" size="sm" :disabled="downloading" @click="downloadPdf">
                    <Download class="mr-1.5 size-4" /> {{ downloading ? 'Preparing…' : 'PDF' }}
                </Button>
                <Button variant="outline" size="sm" @click="printSection(sectionKey)">
                    <Printer class="mr-1.5 size-4" /> Print
                </Button>
            </div>
        </CardHeader>

        <CardContent class="p-0">
            <slot />
        </CardContent>
    </Card>
</template>
