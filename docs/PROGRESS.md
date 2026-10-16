# Progress

Budget: max 60 lines / ~500 words. Compact older entries instead of appending forever.

## Current phase & task
Phase 3 – Account connections. Steps 1–4 done (setup, YouTube OAuth, connected-accounts page, token refresh). Step 5 TikTok built (connection + token refresh); owner to test the connection with the sandbox target user. **Open choice: Step 6 Meta, or jump to Phase 4 (publishing engine) to publish to TikTok sooner.** Refresh actions are not called yet; Phase 4 jobs will call them right before each upload.
Local OAuth: `ngrok http --url=sizzling-headlamp-credibly.ngrok-free.dev https://social-publisher.test --host-header=social-publisher.test`; `GOOGLE_REDIRECT_URI` points there. `vite.config.ts` allows `*.ngrok-free.(app|dev)` in `server.cors` (replaces the plugin defaults, so localhost/`.test` are re-listed) → the dev server works through ngrok.

## Done
- 2026-10-16: Phase 3 TikTok: `socialiteproviders/tiktok` (registered via `SocialiteWasCalled` in `AppServiceProvider`); `TikTokConnectionController` (`open_id` → `external_id`, Guzzle errors → error toast); `RefreshTikTokAccessToken` (`client_key`; `error` checked on any status; rotated refresh token saved).
- 2026-10-16: Phase 3 YouTube: `YouTubeConnectionController` (`youtube.upload` + `youtube.readonly`, `access_type=offline` + `prompt=consent`, granular-scope check, refresh token kept if omitted); `RefreshYouTubeAccessToken` via `Http::asForm()` (Socialite's `refreshToken()` breaks on Google and bypasses `Http::fake`). Both refresh actions: early return if valid > 5 min; `invalid_grant`/no refresh token → `SocialAccountNeedsReconnectionException` (no retry), else `RequestException` (retry). Verified with a real channel.
- 2026-10-16: Connected-accounts page (`SocialAccountController` index + destroy with `can:delete`, per-platform cards, disconnect dialog, sidebar link). `@property` docblock on `SocialAccount` for Larastan. Toast hook shows the initial page's flash.
- 2026-10-15: Phase 2: R2 presigned uploads + uploader page; `ProbeVideo` job (ffprobe on `temporaryUrl`, vertical/ratio/duration rules, retries, `failed()` → Rejected); file cleanup on video/user delete, `Video` prunable. Tests: `Process::fake(['*ffprobe*' => ...])`, `Queue::fake([ProbeVideo::class])`, `r2` faked in `TestCase::setUp`.
- 2026-10-13: Phases 0–1: Herd + PHP 8.5, MySQL 9, Redis, FFmpeg; domain models, enums, owner policies, factories.

## In progress
- Phase 3 Step 5 TikTok: real connection test via ngrok pending (owner).

## Next
1. Phase 3 Step 6: Meta app setup, Instagram + Facebook Page connection.
2. Disconnect should also revoke tokens (Google `oauth2.googleapis.com/revoke`, TikTok `open.tiktokapis.com/v2/oauth/revoke/`).

## Decisions
- 2026-10-16: TikTok before Meta (owner priority). Upload via `FILE_UPLOAD` unless an R2 custom domain gets verified for `PULL_FROM_URL`.
- 2026-10-13: Free Herd → MySQL from the official installer, Redis from Homebrew. Horizon deferred to Phase 4.
- 2026-10-13: Domain: User→Video, User→Post; Post→Video (one video per post, video reusable); Post→Publication→SocialAccount. Per-platform text on Publication (default caption on Post). Statuses Pending/Uploading/Processing/Published/Failed; scheduling via `Post.publish_at`, no Scheduled status. Publication authorized via its Post (no own policy).
- 2026-10-13: FKs: deleting a video cascades its posts (restrict would break user-delete cascades); deleting a SocialAccount nulls `publications.social_account_id`, `platform` is snapshotted on Publication to keep history. Unique (post, account).
- 2026-10-13: `config/boost.php` sends Claude Code guidelines to `AGENTS.md`, so `boost:update` keeps `CLAUDE.md` imports-only.
- 2026-10-13: Tokens in encrypted columns on `social_accounts` (no token package yet; revisit in Phase 3). `composer types:check` uses `--memory-limit=1G`.
- 2026-10-13: Storage: dedicated `r2` disk (`R2_*` env, `throw => true`); bucket `social-publisher-local` is private. Tests use `Storage::fake('r2')`. R2 (not local disk) because files must outlive scheduling/retries, be shared by web + queue workers, and be pullable by IG/TikTok via temporary URLs; delete files once all publications finish.
- 2026-10-13: Git: conventional commits, commit straight to `main` while solo.
- 2026-10-13: Stack and architecture as in `docs/PROJECT.md` (R2 + presigned uploads, one job per platform publish, `PublisherInterface`).

## Blockers / open questions
- Phase 4: wrap token refresh in a per-account lock (`Cache::lock("social-account:{id}:refresh")`, Redis). TikTok rotates refresh tokens, so two concurrent jobs refreshing with the same old token can get `invalid_grant` and wrongly flag the account for reconnection; the waiting job should re-read the fresh token instead.
- Google Testing mode: refresh tokens expire after 7 days; unaudited uploads are private-only.
- Google Client Secret was printed into a Claude session log (2026-10-16) → rotate it in Google Console.
- `npm run types:check`: 3 pre-existing errors in generated `RedirectController.ts` (Wayfinder vs `query` method from `Route::redirect` in `settings.php`).
- Run `wayfinder:generate --with-form` (Vite uses `formVariants: true`), never without the flag.
- Presigned uploads never confirmed (no `Video` row) stay in R2 until the user is deleted → consider an R2 lifecycle rule.
- XAMPP and Homebrew `mysql`/`mariadb` are also installed (stopped) → keep them stopped to avoid port 3306 clashes.
- MySQL root password was echoed into a Claude session log → consider rotating it.

## Platform approvals
| Platform | Status | Notes |
|---|---|---|
| YouTube (Google OAuth) | Testing mode | OAuth client + consent screen (External, Testing, owner as test user); audit needed for public uploads |
| Instagram (Meta) | not started | App Review + Business Verification for public use |
| Facebook Pages (Meta) | not started | Same Meta app as Instagram |
| TikTok | not started | Draft/inbox pre-audit; audit for direct post |
