import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    Film,
    Link2,
    Loader2,
    Plus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import Heading from '@/components/heading';
import { hasInFlightPublications, PostCard } from '@/components/post-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePollWhile } from '@/hooks/use-poll-while';
import { dashboard } from '@/routes';
import { create as createPost, index as postsIndex } from '@/routes/posts';
import { index as socialAccountsIndex } from '@/routes/social-accounts';
import { create as createVideo } from '@/routes/videos';
import type { Post } from '@/types';

type Stats = {
    published: number;
    in_progress: number;
    failed: number;
    scheduled: number;
    accounts: number;
    videos: number;
};

export default function Dashboard({
    stats,
    recentPosts,
}: {
    stats: Stats;
    recentPosts: { data: Post[] };
}) {
    usePollWhile(hasInFlightPublications(recentPosts.data), [
        'stats',
        'recentPosts',
    ]);

    const tiles: { label: string; value: number; icon: LucideIcon }[] = [
        { label: 'Published', value: stats.published, icon: CheckCircle2 },
        { label: 'In progress', value: stats.in_progress, icon: Loader2 },
        {
            label: 'Scheduled posts',
            value: stats.scheduled,
            icon: CalendarClock,
        },
        { label: 'Failed', value: stats.failed, icon: AlertCircle },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Dashboard"
                        description="Your publishing at a glance."
                    />
                    <Button asChild>
                        <Link href={createPost()}>
                            <Plus />
                            New post
                        </Link>
                    </Button>
                </div>

                {(stats.accounts === 0 || stats.videos === 0) && (
                    <GettingStarted stats={stats} />
                )}

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {tiles.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-1 py-4">
                            <CardContent className="px-4">
                                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Icon className="size-4" />
                                    {label}
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold">
                            Recent posts
                        </h2>
                        {recentPosts.data.length > 0 && (
                            <Button asChild variant="link" size="sm">
                                <Link href={postsIndex()}>View all</Link>
                            </Button>
                        )}
                    </div>

                    {recentPosts.data.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing published yet.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {recentPosts.data.map((post) => (
                                <PostCard key={post.id} post={post} />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function GettingStarted({ stats }: { stats: Stats }) {
    const steps = [
        {
            done: stats.accounts > 0,
            title: 'Connect your accounts',
            description: 'YouTube, Instagram, Facebook Pages and TikTok.',
            href: socialAccountsIndex(),
            icon: Link2,
        },
        {
            done: stats.videos > 0,
            title: 'Upload a vertical video',
            description: 'MP4 or MOV, 9:16, up to 3 minutes.',
            href: createVideo(),
            icon: Film,
        },
    ];

    return (
        <Card className="py-4">
            <CardContent className="grid gap-3 px-4 md:grid-cols-2">
                {steps.map((step) => (
                    <Link
                        key={step.title}
                        href={step.href}
                        className="flex items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                    >
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                            {step.done ? (
                                <CheckCircle2 className="size-4 text-green-600" />
                            ) : (
                                <step.icon className="size-4" />
                            )}
                        </div>
                        <div>
                            <p className="text-sm font-medium">{step.title}</p>
                            <p className="text-xs text-muted-foreground">
                                {step.description}
                            </p>
                        </div>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
