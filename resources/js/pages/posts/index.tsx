import { Head, Link } from '@inertiajs/react';
import { Plus, Send } from 'lucide-react';
import Heading from '@/components/heading';
import { hasInFlightPublications, PostCard } from '@/components/post-card';
import { Button } from '@/components/ui/button';
import { usePollWhile } from '@/hooks/use-poll-while';
import { create, index } from '@/routes/posts';
import type { Post, ResourcePage } from '@/types';

export default function PostsIndex({
    posts,
    nextLimit,
}: {
    posts: ResourcePage<Post>;
    nextLimit: number | null;
}) {
    usePollWhile(hasInFlightPublications(posts.data), ['posts', 'nextLimit']);

    return (
        <>
            <Head title="Posts" />

            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Posts"
                        description="Every post and where it was published. Statuses update on their own."
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New post
                        </Link>
                    </Button>
                </div>

                {posts.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="space-y-4">
                        {posts.data.map((post) => (
                            <PostCard key={post.id} post={post} />
                        ))}
                    </div>
                )}

                {nextLimit !== null && (
                    <div className="mt-6 flex justify-center">
                        <Button
                            asChild
                            variant="outline"
                            className="data-loading:pointer-events-none data-loading:opacity-60"
                        >
                            <Link
                                href={index({ query: { limit: nextLimit } })}
                                only={['posts', 'nextLimit']}
                                preserveScroll
                                preserveState
                                replace
                            >
                                Load more
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                <Send className="size-5" />
            </div>
            <div className="space-y-1">
                <p className="font-medium">No posts yet</p>
                <p className="text-sm text-muted-foreground">
                    Pick a video, choose your accounts and publish everywhere at
                    once.
                </p>
            </div>
            <Button asChild size="sm">
                <Link href={create()}>Create your first post</Link>
            </Button>
        </div>
    );
}

PostsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Posts',
            href: index(),
        },
    ],
};
