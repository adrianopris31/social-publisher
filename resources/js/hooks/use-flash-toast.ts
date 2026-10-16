import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

function showToast(data: FlashToast | undefined): void {
    if (!data) {
        return;
    }

    // A stable id lets sonner drop the duplicate if the initial flash event also arrives.
    toast[data.type](data.message, { id: `${data.type}:${data.message}` });
}

/**
 * Show flashed toasts. The initial page's toast is passed in because Inertia
 * fires the first flash event before this effect subscribes, e.g. after an
 * OAuth callback redirects back with a full page load.
 */
export function useFlashToast(initialToast?: FlashToast): void {
    useEffect(() => {
        showToast(initialToast);

        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;

            showToast(flash?.toast as FlashToast | undefined);
        });
    }, []);
}
