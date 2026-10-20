import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, Info, Send, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TikTokDirectPostFields } from '@/components/tiktok-direct-post-fields';
import type { TikTokDirectSettings } from '@/components/tiktok-direct-post-fields';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { formatDuration, formatFileSize, platformIcons } from '@/lib/platforms';
import { create, store, update } from '@/routes/posts';
import { index as socialAccountsIndex } from '@/routes/social-accounts';
import { create as createVideo } from '@/routes/videos';
import type { Platform } from '@/types';

type ComposerVideo = {
    id: number;
    original_filename: string;
    duration_ms: number | null;
    size_bytes: number;
    created_at: string | null;
};

type ComposerAccount = {
    id: number;
    platform: Platform;
    display_name: string;
    avatar_url: string | null;
    needs_reconnection: boolean;
    can_direct_post: boolean;
};

type PlatformLimits = {
    value: Platform;
    label: string;
    max_duration_ms: number;
    max_file_size_bytes: number | null;
    max_caption_length: number;
};

type PublicationInput = {
    social_account_id: number;
    title: string;
    caption: string;
    privacy: string;
    settings: {
        made_for_kids?: boolean;
        share_to_feed?: boolean;
    } & TikTokDirectSettings;
};

type PostForm = {
    video_id: number | null;
    caption: string;
    publish_at: string | null;
    publications: PublicationInput[];
};

type Props = {
    videos: ComposerVideo[];
    accounts: ComposerAccount[];
    platforms: PlatformLimits[];
    privacyOptions: string[];
    selectedVideoId: number | null;
    /** Set when editing a scheduled post. */
    post: EditablePost | null;
};

type EditablePost = {
    id: number;
    video_id: number;
    caption: string;
    publish_at: string | null;
    publications: PublicationInput[];
};

const privacyLabels: Record<string, string> = {
    public: 'Public',
    unlisted: 'Unlisted',
    private: 'Private',
};

function newPublication(account: ComposerAccount): PublicationInput {
    return {
        social_account_id: account.id,
        title: '',
        caption: '',
        privacy: account.platform === 'youtube' ? 'public' : '',
        settings:
            account.platform === 'youtube'
                ? { made_for_kids: false }
                : account.platform === 'instagram'
                  ? { share_to_feed: true }
                  : account.platform === 'tiktok'
                    ? { mode: account.can_direct_post ? 'direct' : 'inbox' }
                    : {},
    };
}

/** The value a datetime-local input expects, in the browser's time zone. */
function toLocalInputValue(date: Date): string {
    const offsetMs = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
}

