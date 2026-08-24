export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/** Backend flash messages shared by HandleInertiaRequests, shown as toasts. */
export type Flash = {
    success?: string | null;
    error?: string | null;
};
