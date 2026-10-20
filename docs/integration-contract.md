# Integration Contract: Frontend and Backend

How the Bayanuk frontend (`frontend/`) and backend (`backend/`) talk to each other. Both sides build against this document, so connecting them later is plug-in, not rework.

- **Owners:** Danah (backend) and Jumana (frontend).
- **Changes:** any change after approval is approved again before it is applied.
- **References:** Technical Plan v1.2 (sections 5, 6.5, 7, 8 and 11), Database Schema v1.2 (sections 3, 4 and 5), AI Stack Decision v2.0 (section 4.3), MVP Definition (section 5). The Technical Plan's API section (7) will be updated after this contract is approved.

All points are agreed by Danah and Jumana. The guest token, magic link, Google sign-in and question limit follow the decisions on the Integration Contract card.

## Checklist

Where each point of the Integration Contract card is covered.

| # | Point | Section |
| --- | --- | --- |
| 1 | Shared values | 3 |
| 2 | Who translates error messages | 4 |
| 3 | Error format and codes | 4 |
| 4 | Queue wait | 6 |
| 5 | Guest token | 5 |
| 6 | Magic link | 10 |
| 7 | Google sign-in | 11 |
| 8 | Documents list pagination | 7 |
| 9 | Duplicate upload | 6 |
| 10 | Sanctum cookies and local development | 2 |
| 11 | Status polling | 6 |
| 12 | Question limit | 8 |
| 13 | Signed file URLs | 9 |
| 14 | Frontend mock API | 12 |

## 1. Base URL

All endpoints are under `/api/v1`, as JSON over HTTPS (Technical Plan, section 7). IDs are UUIDs (Technical Plan, section 8).

## 2. Sanctum cookies and local development

Signed-in users use Sanctum SPA cookie sessions (Technical Plan, section 7).

1. Before the first request that changes data (for example, an upload), the frontend calls `GET /sanctum/csrf-cookie`.
2. Every request is sent with credentials (cookies included).
3. The frontend reads the `XSRF-TOKEN` cookie and sends its value in the `X-XSRF-TOKEN` header.
4. If a request returns `419` (`csrf_token_mismatch`), the frontend calls `GET /sanctum/csrf-cookie` again and repeats the request **once**.

**Local development:** the backend runs on `http://localhost:8000`. The frontend dev server (Vite) proxies `/api` and `/sanctum` to it, so both sides share the same origin and the cookies work.

**Production:** the domain setup is part of the deployment task.

## 3. Shared values

The exact strings, the same in `backend/app/Enums` and in the frontend.

| Value | Strings | Enum |
| --- | --- | --- |
| Document status | `uploaded`, `scanning`, `extracting`, `classifying`, `analyzing`, `indexing`, `ready`, `failed`, `rejected` | `DocumentStatus` |
| Document type | `job_offer`, `employment_contract`, `other` | `DocumentType` |
| Document language | `ar`, `en`, `mixed` | `DocumentLanguage` |
| Attention severity | `info`, `attention`, `high` | `FindingSeverity` |
| Attention category | `non_compete`, `long_notice`, `long_probation`, `penalties`, `salary_mismatch`, `missing_info`, `one_sided_change`, `working_time`, `other` | `FindingCategory` |
| Reason for `rejected` | `unsafe_file`, `too_many_pages`, `not_employment_document` | In `status_detail` (section 6) |
| Reason for `failed` | `file_unreadable`, `analysis_failed` | In `status_detail` (section 6) |
| Reason for a pause | `ai_budget_reached` | In `status_detail` (section 6) |
| Error codes | Section 4 | |

## 4. Error format and codes

Every error has the same shape (Technical Plan, section 7). The `code` is the same in both languages, and the `message` follows the interface language:

```json
{
  "error": {
    "code": "file_too_large",
    "message": "حجم الملف يتجاوز الحد المسموح به (10 ميجابايت)."
  }
}
```

```json
{
  "error": {
    "code": "file_too_large",
    "message": "The file is larger than the allowed limit (10 MB)."
  }
}
```

`code` is always lowercase words joined by `_`, and the frontend compares it as an exact string. `details` is used only by `validation_error` (see below).

### Who translates

