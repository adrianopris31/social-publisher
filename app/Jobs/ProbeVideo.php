<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ProbeVideo implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    private const array SUPPORTED_CODECS = ['h264', 'hevc'];

    private const float MIN_ASPECT_RATIO = 0.45;

    private const float MAX_ASPECT_RATIO = 0.6;

    private const int MIN_DURATION_MS = 3_000;

    /** YouTube Shorts limit; refine per platform later. */
    private const int MAX_DURATION_MS = 180_000;

    /**
     * Create a new job instance.
     */
    public function __construct(public Video $video) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = Storage::disk($this->video->disk);
        $url = $disk->temporaryUrl($this->video->path, now()->addMinutes(10));

        $result = Process::run([
            'ffprobe', '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=codec_name,width,height:stream_side_data=rotation:format=duration',
            '-of', 'json',
            $url,
        ]);

        if (! $result->successful()) {
            $this->reject('Unreadable video file.');

            return;
        }

        $probe = json_decode($result->output(), true);

        $codec = (string) data_get($probe, 'streams.0.codec_name', '');
        $width = (int) data_get($probe, 'streams.0.width', 0);
        $height = (int) data_get($probe, 'streams.0.height', 0);
        $rotation = (int) data_get($probe, 'streams.0.side_data_list.0.rotation', 0);
        $durationMs = (int) round((float) data_get($probe, 'format.duration', 0) * 1000);

        if (abs($rotation) === 90) {
            [$width, $height] = [$height, $width];
        }

        $this->video->fill([
            'width' => $width,
            'height' => $height,
            'duration_ms' => $durationMs,
        ]);

        $reason = $this->rejectionReason($codec, $width, $height, $durationMs);

        if ($reason !== null) {
            $this->reject($reason);

            return;
        }

        $this->video->update([
            'status' => VideoStatus::Ready,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Return the first broken rule or null when the video is valid.
     */
    private function rejectionReason(string $codec, int $width, int $height, int $durationMs): ?string
    {
        if (! in_array($codec, self::SUPPORTED_CODECS, true)) {
            return "Unsupported codec: {$codec}.";
        }

        if ($height <= $width) {
            return 'Video must be vertical.';
        }

        $aspectRatio = $width / $height;

        if ($aspectRatio < self::MIN_ASPECT_RATIO || $aspectRatio > self::MAX_ASPECT_RATIO) {
            return 'Aspect ratio must be close to 9:16.';
        }

        if ($durationMs < self::MIN_DURATION_MS || $durationMs > self::MAX_DURATION_MS) {
            return 'Duration must be between 3 and 180 seconds.';
        }

        return null;
    }

    /**
     * Mark the video as rejected and delete its file from storage.
     */
    private function reject(string $reason): void
    {
        $this->video->update([
            'status' => VideoStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        Storage::disk($this->video->disk)->delete($this->video->path);
    }
}
