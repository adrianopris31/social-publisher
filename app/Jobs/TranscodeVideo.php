<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Convert a probed video to H.264 + AAC MP4 (max 60 fps, fast start), the format every platform accepts,
 * scaling it onto a 1080x1920 canvas when it isn't 9:16 or is too wide (black bars fill the rest), then replace the original file in storage
 * and mark the video ready.
 */
#[Queue('uploads')]
#[Tries(2)]
#[Backoff([60])]
#[Timeout(900)]
#[DeleteWhenMissingModels]
class TranscodeVideo implements ShouldQueue
{
    use Queueable;

    /** Scale to fit 1080x1920, then pad the rest with black bars. */
    private const string LETTERBOX_FILTER = 'scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2:black,setsar=1';

    public function __construct(public Video $video) {}

    public function handle(): void
    {
        $disk = Storage::disk($this->video->disk);
        $source = $disk->temporaryUrl($this->video->path, now()->addHour());

        $directory = storage_path('app/transcode');
        File::ensureDirectoryExists($directory);
        $output = $directory.'/'.Str::uuid().'.mp4';
        $letterbox = $this->video->needsResizing();

        try {
            $result = Process::timeout(880)->run([
                'ffmpeg', '-y', '-v', 'error',
                '-i', $source,
                '-map', '0:v:0', '-map', '0:a:0?',
                ...($letterbox ? ['-vf', self::LETTERBOX_FILTER] : []),
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20',
                '-pix_fmt', 'yuv420p', '-fpsmax', '60',
                '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', '-ac', '2',
                '-movflags', '+faststart',
                $output,
            ]);

            if (! $result->successful() || ! File::exists($output)) {
                throw new RuntimeException('ffmpeg failed: '.$result->errorOutput());
            }

            $oldPath = $this->video->path;
            $newPath = preg_replace('/\.[^.\/]+$/', '', $oldPath).'-'.Str::random(6).'.mp4';

            $stream = fopen($output, 'r');

            if ($stream === false) {
                throw new RuntimeException('The transcoded file cannot be read.');
            }

            try {
                $disk->writeStream($newPath, $stream);
            } finally {
                fclose($stream);
            }

            $this->video->update([
                'path' => $newPath,
                'mime_type' => 'video/mp4',
                'size_bytes' => File::size($output),
                ...($letterbox ? ['width' => 1080, 'height' => 1920] : []),
                'status' => VideoStatus::Ready,
                'rejection_reason' => null,
            ]);

            $disk->delete($oldPath);
        } finally {
            File::delete($output);
        }
    }

    /**
     * Handle a job failure after the last retry.
     */
    public function failed(?Throwable $exception): void
    {
        $this->video->update([
            'status' => VideoStatus::Rejected,
            'rejection_reason' => 'We could not convert this video. Please export it as an MP4 (H.264) and upload it again.',
        ]);
    }
}
