<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
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
            'key' => [
                'required',
                'string',
                'regex:'.$this->ownedKeyPattern(),
                Rule::unique('videos', 'path'),
            ],
        ];
    }

    /**
     * Match only keys issued by VideoUploadController for the current user,
     * e.g. "videos/7/{uuid}.mp4". Anchored so "../" cannot escape the prefix.
     */
    private function ownedKeyPattern(): string
    {
        $extensions = implode('|', StoreVideoUploadRequest::EXTENSIONS);

        return '#^videos/'.$this->user()->id.'/[0-9a-f-]{36}\.('.$extensions.')$#';
    }
}