- **Error `message`: the backend.** It returns a translated message that is safe to show to the user (Technical Plan, section 7).
- **Language:** the frontend sends the interface language in the `Accept-Language` header (`ar` or `en`), and the backend returns `message` in that language.
- **`status_detail`: the frontend.** The backend stores a code (section 6), and the frontend shows its own translated message for it.

### Codes

| Case | Status | Code | Notes |
| --- | --- | --- | --- |
| Wrong file type | 422 | `file_type_not_supported` | Returned by the upload |
| File larger than 10 MB | 422 | `file_too_large` | Returned by the upload |
| A field is missing or not valid | 422 | `validation_error` | `details` lists the messages of each field (example below) |
| Not signed in, on a request that needs sign-in | 401 | `unauthorized` | Missing or invalid session. The frontend sends the user to sign in |
| CSRF token expired | 419 | `csrf_token_mismatch` | The frontend refreshes the CSRF cookie and repeats the request once (section 2) |
| Guest token missing or not in the right format | 400 | `invalid_guest_token` | Section 5 |
| Document does not exist or is not the user's | 404 | `document_not_found` | The same code for both, so a document's existence is never revealed |
| Document deleted after the retention period | 410 | `document_expired` | Section 6 |
| Daily upload limit reached | 429 | `upload_limit_reached` | 2 a day for guests, 10 for signed-in users (section 6) |
| Question limit reached | 429 | `question_limit_reached` | 3 per document for guests, 20 for signed-in users |
| Daily AI budget reached | 429 | `ai_budget_reached` | Returned by the upload and by the ask endpoint, until the budget resets (sections 6 and 8) |
| Too many sign-in link requests | 429 | `magic_link_limit_reached` | Section 10 |
| Too many requests (any other rate limit) | 429 | `too_many_requests` | Rate limits per IP, guest token and user (Technical Plan, section 8) |
| Sign-in link expired | 410 | `link_expired` | Section 10 |
| Sign-in link already used | 410 | `link_used` | Section 10 |
| Sign-in link token unknown or changed | 400 | `link_invalid` | Section 10 |
| Unexpected server error | 500 | `server_error` | `message` is general ("Internal server error") and never shows internal details. The frontend shows it with an option to try again |

`validation_error` example. `details` uses Laravel's format: each field with its list of messages.

```json
{
  "error": {
    "code": "validation_error",
    "message": "Please check the highlighted fields.",
    "details": { "email": ["Enter a valid email address."] }
  }
}
```

A rejected file (more than 30 pages, unsafe, or not an employment document) and failed processing are not upload errors. The document is created, then ends with `status = rejected` or `status = failed`, and the reason in `status_detail` (section 6; Technical Plan, section 5).

## 5. Guest token

Agreed on the Integration Contract card (October 12). It replaces the Technical Plan v1.2 wording ("random value created by the frontend") until section 7 is updated.

1. **The backend generates the token.** Before the first upload, if no token is stored, the frontend calls the guest endpoint, which returns a random token once. The backend stores only its SHA-256 hash (Database Schema, `documents.guest_token_hash`).
2. The frontend keeps the token in the browser (`localStorage`).
3. The frontend sends the guest token in the `X-Guest-Token` HTTP request header on every **document and question request**, while the user is **not signed in**. The token is sent only in this header, never in the request body or the URL.
4. After sign-in, the frontend sends the token once in the `X-Guest-Token` header, so the guest documents are claimed, then deletes it.
5. If the backend answers `invalid_guest_token`, the frontend deletes the token and starts as a new guest (asks the guest endpoint for a new one).
6. If the token is valid but the document is not the guest's, the backend answers `document_not_found` (404).

**Guest endpoint:**

```
POST /api/v1/guest-token
→ 201 { "token": "..." }
```

## 6. Upload and status polling

### Upload

`POST /api/v1/documents`, multipart form with one field, `file`. Answers `202`:

```json
{ "id": "0193...", "status": "uploaded" }
```

### Duplicate upload

A duplicate is the same file (same `sha256`) from the same owner: the user, or the guest token for a guest (Technical Plan, section 6.5; Database Schema, `documents (user_id, sha256)`).

