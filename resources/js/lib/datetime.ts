// Centralised date/time formatting so every screen shows the same style:
//   "August 24, 2026 1:00PM"  (date + time)
//   "August 24, 2026"          (date only)
//
// Accepts ISO strings ("2026-08-24", "2026-08-24T13:00:00"), Date objects,
// epoch millis, or null. A date-only string is parsed as local midnight so it
// never shifts a day due to the timezone offset.

type DateInput = string | number | Date | null | undefined;

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

/** True when the input carries a meaningful time-of-day component. */
function hasTime(value: DateInput): boolean {
    if (value instanceof Date) return value.getHours() !== 0 || value.getMinutes() !== 0;
    if (typeof value === 'number') return true;
    if (typeof value === 'string') return value.includes('T') || /\d{1,2}:\d{2}/.test(value);
    return false;
}

function toDate(value: DateInput): Date | null {
    if (value === null || value === undefined || value === '') return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
    if (typeof value === 'number') {
        const d = new Date(value);
        return isNaN(d.getTime()) ? null : d;
    }
    // Date-only string → pin to local midnight to avoid a UTC day shift.
    const raw = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value;
    const d = new Date(raw);
    return isNaN(d.getTime()) ? null : d;
}

/** "1:00PM" — hour with no leading zero, minutes padded, no space before AM/PM. */
export function fmtTime(value: DateInput): string {
    const d = toDate(value);
    if (!d) return '';
    let h = d.getHours();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const m = String(d.getMinutes()).padStart(2, '0');
    return `${h}:${m}${ampm}`;
}

/** "August 24, 2026" */
export function fmtDate(value: DateInput): string {
    const d = toDate(value);
    if (!d) return '';
    return `${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
}

/**
 * Main helper. Shows date + time when a time component is present, otherwise
 * just the date. Pass `withTime: true`/`false` to force one form.
 */
export function fmt(value: DateInput, opts: { withTime?: boolean } = {}): string {
    const d = toDate(value);
    if (!d) return '';
    const showTime = opts.withTime ?? hasTime(value);
    return showTime ? `${fmtDate(d)} ${fmtTime(d)}` : fmtDate(d);
}

export default fmt;
