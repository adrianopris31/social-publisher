import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { creatorInfo as creatorInfoRoute } from '@/routes/tiktok';

export type TikTokDirectSettings = {
    mode?: 'inbox' | 'direct';
    privacy_level?: string;
    allow_comment?: boolean;
    allow_duet?: boolean;
    allow_stitch?: boolean;
    disclose_commercial?: boolean;
    brand_organic?: boolean;
    brand_content?: boolean;
};

type CreatorInfo = {
    nickname: string | null;
    username: string | null;
    avatar_url: string | null;
    privacy_level_options: string[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
};

const privacyLabels: Record<string, string> = {
    PUBLIC_TO_EVERYONE: 'Everyone',
    MUTUAL_FOLLOW_FRIENDS: 'Friends',
    FOLLOWER_OF_CREATOR: 'Followers',
    SELF_ONLY: 'Only me',
};

/**
 * The direct post form TikTok's audit requires: the creator's nickname, a privacy choice with
 * no default, interaction toggles the creator allows, commercial content disclosure and the
 * consent declaration.
 */
export function TikTokDirectPostFields({
    accountId,
    settings,
    videoDurationMs,
    errors,
    errorPrefix,
    onChange,
}: {
    accountId: number;
    settings: TikTokDirectSettings;
    videoDurationMs: number | null;
    errors: Record<string, string | undefined>;
    errorPrefix: string;
    onChange: (settings: TikTokDirectSettings) => void;
}) {
    const [creator, setCreator] = useState<CreatorInfo | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    useEffect(() => {
        let isCurrent = true;

        fetch(creatorInfoRoute.url(accountId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const body = await response.json();

                if (!isCurrent) {
                    return;
                }

                if (response.ok) {
                    setCreator(body as CreatorInfo);
                } else {
                    setLoadError(
                        (body as { message?: string }).message ??
                            'Could not load the TikTok account.',
                    );
                }
            })
            .catch(() => {
                if (isCurrent) {
                    setLoadError('Could not load the TikTok account.');
                }
            });

        return () => {
            isCurrent = false;
        };
    }, [accountId]);

    if (loadError) {
        return (
            <p className="flex items-center gap-1.5 text-sm text-destructive">
                <AlertTriangle className="size-4" />
                {loadError}
            </p>
        );
    }

    if (!creator) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Loading TikTok account…
            </p>
        );
    }

    const update = (changes: TikTokDirectSettings) =>
        onChange({ ...settings, ...changes });
    const isTooLong =
        creator.max_video_post_duration_sec !== null &&
        videoDurationMs !== null &&
        videoDurationMs > creator.max_video_post_duration_sec * 1000;

    const interactions = [
        {
            key: 'allow_comment' as const,
            label: 'Comments',
            disabled: creator.comment_disabled,
        },
        {
            key: 'allow_duet' as const,
            label: 'Duet',
            disabled: creator.duet_disabled,
        },
        {
            key: 'allow_stitch' as const,
            label: 'Stitch',
            disabled: creator.stitch_disabled,
        },
    ];

    return (
        <div className="grid gap-4">
            <div className="flex items-center gap-2 text-sm">
                <Avatar className="size-6">
                    {creator.avatar_url && (
                        <AvatarImage src={creator.avatar_url} alt="" />
                    )}
                    <AvatarFallback>T</AvatarFallback>
                </Avatar>
                Posting to{' '}
                <span className="font-medium">{creator.nickname}</span>
            </div>

            {isTooLong && (
                <p className="text-sm text-destructive">
                    This creator can post videos up to{' '}
                    {creator.max_video_post_duration_sec} seconds.
                </p>
            )}

            <div className="grid gap-2">
                <Label>Who can view this video</Label>
                <Select
                    value={settings.privacy_level ?? ''}
                    onValueChange={(privacy_level) => update({ privacy_level })}
                >
                    <SelectTrigger className="w-56">
                        <SelectValue placeholder="Choose privacy" />
                    </SelectTrigger>
                    <SelectContent>
                        {creator.privacy_level_options.map((option) => {
                            const isBlocked =
                                option === 'SELF_ONLY' &&
                                !!settings.brand_content;

                            return (
                                <SelectItem
                                    key={option}
                                    value={option}
                                    disabled={isBlocked}
                                >
                                    {privacyLabels[option] ?? option}
                                    {isBlocked &&
                                        ' (not allowed for branded content)'}
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>
                <InputError
                    message={errors[`${errorPrefix}.settings.privacy_level`]}
                />
            </div>

            <div className="grid gap-2">
                <Label>Allow users to</Label>
                <div className="flex flex-wrap gap-4">
                    {interactions.map(({ key, label, disabled }) => (
                        <label
                            key={key}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={!disabled && !!settings[key]}
                                disabled={disabled}
                                onCheckedChange={(checked) =>
                                    update({ [key]: checked === true })
                                }
                            />
                            {label}
                            {disabled && (
                                <span className="text-xs text-muted-foreground">
                                    (off in TikTok settings)
                                </span>
                            )}
                        </label>
                    ))}
                </div>
            </div>

            <div className="grid gap-3 rounded-md border p-3">
                <label className="flex items-center justify-between gap-4 text-sm">
                    <span>
                        <span className="font-medium">
                            Disclose video content
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            Turn on if this video promotes goods or services in
                            exchange for something of value.
                        </span>
                    </span>
                    <Switch
                        checked={!!settings.disclose_commercial}
                        onCheckedChange={(checked) =>
                            update({
                                disclose_commercial: checked,
                                brand_organic: checked
                                    ? settings.brand_organic
                                    : false,
                                brand_content: checked
                                    ? settings.brand_content
                                    : false,
                            })
                        }
                    />
                </label>

                {settings.disclose_commercial && (
                    <div className="grid gap-2 border-t pt-3">
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={!!settings.brand_organic}
                                onCheckedChange={(checked) =>
                                    update({ brand_organic: checked === true })
                                }
                            />
                            <span>
                                Your brand
                                <span className="block text-xs text-muted-foreground">
                                    You are promoting yourself or your own
                                    business. Labeled “Promotional content”.
                                </span>
                            </span>
                        </label>
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={!!settings.brand_content}
                                onCheckedChange={(checked) =>
                                    update({
                                        brand_content: checked === true,
                                        privacy_level:
                                            checked === true &&
                                            settings.privacy_level ===
                                                'SELF_ONLY'
                                                ? ''
                                                : settings.privacy_level,
                                    })
                                }
                            />
                            <span>
                                Branded content
                                <span className="block text-xs text-muted-foreground">
                                    You are promoting another brand or a third
                                    party. Labeled “Paid partnership”.
                                </span>
                            </span>
                        </label>
                        {!settings.brand_organic && !settings.brand_content && (
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                                Pick at least one option to continue.
                            </p>
                        )}
                        <InputError
                            message={
                                errors[
                                    `${errorPrefix}.settings.disclose_commercial`
                                ]
                            }
                        />
                    </div>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                By posting, you agree to TikTok&apos;s{' '}
                {settings.brand_content && (
                    <>
                        <a
                            className="underline"
                            href="https://www.tiktok.com/legal/page/global/bc-policy/en"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Branded Content Policy
                        </a>{' '}
                        and{' '}
                    </>
                )}
                <a
                    className="underline"
                    href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en"
                    target="_blank"
                    rel="noreferrer"
                >
                    Music Usage Confirmation
                </a>
                . It may take a few minutes for the video to appear on the
                profile.
            </p>
        </div>
    );
}
