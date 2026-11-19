<?php

namespace App\Policies;

use App\Models\CaptionTemplate;
use App\Models\User;

class CaptionTemplatePolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CaptionTemplate $captionTemplate): bool
    {
        return $captionTemplate->user()->is($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CaptionTemplate $captionTemplate): bool
    {
        return $captionTemplate->user()->is($user);
    }
}
