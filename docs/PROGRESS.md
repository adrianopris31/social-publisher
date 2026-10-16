# Progress

Budget: max 60 lines / ~500 words. Compact older entries instead of appending forever.

## Current phase & task
Phases 0–7 complete in code (builder mode, owner gave full autonomy 2026-10-16), ~270 tests green. **Everything since commit `9de23d8` is uncommitted**; offer to split it into conventional feature commits (no Co-Authored-By). `README.md` is the full user/setup/go-public guide.
**Where we left off (2026-10-16):** real YouTube + TikTok inbox publishes worked. The owner was setting up **TikTok direct post**: enable Direct Post + `video.publish` in the portal, make the sandbox target account private, set `TIKTOK_DIRECT_POST=true` (not yet in `.env`), `config:clear`, restart `composer dev`, reconnect TikTok (the current account only has `user.info.basic`, `video.upload`). The `creator_info` "parameter type is incorrect" bug is fixed but not yet verified against real TikTok. The owner said no inbox draft showed up in the TikTok app for publication #6, although TikTok answered `SEND_TO_USER_INBOX`; still unexplained.
**Open infra:** R2 CORS must allow the ngrok origin (uploads via ngrok fail with a 403 preflight; uploads via `social-publisher.test` work). The R2 bucket was found empty once: check the lifecycle rules (the R2 token is object-only, so Claude can't read bucket settings).
Local OAuth: `ngrok http --url=sizzling-headlamp-credibly.ngrok-free.dev https://social-publisher.test --host-header=social-publisher.test`; every `*_REDIRECT_URI` points there. `vite.config.ts` allows `*.ngrok-free.(app|dev)` in `server.cors`.

## Done
- 2026-10-16 (3): `README.md` (features, pipeline, platform setup, go-public checklist, troubleshooting). Fix: TikTok `creator_info` sends `{}` (an empty array body was rejected as "parameter type is incorrect"). Real YouTube + TikTok inbox publishes succeeded.
- 2026-10-16 (2): Horizon (`supervisor-default` + `supervisor-uploads`; job 900 < supervisor 930 < `retry_after` 960; `HORIZON_ADMIN_EMAILS` gate; snapshot every 5 min). Daily caps per account (`config/publishing.php`, `PublishingLimits`, rolling ±24 h around the publish time, failed ones excluded). Edit/reschedule of not-yet-dispatched posts (`UpdatePostRequest`, row lock against the scheduler). `TranscodeVideo` (non-mp4/non-h264/non-aac/>60 fps → H.264+AAC mp4, `-fpsmax 60`, faststart). TikTok direct post behind `TIKTOK_DIRECT_POST` (`video.publish`, `creator_info` endpoint, audit UX: nickname, privacy without default, interaction toggles, commercial disclosure, consent). Threads (`socialiteproviders/threads`, long-lived token + `th_refresh_token`) and LinkedIn (`linkedin-openid`, multipart Videos API + Posts API, `LINKEDIN_API_VERSION`). Tests for all new code.
- 2026-10-16 (1): Meta connection (one Facebook Login → Page + linked IG accounts, non-expiring Page tokens). `app/Publishing`: `Publisher`, `PublishResult`, `PublisherResolver`, `MetaGraph`, one publisher per platform. `PublishPublication` (`uploads` queue, `WithoutOverlapping` per account, Tries 10/MaxExceptions 3) + `CheckPublicationStatus` (release 20 s, 2 h). `EnsureFreshAccessToken` (per-account `Cache::lock`). `DispatchPost` claims `posts.dispatched_at`; `posts:publish-due` every minute. Composer, posts list, video library, dashboard, retry/delete, token revoke on disconnect, `/privacy` + `/terms`, new welcome page.
- 2026-10-16: Phase 3 YouTube + TikTok OAuth and token refresh (`invalid_grant` → `SocialAccountNeedsReconnectionException`), connected-accounts page.
- 2026-10-15: Phase 2: R2 presigned uploads, `ProbeVideo` (ffprobe rules, retries, Rejected), file cleanup, prunable rejects.
- 2026-10-13: Phases 0–1: Herd + PHP 8.5, MySQL 9, Redis, FFmpeg; domain models, enums, owner policies, factories.

## Next
1. Owner: finish the TikTok direct post test (steps above), then create the Meta app (Facebook Login, `/auth/facebook/callback`), the Threads use case and the LinkedIn app (OpenID Connect + Share on LinkedIn), and test each one end to end.
2. Code (Claude, offered and not started): show TikTok `SEND_TO_USER_INBOX` as "Sent to TikTok inbox: finish in the app" instead of Published (`TikTokPublisher.php` `checkStatus`); per-platform probe rules (`ProbeVideo` caps every platform at 180 s); feature commits.
3. Replace `CONTACT_EMAIL` in `resources/js/components/legal-page.tsx`; review the legal texts.
4. Decide X (paid API tier) and Snapchat (partner allowlist). Phase 8: production domain, reviews and audits (see README "Going public").
5. Optional: Reverb instead of polling; R2 lifecycle rule only for unconfirmed uploads.

## Decisions
- 2026-10-16: Owner approved installing packages freely: `laravel/horizon`, `socialiteproviders/threads`, `@radix-ui/react-switch`.
- 2026-10-16: New `app/Publishing` folder. Effective caption snapshotted on each Publication; `processing_id` holds the container/upload id while polling. Videos stay in R2 after publishing (reusable); the old "delete once all publications finish" idea is dropped.
- 2026-10-16: TikTok: inbox by default, direct post only with the flag + scope. `FILE_UPLOAD` unless an R2 custom domain is verified for `PULL_FROM_URL`.
- 2026-10-13: Domain: User→Video, User→Post; Post→Video (video reusable); Post→Publication→SocialAccount. Statuses Pending/Uploading/Processing/Published/Failed; scheduling via `Post.publish_at`. Publication authorized via its Post.
- 2026-10-13: FKs: deleting a video cascades its posts; deleting a SocialAccount nulls `publications.social_account_id` (platform snapshotted). Unique (post, account).
- 2026-10-13: `config/boost.php` sends Claude Code guidelines to `AGENTS.md`. Tokens in encrypted columns. `composer types:check` uses `--memory-limit=1G`.
- 2026-10-13: Storage: private `r2` disk; tests fake it in `TestCase::setUp`. Git: conventional commits straight to `main`.

## Blockers / open questions
- Google Testing mode: refresh tokens expire after 7 days; unaudited uploads are private-only. Google Client Secret and MySQL root password were printed into Claude session logs → rotate.
- `npm run types:check`: 3 pre-existing errors in generated `RedirectController.ts`. Run `wayfinder:generate --with-form`, never without the flag.
- `npm run check:fix` also reformats `docs/*.md` and `.ai/mcp/mcp.json`; use `npx vp check --fix resources/js`.
- XAMPP and Homebrew `mysql`/`mariadb` are installed (stopped) → keep them stopped (port 3306).

## Platform approvals
| Platform | Status | Notes |
|---|---|---|
| YouTube (Google OAuth) | Testing mode | Audit needed for public uploads |
| Instagram + Facebook (Meta) | code ready, app not created | App Review + Business Verification for public use |
| TikTok | sandbox app | Inbox pre-audit; direct post needs Direct Post product + audit |
| Threads | code ready, app not created | Threads use case in a Meta app; App Review for public use |
| LinkedIn | code ready, app not created | Self-serve "Share on LinkedIn"; no refresh tokens (reconnect every 60 days) |
