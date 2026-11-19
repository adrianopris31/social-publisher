<?php

use App\Enums\Platform;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('every page lists the accounts of the user that need reconnection with their scheduled publications', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->forPlatform(Platform::TikTok)->needsReconnection()->create(['display_name' => 'My TikTok']);
    SocialAccount::factory()->for($user)->forPlatform(Platform::YouTube)->create();
    SocialAccount::factory()->forPlatform(Platform::TikTok)->needsReconnection()->create();
    Publication::factory()->for(Post::factory()->for($user)->scheduled())->for($account)->create();
    Publication::factory()->for(Post::factory()->for($user)->scheduled())->for($account)->create();
    Publication::factory()->for(Post::factory()->for($user)->dispatched())->for($account)->failed()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('accountsNeedingReconnection', 1)
            ->where('accountsNeedingReconnection.0', [
                'id' => $account->id,
                'platform' => 'tiktok',
                'display_name' => 'My TikTok',
                'scheduled_publications' => 2,
            ])
        );
});

test('no accounts are listed when every connection works', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->forPlatform(Platform::YouTube)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('accountsNeedingReconnection', [])
        );
});
