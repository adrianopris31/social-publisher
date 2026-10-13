<?php

use App\Models\Video;

test('videos are deleted with their user', function () {
    $video = Video::factory()->create();

    $video->user->delete();

    $this->assertModelMissing($video);
});
