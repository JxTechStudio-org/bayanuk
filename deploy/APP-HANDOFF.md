# Before production: application changes (for the team)

These are changes to the application code. The production infrastructure (Compose override,
Jenkins pipeline, host nginx, backups) is separate and doesn't depend on them. Each item can be one card.

## Rule: database migrations (applies from the first production deploy)

Production runs `php artisan migrate --force` on every deploy, so every migration already merged to
`main` has run on the production database. From now on:

- **Add a new migration** for every schema change (new table, column, index, rename, drop).
- **Never edit, rename, or delete a migration** that is already on `main`. Laravel will not run it
  again, so the change never reaches production, and production and local databases drift apart.
- Keep migrations safe for the previous release: if a deploy fails, the pipeline rolls back the
  images but not the database. Prefer adding over renaming or dropping in the same release.
- Write a working `down()` method anyway, for local work and manual fixes.
- Before you open a PR, check locally: `php artisan migrate:fresh` and `php artisan migrate:rollback`
  both work.

If an old migration has to change anyway, ask Jana first: it needs a planned production fix.

## 1. Build the API routes in the integration contract

Only these exist now: `GET /api/v1/health`, `POST /api/v1/auth/google/redirect`, and Laravel's
default `GET /api/user`. `routes/api.php` imports `GuestTokenController` and
`DocumentController`, but neither class exists yet. Production builds of the frontend call the real API
(the mock API is development only), so every page after the landing page gets 404 until these exist:

| Contract | Endpoint(s) | Feature that needs it |
| --- | --- | --- |
| §5 | `POST /guest-token` | anything a guest does (called first) |
| §10 | magic link: request + verify | email sign-in |
| §11 | `GET /auth/google/callback` | Google sign-in (redirect exists, callback doesn't) |
| — | current user, sign-out | account menu |
| §6 | upload, status polling | upload and processing pages |
| §13 | document result | result page |
| §8, §14 | chat history, ask a question | chat |
| §7 | documents list (paginated) | My documents |
| §9 | document file | document viewer (see item 4) |
| — | feedback, delete document | result page actions |

Also remove the default `GET /api/user` route if the contract doesn't use it.

## 2. Turn on Sanctum SPA (cookie) authentication

`bootstrap/app.php` doesn't call `$middleware->statefulApi()`. Without it, requests to `/api/*`
don't start a session, so cookie sign-in won't work even after the routes exist (contract §2).

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->statefulApi();
})
```

Production sets `SANCTUM_STATEFUL_DOMAINS=bayanuk.jxtechstudio.com` and
`SESSION_SECURE_COOKIE=true`. The frontend and API share one origin, so no CORS change is needed.

## 3. PDF only in the backend config

`config/bayanuk.php` has `'allowed_mimes' => ['pdf', 'docx']`. The frontend accepts PDF only, as
decided ("Accept PDF files only, as the manager decided"). Change it to `['pdf']`, and make
the upload validation use this config, so a direct API call can't upload a DOCX.

## 4. Serve the document file through the API, not a RustFS signed URL (contract §9 change)

**Needs a decision with Jana before it's built.** Contract §9 says the API returns a signed
storage URL. RustFS runs only on the Docker network (`http://rustfs:9000`), so a signed URL points
to a host the browser can't reach. Exposing RustFS publicly would need its own domain, TLS, and
CORS, and would put the bucket on the internet.

Simplest secure option for the MVP: `GET /api/v1/documents/{id}/file` checks that the
user (or guest token) owns the document and streams the PDF from storage:

```php
return Storage::disk('s3')->response($document->storage_path, 'document.pdf', [
    'Content-Type' => 'application/pdf',
    'Cache-Control' => 'private, no-store',
    'X-Content-Type-Options' => 'nosniff',
]);
```

The frontend viewer then loads that URL directly (same origin, the session cookie is sent) instead of
asking for `{ url, expires_at }`. Contract §9 point 2 ("never from the API domain") changes,
so Danah and Jumanah need to approve the change. Files are at most 10 MB, so PHP-FPM can stream them.

## 5. Production defaults in `.env.example`

`.env.example` is for local development and has `APP_DEBUG=true`, `LOG_LEVEL=debug`, and
`SESSION_DOMAIN=localhost`. That's fine locally, but nobody should copy it to a server. Production uses
`deploy/bayanuk.env.example` (`APP_DEBUG=false`, `LOG_CHANNEL=stderr`, secure cookies). Please:

- add a line at the top of `backend/.env.example`: "Local development only. Production: deploy/bayanuk.env.example."
- add any new environment variable to **both** files, so production isn't missing it.
- keep `GEMINI_*` and `GOOGLE_*` optional: the app must start, and `php artisan optimize` must
  pass, when they're empty.

## 6. Card for Danah: make the backend CI checks pass (blocks every deploy)

**Owner:** Danah (backend). **Why now:** the pipeline runs Pint and PHPStan on every branch, and
nothing reaches production until they pass. They fail on `development` today (commit 46fcd12).

**Tasks**

1. Pint: 3 files have style issues. Run `vendor/bin/pint` and commit the result:
   - `app/Http/Controllers/Api/V1/AuthController.php` (single_blank_line_at_eof)
   - `bootstrap/providers.php` (fully_qualified_strict_types, single_line_after_imports)
   - `routes/api.php` (no_unused_imports, no_extra_blank_lines, ordered_imports)
2. PHPStan: 1 error. `AuthController.php:14` calls `stateless()`, which isn't declared on the
   `Laravel\Socialite\Contracts\Provider` interface that `Socialite::driver()` returns. Narrow the type, e.g.:
   ```php
   /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
   $driver = Socialite::driver('google');
   $url = $driver->stateless()->redirect()->getTargetUrl();
   ```

**Done when** these all pass locally:

```sh
docker compose run --rm api sh -c "vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/pest"
```

and the Jenkins `bayanuk` job is green on the branch.

**Already passing (no action):** Pest (3 tests), composer audit, npm audit of the shipped frontend deps.

**Frontend (Jumanah, not blocking):** `npm audit` reports 5 high, 2 moderate and 1 low in **dev**
dependencies (zod, undici, qs, body-parser, fast-uri, deepmerge, mostly through the `shadcn` CLI).
The pipeline gate skips dev dependencies, but fix them anyway: `npm audit fix`, or move `shadcn`
out of the dependencies and run it with `npx`.
