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
    meta: { clinic: string | null; address?: string | null; phone?: string | null; generated_at: string; currency: string };
    subtitle?: string;
}>();

const contactLine = computed(() =>
    [props.meta.address, props.meta.phone].filter((v) => v && String(v).trim() !== '').join('  ·  '),
);

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
        <!-- Standalone letterhead (only when this list is printed/exported by itself) -->
        <table v-if="soleTarget" class="print-only report-letterhead report-letterhead--section">
            <tbody>
                <tr>
                    <td class="report-letterhead__brand">
                        <span class="report-letterhead__name">{{ meta.clinic || 'Clinic' }}</span>
                        <span v-if="contactLine" class="report-letterhead__contact">{{ contactLine }}</span>
                    </td>
                    <td class="report-letterhead__meta">
                        <span class="report-letterhead__doctype">{{ title }}</span>
                        <span v-if="subtitle" class="report-letterhead__period">{{ subtitle }}</span>
                        <span class="report-letterhead__generated">Generated {{ meta.generated_at }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

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
