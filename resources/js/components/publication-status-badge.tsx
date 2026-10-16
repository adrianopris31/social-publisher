import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Loader2,
    Upload,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Platform, PublicationStatus } from '@/types';

const labels: Record<PublicationStatus, string> = {
    pending: 'Queued',
    uploading: 'Uploading',
    processing: 'Processing',
    published: 'Published',
    failed: 'Failed',
};

export function PublicationStatusBadge({
    status,
    platform,
    isScheduled = false,
}: {
    status: PublicationStatus;
    platform: Platform;
    /** A pending publication of a post that waits for its publish time. */
    isScheduled?: boolean;
}) {
    if (status === 'pending' && isScheduled) {
        return (
            <Badge variant="outline">
                <Clock />
                Scheduled
            </Badge>
        );
    }

    if (status === 'published') {
        return (
            <Badge className="border-transparent bg-green-600/10 text-green-700 dark:bg-green-400/10 dark:text-green-400">
                <CheckCircle2 />
                {platform === 'tiktok' ? 'In TikTok inbox' : labels.published}
            </Badge>
        );
    }

    if (status === 'failed') {
        return (
            <Badge variant="destructive">
                <AlertCircle />
                {labels.failed}
            </Badge>
        );
    }

    const Icon = status === 'uploading' ? Upload : Loader2;

    return (
        <Badge variant="secondary">
            <Icon className={cn(status !== 'uploading' && 'animate-spin')} />
            {labels[status]}
        </Badge>
    );
}
