<?php

namespace App\Http\Requests;

use App\Models\Post;
use App\Publishing\PublishingLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdatePostScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->routePost());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'publish_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'publish_at.after' => 'The publish time must be in the future.',
        ];
    }

    /**
     * The new time must keep every account of the post under its daily limit.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $limits = app(PublishingLimits::class);
                $publications = $this->routePost()->publications()->with('socialAccount')->get();

                foreach ($publications as $publication) {
                    $account = $publication->socialAccount;

                    if ($account === null || $limits->remaining($account, $this->publishAt(), $this->routePost()->id) > 0) {
                        continue;
                    }

                    $validator->errors()->add(
                        'publish_at',
                        "{$account->display_name} reached its limit of {$limits->dailyLimit($account)} {$account->platform->label()} posts per 24 hours around this time.",
                    );

                    return;
                }
            },
        ];
    }

    /**
     * The requested publish time in UTC.
     */
    public function publishAt(): Carbon
    {
        return Carbon::parse($this->string('publish_at')->toString())->utc();
    }

    private function routePost(): Post
    {
        $post = $this->route('post');

        abort_unless($post instanceof Post, 404);

        return $post;
    }
}
