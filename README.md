# Social Publisher

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="public/logo-horizontal-dark.svg">
  <img src="public/logo-horizontal-light.svg" alt="Social Publisher" height="48">
</picture>

Upload one vertical video once, customise the caption per platform, and publish it now or on a schedule to **YouTube Shorts, Instagram Reels, Facebook Reels, TikTok, Threads and LinkedIn**.

Built for individual creators: every user owns their own videos, posts and connected accounts, and can connect several accounts per platform.

---

## Contents

1. [What it does](#what-it-does)
2. [Tech stack](#tech-stack)
3. [How a video travels through the app](#how-a-video-travels-through-the-app)
4. [Platform details](#platform-details)
5. [Local setup](#local-setup)
6. [Configuration reference](#configuration-reference)
7. [Connecting each platform (developer apps)](#connecting-each-platform-developer-apps)
8. [Going public: reviews, audits and verification](#going-public-reviews-audits-and-verification)
9. [Testing and code quality](#testing-and-code-quality)
10. [Operations and troubleshooting](#operations-and-troubleshooting)
11. [Project layout](#project-layout)
12. [Remaining work](#remaining-work)

---

## What it does

| Area | Features |
|---|---|
| **Video library** | Direct browser → Cloudflare R2 upload (presigned URL, never through PHP), max 500 MB. Automatic ffprobe check (codec, vertical 9:16, 3–180 s). Automatic transcoding to H.264/AAC MP4 when needed. Videos are reusable across posts. |
| **Connected accounts** | OAuth for YouTube (Google), TikTok, Facebook Pages + Instagram professional accounts (one Facebook Login), Threads, LinkedIn. Tokens are stored encrypted and refreshed automatically. Expired or revoked access shows a **Reconnect** button. Disconnecting revokes the token where the platform supports it. |
| **Composer** | Pick a ready video, pick target accounts, write one caption plus per-platform overrides (YouTube title, per-platform length limits). TikTok inbox or direct post (with the full audit UX). Publish now or schedule with a timezone. |
| **Limits** | Per-account daily caps (rolling 24 h) checked in the composer, so posts are refused up front instead of failing on the platform. Per-platform duration and caption limits. |
| **Publishing** | One queued job per target account, so one failure never blocks the others. Retries with backoff, status polling while the platform processes the video, clear error messages, manual **Retry**. |
| **Posts** | List with per-platform status badges and links to the live post, live polling while anything is in progress, edit/reschedule/delete until the post is dispatched. |
| **Dashboard** | Counts (videos, scheduled, published, failed) and recent posts. |
| **Legal pages** | `/privacy` and `/terms` (needed by every platform review). |
| **Ops** | Laravel Horizon at `/horizon` (restricted to `HORIZON_ADMIN_EMAILS`), scheduler for due posts. |

---

## Tech stack

- **Backend:** Laravel 13, PHP 8.5, MySQL, Redis, Laravel Horizon, Fortify (auth, 2FA), Socialite + SocialiteProviders.
- **Frontend:** Inertia v3, React 19, TypeScript, Tailwind v4, shadcn/ui, Wayfinder (typed routes).
- **Storage:** Cloudflare R2 (S3 API), private bucket.
- **Video:** FFmpeg / ffprobe binaries.
- **Quality:** Pest, Pint, Larastan (level 7).
- **Local:** Laravel Herd (`https://social-publisher.test`), ngrok for public HTTPS OAuth callbacks.

All platform APIs are called with Laravel's `Http` client. There are no vendor SDKs.

---

## How a video travels through the app

### 1. Upload

```
Browser ──POST /videos/uploads──▶ Laravel  (validates name/type/size, returns a presigned R2 PUT URL)
Browser ──PUT file─────────────▶ R2       (direct, bypasses PHP)
Browser ──POST /videos─────────▶ Laravel  (creates Video, status = pending, dispatches ProbeVideo)
```

### 2. Validation and transcoding (queue `default` → `uploads`)

1. **`ProbeVideo`** runs `ffprobe` on a temporary signed URL and stores width, height, duration and codec.
2. The video is **rejected** (with a reason, and the file is deleted) when it is not H.264/HEVC, not vertical, not close to 9:16, or not 3–180 s long.
3. If the container is not MP4, the codec is not H.264, the audio is not AAC, or the frame rate is above 60 fps, **`TranscodeVideo`** re-encodes it (libx264 + AAC, `faststart`, max 60 fps) and replaces the file.
4. The video becomes **ready**. Only ready videos can be posted.

### 3. Composing a post

`StorePostRequest` checks, per target account:
- the account belongs to the user and does not need reconnection;
- the video duration is within that platform's limit;
- the caption is within that platform's limit;
- the daily cap for that account is not exceeded around the chosen publish time;
- for TikTok direct post: a privacy level was chosen, and the branded content rules are respected.

This creates one `Post` (the publish intent) and one `Publication` per target account. Each publication stores a snapshot of its effective caption and settings.

### 4. Dispatch

- **Publish now:** `DispatchPost` runs immediately.
- **Scheduled:** `posts:publish-due` runs every minute (scheduler) and dispatches due posts.
- `DispatchPost` atomically claims `posts.dispatched_at`, so a post is never sent twice, even if the scheduler and a manual action race.

### 5. Publishing (queue `uploads`)

For each publication, **`PublishPublication`**:
1. checks that the account still exists and the video is ready and still in storage;
2. refreshes the access token if needed (`EnsureFreshAccessToken`, under a per-account lock, because TikTok rotates refresh tokens);
3. calls the platform publisher (`app/Publishing/Publishers/*`), which uploads the file or hands the platform a temporary URL;
4. records the result:
   - **published** → stores the external id and URL;
   - **processing** → stores the platform's processing id and starts **`CheckPublicationStatus`**, which polls every 20 s for up to 2 h.

Retries: transient errors (5xx, rate limits) back off and retry. Permanent errors (`PublishingFailedException`) fail immediately with a readable message. Revoked or expired tokens mark the account **needs reconnection**.

### Statuses

| Model | Statuses |
|---|---|
| Video | `pending` → `ready` / `rejected` |
| Publication | `pending` → `uploading` → `processing` → `published` / `failed` |

"Uploaded" is not "published": each platform is tracked separately.

---

## Platform details

| Platform | Connect | How it publishes | Video | Caption | Daily cap (default) | Token |
|---|---|---|---|---|---|---|
| **YouTube Shorts** | Google OAuth (`youtube.upload`, `youtube.readonly`) | Resumable upload to `videos.insert`, then poll `uploadStatus` | ≤ 180 s | 5000 (+ title) | 6 (project quota: ~1600 units/upload of 10,000/day) | Refresh token |
| **Instagram Reels** | Facebook Login → linked IG professional account | `REELS` container from URL → poll → `media_publish` | ≤ 900 s | 2200 | 50 | Page token, no expiry |
| **Facebook Reels** | Facebook Login → Page | `video_reels` start → upload by `file_url` → finish → poll | ≤ 90 s | 5000 | 30 | Page token, no expiry |
| **TikTok** | TikTok Login Kit (`user.info.basic`, `video.upload`, + `video.publish` for direct) | `FILE_UPLOAD` in 10 MB chunks; **inbox** (draft) or **direct** post; poll status | ≤ 600 s (or the creator's own max) | 2200 (direct only) | 5 | Rotating refresh token |
| **Threads** | Threads OAuth (`threads_basic`, `threads_content_publish`) | Video container from URL → poll → publish | ≤ 300 s | 500 | 200 | Long-lived, refreshed |
| **LinkedIn** | OpenID Connect (`openid`, `profile`, `w_member_social`) | Videos API initialize → PUT parts → finalize → poll → Posts API | ≤ 600 s | 3000 | 50 | 60 days, **no refresh** → reconnect |

### TikTok: inbox vs direct post

- **Inbox (default):** the video lands in the creator's TikTok inbox as a notification/draft. The creator opens the TikTok app, edits, writes the caption and taps **Post**. Our caption is **not** sent in this mode. Works before the audit.
- **Direct (`TIKTOK_DIRECT_POST=true`):** posts straight to the profile with our caption, privacy and interaction settings. The composer shows the TikTok-required UX: creator nickname, a privacy choice with no default, comment/duet/stitch toggles (greyed out when the creator disabled them), commercial content disclosure and the Music Usage / Branded Content consent. **Before the audit, only `SELF_ONLY` posts are allowed, and only to private accounts.**

---

## Local setup

### Requirements

- Laravel Herd (PHP 8.5), MySQL 9, Redis.
- FFmpeg and ffprobe on `PATH` (`brew install ffmpeg`).
- Node 20+.
- A Cloudflare R2 bucket and an API token with Object Read & Write.
- ngrok (or `herd share`) for HTTPS OAuth callbacks.

### Steps

```bash
composer setup                  # install, .env, key, migrate, npm install, build
php artisan wayfinder:generate --with-form
composer dev                    # Vite + Horizon + scheduler + logs
```

The site is served by Herd at `https://social-publisher.test`. Do not run `php artisan serve`.

### R2 bucket

1. Create a private bucket and an S3 API token (Object Read & Write), then fill in `R2_*`.
2. **CORS policy** (bucket → Settings): the browser uploads directly, so every origin you use must be allowed:

```json
[
  {
    "AllowedOrigins": [
      "https://social-publisher.test",
      "https://<your-ngrok-domain>"
    ],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["content-type"],
    "MaxAgeSeconds": 3600
  }
]
```

3. Do **not** add lifecycle rules that delete objects in `videos/`. Videos are kept and reused after publishing.

### Public HTTPS for OAuth

Most platforms require HTTPS redirect URIs:

```bash
ngrok http --url=<your-domain>.ngrok-free.dev https://social-publisher.test --host-header=social-publisher.test
```

Set `APP_URL` and every `*_REDIRECT_URI` to the ngrok domain, and register the same URIs in each developer app. `vite.config.ts` already allows `*.ngrok-free.(app|dev)` for CORS.

---

## Configuration reference

| Key | Purpose |
|---|---|
| `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` | Cloudflare R2 storage (`r2` disk) |
| `QUEUE_CONNECTION=redis`, `REDIS_QUEUE_RETRY_AFTER=960` | Must exceed the longest job timeout (900 s upload) |
| `HORIZON_ADMIN_EMAILS` | Comma-separated emails allowed to open `/horizon` outside `local` |
| `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI` | YouTube |
| `TIKTOK_CLIENT_ID/SECRET/REDIRECT_URI` | TikTok |
| `TIKTOK_DIRECT_POST` | `true` asks for `video.publish` and enables direct post in the composer |
| `FACEBOOK_CLIENT_ID/SECRET/REDIRECT_URI`, `FACEBOOK_GRAPH_VERSION` | Facebook Pages + Instagram |
| `THREADS_CLIENT_ID/SECRET/REDIRECT_URI` | Threads |
| `LINKEDIN_CLIENT_ID/SECRET/REDIRECT_URI`, `LINKEDIN_API_VERSION` | LinkedIn (`YYYYMM` version header) |
| `PUBLISHING_LIMIT_<PLATFORM>` | Override the daily caps in `config/publishing.php` |

After changing `.env`, run `php artisan config:clear` and restart `composer dev`. Horizon workers keep old config and code in memory.

---

## Connecting each platform (developer apps)

Redirect URI pattern: `https://<public-domain>/auth/<provider>/callback`.

### YouTube (Google Cloud)
1. Create a project and enable **YouTube Data API v3**.
2. OAuth consent screen: External, add the scopes `youtube.upload` and `youtube.readonly`, and add yourself as a **test user**.
3. Credentials → OAuth client (Web), redirect `/auth/google/callback`.
4. Testing mode: refresh tokens expire after **7 days** and uploads are forced to **private**.

### TikTok (developers.tiktok.com)
1. Create an app and add the products **Login Kit** and **Content Posting API**.
2. Scopes: `user.info.basic`, `video.upload`. For direct post, enable **Direct Post** and add `video.publish`.
3. Redirect URI `/auth/tiktok/callback` (HTTPS). Verify the domain/URL prefix if TikTok asks.
4. Sandbox: add your TikTok account as a **target user**. For direct post tests, make that account **private**.
5. After changing scopes, **disconnect and reconnect** the account in the app.

### Facebook + Instagram (developers.facebook.com)
1. Create a **Business** app and add **Facebook Login for Business** (or Facebook Login) with redirect `/auth/facebook/callback`.
2. Permissions: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `business_management`, `instagram_basic`, `instagram_content_publish`.
3. The Instagram account must be **Professional** (Business or Creator) and **linked to a Facebook Page** you manage.
4. In development mode, only users with a role on the app (admin/developer/tester) can connect.

### Threads (Meta app with the Threads use case)
1. Add the **Access the Threads API** use case, with permissions `threads_basic` and `threads_content_publish`.
2. Redirect `/auth/threads/callback`. Add your Threads profile as a **Threads tester** and accept the invite in Threads (Settings → Account → Website permissions).

### LinkedIn (linkedin.com/developers)
1. Create an app linked to a LinkedIn Page (required by LinkedIn).
2. Products: **Sign In with LinkedIn using OpenID Connect** and **Share on LinkedIn** (self-serve).
3. Auth → redirect `/auth/linkedin/callback`.
4. There are no refresh tokens: users reconnect every 60 days. The app shows **Reconnect** when the token expires.

---

## Going public: reviews, audits and verification

Everything works for **you** (as app admin/tester) without reviews. To let **other people** use the app, or to publish **publicly** on some platforms, you need the steps below. Requirements change often, so check each platform's current docs before submitting.

### Common prerequisites (all platforms)
- [ ] Production **HTTPS domain** you own (no ngrok), with `APP_URL` and every redirect URI updated in all developer apps.
- [ ] Public **Privacy Policy** (`/privacy`) and **Terms** (`/terms`) with a real contact email (replace `CONTACT_EMAIL` in `resources/js/components/legal-page.tsx`), describing what data is read, stored and deleted, and how to request deletion.
- [ ] App icon, name and description consistent across platforms.
- [ ] A **demo screen recording** for each platform: log in → connect account (showing the consent screen) → upload → compose → publish → result on the platform.
- [ ] A test account for reviewers if the platform asks for one.
- [ ] Production: queues on Horizon under a process manager (Supervisor), a scheduler cron (`* * * * * php artisan schedule:run`), FFmpeg installed, R2 production bucket + CORS for the production domain, mail configured.

### YouTube / Google
| Step | What | How to verify |
|---|---|---|
| 1 | **OAuth app verification** (sensitive scope `youtube.upload`): verified domain in Search Console, privacy policy, demo video, scope justification | Google Cloud → OAuth consent screen shows "Verified"; the "unverified app" warning disappears |
| 2 | Switch the publishing status from Testing to **In production** | Refresh tokens no longer expire after 7 days |
| 3 | **YouTube API Services audit** (compliance audit form) so uploads are no longer locked to private | A test upload with privacy *public* stays public. Before the audit it is forced to private |
| 4 | Optional quota increase (the default 10,000 units/day ≈ 6 uploads) | Google Cloud → APIs → YouTube Data API → Quotas |

### TikTok
| Step | What | How to verify |
|---|---|---|
| 1 | Submit the app for **review** (Login Kit + Content Posting API) with the demo video, privacy policy and terms | Developer portal → app status *Live* (production keys available) |
| 2 | **Direct Post audit**: TikTok checks the composer UX (nickname, privacy without a default, toggles, disclosure, consent) in the demo video. It is all implemented | After approval, `creator_info` returns more privacy options than `SELF_ONLY` and public posts succeed |
| 3 | Without the audit, use inbox mode (`TIKTOK_DIRECT_POST=false`) | The draft arrives in the TikTok inbox |

### Instagram + Facebook (Meta)
| Step | What | How to verify |
|---|---|---|
| 1 | **Business Verification** (Meta Business Manager: company documents, domain) | Business settings → Security Center → "Verified" |
| 2 | **App Review** for every permission listed above, each with a screencast and explanation | App Dashboard → App Review → permissions show "Advanced access" |
| 3 | Switch the app to **Live** mode | Users without an app role can connect |

### Threads
| Step | What | How to verify |
|---|---|---|
| 1 | Business Verification (same Meta business) | as above |
| 2 | App Review for `threads_basic` + `threads_content_publish` | The permissions have advanced access; non-tester accounts can connect |

### LinkedIn
| Step | What | How to verify |
|---|---|---|
| 1 | "Share on LinkedIn" and OpenID are self-serve, so any LinkedIn member can connect | Connect a non-admin account |
| 2 | Posting to **company Pages** would need the Community Management API (review); not implemented | — |

### End-to-end verification checklist (per platform)
1. Connect the account: it appears on **Social accounts** without a "needs reconnection" badge.
2. Upload a valid 9:16 video: the status goes `pending` → `ready` in the library.
3. Compose a post to that account, **Publish now**: on **Posts**, the badge goes `uploading` → `processing` → `published`.
4. Open the link from the badge (where the platform returns one) and check the caption and privacy.
5. Schedule a post 2–3 minutes ahead and check that `posts:publish-due` sends it on time.
6. Watch `/horizon` for failed jobs; error messages also appear on the post card.

---

## Testing and code quality

```bash
php artisan test --compact               # Pest suite (~270 tests)
composer test                            # Pint check + Larastan + Pest
vendor/bin/pint --dirty --format agent   # format changed PHP
npm run types:check                      # TypeScript (3 known errors in generated RedirectController.ts)
npx vp check --fix resources/js          # JS/TS lint+format (do not use npm run check:fix: it touches docs)
npm run build
```

- Platform APIs are faked with `Http::fake()` and OAuth with `Socialite::fake()`. R2 is faked in `TestCase::setUp`.
- Always regenerate routes with `php artisan wayfinder:generate --with-form`.

---

## Operations and troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| "The upload failed. Check your connection…" | R2 CORS does not allow the origin you browse from (e.g. the ngrok domain). Add it to the bucket CORS policy |
| Publication fails "video file is missing from storage" | The object was deleted from R2 (lifecycle rule, manual purge). Upload again and create a new post |
| Jobs never run | `composer dev` not running (Horizon + scheduler). Check `/horizon` |
| Code/config change has no effect on jobs | Restart Horizon (`php artisan horizon:terminate` or restart `composer dev`) |
| Account shows **Reconnect** | Token revoked/expired (LinkedIn every 60 days, Google Testing every 7 days). Reconnect |
| TikTok "too many drafts waiting" | More than 5 pending inbox shares. Post or delete them in the TikTok app |
| TikTok direct post rejected for public account | Unaudited app: make the TikTok account private and use "Only me" |
| YouTube video private even if public chosen | Unaudited Google project. Complete the YouTube API audit |
| YouTube `quotaExceeded` | Daily project quota used up. Wait until midnight Pacific time or request more quota |
| Instagram account not listed after Facebook Login | The IG account is not Professional or not linked to a Page you selected in the login dialog |

---

## Project layout

```
app/
  Actions/            DispatchPost, EnsureFreshAccessToken, RevokeSocialAccountToken, per-platform token refreshers
  Console/Commands/   PublishDuePosts (posts:publish-due)
  Enums/              Platform (limits, labels), statuses
  Exceptions/         PublishingFailedException, SocialAccountNeedsReconnectionException
  Http/Controllers/   *ConnectionController (OAuth), Post, Video, VideoUpload, PublicationRetry, Dashboard
  Http/Requests/      StorePostRequest, UpdatePostRequest, video upload requests
  Http/Resources/     PostResource, PublicationResource
  Jobs/               ProbeVideo, TranscodeVideo, PublishPublication, CheckPublicationStatus
  Models/             User, Video, Post, Publication, SocialAccount
  Publishing/         Publisher interface, PublishResult, PublisherResolver, PublishingLimits, MetaGraph, Publishers/*
config/publishing.php daily caps
resources/js/pages/   dashboard, videos/*, posts/*, social-accounts, legal/*, welcome
routes/console.php    scheduler (publish-due every minute, prune daily, Horizon snapshots)
```

---

## Remaining work

**Owner / platform side**
1. Run a real end-to-end publish on every platform (YouTube and TikTok inbox are done; next: TikTok direct, Meta, Threads, LinkedIn).
2. Create the Meta app (Facebook + Instagram), the Threads use case and the LinkedIn app, then fill in the credentials.
3. Add the ngrok/production origins to the R2 CORS policy. Make sure no lifecycle rule deletes `videos/`.
4. Rotate the Google Client Secret and the MySQL root password (they were exposed in logs).
5. Replace `CONTACT_EMAIL` and review the legal texts.
6. Production domain and hosting, then the reviews and audits above (start the YouTube and TikTok audits early, they take the longest).
7. Decide on **X** (paid API tier) and **Snapchat Spotlight** (partner allowlist).

**Code (optional improvements)**
- Show TikTok inbox uploads as "Sent to TikTok inbox: finish in the app" instead of "Published".
- Per-platform probe rules (the probe currently uses the YouTube 180 s maximum for every platform).
- Reverb (WebSockets) instead of polling for live status.
- R2 lifecycle rule only for unconfirmed uploads (`videos/` objects without a Video row).
- Posting to LinkedIn company Pages, YouTube playlists and thumbnails, first comment, analytics.
