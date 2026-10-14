<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoUploadController extends Controller
{
    /**
     * Issue a presigned URL so the browser can PUT the video straight to R2.
     */
    public function store(StoreVideoUploadRequest $request): JsonResponse
    {
        $contentType = $request->string('content_type')->toString();
        $extension = StoreVideoUploadRequest::EXTENSIONS[$contentType];

        $key = "videos/{$request->user()->id}/".Str::uuid().".{$extension}";

        ['url' => $url, 'headers' => $headers] = Storage::disk('r2')->temporaryUploadUrl(
            $key,
            now()->addMinutes(15),
            ['ContentType' => $contentType],
        );

        return response()->json([
            'key' => $key,
            'url' => $url,
            'headers' => $headers,
        ]);
    }
}
