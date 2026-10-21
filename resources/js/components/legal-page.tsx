import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cookies, home, privacy, terms } from '@/routes';

export const CONTACT_EMAIL = 'office@istorm.ro';

/** The legal entity that operates the app (data controller under GDPR). */
export const COMPANY = {
    name: 'SC ISTORM SRL',
    taxId: 'RO13256672',
    address: 'str. Alunis nr. 4, Targu Mures, jud. Mures, Romania',
};

export function LegalPage({
    title,
    updatedAt,
    children,
}: {
    title: string;
    updatedAt: string;
    children: ReactNode;
}) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-3xl items-center justify-between p-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-medium"
                        >
                            <AppLogoIcon className="size-6 fill-current" />
                            {name}
                        </Link>
                        <nav className="flex gap-4 text-sm text-muted-foreground">
                            <Link
                                href={privacy()}
                                className="hover:text-foreground"
                            >
                                Privacy
                            </Link>
                            <Link
                                href={terms()}
                                className="hover:text-foreground"
                            >
                                Terms
                            </Link>
                            <Link
                                href={cookies()}
                                className="hover:text-foreground"
                            >
                                Cookies
                            </Link>
                        </nav>
                    </div>
                </header>
                <main className="mx-auto max-w-3xl space-y-6 p-4 py-10 text-sm leading-relaxed [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc [&_p]:text-muted-foreground [&_ul]:space-y-1 [&_ul]:text-muted-foreground">
                    <div>
                        <h1 className="text-3xl font-semibold">{title}</h1>
                        <p className="mt-2">Last updated: {updatedAt}</p>
                    </div>
                    {children}
                </main>
            </div>
        </>
    );
}
