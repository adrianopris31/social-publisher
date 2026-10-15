# Progress

Budget: max 60 lines / ~500 words. Compact older entries instead of appending forever.

## Current phase & task
Phase 2 – Upload pipeline. ffprobe validation job built + tested (Steps 1–6 of `docs/tasks/ffprobe-validation-job.md`); done (committed, manual `queue:work` check OK). Retries + `failed()` and storage file cleanup done, uncommitted. **Next: commit, then Phase 3.**

## Done
- 2026-10-15: File cleanup: `Video` `deleted` event deletes its file; `User` `deleting` event deletes `videos/{id}/` (DB cascade skips model events); `Video` is `Prunable` (Rejected > 7 days), `model:prune` scheduled daily. `TestCase::setUp` fakes `r2` globally so tests never hit the real bucket.
- 2026-10-15: `ProbeVideo` job: `VideoStatus` enum (Pending/Ready/Rejected) + `status`/`rejection_reason` columns, dispatched from `VideoController@store`; ffprobe via `Process` on an R2 `temporaryUrl`, rotation swap, codec/vertical/ratio 0.45–0.6/3–180 s rules, rejected files deleted. Tests fake ffprobe with `Process::fake(['*ffprobe*' => ...])` (array commands are quoted, `'ffprobe *'` doesn't match). Controller tests need `Queue::fake([ProbeVideo::class])` (test queue is `sync`). ffprobe failure throws → `#[Tries(3)]`/`#[Backoff([10, 60])]` → `failed()` marks Rejected (generic reason, file kept); `#[DeleteWhenMissingModels]`.
- 2026-10-13: Phase 0: git on `main`; Herd site `social-publisher.test` on PHP 8.5 (`~/.zshrc` puts Herd first); MySQL 9 with least-privilege app user; Redis (queue + cache); FFmpeg 9; docs + `CLAUDE.md` imports.
- 2026-10-14: Uploader page `videos/create`: `useHttp` for presign + confirm, raw XHR PUT to R2 with progress (no app headers cross-origin). Verified in browser.
- 2026-10-14: Upload backend: `POST videos/uploads` (presigned PUT, server-built key `videos/{user}/{uuid}.{ext}`) + `POST videos` (anchored key regex, unique path, size/mime read from R2, >500 MB deleted). Too-large branch untested.
- 2026-10-14: R2 bucket CORS verified: preflight on a presigned PUT → 204 for `http(s)://social-publisher.test`, 403 for other origins. ETag exposure to confirm on first real browser upload.
- 2026-10-13: Phase 1: `SocialAccount`, `Video`, `Post`, `Publication` + `Platform`/`PublicationStatus` enums, owner-only policies (SocialAccount/Video/Post), factories with consistent ownership, 70 tests green.

## In progress
- Nothing; Phase 2 wraps up with this commit.

## Next
1. Phase 3: account connections (YouTube + Meta first).

## Decisions
- 2026-10-13: Free Herd → MySQL from the official installer, Redis from Homebrew. Horizon deferred to Phase 4.
- 2026-10-13: Domain: User→Video, User→Post; Post→Video (one video per post, video reusable); Post→Publication→SocialAccount. Per-platform text on Publication (default caption on Post). Statuses Pending/Uploading/Processing/Published/Failed; scheduling via `Post.publish_at`, no Scheduled status. Publication authorized via its Post (no own policy).
- 2026-10-13: FKs: deleting a video cascades its posts (restrict would break user-delete cascades); deleting a SocialAccount nulls `publications.social_account_id`, `platform` is snapshotted on Publication to keep history. Unique (post, account).
- 2026-10-13: `config/boost.php` sends Claude Code guidelines to `AGENTS.md`, so `boost:update` keeps `CLAUDE.md` imports-only.
- 2026-10-13: Tokens in encrypted columns on `social_accounts` (no token package yet; revisit in Phase 3). `composer types:check` uses `--memory-limit=1G`.
- 2026-10-13: Storage: dedicated `r2` disk (`R2_*` env, `throw => true`); bucket `social-publisher-local` is private. Tests use `Storage::fake('r2')`. R2 (not local disk) because files must outlive scheduling/retries, be shared by web + queue workers, and be pullable by IG/TikTok via temporary URLs; delete files once all publications finish.
- 2026-10-13: Git: conventional commits, commit straight to `main` while solo.
- 2026-10-13: Stack and architecture as in `docs/PROJECT.md` (R2 + presigned uploads, one job per platform publish, `PublisherInterface`).

## Blockers / open questions
- `npm run types:check`: 3 pre-existing errors in generated `RedirectController.ts` (Wayfinder vs `query` method from `Route::redirect` in `settings.php`).
- Run `wayfinder:generate --with-form` (Vite uses `formVariants: true`), never without the flag.
- Presigned uploads never confirmed (no `Video` row) stay in R2 until the user is deleted → consider an R2 lifecycle rule.
- XAMPP and Homebrew `mysql`/`mariadb` are also installed (stopped) → keep them stopped to avoid port 3306 clashes.
- MySQL root password was echoed into a Claude session log → consider rotating it.

## Platform approvals
| Platform | Status | Notes |
|---|---|---|
| YouTube (Google OAuth) | not started | `youtube.upload` sensitive scope; audit for public uploads |
| Instagram (Meta) | not started | App Review + Business Verification for public use |
| Facebook Pages (Meta) | not started | Same Meta app as Instagram |
| TikTok | not started | Draft/inbox pre-audit; audit for direct post |
