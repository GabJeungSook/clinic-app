<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
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
// True only while THIS section is being captured to PDF, so its standalone
// letterhead is rendered into the DOM for the export (print-only → shown in the
// PDF clone). Local so it never affects the other sections on screen.
const exporting = ref(false);

const downloadPdf = async () => {
    downloading.value = true;
    exporting.value = true;
    await nextTick();
    try {
        await downloadElementPdf(cardEl.value?.$el, [props.meta.clinic, props.title, pdfDateStamp()]);
    } finally {
        exporting.value = false;
        downloading.value = false;
    }
};

// Hidden from the printout when a *different* section is being printed alone.
const hidden = computed(() => activeSection.value !== null && activeSection.value !== props.sectionKey);
// This section is the sole print target OR is being exported to PDF → show its
// standalone report header (same letterhead + title band as the whole report).
const standalone = computed(() => activeSection.value === props.sectionKey || exporting.value);
</script>

<template>
    <Card ref="cardEl" :class="{ 'print-hidden': hidden }">
        <!-- Standalone letterhead + title band — same format as the whole report,
             shown only when this section is printed/exported by itself. -->
        <template v-if="standalone">
            <table class="print-only report-letterhead">
                <tbody>
                    <tr>
                        <td class="report-letterhead__brand">
                            <span class="report-letterhead__name">{{ meta.clinic || 'Clinic' }}</span>
                            <span v-if="contactLine" class="report-letterhead__contact">{{ contactLine }}</span>
                        </td>
                        <td class="report-letterhead__meta">
                            <span class="report-letterhead__generated">Generated {{ meta.generated_at }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="print-only report-title">
                <span class="report-title__name">{{ title }}</span>
                <span v-if="subtitle" class="report-title__period">{{ subtitle }}</span>
            </div>
        </template>

        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <div>
                <!-- The card's own title doubles as the report title band when
                     printed alone, so hide it in that case to avoid repetition. -->
                <CardTitle v-if="!standalone" class="text-base">{{ title }}</CardTitle>
                <p v-if="subtitle && !standalone" class="text-xs text-muted-foreground no-print">{{ subtitle }}</p>
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

        <!-- Sign-off block when this section is printed/exported on its own -->
        <table v-if="standalone" class="print-only report-signoff">
            <tbody>
                <tr>
                    <td>
                        <div class="report-signoff__line"></div>
                        <p class="report-signoff__role">Prepared by</p>
                        <p class="report-signoff__cap">Signature over printed name &amp; date</p>
                    </td>
                    <td>
                        <div class="report-signoff__line"></div>
                        <p class="report-signoff__role">Approved by</p>
                        <p class="report-signoff__cap">Signature over printed name &amp; date</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </Card>
</template>
