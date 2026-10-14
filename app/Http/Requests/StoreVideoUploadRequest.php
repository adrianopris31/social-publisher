<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoUploadRequest extends FormRequest
{
    /**
     * Maximum upload size in bytes (500 MB).
     */
    public const int MAX_SIZE_BYTES = 500 * 1024 * 1024;

    /**
     * File extension stored in R2 for each accepted MIME type.
     *
     * @var array<string, string>
     */
    public const array EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
    ];

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
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', Rule::in(array_keys(self::EXTENSIONS))],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SIZE_BYTES],
        ];
    }
}
