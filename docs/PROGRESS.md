# Progress

Budget: max 60 lines / ~500 words. Compact older entries instead of appending forever.

## Current phase & task
Phase 2 – Upload pipeline. `r2` disk works (round-trip verified); next: bucket CORS.

## Done
- 2026-10-13: Phase 0: git on `main`; Herd site `social-publisher.test` on PHP 8.5 (`~/.zshrc` puts Herd first); MySQL 9 with least-privilege app user; Redis (queue + cache); FFmpeg 9; docs + `CLAUDE.md` imports.
- 2026-10-13: Phase 1: `SocialAccount`, `Video`, `Post`, `Publication` + `Platform`/`PublicationStatus` enums, owner-only policies (SocialAccount/Video/Post), factories with consistent ownership, 70 tests green.

## In progress
- Nothing.

## Next
1. Phase 2: bucket CORS for browser PUT uploads from `http://social-publisher.test`.
2. Phase 2: presigned upload endpoint + React uploader with progress.
3. Phase 2: ffprobe validation job (9:16, duration, size, codec); storage file cleanup on delete.

## Decisions
- 2026-10-13: Free Herd → MySQL from the official installer, Redis from Homebrew. Horizon deferred to Phase 4.
- 2026-10-13: Domain: User→Video, User→Post; Post→Video (one video per post, video reusable); Post→Publication→SocialAccount. Per-platform text on Publication (default caption on Post). Statuses Pending/Uploading/Processing/Published/Failed; scheduling via `Post.publish_at`, no Scheduled status. Publication authorized via its Post (no own policy).
- 2026-10-13: FKs: deleting a video cascades its posts (restrict would break user-delete cascades); deleting a SocialAccount nulls `publications.social_account_id`, `platform` is snapshotted on Publication to keep history. Unique (post, account).
- 2026-10-13: `config/boost.php` sends Claude Code guidelines to `AGENTS.md`, so `boost:update` keeps `CLAUDE.md` imports-only.
- 2026-10-13: Tokens in encrypted columns on `social_accounts` (no token package yet; revisit in Phase 3). `composer types:check` uses `--memory-limit=1G`.
- 2026-10-13: Storage: dedicated `r2` disk (`R2_*` env, `throw => true`); bucket `social-publisher-local` is private. Tests use `Storage::fake('r2')`.
- 2026-10-13: Git: conventional commits, commit straight to `main` while solo.
- 2026-10-13: Stack and architecture as in `docs/PROJECT.md` (R2 + presigned uploads, one job per platform publish, `PublisherInterface`).

## Blockers / open questions
- Deleting a user/video cascades DB rows but not files in storage → handle file cleanup in Phase 2.
- XAMPP and Homebrew `mysql`/`mariadb` are also installed (stopped) → keep them stopped to avoid port 3306 clashes.
- MySQL root password was echoed into a Claude session log → consider rotating it.

## Platform approvals
| Platform | Status | Notes |
|---|---|---|
| YouTube (Google OAuth) | not started | `youtube.upload` sensitive scope; audit for public uploads |
| Instagram (Meta) | not started | App Review + Business Verification for public use |
| Facebook Pages (Meta) | not started | Same Meta app as Instagram |
| TikTok | not started | Draft/inbox pre-audit; audit for direct post |
