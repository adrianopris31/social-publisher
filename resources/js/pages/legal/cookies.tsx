import { CONTACT_EMAIL, LegalPage } from '@/components/legal-page';

export default function Cookies() {
    return (
        <LegalPage title="Cookie Policy" updatedAt="October 21, 2026">
            <p>
                Cookies are small text files a website stores in your browser.
                This app only uses cookies that are needed for it to work, so it
                does not ask for consent. We do not use analytics, advertising
                or tracking cookies.
            </p>

            <h2>Cookies we use</h2>
            <ul>
                <li>
                    Session cookie: keeps you signed in while you use the app.
                    Expires after 2 hours of inactivity.
                </li>
                <li>
                    XSRF-TOKEN: protects forms against cross-site request
                    forgery. Expires with the session.
                </li>
                <li>
                    remember_web_*: keeps you signed in between visits, only if
                    you tick &quot;Remember me&quot;. Removed when you log out.
                </li>
                <li>
                    appearance: remembers your light/dark theme choice. Expires
                    after 1 year.
                </li>
                <li>
                    sidebar_state: remembers whether the sidebar is open.
                    Expires after 7 days.
                </li>
            </ul>

            <h2>Third-party platforms</h2>
            <p>
                When you connect a social account, you sign in on that
                platform&apos;s own site (Google, Meta, TikTok, LinkedIn), which
                sets its own cookies under its own policy.
            </p>

            <h2>Controlling cookies</h2>
            <p>
                You can delete or block cookies in your browser settings.
                Blocking the session or XSRF-TOKEN cookies stops you from
                signing in.
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
