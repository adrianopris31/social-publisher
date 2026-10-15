<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\StoreVideoUploadRequest;
use App\Jobs\ProbeVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VideoController extends Controller
{
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
