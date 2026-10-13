<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SocialAccount $socialAccount): bool
    {
        return $socialAccount->user()->is($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SocialAccount $socialAccount): bool
    {
        return $socialAccount->user()->is($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SocialAccount $socialAccount): bool
    {
        return $socialAccount->user()->is($user);
    }
}
