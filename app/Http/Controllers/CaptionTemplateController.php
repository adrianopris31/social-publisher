<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaptionTemplateRequest;
use App\Http\Requests\UpdateCaptionTemplateRequest;
use App\Http\Resources\CaptionTemplateResource;
use App\Models\CaptionTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaptionTemplateController extends Controller
{
    /**
     * Show the user's caption templates and hashtag sets.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('caption-templates/index', [
            'templates' => CaptionTemplateResource::collection($request->user()->captionTemplates()->orderBy('name')->get())->resolve(),
        ]);
    }

    /**
     * Save a new template. Redirects back, so the composer keeps the post being written.
     */
    public function store(StoreCaptionTemplateRequest $request): RedirectResponse
    {
        $template = $request->user()->captionTemplates()->create($request->templateAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $template->name])]);

        return back();
    }

    /**
     * Change a template. Posts that already used it keep their own copy of the text.
     */
    public function update(UpdateCaptionTemplateRequest $request, CaptionTemplate $captionTemplate): RedirectResponse
    {
        $captionTemplate->update($request->templateAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $captionTemplate->name])]);

        return back();
    }

    /**
     * Delete a template.
     */
    public function destroy(CaptionTemplate $captionTemplate): RedirectResponse
    {
        $captionTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name deleted.', ['name' => $captionTemplate->name])]);

        return back();
    }
}