| The existing document is | What happens | Response |
| --- | --- | --- |
| `ready` or still processing | Returned as it is. No new record, no new processing | `202 { "id": "<existing id>", "status": "<its status>" }` |
| `rejected` | Returned with its reason. The checks give the same result for the same file | `202 { "id": "<existing id>", "status": "rejected" }` |
| `failed`, under the retry limit | The **same document** is queued again: same `id`, `status` back to `uploaded`, `status_detail` back to `null` | `202 { "id": "<existing id>", "status": "uploaded" }` |
| `failed`, retry limit reached | The existing failure is returned. Nothing is queued | `202 { "id": "<existing id>", "status": "failed" }` |

- **Retryable:** every `failed` reason (`analysis_failed` and `file_unreadable`), because a failure can come from a temporary problem in an AI or OCR call.
- **Retry limit:** 2 per document. The pipeline already retries the analysis once by itself (Technical Plan, section 5, step 4).
- **Storage:** when an existing document is returned or queued again, the newly uploaded copy of the file is not stored.
- The frontend opens the document from the response the same way in every case.

### Daily upload limit

Guests: 2 uploads a day (per device and IP). Signed-in users: 10 a day (Technical Plan, section 6.5). Only a new, distinct document uses an upload. Returning an existing document or retrying a failed one does not.

### Status

`GET /api/v1/documents/{id}/status` answers:

```json
{ "id": "0193...", "status": "analyzing", "status_detail": null }
```

- The frontend asks every **2 seconds**.
- It stops when the status is `ready`, `failed` or `rejected`, or when the answer is `404` (`document_not_found`) or `410` (`document_expired`). It does not retry after a `410`, and shows a translated message.
- The backend decides when a document expires, from the retention policy (`expires_at`). This contract does not set a duration.

### What the user sees for each status

The MVP Definition (section 5, step 3) sets four progress stages. Each status maps to one of them, so the user sees 4 steps, not 9:

| Status | Meaning | What the user sees |
| --- | --- | --- |
| `uploaded` | Accepted, waiting for the first job | Stage 1 "Checking file", and under the stages: "Your document is in the queue and will start shortly." |
| `scanning` | Safety scan and page count | Stage 1 "Checking file" |
| `extracting` | Reading the text | Stage 2 "Reading document" |
| `classifying` | Checking the document type | Stage 3 "Finding key terms" |
| `analyzing` | Extracting the terms and summary | Stage 3 "Finding key terms" |
| `indexing` | Preparing chat | Stage 4 "Looking for points that need attention" |
| `ready` | Done | The result opens |
| `rejected` | Not supported | "This document is not supported", with the reason |
| `failed` | Could not be analyzed | "We could not analyze this file", with the reason |

The queue message shows only while the status is `uploaded`, never on a final state.

### Daily AI budget

The daily AI budget is $2, and every AI call checks it first (AI Stack v2.0, section 4.3). It resets at **00:00 Riyadh time (UTC+3)**, for both the pipeline and chat.

- **During processing:** the document **pauses**. It does not fail. `status` stays at the last stage reached, and `status_detail` is `ai_budget_reached`. Processing resumes the next day on its own, and `status_detail` goes back to `null`.
- **New upload while the budget is reached:** refused with `429 ai_budget_reached`. No document is created and no upload is used.
- **Frontend while paused:** the stages stay on screen with the pause message under them, and the status is checked every 60 seconds instead of 2. It goes back to 2 seconds when the pause ends.

```json
{ "id": "0193...", "status": "analyzing", "status_detail": "ai_budget_reached" }
```

### `status_detail`

`status_detail` holds a **code**, and the frontend shows a translated message for it. Processing stops at the first failure (Technical Plan, section 5), so there is one reason only. It is `null` while processing normally and when the document is `ready`.

| Status | Code | Meaning |
| --- | --- | --- |
| `rejected` | `unsafe_file` | The file failed the safety scan |
| `rejected` | `too_many_pages` | More than 30 pages |
| `rejected` | `not_employment_document` | Not a job offer or employment contract |
| `failed` | `file_unreadable` | The text could not be read |
| `failed` | `analysis_failed` | The analysis failed after its retry |
| Any processing status | `ai_budget_reached` | Paused until the daily AI budget resets |

## 7. Documents list pagination

`GET /api/v1/documents` returns the signed-in user's documents, paginated (Technical Plan, section 7). The format is Laravel's standard paginated resource:

