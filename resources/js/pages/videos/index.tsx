import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Film,
    Loader2,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import VideoController from '@/actions/App/Http/Controllers/VideoController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { usePollWhile } from '@/hooks/use-poll-while';
import { dateTimeFormatter, formatDuration } from '@/lib/platforms';
import { create as createPost } from '@/routes/posts';
import { create as createVideo, index } from '@/routes/videos';
import type { Paginator, VideoStatus } from '@/types';

type LibraryVideo = {
    id: number;
    original_filename: string;
    size_bytes: number;
    duration_ms: number | null;
    width: number | null;
    height: number | null;
    status: VideoStatus;
    rejection_reason: string | null;
    posts_count: number;
    preview_url: string | null;
    created_at: string | null;
};

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 * 1024
        ? `${(bytes / 1024 / 1024 / 1024).toFixed(1)} GB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function VideosIndex({
    videos,
}: {
    videos: Paginator<LibraryVideo>;
}) {
    usePollWhile(
        videos.data.some((video) => video.status === 'pending'),
        ['videos'],
    );

    return (
        <>
            <Head title="Videos" />

            <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Videos"
                        description="Your uploaded videos. Each one is checked for format, ratio and length before you can post it."
                    />
                    <Button asChild>
                        <Link href={createVideo()}>
                            <Upload />
                            Upload
                        </Link>
                    </Button>
                </div>

                {videos.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Film className="size-5" />
                        </div>
                        <p className="font-medium">No videos yet</p>
                        <Button asChild size="sm">
                            <Link href={createVideo()}>
                                Upload your first video
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {videos.data.map((video) => (
                            <VideoCard key={video.id} video={video} />
                        ))}
                    </div>
                )}

                {videos.last_page > 1 && (
                    <nav className="mt-6 flex items-center justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!videos.prev_page_url}
                            asChild={!!videos.prev_page_url}
                        >
                            {videos.prev_page_url ? (
                                <Link href={videos.prev_page_url}>
                                    <ChevronLeft />
                                    Newer
                                </Link>
                            ) : (
                                <span>
                                    <ChevronLeft />
                                    Newer
                                </span>
                            )}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {videos.current_page} of {videos.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!videos.next_page_url}
                            asChild={!!videos.next_page_url}
                        >
                            {videos.next_page_url ? (
                                <Link href={videos.next_page_url}>
                                    Older
                                    <ChevronRight />
                                </Link>
                            ) : (
                                <span>
                                    Older
                                    <ChevronRight />
                                </span>
                            )}
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

function VideoCard({ video }: { video: LibraryVideo }) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="relative aspect-[9/16] bg-muted">
                {video.preview_url ? (
                    <video
                        src={video.preview_url}
                        className="size-full object-cover"
                        preload="metadata"
                        muted
                        playsInline
                        controls
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-sm text-muted-foreground">
                        {video.status === 'pending' ? (
                            <>
                                <Loader2 className="size-6 animate-spin" />
                                Checking video…
                            </>
                        ) : (
                            <>
                                <AlertCircle className="size-6 text-destructive" />
                                {video.rejection_reason ?? 'Rejected'}
                            </>
                        )}
                    </div>
                )}
                <div className="absolute top-2 left-2">
                    <StatusBadge status={video.status} />
                </div>
            </div>
            <CardContent className="space-y-2 p-3">
                <p
                    className="truncate text-sm font-medium"
                    title={video.original_filename}
                >
                    {video.original_filename}
                </p>
                <p className="text-xs text-muted-foreground">
                    {formatDuration(video.duration_ms)} ·{' '}
                    {formatSize(video.size_bytes)}
                    {video.created_at &&
                        ` · ${dateTimeFormatter.format(new Date(video.created_at))}`}
                </p>
                <div className="flex items-center gap-2">
                    {video.status === 'ready' && (
                        <Button asChild size="sm" className="flex-1">
                            <Link
                                href={createPost({
                                    query: { video: video.id },
                                })}
                            >
                                <Send />
                                Post
                            </Link>
                        </Button>
                    )}
                    <DeleteVideoDialog video={video} />
                </div>
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: { status: VideoStatus }) {
    if (status === 'ready') {
        return (
            <Badge className="border-transparent bg-green-600 text-white">
                Ready
            </Badge>
        );
    }

    if (status === 'pending') {
        return <Badge variant="secondary">Processing</Badge>;
    }

    return <Badge variant="destructive">Rejected</Badge>;
}

function DeleteVideoDialog({ video }: { video: LibraryVideo }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="ml-auto">
                    <Trash2 />
                    <span className="sr-only">Delete video</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {video.original_filename}?</DialogTitle>
                <DialogDescription>
                    The file is removed from storage
                    {video.posts_count > 0 &&
                        `, together with ${video.posts_count} post${video.posts_count === 1 ? '' : 's'} that use it (scheduled ones won't be published)`}
                    . Videos already published stay on the platforms.
                </DialogDescription>
                <Form
                    {...VideoController.destroy.form(video.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
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

VideosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Videos',
            href: index(),
        },
    ],
};
