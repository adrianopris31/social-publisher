import {
    AtSign,
    Facebook,
    Instagram,
    Linkedin,
    Music2,
    Youtube,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Platform, PublicationStatus } from '@/types';

export const platformNames: Record<Platform, string> = {
    youtube: 'YouTube',
    instagram: 'Instagram',
    facebook: 'Facebook',
    tiktok: 'TikTok',
    threads: 'Threads',
    linkedin: 'LinkedIn',
};

export const platformIcons: Record<Platform, LucideIcon> = {
    youtube: Youtube,
    instagram: Instagram,
    facebook: Facebook,
    tiktok: Music2,
    threads: AtSign,
    linkedin: Linkedin,
};

/** Statuses that change on their own, so pages showing them keep polling. */
export const inFlightStatuses: PublicationStatus[] = [
    'pending',
    'uploading',
    'processing',
];

export function formatDuration(durationMs: number | null): string {
    if (durationMs === null) {
        return '–';
    }

    const totalSeconds = Math.round(durationMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

export const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});
