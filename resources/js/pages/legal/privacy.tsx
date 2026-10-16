import { CONTACT_EMAIL, LegalPage } from '@/components/legal-page';

export default function Privacy() {
    return (
        <LegalPage title="Privacy Policy" updatedAt="October 16, 2026">
            <p>
                This app lets creators upload a short vertical video once and
                publish it to the social accounts they connect. This policy
                explains what data we handle and why.
            </p>

            <h2>Data we collect</h2>
            <ul>
                <li>
                    Your account details: name, email address and password
                    (stored hashed).
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
            </ul>

            <h2>How we use it</h2>
            <p>
                We use connected-account data only to publish the videos you ask
                us to publish, to the accounts you pick, at the time you pick,
                and to show you the result. We do not sell data, show ads, or
                use your content or platform data for any other purpose.
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
            </ul>

            <h2>Retention and deletion</h2>
            <ul>
                <li>
                    Disconnecting an account deletes its tokens and, where the
                    platform supports it, revokes our access.
                </li>
                <li>Deleting a video removes the file from our storage.</li>
                <li>
                    Deleting your profile (Settings → Profile) deletes your
                    account, videos, posts and connected accounts.
                </li>
                <li>
                    Content already published on a platform stays there; remove
                    it on that platform.
                </li>
            </ul>
            <p>
                You can also revoke access at any time from your Google, Meta or
                TikTok account settings, or ask us to delete your data by
                emailing{' '}
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
                .
            </p>

            <h2>Third parties</h2>
            <p>
                Videos and metadata are sent to the platforms you choose
                (Google/YouTube, Meta/Instagram/Facebook/Threads, TikTok,
                LinkedIn), each under its own privacy policy. Files are stored
                with Cloudflare R2.
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
