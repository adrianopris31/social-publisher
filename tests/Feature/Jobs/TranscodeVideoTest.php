<?php

use App\Enums\VideoStatus;
use App\Jobs\TranscodeVideo;
use App\Models\Video;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function videoAwaitingTranscode(int $width = 1080, int $height = 1920): Video
{
    $video = Video::factory()->unprocessed()->create([
        'disk' => 'r2',
        'path' => 'videos/1/clip.mov',
        'mime_type' => 'video/quicktime',
        'width' => $width,
        'height' => $height,
    ]);
    Storage::disk('r2')->put($video->path, 'original bytes');

    return $video;
}

test('it replaces the original with the transcoded mp4 and marks the video ready', function () {
    Process::preventStrayProcesses();
    Process::fake(['*ffmpeg*' => function (PendingProcess $process) {
        // ffmpeg writes its output to the last argument.
        file_put_contents((string) last($process->command), 'transcoded');

        return Process::result();
    }]);
    $video = videoAwaitingTranscode();

    (new TranscodeVideo($video))->handle();

    $video->refresh();
    expect($video)
        ->status->toBe(VideoStatus::Ready)
        ->mime_type->toBe('video/mp4')
        ->size_bytes->toBe(10)
        ->path->toStartWith('videos/1/clip-')->toEndWith('.mp4');
    Storage::disk('r2')->assertMissing('videos/1/clip.mov');
    expect(Storage::disk('r2')->get($video->path))->toBe('transcoded');
    Process::assertRan(fn (PendingProcess $process) => in_array('libx264', $process->command, true)
        && in_array('aac', $process->command, true)
        && ! in_array('-vf', $process->command, true));
});

test('it scales a video onto a 1080x1920 canvas when it is not 9:16 or too wide', function (int $width, int $height) {
    Process::preventStrayProcesses();
    Process::fake(['*ffmpeg*' => function (PendingProcess $process) {
        file_put_contents((string) last($process->command), 'transcoded');

        return Process::result();
    }]);
    $video = videoAwaitingTranscode($width, $height);

    (new TranscodeVideo($video))->handle();

    expect($video->fresh())
        ->status->toBe(VideoStatus::Ready)
        ->width->toBe(1080)
        ->height->toBe(1920);
    Process::assertRan(function (PendingProcess $process) {
        $filterIndex = array_search('-vf', $process->command, true);

        return $filterIndex !== false && str_contains((string) $process->command[$filterIndex + 1], 'pad=1080:1920');
    });
})->with([
    'not 9:16' => [656, 1376],
    '4k vertical' => [2160, 3840],
]);

test('it keeps the original and throws when ffmpeg fails so the job is retried', function () {
    Process::preventStrayProcesses();
    Process::fake(['*ffmpeg*' => Process::result(errorOutput: 'Invalid data', exitCode: 1)]);
    $video = videoAwaitingTranscode();

    expect(fn () => (new TranscodeVideo($video))->handle())->toThrow(RuntimeException::class, 'ffmpeg failed: Invalid data');
    Storage::disk('r2')->assertExists('videos/1/clip.mov');
    expect($video->fresh()->status)->toBe(VideoStatus::Pending);
});

test('it rejects the video after the final failure', function () {
    $video = videoAwaitingTranscode();

    (new TranscodeVideo($video))->failed(new RuntimeException('ffmpeg failed'));

    expect($video->fresh())
        ->status->toBe(VideoStatus::Rejected)
        ->rejection_reason->toContain('MP4 (H.264)');
});
