import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, Layers, Upload } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { platformIcons, platformNames } from '@/lib/platforms';
import { cookies, dashboard, login, privacy, register, terms } from '@/routes';
import type { Platform } from '@/types';

const platforms: Platform[] = [
    'youtube',
    'instagram',
    'facebook',
    'tiktok',
    'threads',
    'linkedin',
];

const features = [
    {
        icon: Upload,
        title: 'Upload once',
        description:
            'Drop one vertical video. We check the format, ratio and length before anything goes out.',
    },
    {
        icon: Layers,
        title: 'Customize per platform',
        description:
            'One caption for everywhere, with overrides for YouTube titles, visibility and Instagram feed sharing.',
    },
    {
        icon: CalendarClock,
        title: 'Publish now or later',
        description:
            'Schedule a post and track every platform separately: uploading, processing, published or failed.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Publish short videos everywhere" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between p-4">
                    <span className="flex items-center gap-2 font-medium">
                        <AppLogoIcon className="size-6 fill-current" />
                        {name}
                    </span>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={register()}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-4 py-16 text-center md:py-24">
                    <h1 className="max-w-2xl text-4xl font-semibold tracking-tight md:text-5xl">
                        One upload. Every short-form platform.
                    </h1>
                    <p className="mt-4 max-w-xl text-lg text-muted-foreground">
                        Publish your vertical videos to YouTube Shorts,
                        Instagram Reels, Facebook Reels, TikTok, Threads and
                        LinkedIn from one place.
                    </p>

                    <div className="mt-8 flex flex-wrap justify-center gap-3">
                        {platforms.map((platform) => {
                            const Icon = platformIcons[platform];

                            return (
                                <span
                                    key={platform}
                                    className="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                                >
                                    <Icon className="size-4" />
                                    {platformNames[platform]}
                                </span>
                            );
                        })}
                    </div>

                    <Button asChild size="lg" className="mt-8">
                        <Link href={auth.user ? dashboard() : register()}>
                            {auth.user ? 'Go to dashboard' : 'Start publishing'}
                        </Link>
                    </Button>

                    <div className="mt-20 grid w-full gap-6 text-left md:grid-cols-3">
                        {features.map(({ icon: Icon, title, description }) => (
                            <div key={title} className="rounded-xl border p-5">
                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <Icon className="size-5" />
                                </div>
                                <h2 className="mt-4 font-medium">{title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-5xl items-center justify-between p-4 text-sm text-muted-foreground">
                        <span>
                            © {new Date().getFullYear()} {name}
                        </span>
                        <nav className="flex gap-4">
                            <Link
                                href={privacy()}
                                className="hover:text-foreground"
                            >
                                Privacy Policy
                            </Link>
                            <Link
                                href={terms()}
                                className="hover:text-foreground"
                            >
                                Terms of Service
                            </Link>
                            <Link
                                href={cookies()}
                                className="hover:text-foreground"
                            >
                                Cookie Policy
                            </Link>
                        </nav>
                    </div>
                </footer>
            </div>
        </>
    );
}
