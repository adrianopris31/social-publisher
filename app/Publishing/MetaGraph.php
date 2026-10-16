<?php

namespace App\Publishing;

use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin helper around the Meta Graph API shared by the Meta connection flow and publishers.
 */
class MetaGraph
{
    /**
     * Meta error codes that mean the token is no longer usable and the user has to reconnect.
     *
     * @var list<int>
     */
    private const array TOKEN_ERROR_CODES = [102, 190];

    /**
     * Meta error codes that are transient (rate limits, temporary outages) and worth retrying.
     *
     * @var list<int>
     */
    private const array TRANSIENT_ERROR_CODES = [1, 2, 4, 17, 32, 341, 613];

    public static function version(): string
    {
        return (string) config('services.facebook.graph_version', 'v23.0');
    }

    public static function url(string $path): string
    {
        return 'https://graph.facebook.com/'.self::version().'/'.ltrim($path, '/');
    }

    /**
     * A request authenticated with the given token, signed with appsecret_proof.
     */
    public static function request(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->withQueryParameters([
                'access_token' => $accessToken,
                'appsecret_proof' => hash_hmac('sha256', $accessToken, (string) config('services.facebook.client_secret')),
            ]);
    }

    /**
     * Turn a failed Graph response into the exception the publishing jobs understand.
     *
     * @throws SocialAccountNeedsReconnectionException when the token was revoked or expired
     * @throws PublishingFailedException when Meta rejected the request for good
     * @throws RequestException on transient failures, so jobs can retry
     */
    public static function throwIfFailed(Response $response, SocialAccount $account): Response
    {
        if ($response->successful() && ! $response->json('error')) {
            return $response;
        }

        $code = (int) $response->json('error.code');

        if (in_array($code, self::TOKEN_ERROR_CODES, true)) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        if ($response->serverError() || in_array($code, self::TRANSIENT_ERROR_CODES, true) || $response->json('error.is_transient') === true) {
            throw new RequestException($response);
        }

        throw new PublishingFailedException(
            (string) ($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Meta rejected the request.'),
        );
    }
}
