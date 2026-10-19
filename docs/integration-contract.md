Integration Contract: Frontend and Backend

How the Bayanuk frontend (`frontend/`) and backend (`backend/`) talk to each other. Both sides build against this document, so connecting them later is plug-in, not rework.

- **Owners:** Dana (backend) and Jumana (frontend).
- **Changes:** any change after approval is approved again before it is applied.
- **References:** Technical Plan v1.2 (sections 5, 6.5, 7, 8 and 11), Database Schema v1.2 (sections 3 and 4), AI Stack Decision v2.0 (section 4.3). The Technical Plan's API section (7) will be updated after this contract is approved.

All points are agreed by Dana and Jumana. The guest token, magic link, Google sign-in and question limit follow the decisions on the Integration Contract card.

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
4. If a request returns `419`, the frontend calls `GET /sanctum/csrf-cookie` again and repeats the request **once**.

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

`details` is optional. It is added only when it helps (for example, which field failed validation). The frontend never depends on it.

### Who translates

- **Error `message`: the backend.** It returns a translated message that is safe to show to the user (Technical Plan, section 7).
- **Language:** the frontend sends the interface language in the `Accept-Language` header (`ar` or `en`), and the backend returns `message` in that language.
- **`status_detail`: the frontend.** The backend stores a code (section 6), and the frontend shows its own translated message for it.

### Codes

| Case | Status | Code | Notes |
| --- | --- | --- | --- |
| Wrong file type | 422 | `file_type_not_supported` | Returned by the upload |
| File larger than 10 MB | 422 | `file_too_large` | Returned by the upload |
| Daily upload limit reached | 429 | `upload_limit_reached` | 2 a day for guests, 10 for signed-in users |
| Question limit reached | 429 | `question_limit_reached` | 3 per document for guests, 20 for signed-in users |
| Document does not exist or is not the user's | 404 | `document_not_found` | The same code for both, so a document's existence is never revealed |
| Document deleted after the retention period | 410 | `document_expired` | |
| Guest token missing or not in the right format | 400 | `invalid_guest_token` | |
| New analyses paused by the daily AI budget | 429 | `ai_budget_reached` | Returned by the upload, until the next day (AI Stack v2.0, section 4.3) |
| Sign-in link expired | 410 | `link_expired` | |
| Sign-in link already used | 410 | `link_used` | |

A rejected file (more than 30 pages, unsafe, or not an employment document) and failed processing are not upload errors. The document is created, then ends with `status = rejected` or `status = failed`, and the reason in `status_detail` (section 6; Technical Plan, section 5).

## 5. Guest token

Agreed on the Integration Contract card (October 12). It replaces the Technical Plan v1.2 wording ("random value created by the frontend") until section 7 is updated.

1. **The backend generates the token.** Before the first upload, if no token is stored, the frontend calls the guest endpoint, which returns a random token once. The backend stores only its SHA-256 hash (Database Schema, `documents.guest_token_hash`).
2. The frontend keeps the token in the browser (`localStorage`).
3. The frontend sends it in the `X-Guest-Token` header on every **document and question request**, while the user is **not signed in**.
4. After sign-in, the frontend sends the token once so the guest documents are claimed, then deletes it.
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

The same file (same `sha256`) uploaded again by the same owner reuses the existing analysis (Technical Plan, section 6.5; Database Schema, `documents (user_id, sha256)`).

**Response:** the same `202` shape, with the **existing** document's `id` and its current `status`. The frontend opens it the same way as a new upload, so no extra error code is needed.

### Status

`GET /api/v1/documents/{id}/status` answers:

```json
{ "id": "0193...", "status": "analyzing", "status_detail": null }
```

- The frontend asks every **2 seconds**.
- It stops when the status is `ready`, `failed` or `rejected`, or when the answer is `404`.

### Queue wait

Processing runs as a queue (Technical Plan, section 5). While the document waits for a worker, its status stays `uploaded`, and the frontend keeps showing the first stage as in progress. No extra status or field is needed. The team is alerted when the wait is over 2 minutes (Technical Plan, section 11).

### `status_detail`

Processing stops at the first failure (Technical Plan, section 5), so there is one reason only. `status_detail` holds a **code** from section 3, and the frontend shows a translated message for it. It is `null` while processing and when the document is `ready`.

| Status | Code | Meaning |
| --- | --- | --- |
| `rejected` | `unsafe_file` | The file failed the safety scan |
| `rejected` | `too_many_pages` | More than 30 pages |
| `rejected` | `not_employment_document` | Not a job offer or employment contract |
| `failed` | `file_unreadable` | The text could not be read |
| `failed` | `analysis_failed` | The analysis failed after its retry |

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
4. If a guest token exists, the frontend sends it in the same request, so the guest documents are claimed.
5. An expired or already-used link shows a clear message on the same page, with an option to request a new link (`link_expired`, `link_used`).

**Endpoints** (Technical Plan, section 7):

```
POST /api/v1/auth/magic-link          { "email": "..." }
POST /api/v1/auth/magic-link/verify   { "token": "..." }
```

## 11. Google sign-in

Agreed on the Integration Contract card (October 12). Built in the sign-in task.

1. The frontend saves the page the user is on, then calls the backend with `POST`, including `X-Guest-Token` if one exists.
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
