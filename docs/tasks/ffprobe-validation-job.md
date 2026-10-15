# Task: ffprobe validation job (Phase 2)

Status: **Steps 1–6 done (2026-10-15)**; remaining: full suite + manual `queue:work` check.
Explain in Romanian when presenting it; give the primer first.

## Primer: Jobs and queues (new concept for the owner)

- Running ffprobe in the controller makes the user wait (and may time out). Heavy work goes to the background.
- **Job** = a class describing one unit of work; logic lives in `handle()`. `implements ShouldQueue` → queued, not run inline.
- **`dispatch()`** only pushes the job onto the queue (Redis here) and returns immediately; the controller still answers 201 instantly.
- **Worker** = separate long-running process (`php artisan queue:work`) that pops jobs and runs `handle()`.
- Constructor `public Video $video`: only the model id is serialized; the worker reloads a fresh model from the DB.
- No `$request` / `auth()->user()` inside a job: it runs in another process, without HTTP or a session.
- Exceptions in `handle()` → retries (`$tries`, `$backoff`) → `failed_jobs`. "Video isn't vertical" is **not** an error to retry: it's a valid result (`Rejected`). Only retry transient failures (network, R2 down).
- External programs run via the `Process` facade with an **array** of arguments (safe escaping): `Process::run([...])`, `->successful()`, `->output()`. Tests use `Process::fake()`, like `Storage::fake()`.
- Docs: https://laravel.com/docs/13.x/queues · https://laravel.com/docs/13.x/processes

## Verified facts
- ffprobe 9.0.2 at `/opt/homebrew/bin/ffprobe`; `queue.default` = `redis`.
- ffprobe reads metadata straight from an R2 `temporaryUrl` in ~1.5 s, without downloading the file.
- Owner's real upload is 656×1376 h264 (ratio 0.477), so a strict 9:16 check would reject it → accepted ratio range 0.45–0.6.

## Goal
After the upload is confirmed, a queued job reads the real metadata from R2 with ffprobe, stores it on `Video` and marks the video **Ready** or **Rejected** (with a reason).

## Step 1 — `VideoStatus` enum
`app/Enums/VideoStatus.php`, modelled on `PublicationStatus.php`: `Pending = 'pending'`, `Ready = 'ready'`, `Rejected = 'rejected'`.

## Step 2 — Migration
`php artisan make:migration add_status_to_videos_table --no-interaction`
- `status` string, default `'pending'`, indexed.
- `rejection_reason` string, nullable.
- `down()` drops both columns.

## Step 3 — Model + factory
- Add `status`, `rejection_reason` to `#[Fillable]` on `Video`.
- Cast `'status' => VideoStatus::class`.
- `VideoFactory`: default `'status' => VideoStatus::Ready`, plus a `pending()` state.

## Step 4 — Job `ProbeVideo`
`php artisan make:job ProbeVideo --no-interaction`. Constructor: `public Video $video`.

`handle()`:
1. Temporary read URL for the R2 file (10 min) — `temporaryUrl`, sibling of `temporaryUploadUrl`.
2. Run (each word a separate array element):
   `ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height:stream_side_data=rotation:format=duration -of json <URL>`
3. Process not successful → `Rejected`, reason `Unreadable video file.`
4. `json_decode($result->output(), true)`; read `codec_name`, `width`, `height` from `streams[0]`; `rotation` from `streams[0]['side_data_list'][0]['rotation']` if present; `format.duration` is a **string in seconds** (`"7.960000"`) → store `duration_ms` as int.
5. **Rotation edge case:** phones store landscape pixels + a 90° rotation flag. If `abs(rotation) === 90`, swap width/height before validating.
6. Validate in this order, stop at the first failure:

   | Rule | Condition | Rejection reason |
   |---|---|---|
   | codec | `h264` or `hevc` | `Unsupported codec: {codec}.` |
   | vertical | `height > width` | `Video must be vertical.` |
   | ratio | `width / height` between 0.45 and 0.6 | `Aspect ratio must be close to 9:16.` |
   | duration | 3–180 s | `Duration must be between 3 and 180 seconds.` |

   Limits as constants on the job (`MIN_DURATION_MS`, `MAX_DURATION_MS`, `MIN_ASPECT_RATIO`, …). 180 s = YouTube Shorts limit; refine per platform later.
7. Always save `width`, `height`, `duration_ms`. Valid → `Ready`, `rejection_reason = null`. Invalid → `Rejected` + reason **and delete the file from R2**.

Structure tip: move validation into a private method (e.g. `rejectionReason(...)`) returning `?string` (null = valid).

## Step 5 — Dispatch
In `VideoController@store`, after `create()`: `ProbeVideo::dispatch($newVideo);`. Response stays 201; video starts `pending`.

## Step 6 — Tests
**A. Job** — `tests/Feature/Jobs/ProbeVideoTest.php`, run directly: `(new ProbeVideo($video))->handle();`
Fake ffprobe: `Process::fake(['*ffprobe*' => Process::result(output: json_encode([...]))]);`
- valid vertical (1080×1920, h264, 30 s) → `Ready`, metadata saved;
- rotated 1920×1080 + `rotation: -90` → `Ready`, width 1080 / height 1920;
- horizontal 1920×1080, no rotation → `Rejected` + reason + file deleted (`Storage::fake('r2')`, `put()` first, then `assertMissing($path)`);
- unsupported codec (`vp9`) → `Rejected`;
- too short (2 s) / too long (181 s) → `Rejected` (dataset);
- ffprobe fails (`Process::result(exitCode: 1)`) → `Rejected`, `Unreadable video file.`

**B. Controller** — in `VideoControllerTest` happy path: `Queue::fake([ProbeVideo::class]);` then `Queue::assertPushed(ProbeVideo::class, fn (ProbeVideo $job) => ...)` for the right video.

## Done when
- `php artisan test --compact` (full suite) passes; Pint + `composer types:check` clean.
- Manual: run `php artisan queue:work` in a separate terminal, upload a video from `/videos/create`, DB shows `status = ready` and `duration_ms` filled.

## Decisions taken (owner may override)
1. Rejected files are deleted from R2 immediately.
2. Accepted ratio is 0.45–0.6, not strict 9:16.
