import { AlertTriangle, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { AccountAvatar } from '@/components/account-avatar';
import {
    captionFoldLength,
    captionWarnings,
    countHashtags,
    splitHashtags,
} from '@/lib/captions';
import { platformNames } from '@/lib/platforms';
import { cn } from '@/lib/utils';
import type { Platform } from '@/types';

export type CaptionPreviewItem = {
    accountId: number;
    platform: Platform;
    accountName: string;
    avatarUrl: string | null;
    caption: string;
    maxCaptionLength: number;
    maxHashtags: number | null;
};

/**
 * How each selected account will show its caption: the part visible before "… more",
 * highlighted hashtags, and the limits that would block or weaken the post.
 */
export function CaptionPreview({ items }: { items: CaptionPreviewItem[] }) {
    if (items.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Pick an account to preview its caption.
            </p>
        );
    }

    return (
        <ul className="grid grid-cols-1 gap-3">
            {items.map((item) => (
                <CaptionPreviewRow key={item.accountId} item={item} />
            ))}
        </ul>
    );
}

function CaptionPreviewRow({ item }: { item: CaptionPreviewItem }) {
    const [isExpanded, setIsExpanded] = useState(false);
    const caption = item.caption.trim();
    const foldLength = captionFoldLength[item.platform];
    const isFolded =
        foldLength !== undefined && caption.length > foldLength && !isExpanded;
    const visibleCaption = isFolded
        ? caption.slice(0, foldLength).trimEnd()
        : caption;
    const hashtags = countHashtags(caption);
    const isTooLong = caption.length > item.maxCaptionLength;
    const hasTooManyHashtags =
        item.maxHashtags !== null && hashtags > item.maxHashtags;
    const warnings = captionWarnings(item.platform, caption);

    return (
        <li className="min-w-0 rounded-lg border p-3">
            <div className="mb-2 flex items-center gap-2">
                <AccountAvatar
                    platform={item.platform}
                    name={item.accountName}
                    avatarUrl={item.avatarUrl}
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                        {item.accountName}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {platformNames[item.platform]}
                    </p>
                </div>
            </div>

            {caption === '' ? (
                <p className="text-sm text-muted-foreground italic">
                    No caption
                </p>
            ) : (
                <p className="text-sm wrap-anywhere whitespace-pre-line">
                    {splitHashtags(visibleCaption).map((part, index) =>
                        part.isHashtag ? (
                            <span
                                key={index}
                                className="text-blue-600 dark:text-blue-400"
                            >
                                {part.text}
                            </span>
                        ) : (
                            part.text
                        ),
                    )}
                    {isFolded && (
                        <button
                            type="button"
                            onClick={() => setIsExpanded(true)}
                            className="text-muted-foreground hover:underline"
                        >
                            … more
                        </button>
                    )}
                </p>
            )}

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span
                    className={cn(
                        isTooLong
                            ? 'text-destructive'
                            : 'text-muted-foreground',
                    )}
                >
                    {caption.length} / {item.maxCaptionLength} characters
                </span>
                <span
                    className={cn(
                        hasTooManyHashtags
                            ? 'text-destructive'
                            : 'text-muted-foreground',
                    )}
                >
                    {hashtags}
                    {item.maxHashtags !== null && ` / ${item.maxHashtags}`}{' '}
                    {hashtags === 1 ? 'hashtag' : 'hashtags'}
                </span>
                {foldLength !== undefined && caption.length > foldLength && (
                    <button
                        type="button"
                        onClick={() => setIsExpanded(!isExpanded)}
                        className="flex items-center gap-0.5 text-muted-foreground hover:text-foreground"
                    >
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform',
                                isExpanded && 'rotate-180',
                            )}
                        />
                        {isExpanded
                            ? `Show what fits before "more" (about ${foldLength} characters)`
                            : 'Show all'}
                    </button>
                )}
            </div>

            {(isTooLong || hasTooManyHashtags || warnings.length > 0) && (
                <ul className="mt-2 grid gap-1 text-xs text-amber-700 dark:text-amber-400">
                    {isTooLong && (
                        <Warning>
                            {platformNames[item.platform]} allows at most{' '}
                            {item.maxCaptionLength} characters.
                        </Warning>
                    )}
                    {hasTooManyHashtags && (
                        <Warning>
                            {platformNames[item.platform]} allows at most{' '}
                            {item.maxHashtags} hashtags.
                        </Warning>
                    )}
                    {warnings.map((warning) => (
                        <Warning key={warning}>{warning}</Warning>
                    ))}
                </ul>
            )}
        </li>
    );
}

function Warning({ children }: { children: React.ReactNode }) {
    return (
        <li className="flex items-start gap-1">
            <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
            <span>{children}</span>
        </li>
    );
}