export default function CreatePost({
    videos,
    accounts,
    platforms,
    privacyOptions,
    selectedVideoId,
    post,
}: Props) {
    const initialVideoId =
        videos.find((video) => video.id === selectedVideoId)?.id ??
        videos[0]?.id ??
        null;

    const form = useForm<PostForm>({
        video_id: initialVideoId,
        caption: post?.caption ?? '',
        publish_at: null,
        publications: post?.publications ?? [],
    });

    const [timing, setTiming] = useState<'now' | 'schedule'>(
        post?.publish_at ? 'schedule' : 'now',
    );
    const [scheduledLocal, setScheduledLocal] = useState(() =>
        toLocalInputValue(
            post?.publish_at
                ? new Date(post.publish_at)
                : new Date(Date.now() + 60 * 60_000),
        ),
    );

    const errors = form.errors as Record<string, string | undefined>;
    const video = videos.find(
        (candidate) => candidate.id === form.data.video_id,
    );
    const limitsByPlatform = Object.fromEntries(
        platforms.map((platform) => [platform.value, platform]),
    ) as Record<Platform, PlatformLimits>;

    function tooLongFor(platform: Platform): boolean {
        return (
            video?.duration_ms != null &&
            video.duration_ms > limitsByPlatform[platform].max_duration_ms
        );
    }

    function tooLargeFor(platform: Platform): boolean {
        const maxFileSizeBytes = limitsByPlatform[platform].max_file_size_bytes;

        return (
            video != null &&
            maxFileSizeBytes !== null &&
            video.size_bytes > maxFileSizeBytes
        );
    }

    function toggleAccount(account: ComposerAccount, checked: boolean) {
        form.setData(
            'publications',
            checked
                ? [...form.data.publications, newPublication(account)]
                : form.data.publications.filter(
                      (publication) =>
                          publication.social_account_id !== account.id,
                  ),
        );
    }

    function updatePublication(
        accountId: number,
        changes: Partial<PublicationInput>,
    ) {
        form.setData(
            'publications',
            form.data.publications.map((publication) =>
                publication.social_account_id === accountId
                    ? { ...publication, ...changes }
                    : publication,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            publish_at:
                timing === 'schedule' && scheduledLocal
                    ? new Date(scheduledLocal).toISOString()
                    : null,
        }));

        if (post) {
            form.put(update.url(post.id));
        } else {
            form.post(store.url());
        }
    }

    if (videos.length === 0) {
        return (
            <ComposerShell isEditing={post !== null}>
                <EmptyNotice
                    title="Upload a video first"
                    description="Posts are built from a processed vertical video. Upload one and come back once it's ready."
                    href={createVideo().url}
                    action="Upload video"
                />
            </ComposerShell>
        );
    }

    if (accounts.length === 0) {
        return (
            <ComposerShell isEditing={post !== null}>
                <EmptyNotice
                    title="Connect an account first"
                    description="Connect at least one YouTube, Instagram, Facebook or TikTok account to publish to."
                    href={socialAccountsIndex().url}
                    action="Connect accounts"
                />
            </ComposerShell>
        );
    }

    const selectedIds = new Set(
        form.data.publications.map(
            (publication) => publication.social_account_id,
        ),
    );

    return (
        <ComposerShell isEditing={post !== null}>
            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Video</CardTitle>
                        <CardDescription>
                            Only videos that passed processing are listed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Select
                            value={form.data.video_id?.toString() ?? ''}
                            onValueChange={(value) =>
                                form.setData('video_id', Number(value))
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pick a video" />
                            </SelectTrigger>
                            <SelectContent>
                                {videos.map((candidate) => (
                                    <SelectItem
                                        key={candidate.id}
                                        value={candidate.id.toString()}
                                    >
                                        {candidate.original_filename} ·{' '}
                                        {formatDuration(candidate.duration_ms)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.video_id} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Caption</CardTitle>
                        <CardDescription>
                            Used everywhere unless you override it for an
                            account below. Hashtags and mentions go here too.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Textarea
                            value={form.data.caption}
                            onChange={(event) =>
                                form.setData('caption', event.target.value)
                            }
                            rows={4}
                            placeholder="Write a caption…"
                        />
                        <p className="text-right text-xs text-muted-foreground">
                            {form.data.caption.length} characters
                        </p>
                        <InputError message={errors.caption} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Publish to</CardTitle>
                        <CardDescription>
                            Each account is published by its own job, so one
                            failure never blocks the others.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {accounts.map((account) => {
                            const index = form.data.publications.findIndex(
                                (publication) =>
                                    publication.social_account_id ===
                                    account.id,
                            );

                            return (
                                <AccountOption
                                    key={account.id}
                                    account={account}
                                    limits={limitsByPlatform[account.platform]}
                                    isTooLong={tooLongFor(account.platform)}
                                    isTooLarge={tooLargeFor(account.platform)}
                                    videoDurationMs={video?.duration_ms ?? null}
                                    publication={
                                        index >= 0
                                            ? form.data.publications[index]
                                            : null
                                    }
                                    errorPrefix={`publications.${index}`}
                                    errors={errors}
                                    defaultCaption={form.data.caption}
                                    fallbackTitle={video?.original_filename.replace(
                                        /\.[^.]+$/,
                                        '',
                                    )}
                                    privacyOptions={privacyOptions}
                                    isChecked={selectedIds.has(account.id)}
                                    onToggle={(checked) =>
                                        toggleAccount(account, checked)
                                    }
                                    onChange={(changes) =>
                                        updatePublication(account.id, changes)
                                    }
                                />
                            );
                        })}
                        <InputError message={errors.publications} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>When</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={timing}
                            onValueChange={(value) =>
                                value && setTiming(value as 'now' | 'schedule')
                            }
                        >
                            <ToggleGroupItem value="now" className="px-4">
                                <Send />
                                Publish now
                            </ToggleGroupItem>
                            <ToggleGroupItem value="schedule" className="px-4">
                                <CalendarClock />
                                Schedule
                            </ToggleGroupItem>
                        </ToggleGroup>

                        {timing === 'schedule' && (
                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="publish_at">
                                    Publish at (your local time)
                                </Label>
                                <Input
                                    id="publish_at"
                                    type="datetime-local"
                                    value={scheduledLocal}
                                    min={toLocalInputValue(new Date())}
                                    onChange={(event) =>
                                        setScheduledLocal(event.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {
                                        Intl.DateTimeFormat().resolvedOptions()
                                            .timeZone
                                    }
                                </p>
                            </div>
                        )}
                        <InputError message={errors.publish_at} />
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    <p className="text-sm text-muted-foreground">
                        {form.data.publications.length === 0
                            ? 'Pick at least one account.'
                            : `${form.data.publications.length} account${form.data.publications.length === 1 ? '' : 's'} selected`}
                    </p>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            form.data.publications.length === 0 ||
                            form.data.video_id === null
                        }
                    >
                        {timing === 'schedule' ? <CalendarClock /> : <Send />}
                        {timing === 'schedule'
                            ? post
                                ? 'Save schedule'
                                : 'Schedule post'
                            : 'Publish'}
                    </Button>
                </div>
            </form>
        </ComposerShell>
    );
}

function ComposerShell({
    isEditing,
    children,
}: {
    isEditing: boolean;
    children: React.ReactNode;
}) {
    return (
        <>
            <Head title={isEditing ? 'Edit post' : 'New post'} />

            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <Heading
                    title={isEditing ? 'Edit scheduled post' : 'New post'}
                    description={
                        isEditing
                            ? 'Change anything until the post starts publishing.'
                            : 'Upload once, customize per platform, publish now or later.'
                    }
                />
                {children}
            </div>
        </>
    );
}

function EmptyNotice({
    title,
    description,
    href,
    action,
}: {
    title: string;
    description: string;
    href: string;
    action: string;
}) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                <Upload className="size-5" />
            </div>
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <Button asChild size="sm">
                <Link href={href}>{action}</Link>
            </Button>
        </div>
    );
}

function AccountOption({
    account,
    limits,
    isTooLong,
    isTooLarge,
    videoDurationMs,
    publication,
    errorPrefix,
    errors,
    defaultCaption,
    fallbackTitle,
    privacyOptions,
    isChecked,
    onToggle,
    onChange,
}: {
    account: ComposerAccount;
    limits: PlatformLimits;
    isTooLong: boolean;
    isTooLarge: boolean;
    videoDurationMs: number | null;
    publication: PublicationInput | null;
    errorPrefix: string;
    errors: Record<string, string | undefined>;
    defaultCaption: string;
    fallbackTitle: string | undefined;
    privacyOptions: string[];
    isChecked: boolean;
    onToggle: (checked: boolean) => void;
    onChange: (changes: Partial<PublicationInput>) => void;
}) {
    const Icon = platformIcons[account.platform];
    const isDisabled = account.needs_reconnection || isTooLong || isTooLarge;
    const checkboxId = `account-${account.id}`;
    const effectiveCaption = publication?.caption || defaultCaption;
    const isTikTokDirect =
        account.platform === 'tiktok' &&
        publication?.settings.mode === 'direct';
    const isCaptionTooLong =
        effectiveCaption.length > limits.max_caption_length;

    return (
        <div
            className={cn(
                'rounded-lg border',
                isChecked && 'border-primary/50 bg-muted/30',
            )}
        >
            <label
                htmlFor={checkboxId}
                className={cn(
                    'flex items-center gap-3 p-3',
                    isDisabled
                        ? 'cursor-not-allowed opacity-60'
                        : 'cursor-pointer',
                )}
            >
                <Checkbox
                    id={checkboxId}
                    checked={isChecked}
                    disabled={isDisabled}
                    onCheckedChange={(checked) => onToggle(checked === true)}
                />
                <Avatar className="size-8">
                    {account.avatar_url && (
                        <AvatarImage
                            src={account.avatar_url}
                            alt={account.display_name}
                        />
                    )}
                    <AvatarFallback>
                        <Icon className="size-4" />
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                        {account.display_name}
                    </p>
                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Icon className="size-3" />
                        {limits.label}
                    </p>
                </div>
                {account.needs_reconnection && (
                    <span className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="size-3.5" />
                        Reconnect needed
                    </span>
                )}
                {!account.needs_reconnection && isTooLong && (
                    <span className="text-xs text-muted-foreground">
                        Max {formatDuration(limits.max_duration_ms)}
                    </span>
                )}
                {!account.needs_reconnection &&
                    !isTooLong &&
                    isTooLarge &&
                    limits.max_file_size_bytes !== null && (
                        <span className="text-xs text-muted-foreground">
                            Max {formatFileSize(limits.max_file_size_bytes)}
                        </span>
                    )}
            </label>
            <div className="px-3">
                <InputError
                    message={errors[`${errorPrefix}.social_account_id`]}
                />
            </div>

            {publication && (
                <div className="grid gap-4 border-t p-3">
                    {account.platform === 'youtube' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${checkboxId}-title`}>
                                    Title
                                </Label>
                                <Input
                                    id={`${checkboxId}-title`}
                                    value={publication.title}
                                    maxLength={100}
                                    placeholder={fallbackTitle}
                                    onChange={(event) =>
                                        onChange({ title: event.target.value })
                                    }
                                />
                                <InputError
                                    message={errors[`${errorPrefix}.title`]}
                                />
                            </div>
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="grid gap-2">
                                    <Label>Visibility</Label>
                                    <Select
                                        value={publication.privacy}
                                        onValueChange={(privacy) =>
                                            onChange({ privacy })
                                        }
                                    >
                                        <SelectTrigger className="w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {privacyOptions.map((option) => (
                                                <SelectItem
                                                    key={option}
                                                    value={option}
                                                >
                                                    {privacyLabels[option] ??
                                                        option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <label className="flex h-9 items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={
                                            publication.settings
                                                .made_for_kids ?? false
                                        }
                                        onCheckedChange={(checked) =>
                                            onChange({
                                                settings: {
                                                    ...publication.settings,
                                                    made_for_kids:
                                                        checked === true,
                                                },
                                            })
                                        }
                                    />
                                    Made for kids
                                </label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Until the Google project passes its audit,
                                YouTube keeps API uploads private.
                            </p>
                        </>
                    )}

                    {account.platform === 'instagram' && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={
                                    publication.settings.share_to_feed ?? true
                                }
                                onCheckedChange={(checked) =>
                                    onChange({
                                        settings: {
                                            ...publication.settings,
                                            share_to_feed: checked === true,
                                        },
                                    })
                                }
                            />
                            Also show the Reel in the profile grid
                        </label>
                    )}

                    {account.platform === 'tiktok' &&
                        account.can_direct_post && (
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={publication.settings.mode ?? 'inbox'}
                                onValueChange={(mode) =>
                                    mode &&
                                    onChange({
                                        settings: {
                                            mode: mode as 'inbox' | 'direct',
                                        },
                                    })
                                }
                            >
                                <ToggleGroupItem value="inbox" className="px-3">
                                    Send to inbox
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="direct"
                                    className="px-3"
                                >
                                    Post directly
                                </ToggleGroupItem>
                            </ToggleGroup>
                        )}

                    {isTikTokDirect && (
                        <TikTokDirectPostFields
                            accountId={account.id}
                            settings={publication.settings}
                            videoDurationMs={videoDurationMs}
                            errors={errors}
                            errorPrefix={errorPrefix}
                            onChange={(settings) => onChange({ settings })}
                        />
                    )}

                    {account.platform === 'tiktok' && !isTikTokDirect ? (
                        <Alert>
                            <Info />
                            <AlertTitle>Sent to the TikTok inbox</AlertTitle>
                            <AlertDescription>
                                TikTok notifies the creator to finish the post
                                in the app, where caption, privacy and sounds
                                are set.
                                {!account.can_direct_post &&
                                    ' Posting directly needs Direct Post enabled on the TikTok app.'}
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor={`${checkboxId}-caption`}>
                                {account.platform === 'youtube'
                                    ? 'Description'
                                    : 'Caption'}{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional override)
                                </span>
                            </Label>
                            <Textarea
                                id={`${checkboxId}-caption`}
                                value={publication.caption}
                                rows={3}
                                placeholder={
                                    defaultCaption || 'Uses the default caption'
                                }
                                onChange={(event) =>
                                    onChange({ caption: event.target.value })
                                }
                            />
                            <p
                                className={cn(
                                    'text-right text-xs',
                                    isCaptionTooLong
                                        ? 'text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {effectiveCaption.length} /{' '}
                                {limits.max_caption_length}
                            </p>
                            <InputError
                                message={errors[`${errorPrefix}.caption`]}
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

CreatePost.layout = {
    breadcrumbs: [
        {
            title: 'New post',
            href: create(),
        },
    ],
};
