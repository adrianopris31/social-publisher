<?php

use App\Enums\CaptionTemplateType;
use App\Http\Requests\StoreCaptionTemplateRequest;
use App\Models\CaptionTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function (string $method, string $url) {
    $this->{$method}($url)->assertRedirect(route('login'));
})->with([
    'index' => ['get', '/caption-templates'],
    'store' => ['post', '/caption-templates'],
]);

test('it lists only the templates of the user by name', function () {
    $user = User::factory()->create();
    $second = CaptionTemplate::factory()->for($user)->create(['name' => 'Subscribe']);
    $first = CaptionTemplate::factory()->for($user)->hashtags()->create(['name' => 'Gaming tags']);
    CaptionTemplate::factory()->create();

    $this->actingAs($user)
        ->get(route('caption-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('caption-templates/index')
            ->has('templates', 2)
            ->where('templates.0.id', $first->id)
            ->where('templates.0.type', 'hashtags')
            ->where('templates.1.id', $second->id)
        );
});

describe('store', function () {
    test('it saves a caption template for the user and goes back', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('posts.create'))
            ->post(route('caption-templates.store'), [
                'type' => 'caption',
                'name' => '  Follow for more  ',
                'body' => "Follow for a clip every day!\n",
            ])
            ->assertRedirect(route('posts.create'))
            ->assertInertiaFlash('toast.message', 'Follow for more saved.');

        expect($user->captionTemplates()->sole())
            ->type->toBe(CaptionTemplateType::Caption)
            ->name->toBe('Follow for more')
            ->body->toBe('Follow for a clip every day!');
    });

    test('it normalizes a hashtag set to unique hashtags', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('caption-templates.store'), [
                'type' => 'hashtags',
                'name' => 'Gaming',
                'body' => "gaming, #Shorts\n#funny #gaming #GAMING",
            ])
            ->assertSessionHasNoErrors();

        expect($user->captionTemplates()->sole()->body)->toBe('#gaming #Shorts #funny');
    });

    test('it validates the template', function (array $input, string $field, string $message) {
        $this->actingAs(User::factory()->create())
            ->post(route('caption-templates.store'), [
                'type' => 'caption',
                'name' => 'Template',
                'body' => 'Text',
                ...$input,
            ])
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'unknown type' => [['type' => 'thread'], 'type', 'The selected type is invalid.'],
        'missing name' => [['name' => ''], 'name', 'The name field is required.'],
        'long name' => [['name' => str_repeat('a', 61)], 'name', 'The name field must not be greater than 60 characters.'],
        'missing body' => [['body' => ''], 'body', 'The body field is required.'],
        'hashtag set without hashtags' => [['type' => 'hashtags', 'body' => '# , #'], 'body', 'Add at least one hashtag.'],
        'more hashtags than instagram allows' => [['type' => 'hashtags', 'body' => implode(' ', range(1, 31))], 'body', 'A hashtag set can hold at most 30 hashtags, the Instagram limit.'],
    ]);

    test('it caps how many templates a user can keep', function () {
        $user = User::factory()->create();
        CaptionTemplate::factory()->for($user)->count(StoreCaptionTemplateRequest::MAX_TEMPLATES)->create();

        $this->actingAs($user)
            ->post(route('caption-templates.store'), ['type' => 'caption', 'name' => 'One more', 'body' => 'Text'])
            ->assertSessionHasErrors(['name' => 'You can keep at most 100 templates. Delete one first.']);

        expect($user->captionTemplates()->count())->toBe(StoreCaptionTemplateRequest::MAX_TEMPLATES);
    });
});

describe('update', function () {
    test('the owner can change a template', function () {
        $template = CaptionTemplate::factory()->create(['name' => 'Old']);

        $this->actingAs($template->user)
            ->put(route('caption-templates.update', $template), ['type' => 'hashtags', 'name' => 'New', 'body' => 'one two'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'New updated.');

        expect($template->fresh())
            ->type->toBe(CaptionTemplateType::Hashtags)
            ->name->toBe('New')
            ->body->toBe('#one #two');
    });

    test('the template limit does not block changing a template', function () {
        $user = User::factory()->create();
        $templates = CaptionTemplate::factory()->for($user)->count(StoreCaptionTemplateRequest::MAX_TEMPLATES)->create();

        $this->actingAs($user)
            ->put(route('caption-templates.update', $templates->first()), ['type' => 'caption', 'name' => 'Renamed', 'body' => 'Text'])
            ->assertSessionHasNoErrors();
    });

    test('users cannot change templates they do not own', function () {
        $template = CaptionTemplate::factory()->create(['name' => 'Theirs']);

        $this->actingAs(User::factory()->create())
            ->put(route('caption-templates.update', $template), ['type' => 'caption', 'name' => 'Mine', 'body' => 'Text'])
            ->assertForbidden();

        expect($template->fresh()->name)->toBe('Theirs');
    });
});

describe('destroy', function () {
    test('the owner can delete a template', function () {
        $template = CaptionTemplate::factory()->create();

        $this->actingAs($template->user)
            ->delete(route('caption-templates.destroy', $template))
            ->assertRedirect();

        $this->assertModelMissing($template);
    });

    test('users cannot delete templates they do not own', function () {
        $template = CaptionTemplate::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('caption-templates.destroy', $template))
            ->assertForbidden();

        $this->assertModelExists($template);
    });
});
