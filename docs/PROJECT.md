# Social Publisher — Project Context

Stable context. Update only when a real decision changes. Living status lives in `docs/PROGRESS.md`.

## Working agreement (Claude's role)
- Claude is a senior Laravel/React mentor and pair programmer. Goals: ship faster **and** the owner learns.
- **Mentor mode (default)**: explain what + why, then say which files/commands/docs to touch. Give hints and small snippets (signatures, key lines), not full implementations. Escalate hint → concrete hint → full solution only if still stuck. Name the Laravel/Inertia/React concept worth learning.
- **Review** ("review"): correctness → conventions → security → readability, grouped by severity, concise.
- **Builder mode**: only when explicitly asked ("write it", "do it", "implement it", "you do this one"). Applies to that task only.
- **Split by area** (agreed 2026-10-14): **React/frontend** → Claude builds by default and explains the result. **Laravel** → owner builds from a well-defined task spec: goal, files to create (with the `make:` command), what each class must do, edge cases, tests to write, and a short primer on any concept the owner hasn't used yet (e.g. Form Requests) *before* they start.
- Claude may always maintain `docs/PROJECT.md`, `docs/PROGRESS.md` and `CLAUDE.md` imports.
- Ask before installing packages, migrating non-local data, or anything destructive. Read-only commands and tests are fine.
- One task at a time; no jumping ahead in the roadmap without agreement.
- Reply in the owner's language; code, comments, commits and docs in English.
- Definition of done: tests pass, Pint + Larastan clean, owner understands it (one short question if a concept was new), `PROGRESS.md` updated.

## Product
Web app for short-form creators: upload one vertical video once, customize metadata per platform, publish now or scheduled to YouTube Shorts, Instagram Reels, Facebook Reels, TikTok (later Snapchat Spotlight, X, Threads, LinkedIn). Built for the owner first, then opened as a product.

**User model**: individual creators only, no teams/tenants. A user owns their social accounts and may connect several per platform. Every query is scoped to the owning user (policies + scoped relationships).

## Stack
- Laravel 13 monolith, PHP 8.5 (target), MySQL, Redis + Horizon.
- Inertia v3 + React 19 + TypeScript + Tailwind v4, shadcn/ui, Wayfinder, Fortify (starter kit).
- Pest, Pint, Larastan. Local env: Laravel Herd; `herd share` for HTTPS OAuth callbacks (stable subdomain).

## Architecture decisions
- **Domain**: `SocialAccount` (user, platform, many per platform) · `Video` (asset + metadata) · `Post` (one publish intent) · `Publication` (per target account: caption/title/privacy, status, external id, error, timestamps). PHP enums for platform and status.
- **Queues**: each platform publish is its own job → one failure never blocks others. Retries with backoff, per-platform rate limiting.
- **OAuth**: Socialite + SocialiteProviders; token storage/refresh package (e.g. `pr4w/laravel-social-tokens`). TikTok rotates refresh tokens; Meta publishes per IG account / per Page.
- **Publishing**: `PublisherInterface`, one implementation per platform, swappable for a unified third-party API later. YouTube via `google/apiclient` (resumable). Meta + TikTok via `Http` client.
- **Storage**: Cloudflare R2 (S3). Browser uploads directly via presigned URLs (e.g. Uppy), never through PHP. Pull-based platforms get temporary signed URLs.
- **Video processing**: FFmpeg/ffprobe (e.g. `pbmedia/laravel-ffmpeg`): metadata, validation (9:16, duration, size, codec), transcoding to a universally accepted format.
- **Status**: "uploaded" ≠ "published". Track per-platform status; UI polls first, Reverb later.

## Platform constraints (verify against current official docs before each integration)
- **YouTube**: Data API v3 `videos.insert` (no Shorts endpoint). Unaudited projects → uploads locked private. `youtube.upload` is a sensitive scope (OAuth verification; Testing mode refresh tokens expire after 7 days). Limited daily upload quota.
- **Instagram**: professional accounts only. `media_type=REELS`, container → upload → publish. Rolling 24h publish cap per account. Dev mode works for accounts with an app role; public use needs App Review + Business Verification.
- **Facebook Reels**: separate, lower daily API cap.
- **TikTok**: Content Posting API. Pre-audit direct posts are SELF_ONLY; draft/inbox upload works unaudited. Audit requires UX: creator nickname, privacy options from `creator_info`, commercial content disclosure, music usage consent. HTTPS redirect URIs. Daily per-creator cap.

## Roadmap
0. Tooling — versions, MySQL + Redis under Herd, Pest/Pint/Larastan, git, docs.
1. Domain model — models, enums, policies, factories, tests.
2. Upload pipeline — R2, presigned uploads, React uploader, ffprobe validation job.
3. Account connections — Socialite flows, token refresh, connected-accounts page (YouTube + Meta first).
4. Publishing engine — `PublisherInterface`, jobs, retries, rate limits, status polling.
5. Integrations — YouTube → Instagram → Facebook → TikTok (draft first) → others.
6. UI — composer (per-platform overrides, TikTok fields), posts dashboard.
7. Scheduling — `publish_at`, scheduler, timezones.
8. Go-public — HTTPS domain, privacy/ToS, review demo flow, audits (start YouTube/TikTok audits early).

## Conventions
- `AGENTS.md` (Laravel Boost) and `.claude/skills/*` are the primary source for conventions; follow them.
- Larastan runs at level 7 (`phpstan.neon`). `composer test` = Pint check + Larastan + Pest.
