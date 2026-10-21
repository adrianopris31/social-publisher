import { Link } from '@inertiajs/react';
import { COMPANY, CONTACT_EMAIL, LegalPage } from '@/components/legal-page';
import { privacy } from '@/routes';

export default function Terms() {
    return (
        <LegalPage title="Terms of Service" updatedAt="October 21, 2026">
            <p>
                By using this app you agree to these terms. If you do not agree,
                do not use the app.
            </p>

            <h2>Who we are</h2>
            <p>
                The app is operated by {COMPANY.name}, tax id {COMPANY.taxId},{' '}
                {COMPANY.address} (&quot;we&quot;).
            </p>

            <h2>The service</h2>
            <p>
                The app uploads videos you provide to the social accounts you
                connect and publishes them with the metadata you choose, now or
                at a scheduled time. It is provided as is, without guarantees
                that a platform accepts, keeps or ranks a video.
            </p>

            <h2>Your content and accounts</h2>
            <ul>
                <li>
                    You own your videos and must have the rights to publish
                    them, including music and other third-party material.
                </li>
                <li>
                    You may only connect accounts you are authorized to manage,
                    and you must follow the rules of each platform (YouTube
                    Terms of Service, Meta Terms and Community Guidelines,
                    TikTok Terms of Service and Community Guidelines, LinkedIn
                    User Agreement).
                </li>
                <li>
                    You are responsible for what you publish. Do not use the app
                    for spam, illegal content or misleading content.
                </li>
                <li>
                    Keep your login details secure; you are responsible for
                    activity on your account.
                </li>
            </ul>

            <h2>Platform limits</h2>
            <p>
                Platforms apply their own limits, reviews and processing. A
                platform may reject, delay, restrict or remove a video, or
                revoke the app&apos;s access; we are not responsible for those
                decisions.
            </p>

            <h2>Availability</h2>
            <p>
                We aim to keep the app available, but it may be interrupted for
                maintenance, updates or reasons outside our control. Keep your
                own copies of your videos.
            </p>

            <h2>Personal data</h2>
            <p>
                We process personal data as described in the{' '}
                <Link className="underline" href={privacy()}>
                    Privacy Policy
                </Link>
                .
            </p>

            <h2>Termination</h2>
            <p>
                You can delete your profile at any time. We may suspend accounts
                that break these terms or a platform&apos;s rules.
            </p>

            <h2>Liability</h2>
            <p>
                To the extent permitted by law, we are not liable for indirect
                damages, lost revenue or lost content arising from use of the
                app.
            </p>

            <h2>Changes</h2>
            <p>
                We may update these terms. We will change the date above and,
                for significant changes, tell you in the app or by email.
                Continuing to use the app after a change means you accept it.
            </p>

            <h2>Governing law</h2>
            <p>
                These terms are governed by Romanian law. Disputes are settled
                by the competent courts in Romania, without limiting any
                mandatory consumer protection rights you have where you live.
            </p>

            <h2>Contact</h2>
            <p>
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
            </p>
        </LegalPage>
    );
}
