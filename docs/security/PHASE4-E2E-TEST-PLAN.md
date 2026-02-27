# E2E Test Plan — Security Phase 4: API Authorization & IDOR Prevention

**Branch:** `Security-Phase-4-API-Authorization-IDOR-Prevention`
**Scope:** ChatAttachment IDOR, KYC document IDOR, admin-only KYC actions, Nginx private storage, devCode env-gating, filename sanitization

---

## A) Test environment

| Item | Value |
|---|---|
| Backend | `http://localhost:8000` |
| SPA | `http://localhost:5173` |
| API base | `http://localhost:8000/api/v1` |
| Auth cookie | Sanctum stateful (obtain via `/sanctum/csrf-cookie` first) |
| HTTP tool | Browser DevTools / curl / Postman |

### Demo users (password: `password`)

| Email | Role |
|---|---|
| `admin@example.com` | admin |
| `lana@demo.com` | landlord (has KYC + chat history) |
| `leo@demo.com` | landlord |
| `tena@demo.com` | seeker/tenant |
| `tomas@demo.com` | seeker/tenant |

---

## B) Pre-test setup

1. `php artisan migrate:fresh --seed` — fresh DB with demo data
2. Have both users open in separate browser profiles (or use two different browsers) to maintain independent sessions
3. Seed should have at least one KYC submission for `lana@demo.com` and one conversation between `lana` and `tena`

---

## C) KYC Document — IDOR prevention

### C-1 Owner can download their own KYC document

| Field | Value |
|---|---|
| **ID** | KYC-IDOR-01 |
| **Actor** | `lana@demo.com` (document owner) |
| **Precondition** | Lana has at least one KYC submission with a document in the database |
| **Steps** | 1. Authenticate as `lana@demo.com` (obtain CSRF cookie + session) <br> 2. `GET /api/v1/kyc/documents/{id}` using Lana's document ID |
| **Expected** | `200 OK`, binary file stream returned, `Content-Disposition` header present |
| **FAIL if** | `403`, `404`, or no `Content-Disposition` |

---

### C-2 Third-party user cannot access another user's KYC document (IDOR)

| Field | Value |
|---|---|
| **ID** | KYC-IDOR-02 |
| **Actor** | `tomas@demo.com` (not the document owner) |
| **Precondition** | A KYC document exists for `lana@demo.com`; its numeric ID is known |
| **Steps** | 1. Authenticate as `tomas@demo.com` <br> 2. `GET /api/v1/kyc/documents/{lana_document_id}` |
| **Expected** | `403 Forbidden`, JSON `{ "message": "Forbidden" }` (or redirect to error page if browser request) |
| **FAIL if** | `200` or file is returned |

---

### C-3 Unauthenticated request is rejected

| Field | Value |
|---|---|
| **ID** | KYC-IDOR-03 |
| **Actor** | No session (guest) |
| **Steps** | 1. Clear cookies / open incognito <br> 2. `GET /api/v1/kyc/documents/{any_id}` |
| **Expected** | `401 Unauthenticated` |
| **FAIL if** | `200` or `403` |

---

### C-4 Admin can access any user's KYC document and action is audit-logged

| Field | Value |
|---|---|
| **ID** | KYC-IDOR-04 |
| **Actor** | `admin@example.com` |
| **Precondition** | KYC document exists for `lana@demo.com` |
| **Steps** | 1. Authenticate as `admin@example.com` <br> 2. `GET /api/v1/kyc/documents/{lana_document_id}` <br> 3. Check `audit_logs` table: `SELECT * FROM audit_logs WHERE action = 'kyc.document.admin_downloaded'` |
| **Expected** | `200 OK`, file returned; `audit_logs` row exists with `actor_user_id = admin_id`, `subject_id = document_id` |
| **FAIL if** | `403`, or no audit log row |

---

### C-5 Direct URL to private storage path is blocked

| Field | Value |
|---|---|
| **ID** | KYC-IDOR-05 |
| **Actor** | Any user (or guest) |
| **Precondition** | Nginx running; a KYC file exists at e.g. `storage/app/private/kyc/...` |
| **Steps** | 1. Guess or construct the storage path: `http://localhost:8000/storage/app/private/kyc/{user_id}/{submission_id}/id_front.jpg` <br> 2. Make a GET request to that URL (browser or curl) |
| **Expected** | `404 Not Found` from Nginx (no file content returned) |
| **FAIL if** | File content is returned (image or PDF visible) |
| **Note** | This tests the Nginx `deny all` rule for `/storage/app/` paths |

