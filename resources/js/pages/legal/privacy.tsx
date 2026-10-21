import { Link } from '@inertiajs/react';
import { COMPANY, CONTACT_EMAIL, LegalPage } from '@/components/legal-page';
import { cookies } from '@/routes';

export default function Privacy() {
    return (
        <LegalPage title="Privacy Policy" updatedAt="October 21, 2026">
            <p>
                This app lets creators upload a short vertical video once and
                publish it to the social accounts they connect. This policy
                explains what personal data we process, why, and what rights you
                have under the EU General Data Protection Regulation (GDPR).
            </p>

            <h2>Data controller</h2>
            <p>
                The controller of your personal data is {COMPANY.name}, tax id{' '}
                {COMPANY.taxId}, {COMPANY.address}. You can reach us at{' '}
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
                .
            </p>

            <h2>Data we collect</h2>
            <ul>
                <li>
                    Your account details: name, email address and password
                    (stored hashed), and two-factor authentication settings if
                    you enable them.
                </li>
                <li>
                    Videos you upload and the captions, titles and settings you
                    write for each platform.
                </li>
                <li>
                    For each social account you connect (YouTube, Instagram,
                    Facebook Pages, TikTok, Threads, LinkedIn): its id, display
                    name, profile picture, the permissions you granted and the
                    access tokens the platform issues.
                </li>
                <li>
                    Publishing results returned by the platforms, such as post
                    ids, links, processing status and error messages.
                </li>
                <li>
                    Technical data needed to run the app securely: IP address,
                    browser details, session data and server logs.
                </li>
            </ul>

            <h2>Purposes and legal bases</h2>
            <ul>
                <li>
                    Providing the service (your account, uploads, publishing to
                    the accounts you pick, at the time you pick, and showing you
                    the result): performance of our contract with you (Art.
                    6(1)(b) GDPR).
                </li>
                <li>
                    Keeping the app secure and preventing abuse: our legitimate
                    interest (Art. 6(1)(f) GDPR).
                </li>
                <li>
                    Meeting legal obligations, such as accounting or answering
                    lawful requests from authorities (Art. 6(1)(c) GDPR).
                </li>
            </ul>
            <p>
                We do not sell data, show ads, profile you, or use your content
                or platform data for any other purpose.
            </p>

            <h2>Google user data</h2>
            <p>
                Our use and transfer of information received from Google APIs
                adheres to the{' '}
                <a
                    className="underline"
                    href="https://developers.google.com/terms/api-services-user-data-policy"
                >
                    Google API Services User Data Policy
                </a>
                , including the Limited Use requirements. We request the YouTube
                upload scope to upload videos to your channel and the read-only
                scope to identify the channel you connect.
            </p>

            <h2>Storage and security</h2>
            <ul>
                <li>Access and refresh tokens are encrypted at rest.</li>
                <li>
                    Videos are stored in private cloud object storage and are
                    shared with a platform only through short-lived signed links
                    while it is being published there.
                </li>
                <li>All traffic is encrypted with HTTPS.</li>
                <li>
                    Access to data is limited to what is needed to run and
                    support the app.
                </li>
            </ul>

            <h2>Service providers</h2>
            <ul>
                <li>
                    Cloudflare (R2 object storage): stores your uploaded videos.
                </li>
                <li>
                    Our hosting provider: runs the app, its database and its
                    background jobs.
                </li>
                <li>
                    The platforms you choose to publish to (Google/YouTube,
                    Meta/Instagram/Facebook/Threads, TikTok, LinkedIn) receive
                    your videos and metadata and process them under their own
                    privacy policies.
                </li>
            </ul>

            <h2>Transfers outside the EU/EEA</h2>
            <p>
                Some of these providers and platforms process data outside the
                EU/EEA, for example in the United States. Such transfers rely on
                an adequacy decision (such as the EU-U.S. Data Privacy
                Framework) or on the European Commission&apos;s Standard
                Contractual Clauses.
            </p>

            <h2>Retention and deletion</h2>
            <ul>
                <li>
                    We keep your data while your account exists. Deleting your
                    profile (Settings → Profile) deletes your account, videos,
                    posts and connected accounts.
                </li>
                <li>
                    Disconnecting an account deletes its tokens and, where the
                    platform supports it, revokes our access.
                </li>
                <li>Deleting a video removes the file from our storage.</li>
                <li>
                    Server logs and backups are kept only as long as needed for
                    security and recovery, then deleted.
                </li>
                <li>
                    Content already published on a platform stays there; remove
                    it on that platform.
                </li>
            </ul>
            <p>
                You can also revoke access at any time from your Google, Meta,
                TikTok or LinkedIn account settings.
            </p>

            <h2>Your rights</h2>
            <p>
                Under the GDPR you have the right to access your data, to have
                it corrected or deleted, to restrict or object to its
                processing, and to receive it in a portable format. To exercise
                these rights, email{' '}
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
                ; we answer within one month. You can also complain to the
                Romanian data protection authority, ANSPDCP (
                <a className="underline" href="https://www.dataprotection.ro">
                    dataprotection.ro
                </a>
                ), or to the authority in your EU country.
            </p>

            <h2>Cookies</h2>
            <p>
                We only use cookies needed to run the app. See the{' '}
                <Link className="underline" href={cookies()}>
                    Cookie Policy
                </Link>
                .
            </p>

            <h2>Changes</h2>
            <p>
                We may update this policy. We will change the date above and,
                for significant changes, tell you in the app or by email.
            </p>

            <h2>Contact</h2>
            <p>
                Questions about this policy:{' '}
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
                .
            </p>
        </LegalPage>
    );
}
