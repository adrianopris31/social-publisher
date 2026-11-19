import type { Platform } from '@/types';

/** "#" followed by letters, digits or "_", matching `Platform::countHashtags()` on the server. */
const hashtagPattern = /#[\p{L}\p{N}_]+/gu;

export function countHashtags(caption: string): number {
    return caption.match(hashtagPattern)?.length ?? 0;
}

/**
 * Adds text to the end of a caption: hashtag sets on a new line, caption templates as a new paragraph.
 */
export function appendToCaption(
    caption: string,
    text: string,
    separator: 'line' | 'paragraph',
): string {
    const trimmed = caption.trimEnd();

    if (trimmed === '') {
        return text;
    }

    return `${trimmed}${separator === 'line' ? '\n' : '\n\n'}${text}`;
}

/** Splits a caption into plain text and hashtags, so previews can highlight the tags. */
export function splitHashtags(
    caption: string,
): { text: string; isHashtag: boolean }[] {
    const parts: { text: string; isHashtag: boolean }[] = [];
    let lastIndex = 0;

    for (const match of caption.matchAll(hashtagPattern)) {
        const index = match.index ?? 0;

        if (index > lastIndex) {
            parts.push({
                text: caption.slice(lastIndex, index),
                isHashtag: false,
            });
        }

        parts.push({ text: match[0], isHashtag: true });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < caption.length) {
        parts.push({ text: caption.slice(lastIndex), isHashtag: false });
    }

    return parts;
}

/**
 * Roughly how many characters the app shows before cutting the caption with "… more".
 * Approximate: it depends on the screen and line breaks, and is not documented by the platforms.
 */
export const captionFoldLength: Partial<Record<Platform, number>> = {
    instagram: 125,
    facebook: 125,
    tiktok: 100,
    linkedin: 210,
};

/** Platform rules that do not block publishing but are worth knowing while writing. */
export function captionWarnings(platform: Platform, caption: string): string[] {
    const hashtags = countHashtags(caption);
    const warnings: string[] = [];

    if (platform === 'youtube' && hashtags > 60) {
        warnings.push(
            'YouTube ignores every hashtag on a video with more than 60.',
        );
    }

    if (platform === 'youtube' && /[<>]/.test(caption)) {
        warnings.push(
            'YouTube rejects descriptions that contain < or >, so this upload would fail.',
        );
    }

    if (platform === 'threads' && hashtags > 1) {
        warnings.push(
            'Threads turns only the first hashtag into a topic tag; the rest stay plain text.',
        );
    }

    return warnings;
}
