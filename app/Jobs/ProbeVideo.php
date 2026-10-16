<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Tries(3)]
#[Backoff([10, 60])]
#[DeleteWhenMissingModels]
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
            '-show_entries', 'stream=codec_type,codec_name,width,height,avg_frame_rate:stream_side_data=rotation:format=duration',
            '-of', 'json',
            $url,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffprobe failed: '.$result->errorOutput());
        }

        $probe = (array) json_decode($result->output(), true);
        $streams = (array) data_get($probe, 'streams', []);

        $videoStream = collect($streams)->first(fn (mixed $stream): bool => data_get($stream, 'codec_type', 'video') === 'video', []);
        $audioStream = collect($streams)->first(fn (mixed $stream): bool => data_get($stream, 'codec_type') === 'audio');

        $codec = (string) data_get($videoStream, 'codec_name', '');
        $width = (int) data_get($videoStream, 'width', 0);
        $height = (int) data_get($videoStream, 'height', 0);
        $rotation = (int) data_get($videoStream, 'side_data_list.0.rotation', 0);
        $frameRate = $this->frameRate((string) data_get($videoStream, 'avg_frame_rate', '0/1'));
        $audioCodec = $audioStream === null ? null : (string) data_get($audioStream, 'codec_name', '');
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

        if ($this->needsTranscoding($codec, $audioCodec, $frameRate)) {
            $this->video->save();
            TranscodeVideo::dispatch($this->video);

            return;
        }

        $this->video->update([
            'status' => VideoStatus::Ready,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Every platform accepts H.264 + AAC in an MP4 at up to 60 fps; anything else is converted first.
     */
    private function needsTranscoding(string $codec, ?string $audioCodec, float $frameRate): bool
    {
        return $this->video->mime_type !== 'video/mp4'
            || $codec !== 'h264'
            || ($audioCodec !== null && $audioCodec !== 'aac')
            || $frameRate > 60.5;
    }

    /**
     * Parse ffprobe's "30000/1001" style frame rate.
     */
    private function frameRate(string $rate): float
    {
        [$numerator, $denominator] = array_pad(explode('/', $rate), 2, '1');

        return (float) $denominator > 0 ? (float) $numerator / (float) $denominator : 0.0;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->video->update([
            'status' => VideoStatus::Rejected,
            'rejection_reason' => 'We could not process this video. Please upload it again.',
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
