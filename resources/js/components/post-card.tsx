import { Form, Link } from '@inertiajs/react';
import {
    CalendarClock,
    ExternalLink,
    Film,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import PostController from '@/actions/App/Http/Controllers/PostController';
import PublicationRetryController from '@/actions/App/Http/Controllers/PublicationRetryController';
import { PublicationStatusBadge } from '@/components/publication-status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    dateTimeFormatter,
    formatDuration,
    inFlightStatuses,
    platformIcons,
    platformNames,
} from '@/lib/platforms';
import type { Post, Publication } from '@/types';

export function PostCard({ post }: { post: Post }) {
    const isScheduled = post.dispatched_at === null && post.publish_at !== null;
    const isBusy = post.publications.some(
        (publication) =>
            publication.status === 'uploading' ||
            publication.status === 'processing',
    );

    return (
        <Card className="gap-3 py-4">
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-4">
                <div className="min-w-0 space-y-1">
                    <p className="flex items-center gap-2 truncate text-sm font-medium">
                        <Film className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">
                            {post.video.original_filename}
                        </span>
                        <span className="shrink-0 text-xs font-normal text-muted-foreground">
                            {formatDuration(post.video.duration_ms)}
                        </span>
                    </p>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <CalendarClock className="size-3.5" />
                        {isScheduled && post.publish_at
                            ? `Scheduled for ${dateTimeFormatter.format(new Date(post.publish_at))}`
                            : post.created_at
                              ? `Created ${dateTimeFormatter.format(new Date(post.created_at))}`
                              : null}
                    </p>
                    {post.caption && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {post.caption}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 items-center">
                    {isScheduled && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button asChild variant="ghost" size="icon">
                                    <Link href={PostController.edit(post.id)}>
                                        <Pencil />
                                        <span className="sr-only">
                                            Edit post
                                        </span>
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Edit or reschedule</TooltipContent>
                        </Tooltip>
                    )}
                    <DeletePostDialog
                        post={post}
                        isBusy={isBusy}
                        isScheduled={isScheduled}
                    />
                </div>
            </CardHeader>

            <CardContent className="px-4">
                <ul className="divide-y rounded-lg border">
                    {post.publications.map((publication) => (
                        <PublicationRow
                            key={publication.id}
                            publication={publication}
                            isScheduled={isScheduled}
                        />
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function PublicationRow({
    publication,
    isScheduled,
}: {
    publication: Publication;
    isScheduled: boolean;
}) {
    const Icon = platformIcons[publication.platform];
    const accountName =
        publication.account?.display_name ?? 'Disconnected account';

    return (
        <li className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 p-3">
            <div className="flex min-w-0 items-center gap-3">
                <div className="relative">
                    <Avatar className="size-8">
                        {publication.account?.avatar_url && (
                            <AvatarImage
                                src={publication.account.avatar_url}
                                alt={accountName}
                            />
                        )}
                        <AvatarFallback>
                            <Icon className="size-4" />
                        </AvatarFallback>
                    </Avatar>
                    <span className="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full border bg-background">
                        <Icon className="size-2.5" />
                    </span>
                </div>
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {accountName}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {platformNames[publication.platform]}
                        {publication.title && ` · ${publication.title}`}
                    </p>
                    {publication.status === 'failed' &&
                        publication.error_message && (
                            <p className="mt-1 text-xs text-destructive">
                                {publication.error_message}
                            </p>
                        )}
                </div>
            </div>

            <div className="flex items-center gap-2">
                <PublicationStatusBadge
                    status={publication.status}
                    platform={publication.platform}
                    isScheduled={isScheduled}
                />

                {publication.status === 'published' &&
                    publication.external_url && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button asChild variant="ghost" size="icon">
                                    <a
                                        href={publication.external_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink />
                                        <span className="sr-only">
                                            Open on{' '}
                                            {
                                                platformNames[
                                                    publication.platform
                                                ]
                                            }
                                        </span>
                                    </a>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                Open on {platformNames[publication.platform]}
                            </TooltipContent>
                        </Tooltip>
                    )}

                {publication.status === 'failed' && publication.account && (
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={PublicationRetryController(publication.id)}
                            as="button"
                            preserveScroll
                        >
                            <RotateCcw />
                            Retry
                        </Link>
                    </Button>
                )}
            </div>
        </li>
    );
}

function DeletePostDialog({
    post,
    isBusy,
    isScheduled,
}: {
    post: Post;
    isBusy: boolean;
    isScheduled: boolean;
}) {
    return (
        <Dialog>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            disabled={isBusy}
                            className="shrink-0"
                        >
                            <Trash2 />
                            <span className="sr-only">Delete post</span>
                        </Button>
                    </DialogTrigger>
                </TooltipTrigger>
                <TooltipContent>
                    {isScheduled ? 'Cancel and delete' : 'Delete post'}
                </TooltipContent>
            </Tooltip>
            <DialogContent>
                <DialogTitle>
                    {isScheduled
                        ? 'Cancel this scheduled post?'
                        : 'Delete this post?'}
                </DialogTitle>
                <DialogDescription>
                    {isScheduled
                        ? 'Nothing will be published. The video stays in your library.'
                        : 'The post is removed from this app only. Videos already published stay on the platforms.'}
                </DialogDescription>

                <Form
                    {...PostController.destroy.form(post.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Keep it</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** True while any publication of the posts may still change status. */
export function hasInFlightPublications(posts: Post[]): boolean {
    return posts.some(
        (post) =>
            post.dispatched_at !== null &&
            post.publications.some((publication) =>
                inFlightStatuses.includes(publication.status),
            ),
    );
}
