import { CONTACT_EMAIL, LegalPage } from '@/components/legal-page';

export default function Terms() {
    return (
        <LegalPage title="Terms of Service" updatedAt="October 16, 2026">
            <p>
                By using this app you agree to these terms. If you do not agree,
                do not use the app.
            </p>

            <h2>The service</h2>
            <p>
                The app uploads videos you provide to the social accounts you
                connect and publishes them with the metadata you choose. It is
                provided as is, without guarantees that a platform accepts,
                keeps or ranks a video.
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
                    TikTok Terms of Service and Community Guidelines).
                </li>
                <li>
                    You are responsible for what you publish. Do not use the app
                    for spam, illegal content or misleading content.
                </li>
            </ul>

            <h2>Platform limits</h2>
            <p>
                Platforms apply their own limits, reviews and processing. A
                platform may reject, delay, restrict or remove a video, or
                revoke the app&apos;s access; we are not responsible for those
                decisions.
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

            <h2>Contact</h2>
            <p>
                <a className="underline" href={`mailto:${CONTACT_EMAIL}`}>
                    {CONTACT_EMAIL}
                </a>
            </p>
        </LegalPage>
    );
}
