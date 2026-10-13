<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use Illuminate\Database\UniqueConstraintViolationException;

test('new publications start as pending', function () {
    $post = Post::factory()->create();
    $account = SocialAccount::factory()->for($post->user)->forPlatform(Platform::YouTube)->create();

    $publication = $post->publications()->create([
        'social_account_id' => $account->id,
        'platform' => Platform::YouTube,
    ]);

    expect($publication->status)->toBe(PublicationStatus::Pending);
});

test('publications are deleted with their post', function () {
    $publication = Publication::factory()->create();

    $publication->post->delete();

    $this->assertModelMissing($publication);
});

test('disconnecting a social account keeps its publication history', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create();
    $publication = Publication::factory()
        ->published()
        ->for(Post::factory()->for($account->user))
        ->for($account)
        ->create();

    $account->delete();

    expect($publication->fresh())
        ->social_account_id->toBeNull()
        ->platform->toBe(Platform::TikTok)
        ->status->toBe(PublicationStatus::Published);
});

test('a post cannot target the same social account twice', function () {
    $publication = Publication::factory()->create();

    Publication::factory()->for($publication->post)->create([
        'social_account_id' => $publication->social_account_id,
    ]);
})->throws(UniqueConstraintViolationException::class);
