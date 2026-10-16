<?php

namespace App\Publishing;

use App\Enums\PublicationStatus;
use App\Models\Publication;
use App\Models\SocialAccount;
use Carbon\CarbonInterface;

class PublishingLimits
{
    public function dailyLimit(SocialAccount $account): int
    {
        return (int) config("publishing.daily_limits.{$account->platform->value}", PHP_INT_MAX);
    }

    /**
     * How many more publications the account can take around the given time.
     *
     * Counts non-failed publications whose publish time (scheduled time, else creation time) falls
     * within 24 hours either side, which keeps every rolling 24 hour window under the limit.
     */
    public function remaining(SocialAccount $account, CarbonInterface $at, ?int $ignoredPostId = null): int
    {
        $used = Publication::query()
            ->join('posts', 'posts.id', '=', 'publications.post_id')
            ->where('publications.social_account_id', $account->id)
            ->where('publications.status', '!=', PublicationStatus::Failed)
            ->when($ignoredPostId, fn ($query) => $query->where('publications.post_id', '!=', $ignoredPostId))
            ->whereRaw('coalesce(posts.publish_at, publications.created_at) > ?', [$at->copy()->subDay()])
            ->whereRaw('coalesce(posts.publish_at, publications.created_at) < ?', [$at->copy()->addDay()])
            ->count();

        return max(0, $this->dailyLimit($account) - $used);
    }
}
