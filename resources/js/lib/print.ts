// Shared print state for report pages. A module-level singleton is safe here
// because only one report page is mounted at a time.
//
// `activeSection` drives which part of the page prints:
//   null            → print the whole report (all sections)
//   '<sectionKey>'  → print only that one section as a clean standalone report
//
// ReportShell reads it to hide its full-report header during a section print;
// PrintableSection reads it to hide the other sections and show its own header.
import { nextTick, ref } from 'vue';

const activeSection = ref<string | null>(null);

async function run(key: string | null) {
    activeSection.value = key;
    await nextTick();
    // Switch the printable region into the professional "document" look for the
    // duration of the print dialog, then revert so the on-screen view is untouched.
    const el = document.querySelector('.printable');
    el?.classList.add('report-export');
    const done = () => {
        activeSection.value = null;
        el?.classList.remove('report-export');
        window.removeEventListener('afterprint', done);
    };
    window.addEventListener('afterprint', done);
    window.print();
}

export function usePrint() {
    return {
        activeSection,
        printAll: () => run(null),
        printSection: (key: string) => run(key),
    };
}
