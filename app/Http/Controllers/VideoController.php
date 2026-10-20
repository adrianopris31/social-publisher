<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Enums\VideoStatus;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\StoreVideoUploadRequest;
use App\Jobs\ProbeVideo;
use App\Models\Publication;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    /**
     * List the user's uploaded videos.
     */
    public function index(Request $request): Response
    {
        $videos = $request->user()->videos()
            ->withCount('posts')
            ->latest()
            ->paginate(24)
            ->through(fn (Video $video): array => [
                'id' => $video->id,
                'original_filename' => $video->original_filename,
                'size_bytes' => $video->size_bytes,
                'duration_ms' => $video->duration_ms,
                'width' => $video->width,
                'height' => $video->height,
                'status' => $video->status,
                'rejection_reason' => $video->rejection_reason,
                'posts_count' => $video->posts_count,
                'preview_url' => $video->status === VideoStatus::Ready
                    ? Storage::disk($video->disk)->temporaryUrl($video->path, now()->addHour())
                    : null,
                'created_at' => $video->created_at?->toIso8601String(),
            ]);

        return Inertia::render('videos/index', [
            'videos' => $videos,
        ]);
    }

    /**
     * Show the upload form with the limits every video must meet.
     */
    public function create(): Response
    {
        return Inertia::render('videos/create', [
            'limits' => [
                'max_file_size_bytes' => StoreVideoUploadRequest::MAX_SIZE_BYTES,
                'min_duration_ms' => ProbeVideo::MIN_DURATION_MS,
                'max_duration_ms' => Platform::longestMaxDurationMs(),
                'platforms' => array_map(fn (Platform $platform): array => $platform->limits(), Platform::cases()),
            ],
        ]);
    }

    /**
     * Delete the video, its file and the posts that use it.
     */
    public function destroy(Video $video): RedirectResponse
    {
        $isBusy = Publication::query()
            ->whereIn('post_id', $video->posts()->select('id'))
            ->whereIn('status', [PublicationStatus::Uploading, PublicationStatus::Processing])
            ->exists();

        if ($isBusy) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This video is being published right now. Try again when the uploads finish.')]);

            return back();
        }

        $video->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Video deleted.')]);

        return to_route('videos.index');
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $disk = Storage::disk('r2');
        $key = $request->key;

        if (! $disk->exists($key)) {
            throw ValidationException::withMessages([
                'key' => 'The video does not exist.',
            ]);
        }

        $size = $disk->size($key);

        if ($size > StoreVideoUploadRequest::MAX_SIZE_BYTES) {
            $disk->delete($key);
            throw ValidationException::withMessages([
                'key' => 'The video is too large.',
            ]);
        }

        $newVideo = $request->user()->videos()->create([
            'disk' => 'r2',
            'path' => $key,
            'mime_type' => $disk->mimeType($key),
            'size_bytes' => $size,
            'original_filename' => $request->filename,
        ]);

        ProbeVideo::dispatch($newVideo);

        return response()->json(['video_id' => $newVideo->id, 'message' => 'Video uploaded successfully.'], 201);
    }
}