---

## D) Chat Attachment — IDOR prevention

### D-1 Conversation participant can download attachment

| Field | Value |
|---|---|
| **ID** | CHAT-IDOR-01 |
| **Actor** | `lana@demo.com` (in a conversation with `tena`) |
| **Precondition** | A `chat_attachments` record exists; `lana` is a participant of the owning conversation |
| **Steps** | 1. Authenticate as `lana@demo.com` <br> 2. `GET /api/v1/chat/attachments/{attachment_id}` |
| **Expected** | `200 OK`, file content returned |
| **FAIL if** | `403` or `404` |

---

### D-2 Non-participant cannot access another conversation's attachment (IDOR)

| Field | Value |
|---|---|
| **ID** | CHAT-IDOR-02 |
| **Actor** | `tomas@demo.com` (NOT in the conversation between `lana` and `tena`) |
| **Precondition** | An attachment ID from the `lana`–`tena` conversation is known |
| **Steps** | 1. Authenticate as `tomas@demo.com` <br> 2. `GET /api/v1/chat/attachments/{lana_tena_attachment_id}` |
| **Expected** | `403 Forbidden` |
| **FAIL if** | `200` or file content returned |

---

### D-3 Non-participant cannot access thumbnail either

| Field | Value |
|---|---|
| **ID** | CHAT-IDOR-03 |
| **Actor** | `tomas@demo.com` |
| **Steps** | 1. Authenticate as `tomas@demo.com` <br> 2. `GET /api/v1/chat/attachments/{lana_tena_attachment_id}/thumb` |
| **Expected** | `403 Forbidden` |
| **FAIL if** | `200` or image returned |

---

### D-4 Content-Disposition filename does not contain path traversal or quotes

| Field | Value |
|---|---|
| **ID** | CHAT-IDOR-04 |
| **Actor** | `lana@demo.com` (participant) |
| **Precondition** | Upload an attachment with a malicious filename: `../../etc/passwd` or `file"name".jpg` |
| **Steps** | 1. Send a message with an attachment named `../../etc/passwd` or `"evil".jpg` <br> 2. Fetch the attachment: `GET /api/v1/chat/attachments/{id}` <br> 3. Inspect the `Content-Disposition` response header |
| **Expected** | `Content-Disposition: inline; filename="passwd"` or `filename="evil.jpg"` — no path separators, no unescaped quotes |
| **FAIL if** | `Content-Disposition` contains `../`, `..%2F`, or unescaped `"` or `'` |

---

## E) Admin KYC approval — role & MFA enforcement

### E-1 Non-admin cannot approve a KYC submission

| Field | Value |
|---|---|
| **ID** | ADMIN-KYC-01 |
| **Actor** | `lana@demo.com` (landlord) |
| **Precondition** | A pending KYC submission exists |
| **Steps** | 1. Authenticate as `lana@demo.com` <br> 2. `PATCH /api/v1/admin/kyc/submissions/{id}/approve` |
| **Expected** | `403 Forbidden` |
| **FAIL if** | `200` or submission status changes |

---

### E-2 Non-admin cannot reject a KYC submission

| Field | Value |
|---|---|
| **ID** | ADMIN-KYC-02 |
| **Actor** | `tena@demo.com` (seeker) |
| **Steps** | 1. Authenticate as `tena@demo.com` <br> 2. `PATCH /api/v1/admin/kyc/submissions/{id}/reject` with `{ "reason": "test" }` |
| **Expected** | `403 Forbidden` |
| **FAIL if** | `200` |

---

### E-3 Admin without MFA confirmed cannot approve

| Field | Value |
|---|---|
| **ID** | ADMIN-KYC-03 |
| **Actor** | A user with `role=admin` but `mfa_enabled=false` (create in DB: `UPDATE users SET mfa_enabled=false WHERE email='admin@example.com'`) |
| **Precondition** | `REQUIRE_MFA_FOR_ADMINS=true` in `.env` |
| **Steps** | 1. Authenticate as that admin <br> 2. `PATCH /api/v1/admin/kyc/submissions/{id}/approve` |
| **Expected** | `403`, `{ "message": "Multi-factor authentication is required for admin access." }` |
| **FAIL if** | `200` |

---

### E-4 Admin with MFA confirmed and verified session can approve

