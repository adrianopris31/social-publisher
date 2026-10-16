<?php

namespace App\Http\Controllers;

use App\Enums\PublicationStatus;
use App\Jobs\PublishPublication;
use App\Models\Publication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PublicationRetryController extends Controller
{
    /**
     * Queue a failed publication again.
     */
    public function __invoke(Publication $publication): RedirectResponse
    {
        Gate::authorize('update', $publication->post);

        $error = match (true) {
            $publication->status !== PublicationStatus::Failed => __('Only failed publications can be retried.'),
            $publication->socialAccount === null => __('The account was disconnected. Connect it again and create a new post.'),
            $publication->socialAccount->reconnection_required_at !== null => __('Reconnect :name first.', ['name' => $publication->socialAccount->display_name]),
            default => null,
        };

        if ($error !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $error]);

            return back();
        }

        $publication->update([
            'status' => PublicationStatus::Pending,
            'error_message' => null,
            'processing_id' => null,
        ]);

        PublishPublication::dispatch($publication);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retrying :platform.', ['platform' => $publication->platform->label()])]);

        return back();
    }
}
