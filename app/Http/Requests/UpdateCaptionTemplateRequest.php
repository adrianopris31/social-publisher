<?php

namespace App\Http\Requests;

use App\Models\CaptionTemplate;

class UpdateCaptionTemplateRequest extends StoreCaptionTemplateRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $template = $this->route('captionTemplate');

        return $template instanceof CaptionTemplate && $this->user()->can('update', $template);
    }

    /**
     * Changing a template does not add one, so the template limit does not apply.
     */
    protected function isCreating(): bool
    {
        return false;
    }
}
