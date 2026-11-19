<?php

namespace App\Http\Requests;

use App\Enums\CaptionTemplateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCaptionTemplateRequest extends FormRequest
{
    /**
     * Templates one user can keep, so the composer menu stays usable.
     */
    public const int MAX_TEMPLATES = 100;

    /**
     * Hashtags one set can hold: Instagram rejects captions with more than 30.
     */
    public const int MAX_HASHTAGS = 30;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CaptionTemplateType::class)],
            'name' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Rules that depend on the user's other templates and the normalized hashtags.
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

                if ($this->isCreating() && $this->user()->captionTemplates()->count() >= self::MAX_TEMPLATES) {
                    $validator->errors()->add('name', 'You can keep at most '.self::MAX_TEMPLATES.' templates. Delete one first.');
                }

                if ($this->type() !== CaptionTemplateType::Hashtags) {
                    return;
                }

                $hashtags = $this->hashtags();

                if ($hashtags === []) {
                    $validator->errors()->add('body', 'Add at least one hashtag.');
                } elseif (count($hashtags) > self::MAX_HASHTAGS) {
                    $validator->errors()->add('body', 'A hashtag set can hold at most '.self::MAX_HASHTAGS.' hashtags, the Instagram limit.');
                }
            },
        ];
    }

    /**
     * The attributes to save. Hashtag sets are stored as "#one #two", whatever the user typed.
     *
     * @return array{type: CaptionTemplateType, name: string, body: string}
     */
    public function templateAttributes(): array
    {
        return [
            'type' => $this->type(),
            'name' => $this->string('name')->trim()->toString(),
            'body' => $this->type() === CaptionTemplateType::Hashtags
                ? implode(' ', $this->hashtags())
                : $this->string('body')->trim()->toString(),
        ];
    }

    /**
     * Whether the request creates a template rather than changing one.
     */
    protected function isCreating(): bool
    {
        return true;
    }

    private function type(): CaptionTemplateType
    {
        return CaptionTemplateType::from($this->string('type')->toString());
    }

    /**
     * The unique hashtags in the body, accepting "#a #b", "a, b" or one per line.
     *
     * @return list<string>
     */
    private function hashtags(): array
    {
        preg_match_all('/[^\s,#]+/u', $this->string('body')->toString(), $matches);

        $hashtags = [];

        foreach ($matches[0] as $word) {
            $hashtags[mb_strtolower($word)] ??= '#'.$word;
        }

        return array_values($hashtags);
    }
}
