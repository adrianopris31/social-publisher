import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Reload the given props every few seconds while the condition holds,
 * e.g. while uploads are still in flight.
 */
export function usePollWhile(
    condition: boolean,
    only: string[],
    intervalMs = 4000,
): void {
    const { start, stop } = usePoll(
        intervalMs,
        { only },
        { autoStart: false, background: 'pause' },
    );

    useEffect(() => {
        if (condition) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [condition]);
}
