<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Printer, Download } from '@lucide/vue';
import { usePrint } from '@/lib/print';
import { downloadElementPdf, pdfDateStamp } from '@/lib/pdf';

const props = defineProps<{
    title: string;
    meta: { clinic: string | null; address?: string | null; phone?: string | null; generated_at: string; currency: string };
    subtitle?: string;
}>();

const root = ref<HTMLElement | null>(null);
const downloading = ref(false);
const downloadPdf = async () => {
    downloading.value = true;
    try {
        await downloadElementPdf(root.value, [props.meta.clinic, props.title, pdfDateStamp()]);
    } finally {
        downloading.value = false;
    }
};

const tabs = [
    { label: 'Revenue', href: '/reports/revenue' },
    { label: 'Sales', href: '/reports/sales' },
    { label: 'Appointments', href: '/reports/appointments' },
    { label: 'Patients', href: '/reports/patients' },
    { label: 'Treatments', href: '/reports/treatments' },
    { label: 'Inventory', href: '/reports/inventory' },
    { label: 'Purchasing', href: '/reports/purchasing' },
];

const page = usePage();
const isActive = (href: string) => (page.url as string).split('?')[0] === href;

const clinicName = computed(() => props.meta.clinic || 'Clinic');
const contactLine = computed(() =>
    [props.meta.address, props.meta.phone].filter((v) => v && String(v).trim() !== '').join('  ·  '),
);

const { activeSection, printAll } = usePrint();
</script>

<template>
    <div ref="root" class="printable flex flex-col gap-6 p-4 md:p-6">
        <!-- On-screen toolbar: report tabs + print-all button (hidden when printing) -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex flex-wrap rounded-lg border bg-muted/40 p-0.5">
                <Link
                    v-for="t in tabs"
                    :key="t.href"
                    :href="t.href"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="isActive(t.href) ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ t.label }}
                </Link>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="default" size="sm" :disabled="downloading" @click="downloadPdf">
                    <Download class="mr-1.5 size-4" /> {{ downloading ? 'Preparing…' : 'Download PDF' }}
                </Button>
                <Button variant="outline" size="sm" @click="printAll">
                    <Printer class="mr-1.5 size-4" /> Print
                </Button>
            </div>
        </div>

        <!-- Print/PDF letterhead + report-title band — real tables/blocks so they
             render correctly in the PDF (html2canvas does not lay out flex/grid). -->
        <template v-if="activeSection === null">
            <table class="print-only report-letterhead">
                <tbody>
                    <tr>
                        <td class="report-letterhead__brand">
                            <span class="report-letterhead__name">{{ clinicName }}</span>
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

        <!-- On-screen title -->
        <div class="no-print">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>

        <slot />

        <!-- Sign-off block (print/PDF only) -->
        <table v-if="activeSection === null" class="print-only report-signoff">
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
    </div>
</template>
