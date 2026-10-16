<?php

namespace App\Publishing;

use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * One implementation per platform. Implementations receive an account with a fresh access token.
 *
 * Throw PublishingFailedException for permanent rejections, SocialAccountNeedsReconnectionException
 * when the token is unusable, and RequestException/ConnectionException for transient failures.
 */
interface Publisher
{
    /**
     * Upload the video and start publishing it.
     *
     * @throws PublishingFailedException
     * @throws SocialAccountNeedsReconnectionException
     * @throws RequestException
     * @throws ConnectionException
     */
    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult;

    /**
     * Check on a publication the platform is still processing (status Processing, processing_id set).
     *
     * @throws PublishingFailedException
     * @throws SocialAccountNeedsReconnectionException
     * @throws RequestException
     * @throws ConnectionException
     */
    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult;
}
