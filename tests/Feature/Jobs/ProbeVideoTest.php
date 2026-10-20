<?php

use App\Enums\VideoStatus;
use App\Jobs\ProbeVideo;
use App\Jobs\TranscodeVideo;
use App\Models\Video;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Build the JSON that ffprobe prints for the entries the job requests.
 */
function ffprobeOutput(string $codec, int $width, int $height, string $duration, ?int $rotation = null): string
{
    $stream = ['codec_name' => $codec, 'width' => $width, 'height' => $height];

    if ($rotation !== null) {
        $stream['side_data_list'] = [['rotation' => $rotation]];
    }

    return json_encode(['streams' => [$stream], 'format' => ['duration' => $duration]], JSON_THROW_ON_ERROR);
}

/**
 * Create an unprocessed video whose file exists on the fake r2 disk.
 */
function storedUnprocessedVideo(): Video
{
    $video = Video::factory()->unprocessed()->create(['disk' => 'r2']);
    Storage::disk('r2')->put($video->path, 'fake video bytes');

    return $video;
}

test('it marks a vertical h264 video as ready and stores its metadata', function () {
    Storage::fake('r2');
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: ffprobeOutput('h264', 1080, 1920, '30.000000'))]);
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Ready)
        ->rejection_reason->toBeNull()
        ->width->toBe(1080)
        ->height->toBe(1920)
        ->duration_ms->toBe(30_000);
    Storage::disk('r2')->assertExists($video->path);
});

test('it swaps the dimensions of a video rotated by 90 degrees', function () {
    Storage::fake('r2');
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: ffprobeOutput('h264', 1920, 1080, '12.500000', rotation: -90))]);
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Ready)
        ->width->toBe(1080)
        ->height->toBe(1920)
        ->duration_ms->toBe(12_500);
});

test('it rejects an invalid video and deletes its file', function (string $output, string $reason) {
    Storage::fake('r2');
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: $output)]);
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Rejected)
        ->rejection_reason->toBe($reason);
    Storage::disk('r2')->assertMissing($video->path);
})->with([
    'unsupported codec' => [ffprobeOutput('vp9', 1080, 1920, '30.000000'), 'Unsupported codec: vp9.'],
    'horizontal' => [ffprobeOutput('h264', 1920, 1080, '30.000000'), 'Video must be vertical.'],
    'too narrow for 9:16' => [ffprobeOutput('h264', 600, 1920, '30.000000'), 'Aspect ratio must be close to 9:16.'],
    'too short' => [ffprobeOutput('h264', 1080, 1920, '2.999000'), 'Duration must be between 3 seconds and 15 minutes.'],
    'longer than every platform allows' => [ffprobeOutput('h264', 1080, 1920, '900.001000'), 'Duration must be between 3 seconds and 15 minutes.'],
]);

test('it accepts a video longer than one platform allows, leaving the per-platform limit to the composer', function () {
    Storage::fake('r2');
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: ffprobeOutput('h264', 1080, 1920, '240.000000'))]);
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Ready)
        ->duration_ms->toBe(240_000);
});

test('it throws when ffprobe fails so the job is retried', function () {
    Storage::fake('r2');
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(exitCode: 1)]);
    $video = storedUnprocessedVideo();

    expect(fn () => (new ProbeVideo($video))->handle())->toThrow(RuntimeException::class);

    expect($video->fresh()->status)->toBe(VideoStatus::Pending);
    Storage::disk('r2')->assertExists($video->path);
});

test('it rejects the video without deleting its file when the job fails for good', function () {
    Storage::fake('r2');
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->failed(new RuntimeException('R2 is down.'));

    expect($video->fresh())
        ->status->toBe(VideoStatus::Rejected)
        ->rejection_reason->toBe('We could not process this video. Please upload it again.');
    Storage::disk('r2')->assertExists($video->path);
});

test('it queues a transcode instead of marking the video ready when platforms would not accept the format', function (array $streams, string $mimeType) {
    Storage::fake('r2');
    Queue::fake([TranscodeVideo::class]);
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: json_encode([
        'streams' => $streams,
        'format' => ['duration' => '20.000000'],
    ], JSON_THROW_ON_ERROR))]);
    $video = storedUnprocessedVideo();
    $video->update(['mime_type' => $mimeType]);

    (new ProbeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Pending)
        ->duration_ms->toBe(20_000);
    Queue::assertPushed(TranscodeVideo::class, fn (TranscodeVideo $job) => $job->video->is($video));
})->with([
    'hevc video' => [[['codec_type' => 'video', 'codec_name' => 'hevc', 'width' => 1080, 'height' => 1920]], 'video/mp4'],
    'opus audio' => [[
        ['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1080, 'height' => 1920],
        ['codec_type' => 'audio', 'codec_name' => 'opus'],
    ], 'video/mp4'],
    'quicktime container' => [[['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1080, 'height' => 1920]], 'video/quicktime'],
    'not 9:16' => [[['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 656, 'height' => 1376]], 'video/mp4'],
    'wider than instagram accepts' => [[['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 2160, 'height' => 3840]], 'video/mp4'],
    'above 60 fps' => [[['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1080, 'height' => 1920, 'avg_frame_rate' => '120/1']], 'video/mp4'],
]);

test('it marks an h264 and aac mp4 ready without transcoding', function () {
    Storage::fake('r2');
    Queue::fake([TranscodeVideo::class]);
    Process::preventStrayProcesses();
    Process::fake(['*ffprobe*' => Process::result(output: json_encode([
        'streams' => [
            ['codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1080, 'height' => 1920, 'avg_frame_rate' => '30000/1001'],
            ['codec_type' => 'audio', 'codec_name' => 'aac'],
        ],
        'format' => ['duration' => '20.000000'],
    ], JSON_THROW_ON_ERROR))]);
    $video = storedUnprocessedVideo();

    (new ProbeVideo($video))->handle();

    expect($video->fresh()->status)->toBe(VideoStatus::Ready);
    Queue::assertNothingPushed();
});
