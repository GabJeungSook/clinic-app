import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { Flash } from '@/types/ui';

/**
 * Surface backend flash messages as toasts. Controllers set standard Laravel
 * session flash via `->with('success', …)` / `->with('error', …)`, which the
 * Inertia middleware shares as the `flash` prop. After every successful visit
 * (a redirect after a write is itself a visit), we read that prop and toast it.
 */
export function initializeFlashToast(): void {
    router.on('success', (event) => {
        const page = (event as CustomEvent).detail?.page;
        const flash = page?.props?.flash as Flash | undefined;

        if (!flash) {
            return;
        }

        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    });
}
