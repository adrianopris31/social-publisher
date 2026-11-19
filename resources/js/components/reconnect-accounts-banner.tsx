import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { platformNames } from '@/lib/platforms';
import { index as socialAccountsIndex } from '@/routes/social-accounts';

/**
 * Warns on every page when a connected account lost access, because publishing to it fails
 * (including scheduled posts that run while nobody is watching) until the user reconnects it.
 */
export function ReconnectAccountsBanner() {
    const { props, url } = usePage();
    const accounts = props.accountsNeedingReconnection ?? [];

    if (accounts.length === 0 || url.startsWith('/social-accounts')) {
        return null;
    }

    const scheduled = accounts.reduce(
        (total, account) => total + account.scheduled_publications,
        0,
    );
    const names = accounts
        .map(
            (account) =>
                `${account.display_name} (${platformNames[account.platform]})`,
        )
        .join(', ');

    return (
        <div className="px-4 pt-4">
            <Alert className="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                <AlertTriangle />
                <AlertTitle>
                    {accounts.length === 1
                        ? 'An account needs to be reconnected'
                        : `${accounts.length} accounts need to be reconnected`}
                </AlertTitle>
                <AlertDescription className="text-amber-900/80 dark:text-amber-100/80">
                    <p>
                        The login for {names} expired or was revoked, so we
                        can't publish there until you reconnect.
                        {scheduled > 0 &&
                            ` ${scheduled} scheduled ${scheduled === 1 ? 'post' : 'posts'} will fail if you don't.`}
                    </p>
                    <Button asChild size="sm" className="mt-1">
                        <Link href={socialAccountsIndex()}>Reconnect</Link>
                    </Button>
                </AlertDescription>
            </Alert>
        </div>
    );
}