| Field | Value |
|---|---|
| **ID** | ADMIN-KYC-04 |
| **Actor** | `admin@example.com` (MFA set up and session verified) |
| **Precondition** | Admin has `mfa_enabled=true`, `mfa_confirmed_at` set, and has completed the MFA challenge so the session contains `mfa_verified_at` |
| **Steps** | 1. Login as `admin@example.com` <br> 2. Complete MFA challenge (TOTP code) <br> 3. `PATCH /api/v1/admin/kyc/submissions/{id}/approve` |
| **Expected** | `200 OK`, `{ "status": "approved" }`, `users.verification_status` = `approved`, `users.verified_at` set |
| **FAIL if** | `403` or submission remains `pending` |

---

### E-5 Approved submission updates landlord's profile

| Field | Value |
|---|---|
| **ID** | ADMIN-KYC-05 |
| **Depends on** | ADMIN-KYC-04 |
| **Steps** | 1. After approving `lana@demo.com`'s submission <br> 2. `GET /api/v1/auth/me` as `lana@demo.com` |
| **Expected** | `verification_status: "approved"`, `address_verified: true`, `verified_at` is a non-null timestamp |
| **FAIL if** | Values remain at `pending` / `null` |

---

## F) devCode environment gating

### F-1 devCode is present in local/testing mode

| Field | Value |
|---|---|
| **ID** | DEVCODE-01 |
| **Precondition** | `APP_ENV=local` in `.env`; user with `email_verified=false` exists |
| **Steps** | 1. Authenticate as unverified user <br> 2. `POST /api/v1/me/verification/email/request` |
| **Expected** | `200`, response JSON contains `devCode` field with a numeric code |
| **FAIL if** | `devCode` is absent or null |

---

### F-2 devCode is absent in production mode

| Field | Value |
|---|---|
| **ID** | DEVCODE-02 |
| **Precondition** | `APP_ENV=production` in `.env` (or temporarily override); user with `email_verified=false` |
| **Steps** | 1. Set `APP_ENV=production` <br> 2. Authenticate as unverified user <br> 3. `POST /api/v1/me/verification/email/request` |
| **Expected** | `200`, response JSON does **not** contain `devCode` key |
| **FAIL if** | `devCode` is present in the response |
| **Note** | Restore `APP_ENV=local` after the test |

---

## G) Nginx private storage — additional scenarios

### G-1 Public storage symlink still works

| Field | Value |
|---|---|
| **ID** | NGINX-01 |
| **Precondition** | `php artisan storage:link` has been run; a public image exists in `storage/app/public/` |
| **Steps** | 1. `GET http://localhost:8000/storage/{filename}` (the public symlink path) |
| **Expected** | `200 OK`, image or file returned |
| **FAIL if** | `404` or `403` (the deny rule must not affect the public symlink) |

---

### G-2 Nested private path is blocked

| Field | Value |
|---|---|
| **ID** | NGINX-02 |
| **Steps** | 1. `GET http://localhost:8000/storage/app/kyc/1/1/id_front.jpg` <br> 2. `GET http://localhost:8000/storage/app/private/anything` |
| **Expected** | `404` from Nginx for both |
| **FAIL if** | Any `2xx` response or file content |

---

## H) Regression — existing auth & listing flows unaffected

| ID | Steps | Expected | FAIL if |
|---|---|---|---|
| REG-01 | Login as `tena@demo.com` → `GET /api/v1/listings` | `200`, listings returned | `401`/`403` |
| REG-02 | Login as `lana@demo.com` → `GET /api/v1/landlord/listings` | `200`, landlord's own listings | `403` |
| REG-03 | Login as `tena@demo.com` → open a conversation → send message with image attachment → other side can see it | Message + thumbnail visible in chat | Attachment unavailable to conversation participant |
| REG-04 | Login as `admin@example.com` → `GET /api/v1/admin/kyc/submissions` | `200`, list of pending submissions | `403` |

---

## I) Pass / Fail criteria

| Result | Meaning |
|---|---|
| **PASS** | All C, D, E, F, G cases pass; no regressions in H |
| **CONDITIONAL PASS** | G cases fail only because Nginx is not running in the local dev setup (Docker not used) — document clearly |
| **FAIL** | Any IDOR case (C-2, C-3, D-2, D-3) returns `200`; or any admin-only action succeeds for a non-admin; or a file is served directly via Nginx |
