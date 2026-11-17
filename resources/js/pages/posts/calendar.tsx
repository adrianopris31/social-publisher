import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import type { DragEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import PostScheduleController from '@/actions/App/Http/Controllers/PostScheduleController';
import Heading from '@/components/heading';
import { PlatformIcon } from '@/components/platform-icon';
import { hasInFlightPublications, PostCard } from '@/components/post-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePollWhile } from '@/hooks/use-poll-while';
import type { CalendarDay, PostCalendarStatus } from '@/lib/calendar';
import {
    atLocalTime,
    formatMonth,
    getCalendarDays,
    groupPostsByDay,
    isScheduledPost,
    moveToDay,
    postCalendarDate,
    postCalendarStatus,
    shiftMonth,
    toDateKey,
    toTimeValue,
    weekdayLabels,
} from '@/lib/calendar';
import { cn } from '@/lib/utils';
import { calendar, create } from '@/routes/posts';
import type { Post } from '@/types';

type Props = {
    /** The shown month as "YYYY-MM", exactly as the controller sends it. */
    month: string;
    /** A resource collection wraps the posts in `data`. */
    posts: { data: Post[] };
};

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    timeStyle: 'short',
});

const agendaDayFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const statusStyles: Record<PostCalendarStatus, { label: string; dot: string }> =
    {
        scheduled: { label: 'Scheduled', dot: 'bg-sky-500' },
        publishing: { label: 'Publishing', dot: 'bg-amber-500 animate-pulse' },
        published: {
            label: 'Published',
            dot: 'bg-green-600 dark:bg-green-400',
        },
        failed: { label: 'Failed', dot: 'bg-destructive' },
    };

