# Progress

Budget: max 60 lines / ~500 words. Compact older entries instead of appending forever.

## Current phase & task
Phase 1 – Domain model. Next: Task 2 – `Video`, `Post`, `Publication` + `PublicationStatus`.

## Done
- 2026-10-13: Setup audit; docs + `CLAUDE.md` imports. Git on `main` (initial commit). Site linked as `social-publisher.test`, isolated to PHP 8.5, `composer.json` php `^8.5`; 40 tests pass on 8.5. Lock synced; `~/.zshrc` puts Herd PHP/Composer first.
- 2026-10-13: MySQL 9 (`social_publisher` DB, utf8mb4, least-privilege app user), migrated. Redis via Homebrew; queue + cache on redis. `.env.example` updated. FFmpeg 9 via Homebrew.
- 2026-10-13: Phase 1 Task 1: `SocialAccount` model, `Platform` enum, owner-only policy, factory (`forPlatform`, `expired`), tests (encryption, hidden tokens, unique index, cascade).

## In progress
- Task 2: `Video` done and committed. Next: `Post` + `PublicationStatus`, then `Publication`.

## Next
1. Phase 1: `Video`, `Post`, `Publication` models + relationships, scoped to the owner.

## Decisions
- 2026-10-13: Free Herd → MySQL from the official installer, Redis from Homebrew. Horizon deferred to Phase 4.
- 2026-10-13: Domain: User→Video, User→Post; Post→Video (one video per post, video reusable); Post→Publication→SocialAccount. Per-platform text on Publication (default caption on Post). Statuses Pending/Uploading/Processing/Published/Failed; scheduling via `Post.publish_at`, no Scheduled status. Publication authorized via its Post (no own policy).
- 2026-10-13: Tokens in encrypted columns on `social_accounts` (no token package yet; revisit in Phase 3). `composer types:check` uses `--memory-limit=1G`.
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
