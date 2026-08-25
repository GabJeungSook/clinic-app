<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Printer, Download } from '@lucide/vue';
import { usePrint } from '@/lib/print';
import { downloadElementPdf, pdfDateStamp } from '@/lib/pdf';

const props = defineProps<{
    title: string;
    meta: { clinic: string | null; generated_at: string; currency: string };
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

        <!-- Print-only header — shown only when printing the whole report -->
        <div v-if="activeSection === null" class="print-only border-b pb-3">
            <h1 class="text-lg font-bold">{{ clinicName }}</h1>
            <p class="text-sm">
                {{ title }}<span v-if="subtitle"> · {{ subtitle }}</span>
            </p>
            <p class="text-xs text-muted-foreground">Generated {{ meta.generated_at }}</p>
        </div>

        <!-- On-screen title -->
        <div class="no-print">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>

        <slot />
    </div>
</template>