export default function PostsCalendar({ month, posts }: Props) {
    const [selectedPostId, setSelectedPostId] = useState<number | null>(null);
    const [draggedPostId, setDraggedPostId] = useState<number | null>(null);
    const [dropTargetKey, setDropTargetKey] = useState<string | null>(null);

    usePollWhile(hasInFlightPublications(posts.data), ['posts']);

    const days = getCalendarDays(month);
    const postsByDay = groupPostsByDay(posts.data);
    const todayKey = toDateKey(new Date());
    const postCount = posts.data.length;

    // Derived from the props on every render, so polling keeps the open dialog up to date.
    const selectedPost =
        posts.data.find((post) => post.id === selectedPostId) ?? null;
    const draggedPost =
        posts.data.find((post) => post.id === draggedPostId) ?? null;

    function canDropOn(day: CalendarDay): boolean {
        return draggedPost !== null && day.key >= todayKey;
    }

    function stopDragging(): void {
        setDraggedPostId(null);
        setDropTargetKey(null);
    }

    function moveToOtherDay(post: Post, dayKey: string): void {
        if (!post.publish_at || toDateKey(postCalendarDate(post)) === dayKey) {
            return;
        }

        reschedule(post, moveToDay(post.publish_at, dayKey));
    }

    function reschedule(post: Post, publishAt: Date): void {
        if (publishAt <= new Date()) {
            toast.error('That time has already passed.');

            return;
        }

        router.patch(
            PostScheduleController.url(post.id),
            { publish_at: publishAt.toISOString() },
            {
                preserveScroll: true,
                // Show the post on its new day right away; Inertia rolls it back if the server refuses.
                optimistic: (props) => {
                    const current = props.posts as Props['posts'];

                    return {
                        posts: {
                            ...current,
                            data: current.data.map((item) =>
                                item.id === post.id
                                    ? {
                                          ...item,
                                          publish_at: publishAt.toISOString(),
                                      }
                                    : item,
                            ),
                        },
                    };
                },
                onError: (errors) => {
                    toast.error(
                        errors.publish_at ?? 'The post could not be moved.',
                    );
                },
            },
        );
    }

    return (
        <>
            <Head title="Calendar" />

            <div className="mx-auto w-full max-w-6xl p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Calendar"
                        description={`${formatMonth(month)} · ${postCount} ${postCount === 1 ? 'post' : 'posts'}. Drag a scheduled post to another day, or click it to change the time.`}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            New post
                        </Link>
                    </Button>
                </div>

                <MonthNavigation month={month} />

                <div className="hidden overflow-hidden rounded-xl border md:block">
                    <div className="grid grid-cols-7 border-b bg-muted/50">
                        {weekdayLabels.map((weekday) => (
                            <div
                                key={weekday}
                                className="px-2 py-2 text-xs font-medium text-muted-foreground"
                            >
                                {weekday}
                            </div>
                        ))}
                    </div>

                    {/* The 1px gaps show the border colour behind the cells as grid lines. */}
                    <div className="grid grid-cols-7 gap-px bg-border">
                        {days.map((day) => (
                            <CalendarDayCell
                                key={day.key}
                                day={day}
                                posts={postsByDay[day.key] ?? []}
                                isDropTarget={dropTargetKey === day.key}
                                onPostOpen={setSelectedPostId}
                                onPostDragStart={setDraggedPostId}
                                onPostDragEnd={stopDragging}
                                onDragOver={(event) => {
                                    if (!canDropOn(day)) {
                                        setDropTargetKey(null);

                                        return;
                                    }

                                    // Allowing the drop is opt-in: the browser refuses it unless we prevent the default.
                                    event.preventDefault();
                                    setDropTargetKey(day.key);
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();

                                    if (draggedPost && canDropOn(day)) {
                                        moveToOtherDay(draggedPost, day.key);
                                    }

                                    stopDragging();
                                }}
                            />
                        ))}
                    </div>
                </div>

                <MonthAgenda
                    days={days.filter((day) => day.isCurrentMonth)}
                    postsByDay={postsByDay}
                    onPostOpen={setSelectedPostId}
                />
            </div>

            <Dialog
                open={selectedPost !== null}
                onOpenChange={(open) => !open && setSelectedPostId(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogTitle className="sr-only">Post details</DialogTitle>
                    <DialogDescription className="sr-only">
                        Where the post goes and how far each platform got.
                    </DialogDescription>
                    {selectedPost && (
                        <div className="space-y-4 pt-6">
                            {isScheduledPost(selectedPost) && (
                                <QuickReschedule
                                    // A new key resets the inputs once the saved time comes back.
                                    key={`${selectedPost.id}-${selectedPost.publish_at}`}
                                    post={selectedPost}
                                    onSave={(publishAt) =>
                                        reschedule(selectedPost, publishAt)
                                    }
                                />
                            )}
                            <PostCard post={selectedPost} />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Change the publish date and time right in the dialog, without opening the edit page.
 */
function QuickReschedule({
    post,
    onSave,
}: {
    post: Post;
    onSave: (publishAt: Date) => void;
}) {
    const current = postCalendarDate(post);
    const [dayKey, setDayKey] = useState(toDateKey(current));
    const [time, setTime] = useState(toTimeValue(current));
    const publishAt = atLocalTime(dayKey, time);
    const isUnchanged = publishAt?.getTime() === current.getTime();

    return (
        <form
            className="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/40 p-3"
            onSubmit={(event) => {
                event.preventDefault();

                if (publishAt && !isUnchanged) {
                    onSave(publishAt);
                }
            }}
        >
            <div className="grid gap-1.5">
                <Label htmlFor="quick-reschedule-date">Date</Label>
                <Input
                    id="quick-reschedule-date"
                    type="date"
                    min={toDateKey(new Date())}
                    value={dayKey}
                    onChange={(event) => setDayKey(event.target.value)}
                    className="w-auto"
                />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="quick-reschedule-time">Time</Label>
                <Input
                    id="quick-reschedule-time"
                    type="time"
                    value={time}
                    onChange={(event) => setTime(event.target.value)}
                    className="w-auto"
                />
            </div>
            <Button type="submit" disabled={!publishAt || isUnchanged}>
                Reschedule
            </Button>
        </form>
    );
}

function MonthNavigation({ month }: { month: string }) {
    return (
        <div className="mb-4 flex items-center gap-2">
            <Button asChild variant="outline" size="icon">
                <Link
                    href={calendar({ query: { month: shiftMonth(month, -1) } })}
                    preserveScroll
                >
                    <ChevronLeft />
                    <span className="sr-only">Previous month</span>
                </Link>
            </Button>
            <Button asChild variant="outline" size="icon">
                <Link
                    href={calendar({ query: { month: shiftMonth(month, 1) } })}
                    preserveScroll
                >
                    <ChevronRight />
                    <span className="sr-only">Next month</span>
                </Link>
            </Button>
            <Button asChild variant="outline">
                <Link href={calendar()} preserveScroll>
                    Today
                </Link>
            </Button>
            <h3 className="ml-2 text-lg font-semibold">{formatMonth(month)}</h3>
        </div>
    );
}

function CalendarDayCell({
    day,
    posts,
    isDropTarget,
    onPostOpen,
    onPostDragStart,
    onPostDragEnd,
    onDragOver,
    onDrop,
}: {
    day: CalendarDay;
    posts: Post[];
    isDropTarget: boolean;
    onPostOpen: (postId: number) => void;
    onPostDragStart: (postId: number) => void;
    onPostDragEnd: () => void;
    onDragOver: (event: DragEvent<HTMLDivElement>) => void;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
}) {
    return (
        <div
            onDragOver={onDragOver}
            onDrop={onDrop}
            className={cn(
                'min-h-28 space-y-1 bg-background p-1.5 transition-colors',
                !day.isCurrentMonth && 'text-muted-foreground/60',
                isDropTarget && 'bg-primary/10 ring-2 ring-primary ring-inset',
            )}
        >
            <time
                dateTime={day.key}
                className={cn(
                    'flex size-6 items-center justify-center rounded-full text-xs',
                    day.isToday &&
                        'bg-primary font-semibold text-primary-foreground',
                )}
            >
                {day.date.getDate()}
            </time>

            {posts.map((post) => (
                <CalendarPostChip
                    key={post.id}
                    post={post}
                    onOpen={onPostOpen}
                    onDragStart={onPostDragStart}
                    onDragEnd={onPostDragEnd}
                />
            ))}
        </div>
    );
}

function CalendarPostChip({
    post,
    onOpen,
    onDragStart,
    onDragEnd,
}: {
    post: Post;
    onOpen: (postId: number) => void;
    onDragStart?: (postId: number) => void;
    onDragEnd?: () => void;
}) {
    const status = statusStyles[postCalendarStatus(post)];
    const canMove = isScheduledPost(post) && onDragStart !== undefined;
    const platforms = [
        ...new Set(
            post.publications.map((publication) => publication.platform),
        ),
    ];

    return (
        // A div, not a <button>: Firefox does not let buttons be dragged.
        <div
            role="button"
            tabIndex={0}
            draggable={canMove}
            onClick={() => onOpen(post.id)}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onOpen(post.id);
                }
            }}
            onDragStart={(event) => {
                event.dataTransfer.effectAllowed = 'move';
                // Some browsers only start a drag when it carries data.
                event.dataTransfer.setData('text/plain', String(post.id));
                onDragStart?.(post.id);
            }}
            onDragEnd={onDragEnd}
            title={`${status.label}: ${post.video.original_filename}`}
            className={cn(
                'flex w-full cursor-pointer items-center gap-1.5 rounded-md border bg-card px-1.5 py-1 text-left text-xs text-foreground shadow-xs hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                canMove && 'cursor-grab active:cursor-grabbing',
            )}
        >
            <span
                aria-hidden="true"
                className={cn('size-2 shrink-0 rounded-full', status.dot)}
            />
            <span className="sr-only">{status.label}</span>
            <span className="shrink-0 text-muted-foreground tabular-nums">
                {timeFormatter.format(postCalendarDate(post))}
            </span>
            <span className="min-w-0 flex-1 truncate">
                {post.video.original_filename}
            </span>
            <span className="flex shrink-0 -space-x-1">
                {platforms.map((platform) => (
                    <PlatformIcon
                        key={platform}
                        platform={platform}
                        className="size-3.5 ring-1 ring-background"
                    />
                ))}
            </span>
        </div>
    );
}

/**
 * Phones get a list of the month's days that have posts: seven columns are too narrow there.
 */
function MonthAgenda({
    days,
    postsByDay,
    onPostOpen,
}: {
    days: CalendarDay[];
    postsByDay: Record<string, Post[]>;
    onPostOpen: (postId: number) => void;
}) {
    const daysWithPosts = days.filter((day) => postsByDay[day.key]?.length);

    if (daysWithPosts.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground md:hidden">
                No posts this month.
            </p>
        );
    }

    return (
        <ol className="space-y-4 md:hidden">
            {daysWithPosts.map((day) => (
                <li key={day.key} className="space-y-1.5">
                    <h4
                        className={cn(
                            'text-sm font-medium',
                            day.isToday && 'text-primary',
                        )}
                    >
                        {agendaDayFormatter.format(day.date)}
                        {day.isToday && ' · Today'}
                    </h4>
                    {postsByDay[day.key].map((post) => (
                        <CalendarPostChip
                            key={post.id}
                            post={post}
                            onOpen={onPostOpen}
                        />
                    ))}
                </li>
            ))}
        </ol>
    );
}
