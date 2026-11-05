import { Link } from '@inertiajs/react';
import { CONTACT_EMAIL, LegalPage } from '@/components/legal-page';
import { privacy } from '@/routes';

export default function DataDeletion() {
    return (
        <LegalPage title="Data Deletion" updatedAt="November 5, 2026">
            <p>
                You can delete your data at any time. This applies to data we
                received from Facebook, Instagram, Threads, YouTube, TikTok and
                LinkedIn, as well as everything you stored in the app.
            </p>

            <h2>Delete everything</h2>
            <ul>
                <li>Sign in and open Settings → Profile.</li>
                <li>Choose Delete account and confirm with your password.</li>
            </ul>
            <p>
                This immediately deletes your account, videos, posts and
                connected accounts, including their access tokens.
            </p>

            <h2>Delete one connected account</h2>
            <p>
                Open Connected accounts and disconnect the account. This deletes
                its access tokens and, where the platform supports it, revokes
                our access.
            </p>

            <h2>If you can no longer sign in</h2>
            <p>
                Email{' '}
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>{' '}
                from the address of your account, or tell us the name of the
                connected account if you removed the app from Facebook,
                Instagram or Threads. We delete your data as soon as possible
                and within 7 days, then confirm by email.
            </p>

            <h2>What stays</h2>
            <ul>
                <li>
                    Content already published on a platform stays there; remove
                    it on that platform.
                </li>
                <li>
                    Server logs and backups are kept only as long as described
                    in our{' '}
                    <Link className="underline" href={privacy()}>
                        Privacy Policy
                    </Link>
                    .
                </li>
            </ul>
        </LegalPage>
    );
}
