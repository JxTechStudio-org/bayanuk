# Before production: application changes (for the team)

These are changes to the application code. The production infrastructure (Compose override,
Jenkins pipeline, host nginx, backups) is separate and doesn't depend on them. Each item can be one card.

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

## 6. Production defaults in `.env.example`

`.env.example` is for local development and has `APP_DEBUG=true`, `LOG_LEVEL=debug`, and
`SESSION_DOMAIN=localhost`. That's fine locally, but nobody should copy it to a server. Production uses
`deploy/bayanuk.env.example` (`APP_DEBUG=false`, `LOG_CHANNEL=stderr`, secure cookies). Please:

- add a line at the top of `backend/.env.example`: "Local development only. Production: deploy/bayanuk.env.example."
- add any new environment variable to **both** files, so production isn't missing it.
- keep `GEMINI_*` and `GOOGLE_*` optional: the app must start, and `php artisan optimize` must
  pass, when they're empty.

## 7. Make the CI checks pass (the pipeline blocks deploys until they do)

Results from running the new pipeline's checks on `development` (commit 46fcd12):

| Check | Result | Fix |
| --- | --- | --- |
| Pint | 3 files | `vendor/bin/pint` on `AuthController.php`, `bootstrap/providers.php`, `routes/api.php` |
| PHPStan | 1 error | `AuthController.php:14`, `stateless()` isn't on the Socialite `Provider` contract. Type the driver as `\Laravel\Socialite\Two\GoogleProvider` (e.g. `/** @var GoogleProvider $driver */`) |
| Pest | 3 passed | none |
| composer audit | clean | none |
| npm audit (shipped deps) | clean | none |
| npm audit (dev deps) | 5 high, 2 moderate, 1 low (zod, undici, qs, body-parser, fast-uri, deepmerge, mostly through the `shadcn` CLI) | `npm audit fix`, or move `shadcn` out of the dependencies and run it with `npx` |
