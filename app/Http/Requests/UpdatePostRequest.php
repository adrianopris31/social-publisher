<?php

namespace App\Http\Requests;

use App\Models\Post;

class UpdatePostRequest extends StorePostRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && $this->user()->can('update', $post);
    }

    /**
     * The post's own publications are being replaced, so they do not count towards the limits.
     */
    protected function ignoredPostId(): ?int
    {
        $post = $this->route('post');

        return $post instanceof Post ? $post->id : null;
    }
}
