import { Head, router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { uploadToPresignedUrl } from '@/lib/upload-to-presigned-url';
import type { PresignedUpload } from '@/lib/upload-to-presigned-url';
import {
    create,
    index as videosIndex,
    store as storeVideo,
} from '@/routes/videos';
import { store as storeUpload } from '@/routes/videos/uploads';

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

export default function CreateVideo() {
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
                    description="Vertical MP4 or MOV, up to 500 MB."
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
                    <Button onClick={upload} disabled={!file || isBusy}>
                        {statusLabels[status]}
                    </Button>
                </div>
            </div>
        </>
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
