// Offline "Download PDF" for reports.
//
// The desktop shell (NativePHP/Electron) has no print preview and no built-in
// "save as PDF", so we render the report DOM to a paginated A4 PDF entirely in
// the browser. jsPDF + html2canvas-pro are bundled at build time, so this works
// with no internet. html2canvas-pro (not the original) is used because Tailwind
// v4 emits modern oklch/lab colours that the original html2canvas chokes on.

let busy = false;

/** Turn a title into a safe, dated filename. */
function fileName(parts: Array<string | null | undefined>): string {
    const base = parts
        .filter(Boolean)
        .join('-')
        .replace(/[^a-z0-9\-_ ]/gi, '')
        .replace(/\s+/g, '-')
        .toLowerCase();
    return `${base || 'report'}.pdf`;
}

/**
 * Render an element to a multi-page A4 PDF and trigger a download.
 * Always renders in light mode (readable on paper) and skips `.no-print` chrome.
 */
export async function downloadElementPdf(el: HTMLElement | null | undefined, nameParts: Array<string | null | undefined>): Promise<void> {
    if (!el || busy) {
        return;
    }
    busy = true;
    try {
        const [{ default: jsPDF }, { default: html2canvas }] = await Promise.all([
            import('jspdf'),
            import('html2canvas-pro'),
        ]);

        const canvas = await html2canvas(el, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            // Drop screen-only chrome (toolbars, filters, per-section buttons).
            ignoreElements: (node) => node.classList?.contains('no-print') ?? false,
            // Force a light theme on the *cloned* DOM only, so the real page
            // never flickers during capture.
            onclone: (doc: Document) => {
                doc.documentElement.classList.remove('dark');
            },
        });

        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const margin = 6;
        const imgW = pageW - margin * 2;
        const imgH = (canvas.height * imgW) / canvas.width;
        // JPEG keeps the file small (a PNG of a full report can be tens of MB);
        // 0.92 quality is indistinguishable for text/tables on paper.
        const imgData = canvas.toDataURL('image/jpeg', 0.92);

        let page = 0;
        let remaining = imgH;
        while (remaining > 0) {
            if (page > 0) {
                pdf.addPage();
            }
            // Draw the full tall image shifted up one page height per page.
            pdf.addImage(imgData, 'JPEG', margin, margin - page * pageH, imgW, imgH);
            remaining -= pageH;
            page++;
        }

        pdf.save(fileName(nameParts));
    } finally {
        busy = false;
    }
}

/** A stable, human date fragment for filenames (no external date lib needed). */
export function pdfDateStamp(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
