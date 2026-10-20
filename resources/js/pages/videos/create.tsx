import { Head, router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatDurationLimit,
    formatFileSize,
    platformIcons,
} from '@/lib/platforms';
import { uploadToPresignedUrl } from '@/lib/upload-to-presigned-url';
import type { PresignedUpload } from '@/lib/upload-to-presigned-url';
import {
    create,
    index as videosIndex,
    store as storeVideo,
} from '@/routes/videos';
import { store as storeUpload } from '@/routes/videos/uploads';
import type { Platform } from '@/types';

type PlatformLimits = {
    value: Platform;
    label: string;
    max_duration_ms: number;
    max_file_size_bytes: number | null;
};

type UploadLimits = {
    max_file_size_bytes: number;
    min_duration_ms: number;
    max_duration_ms: number;
    platforms: PlatformLimits[];
};

type UploadTicketForm = {
    filename: string;
    content_type: string;
    size: number;
};

type ConfirmForm = {
    key: string;
    filename: string;
};

type ConfirmResponse = {
    video_id: number;
    message: string;
};

type UploadStatus =
    | 'idle'
    | 'requesting'
    | 'uploading'
    | 'confirming'
    | 'done'
    | 'failed';

const statusLabels: Record<UploadStatus, string> = {
    idle: 'Upload',
    requesting: 'Preparing…',
    uploading: 'Uploading…',
    confirming: 'Finishing…',
    done: 'Upload',
    failed: 'Try again',
};

export default function CreateVideo({ limits }: { limits: UploadLimits }) {
    const [file, setFile] = useState<File | null>(null);
    const [status, setStatus] = useState<UploadStatus>('idle');
    const [progress, setProgress] = useState(0);
    const [uploadError, setUploadError] = useState<string | null>(null);

    // Step 1: ask Laravel for a presigned URL (POST /videos/uploads).
    const ticket = useHttp<UploadTicketForm, PresignedUpload>({
        filename: '',
        content_type: '',
        size: 0,
    });

    // Step 3: tell Laravel the file is in R2 (POST /videos).
    const confirmation = useHttp<ConfirmForm, ConfirmResponse>({
        key: '',
        filename: '',
    });

    const isBusy = ['requesting', 'uploading', 'confirming'].includes(status);

    function selectFile(event: ChangeEvent<HTMLInputElement>) {
        const selected = event.target.files?.[0] ?? null;

        setFile(selected);
        setStatus('idle');
        setProgress(0);
        setUploadError(null);
        ticket.clearErrors();
        confirmation.clearErrors();

        if (selected && selected.size > limits.max_file_size_bytes) {
            setUploadError(
                `This file is ${formatFileSize(selected.size)}. Videos can be up to ${formatFileSize(limits.max_file_size_bytes)}.`,
            );
        }

        if (selected) {
            ticket.setData({
                filename: selected.name,
                content_type: selected.type,
                size: selected.size,
            });
        }
    }

    async function upload() {
        if (!file) {
            return;
        }

        setUploadError(null);
        setProgress(0);

        try {
            setStatus('requesting');
            const presigned = await ticket.post(storeUpload.url());

            // useHttp resolves with undefined on 422 and fills ticket.errors instead.
            if (!presigned) {
                setStatus('failed');

                return;
            }

            // Step 2: send the bytes straight to R2, bypassing Laravel.
            setStatus('uploading');
            await uploadToPresignedUrl(presigned, file, file.type, setProgress);

            setStatus('confirming');
            confirmation.setData({ key: presigned.key, filename: file.name });
            const video = await confirmation.post(storeVideo.url());

            if (!video) {
                setStatus('failed');

                return;
            }

            setStatus('done');
            toast.success('Uploaded. We are checking the video now.');
            router.visit(videosIndex());
        } catch {
            setStatus('failed');
            setUploadError(
                'The upload failed. Check your connection and try again.',
            );
        }
    }

    return (
        <>
            <Head title="Upload video" />

            <div className="flex max-w-xl flex-col gap-6 p-4">
                <Heading
                    title="Upload video"
                    description={`Vertical MP4 or MOV, up to ${formatFileSize(limits.max_file_size_bytes)} and ${formatDurationLimit(limits.min_duration_ms)} to ${formatDurationLimit(limits.max_duration_ms)} long.`}
                />

                <div className="grid gap-2">
                    <Label htmlFor="video">Video file</Label>
                    <Input
                        id="video"
                        type="file"
                        accept="video/mp4,video/quicktime"
                        onChange={selectFile}
                        disabled={isBusy}
                    />
                    <InputError message={ticket.errors.content_type} />
                    <InputError message={ticket.errors.size} />
                    <InputError message={ticket.errors.filename} />
                    <InputError message={confirmation.errors.key} />
                    <InputError message={uploadError ?? undefined} />
                </div>

                {status === 'uploading' && (
                    <div className="grid gap-1">
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-primary transition-[width]"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {progress}%
                        </p>
                    </div>
                )}

                {status === 'done' && (
                    <p className="text-sm text-green-600 dark:text-green-400">
                        {confirmation.response?.message}
                    </p>
                )}

                <div>
                    <Button
                        onClick={upload}
                        disabled={
                            !file ||
                            isBusy ||
                            file.size > limits.max_file_size_bytes
                        }
                    >
                        {statusLabels[status]}
                    </Button>
                </div>

                <PlatformLimitsTable
                    platforms={limits.platforms}
                    maxFileSizeBytes={limits.max_file_size_bytes}
                />
            </div>
        </>
    );
}

function PlatformLimitsTable({
    platforms,
    maxFileSizeBytes,
}: {
    platforms: PlatformLimits[];
    maxFileSizeBytes: number;
}) {
    return (
        <div className="grid gap-2">
            <h3 className="text-sm font-medium">Limits per platform</h3>
            <p className="text-sm text-muted-foreground">
                A video longer or larger than a platform allows can still be
                uploaded, but it can't be posted to that platform.
            </p>
            <div className="overflow-hidden rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 font-medium">Platform</th>
                            <th className="px-3 py-2 font-medium">
                                Max length
                            </th>
                            <th className="px-3 py-2 font-medium">Max size</th>
                        </tr>
                    </thead>
                    <tbody>
                        {platforms.map((platform) => {
                            const Icon = platformIcons[platform.value];

                            return (
                                <tr key={platform.value} className="border-t">
                                    <td className="px-3 py-2">
                                        <span className="flex items-center gap-2">
                                            <Icon className="size-4 text-muted-foreground" />
                                            {platform.label}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatDurationLimit(
                                            platform.max_duration_ms,
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatFileSize(
                                            Math.min(
                                                platform.max_file_size_bytes ??
                                                    maxFileSizeBytes,
                                                maxFileSizeBytes,
                                            ),
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-muted-foreground">
                TikTok may allow less than 10 min for some accounts; the
                composer shows each account's own limit.
            </p>
        </div>
    );
}

CreateVideo.layout = {
    breadcrumbs: [
        {
            title: 'Upload video',
            href: create(),
        },
    ],
};
