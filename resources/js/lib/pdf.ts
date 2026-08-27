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
    // Off-screen copy styled as the professional "document" (see app.css
    // .report-export). We capture the CLONE rather than the live element so its
    // geometry matches the document styling — html2canvas sizes the canvas from
    // the element it renders, so styling and measuring stay in sync — and the
    // on-screen report never flickers.
    let clone: HTMLElement | null = null;
    try {
        const [{ default: jsPDF }, { default: html2canvas }] = await Promise.all([
            import('jspdf'),
            import('html2canvas-pro'),
        ]);

        clone = el.cloneNode(true) as HTMLElement;
        clone.classList.add('report-export');
        Object.assign(clone.style, {
            position: 'fixed',
            left: '-10000px',
            top: '0',
            width: '720px',
            maxWidth: '720px',
            background: '#ffffff',
        });
        document.body.appendChild(clone);

        const canvas = await html2canvas(clone, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            // Drop screen-only chrome (toolbars, filters, per-section buttons).
            ignoreElements: (node) => node.classList?.contains('no-print') ?? false,
            // Force a light theme on the clone only (readable on paper).
            onclone: (doc: Document) => {
                doc.documentElement.classList.remove('dark');
            },
        });

        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const mX = 14; // side margins (mm) → content sits centred on the page
        const mTop = 14;
        const mBottom = 16; // leaves room for the footer
        const imgW = pageW - mX * 2;
        const imgH = (canvas.height * imgW) / canvas.width;
        const usableH = pageH - mTop - mBottom;
        // JPEG keeps the file small (a PNG of a full report can be tens of MB);
        // 0.92 quality is indistinguishable for text/tables on paper.
        const imgData = canvas.toDataURL('image/jpeg', 0.92);

        const footerLeft = [nameParts[0], nameParts[1]].filter(Boolean).join('  ·  ');
        const pageCount = Math.max(1, Math.ceil(imgH / usableH));

        for (let page = 0; page < pageCount; page++) {
            if (page > 0) {
                pdf.addPage();
            }
            // Draw the full tall image shifted up one usable-height per page.
            pdf.addImage(imgData, 'JPEG', mX, mTop - page * usableH, imgW, imgH);
            // Mask whatever bleeds into the top/bottom margins — that content is
            // the adjacent page's and is rendered there, so nothing is lost.
            pdf.setFillColor(255, 255, 255);
            pdf.rect(0, 0, pageW, mTop, 'F');
            pdf.rect(0, pageH - mBottom, pageW, mBottom, 'F');
            // Footer: divider + clinic/title on the left, page number on the right.
            pdf.setDrawColor(225);
            pdf.line(mX, pageH - mBottom + 5, pageW - mX, pageH - mBottom + 5);
            pdf.setFontSize(8);
            pdf.setTextColor(150);
            if (footerLeft) {
                pdf.text(footerLeft, mX, pageH - mBottom + 10);
            }
            pdf.text(`Page ${page + 1} of ${pageCount}`, pageW - mX, pageH - mBottom + 10, { align: 'right' });
        }

        pdf.save(fileName(nameParts));
    } finally {
        clone?.remove();
        busy = false;
    }
}

/** A stable, human date fragment for filenames (no external date lib needed). */
export function pdfDateStamp(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