```
GET /api/v1/documents?page=2
→ 200 {
    "data": [ ... ],
    "meta": { "current_page": 2, "last_page": 3, "per_page": 15, "total": 40 }
  }
```

The backend sets `per_page`. The frontend reads `current_page` and `last_page` only.

## 8. Question limit

Agreed on the Integration Contract card: the ask endpoint returns the number of questions left. **The frontend never calculates it.** When none are left, the backend answers `question_limit_reached`.

- `POST /api/v1/documents/{id}/chat` returns it with the answer.
- `GET /api/v1/documents/{id}/chat` returns it with the chat history (Technical Plan, section 7).

**Field name:** `remaining_questions`.

**Daily AI budget reached:** `POST /chat` answers `429 ai_budget_reached`, and no AI call is made. The question is not counted, so `remaining_questions` does not change. The frontend shows the message in the chat and keeps the question in the input.

```json
{
  "error": {
    "code": "ai_budget_reached",
    "message": "Daily AI usage limit reached. Please try again after the budget resets."
  }
}
```

## 9. Signed file URLs

Built in the document viewer task.

1. The viewer asks `GET /api/v1/documents/{id}/file`. The backend answers with a signed URL valid for 5 minutes (Technical Plan, sections 7 and 8).
2. The file is served from storage, never from the API domain (Technical Plan, section 8).
3. When the URL has expired, the viewer asks for a new one and reloads the file. The user sees no error.

**Response:**

```
GET /api/v1/documents/{id}/file
→ 200 { "url": "...", "expires_at": "2026-11-20T10:05:00Z" }
```

## 10. Magic link sign-in

Agreed on the Integration Contract card (October 12). Built in the sign-in task.

1. The email link opens a frontend page: `/auth/verify?token=...`.
2. That page sends the token to the backend verify endpoint with `POST`, never `GET`. Email scanners open links on their own and could use up the single-use token.
3. The backend sets the session cookie in the response.
4. If a guest token exists, the frontend sends it in the `X-Guest-Token` header of the same request, so the guest documents are claimed.
5. An expired, already-used or unknown link shows a clear message on the same page, with an option to request a new link.

**Endpoints** (Technical Plan, section 7):

```
POST /api/v1/auth/magic-link          { "email": "..." }
POST /api/v1/auth/magic-link/verify   { "token": "..." }
```

**Errors:**

| Endpoint | Case | Status | Code |
| --- | --- | --- | --- |
| `/auth/magic-link` | The email is missing or not valid | 422 | `validation_error` |
| `/auth/magic-link` | Too many link requests | 429 | `magic_link_limit_reached` |
| `/auth/magic-link/verify` | The link has expired | 410 | `link_expired` |
| `/auth/magic-link/verify` | The link was already used | 410 | `link_used` |
| `/auth/magic-link/verify` | The token is unknown or was changed | 400 | `link_invalid` |

## 11. Google sign-in

Agreed on the Integration Contract card (October 12). Built in the sign-in task.

1. The frontend saves the page the user is on, then calls the backend with `POST`, with the guest token in the `X-Guest-Token` header if one exists.
2. The backend saves the guest token with a random `state` value and returns the Google sign-in URL.
3. The frontend opens that URL as a full page.
4. Google returns to the backend callback (`/api/v1/auth/google/callback`). The backend checks `state`, signs the user in, claims the guest documents, sets the session cookie, and redirects to the frontend page `/auth/callback`.
5. On success, the frontend deletes the guest token, loads `/me`, and returns the user to the saved page.
6. On failure, the backend redirects with an error code, and the frontend shows a clear message with an option to try again.

The paths are from the Technical Plan (section 7). The redirect endpoint uses `POST` (Integration Contract card), not `GET`.

**Response and failure codes:**

```
POST /api/v1/auth/google/redirect
→ 200 { "url": "https://accounts.google.com/..." }

On failure, the backend redirects to:
/auth/callback?error=google_cancelled
/auth/callback?error=google_invalid_state
/auth/callback?error=google_sign_in_failed
```

## 12. Frontend mock API

In development, the frontend uses a mock API (MSW) until the backend is ready. It follows this document exactly: the same endpoints, values, `status_detail` codes, error shape and error codes. The mock is never part of the production build.
