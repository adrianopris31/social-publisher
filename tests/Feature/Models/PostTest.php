<?php

use App\Models\Post;

test('posts are deleted with their user', function () {
    $post = Post::factory()->create();

    $post->user->delete();

    $this->assertModelMissing($post);
});

test('posts are deleted with their video', function () {
    $post = Post::factory()->create();

    $post->video->delete();

    $this->assertModelMissing($post);
});
