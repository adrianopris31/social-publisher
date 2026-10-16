<?php

namespace App\Exceptions;

use App\Models\SocialAccount;
use Exception;

class SocialAccountNeedsReconnectionException extends Exception
{
    public function __construct(public SocialAccount $account)
    {
        parent::__construct("Social account {$account->id} must be reconnected.");
    }
}
