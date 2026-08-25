# NOTES.md — internal inventory for writing the README

Working document. Terse on purpose. Everything below was verified by reading the
code in this repository, not inferred from the old README.

Stack: PHP 8 (`declare(strict_types=1)` everywhere), MySQL 5.7+/8.0, PDO,
no Composer, no framework, no JS build step. Vanilla JS on the front end.

---

## 1. Directory map

| Path | Holds |
|---|---|
| `index.php` | Root convenience redirect to `public/checkout.php`. Only relevant when DocumentRoot is the project root instead of `public/`. |
| `.env.example` | Environment template (all placeholders). Copy to `.env`. |
| `config/.env.example` | Byte-identical copy of the above, for deployments that keep the env file in `config/`. |
| `config/payment.php` | The single bootstrap: registers the PSR-4-ish autoloader for `App\`, loads `.env`, returns the config array. Every entry point `require`s it. |
| `config/.htaccess` | `Require all denied` / `Deny from all`. |
| `app/Support/` | Framework-free primitives: env reader, PDO singleton, session guard, CSRF, rate limiter, logger, money maths, validation, HTTP helpers, security headers/session hardening. |
| `app/Models/` | Thin static data-access classes, one per table. Raw SQL over PDO prepared statements. No ORM, no entity objects — every method returns arrays. |
| `app/Services/Payments/` | The gateway abstraction: interface, Asaas implementation, factory, split builder, payment orchestrator. |
| `app/Services/Access/` | Post-payment access granting. |
| `app/Services/Auth/` | HTTP-free authentication core. |
| `app/Services/Mail/` | Mailer interface + two drivers (log file, hand-rolled SMTP) + facade + DTO. |
| `app/Services/Student/` | Builds the student dashboard view model. |
| `app/Controllers/` | Three controllers: checkout API, webhook, auth forms. |
| `app/Views/emails/` | Two PHP e-mail templates. Body copy is Portuguese (customer-facing). |
| `app/.htaccess` | `Require all denied` / `Deny from all`. |
| `database/migrations/` | 15 standalone `.sql` files (14 CREATE TABLE + 1 ALTER). |
| `database/schema_full.sql` | The whole schema in correct execution order, plus both seed blocks. This is the practical way to create the DB. |
| `database/seed_sandbox.sql` | Demo producers + demo product (checkout side). |
| `database/seed_ead_sandbox.sql` | Demo course, modules, lessons, student, enrollment (delivery side). Depends on the file above. |
| `database/setup.php` | CLI installer: runs all 15 migrations in dependency order and optionally seeds. Idempotent — safe to re-run. |
| `database/.htaccess` | `Require all denied` / `Deny from all`. |
| `public/` | Web root. Checkout page, auth pages, three payment-outcome pages, JSON API. |
| `public/api/` | Three JSON endpoints (create payment, poll Pix status, receive webhook). |
| `public/assets/checkout.js` | Checkout front end: method toggle, input masks, POST, Pix QR render, status polling. |
| `public/aluno/` | Student course area (Portuguese UI). Pages + shared includes + its own progress API. |
| `public/aluno/assets/img/` | Generated placeholder artwork for the demo seed (1 banner, 1 cover, 5 lesson thumbnails). Plain colour blocks with the title drawn on them. |
| `admin/` | Minimal admin panel: payments, products, producers, webhooks, courses, students. |
| `storage/logs/` | Runtime log files. Tracked-but-empty via its own `.gitignore`. |
| `storage/.htaccess` | `Require all denied` / `Deny from all`. |
| `tools/simulate_webhook.php` | CLI dev tool: posts an Asaas-shaped webhook to the local endpoint. |

---

## 2. Every class in `app/`

### `app/Support/`
- **`Auth`** — session guard. `start/login/logout/check/id/role/user/requireLogin/requireRole`. Stores only id, name, email, role in `$_SESSION`. `login()` calls `session_regenerate_id(true)`.
- **`Csrf`** — generates a 32-byte hex token into `$_SESSION['_csrf']`, verifies with `hash_equals`. One token per session, never rotated.
- **`Database`** — static PDO singleton. `init()` builds the `mysql:` DSN (utf8mb4) with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`. `pdo()` throws if `init()` was not called.
- **`Env`** — dependency-free `.env` parser. Strips `#` inline comments and surrounding quotes, caches in a static array. Typed getters `get/bool/int/float`. An explicitly configured falsy value (`0`, `''`) is honoured rather than replaced by the default.
- **`Http`** — `json()` (sets status + content-type, echoes, exits), `clientIp()` (`REMOTE_ADDR` only), `body()` (decodes JSON body), `rawBody()`.
- **`Logger`** — appends a timestamped line to `storage/logs/<channel>.log`. `scrub()` recursively replaces a fixed list of sensitive keys with `***`.
- **`MoneyCalculator`** — integer-cents maths. `toCents/toReais/format`, `estimatedFeeCents()` (fixed for Pix; percent + fixed for card), `breakdown()` returning gross/fee/net/principal/coproducer, with the main producer absorbing the rounding remainder.
- **`RateLimiter`** — `tooMany(key, max, window)`. Deletes rows older than the window, counts rows for the key, inserts the current attempt, returns `count >= max`. Backed by the `rate_limits` table.
- **`Security`** — `isHttps()`, `headers()` (nosniff, `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `Referrer-Policy`, and HSTS only over TLS) and `session()` (httponly, `SameSite=Lax`, `secure` over TLS, strict session-id mode). Both are no-ops on the CLI and are called from the bootstrap.
- **`Validator`** — `clean` (trim + `strip_tags`), `email` (`FILTER_VALIDATE_EMAIL`), `digits`, `cpfCnpj` (length 11 or 14 — **length only, no checksum**), `phone` (10–11 digits), `name` (≥ 3 chars).

### `app/Models/` (all static, all array-returning)
- **`Producer`** — `all()`, `find()`.
- **`Product`** — `findBySlug()` (joins both producers to expose `principal_wallet_id` / `coproducer_wallet_id`; filters `status = 'ativo'`), `all()`.
- **`Payment`** — `create`, `findByInternalId`, `findByExternalId`, `find`, `findByIdempotency`, `setExternalId`, `updateStatus` (also sets `paid_at` when status is `pago`), `markAccessGranted` (only when `access_granted_at IS NULL`), `all`.
- **`PaymentSplit`** — `create` (inserts as `previsto`), `markReceivedByPayment` (sets `recebido` and mirrors `expected_cents` into `received_cents`), `byPayment`.
- **`PaymentWebhook`** — `record()` returns false on SQLSTATE 23000 (duplicate idempotency key), `markProcessed`, `all`.
- **`User`** — `findByEmail`, `findById`, `emailExists`, `listAll` (with active-enrollment count), `create` (leaves `password_hash` NULL), `setPasswordHash`, `updateLastLogin`.
- **`Student`** — `findByUser`, `upsert` (only non-empty values overwrite).
- **`PasswordResetToken`** — `issue()` (32 random bytes hex; stores **only the SHA-256 hash**; default 24 h TTL), `findValid()` (unused + unexpired), `consume()`, `purgeExpired()`.
- **`Course`** — `all` (with producer name and lesson count), `create`, `update`, `find`, `findBySlug`.
- **`CourseModule`** — `forCourse` (ordered), `find`, `create`, `update`, `delete`.
- **`Lesson`** — `forCourse` (published only, ordered by module then lesson), `find`, `create`, `update`, `delete`.
- **`Enrollment`** — `grant()` (INSERT … ON DUPLICATE KEY UPDATE; returns true only on a fresh insert), `activeForUser`, `isActive`, `listAll`, `setStatus`.
- **`LessonProgress`** — `mapForUserCourse()`, `save()` (upsert; when completed, stamps `completed_at` and forces watched = duration).
- **`ProductCourse`** — `coursesForProduct`, `link` (INSERT IGNORE), `unlink`, `productsForCourse`.

### `app/Services/Payments/`
- **`PaymentGatewayInterface`** — the contract: `createCustomer`, `createPixPayment`, `createCreditCardPayment`, `createCardToken`, `createSplitPayment`, `getPaymentStatus`, `refundPayment`, `handleWebhook`, `verifyWebhook`, `mapStatus`. Provider-neutral arrays in and out.
- **`AsaasGateway`** — the only implementation. cURL client against Asaas API v3 with the key in the `access_token` header. Creates customers, tokenizes cards (`POST /creditCard/tokenizeCreditCard`), creates charges with the `split` array, fetches the Pix QR code (`GET /payments/{id}/pixQrCode`), verifies the webhook token, and maps Asaas statuses to the internal Portuguese vocabulary (`pago`, `pendente`, `estornado`, `cancelado`, `recusado`). SSL verification is never disabled; falls back to `config/cacert.pem` when php.ini has no CA bundle.
- **`GatewayFactory`** — `make($config, $driver)`; only `'asaas'` is registered, anything else throws. Other providers are commented placeholders.
- **`SplitService`** — builds the split array from the product row according to `ISSUER_MODE`. `platform` → main producer + co-producer; `principal` → co-producer only (the issuer's own wallet is deliberately omitted).
- **`PaymentService`** — the orchestrator. Idempotency check → fee/split breakdown → create gateway customer → tokenize card if needed → insert the payment as `pendente` → insert the split forecast → create the charge → store the external id and status. Also `syncStatus()` for Pix polling.

### Other services
- **`Access\StudentAccessService`** — `grant(paymentRow)`. Short-circuits if `access_granted_at` is set; finds the product's courses; finds-or-creates the user; upserts the student row; enrolls in each course; stamps `access_granted_at` **before** sending mail; issues a password token when the user has no password; sends the access e-mail.
- **`Auth\AuthService`** — `attempt()` (rejects inactive users and users with a NULL hash, then `password_verify`), `completeLogin()`, `setPassword()` (`password_hash(..., PASSWORD_BCRYPT)`).
- **`Mail\Mailer`** — one-method driver interface.
- **`Mail\Message`** — DTO; derives a plain-text part from the HTML when none is supplied.
- **`Mail\LogMailer`** — dev driver; appends the rendered message to `storage/logs/mail.log`.
- **`Mail\SmtpMailer`** — hand-written socket SMTP client: SMTPS or STARTTLS, AUTH LOGIN, multipart/alternative, dot-stuffing. `verify_peer` is on.
- **`Mail\MailService`** — facade. Picks the driver from `MAIL_DRIVER`, renders templates, wraps them in a shared HTML layout, and swallows all send exceptions (logs, returns false) so a mail failure can never break the webhook.
- **`Student\StudentDashboardService`** — `forUser(userId, ?courseSlug)`. Returns `student / course / modules / lessons / continue`, or null when there is no active enrollment. Computes per-course progress and picks the "continue watching" lesson.

### Controllers
- **`CheckoutController`** — `createPayment()` (CSRF → rate limit → server-side validation → product lookup → `PaymentService::checkout` → JSON) and `pixStatus($externalId)` (poll-aware rate limit → re-syncs status from the gateway).
- **`WebhookPaymentController`** — `handle()`. Verifies the token, normalises the payload, records the event for idempotency, updates the payment, marks splits received, calls `StudentAccessService::grant`, and flags the webhook row processed/errored. Returns 200 on duplicates, 500 on failure so Asaas retries.
- **`AuthController`** — `handleLogin()`, `handleLogout()`, `handleSetPassword()`. Returns view state arrays; redirects on success. `safeNext()` blocks absolute and protocol-relative redirect targets.

---

## 3. HTTP endpoints

### Public checkout (`public/`)
| Method | Path | Purpose |
|---|---|---|
| GET | `checkout.php?p=<checkout_slug>` | Renders the checkout page for a product. Emits a CSRF token and a fresh idempotency key into `window.CHECKOUT`. Shows a "product unavailable" state for an unknown slug. |
| POST | `api/create_payment.php` | JSON. Creates the Pix or card charge with the split attached. Returns `internal_id`, `external_id`, `status`, `method`, `pix_qr_code` (base64 PNG), `pix_copy_paste`, `amount`. Rejects: 419 CSRF, 429 rate limit, 422 validation, 404 unknown product, 405 non-POST, 500 gateway error. |
| GET | `api/pix_status.php?external_id=…` | JSON. Re-reads the charge status from the gateway, persists it, returns `{ok, status}`. Polled every 4 s by the browser while a Pix QR is on screen. Unauthenticated by design, throttled per IP (429 beyond the poll budget). |
| POST | `api/webhook.php` | JSON. Asaas webhook receiver. 401 without a valid `asaas-access-token` header, 405 on non-POST. |
| GET | `payment_success.php` / `payment_pending.php` / `payment_failed.php` | Static outcome pages the front end redirects to. Pure HTML: they never load the bootstrap, so they receive no security headers. Success links to `login.php`; the other two carry no button. |
| GET/POST | `login.php` | E-mail + password login. `?next=` supports a relative post-login destination. |
| GET | `logout.php` | Destroys the session, redirects to login. |
| GET/POST | `definir-senha.php?token=…` | First-access / reset password form. Validates the token, sets the password, consumes the token, logs the user in. |

### Student area (`public/aluno/`, login required)
| Method | Path | Purpose |
|---|---|---|
| GET | `index.php` | Dashboard: greeting, progress, "continue watching", module/lesson list. |
| GET | `curso.php?slug=…` | Course landing page (banner, description, curriculum). |
| GET | `aula.php?id=…` | Lesson player with prev/next navigation. Falls back to the "continue" lesson when `id` is absent or not in the enrolled set. |
| GET | `minha-conta.php` | Read-only account details. |
| POST | `api/progress.php` | JSON. Saves lesson progress and returns recomputed course progress. 401 unauthenticated, 419 bad CSRF (`X-CSRF-Token` header or `_csrf` in body), 404 unknown lesson, **403 when the user has no active enrollment in that lesson's course**. |

### Admin (`admin/`)
| Method | Path | Purpose |
|---|---|---|
| GET/POST | `index.php?tab=payments\|products\|producers\|webhooks` | Read-only dashboards. Also hosts the admin login POST (`action=admin_login`) and `?logout=1`. |
| GET/POST | `courses.php` | CRUD for courses, modules and lessons; link/unlink products. POST actions: `save_course`, `save_module`, `delete_module`, `save_lesson`, `delete_lesson`, `link_product`, `unlink_product`. Post/Redirect/Get with a flash message. |
| GET/POST | `students.php` | Student and enrollment management. POST actions: `grant_access` (manual enrollment, optional e-mail), `resend_password` (issues a fresh token and mails the link), `set_enrollment_status`. |

---

## 4. Database tables

From `database/migrations/` (order matters — FKs).

**`producers`** — the people who get paid.
`id`, `name`, `email` (UNIQUE), `document` (tax ID, digits only), `type` ENUM(`produtor_principal`,`coprodutor`), `gateway` (default `asaas`), `wallet_id` (the gateway walletId used in the split), `account_status` ENUM(`pendente`,`ativo`,`bloqueado`), `created_at`, `updated_at`.

**`products`** — what is sold, and how the revenue is divided.
`id`, `name`, `description`, `price_cents`, `status` ENUM(`ativo`,`inativo`), `checkout_slug` (UNIQUE — the `?p=` parameter), `principal_producer_id` FK, `coproducer_producer_id` FK, `principal_percent` (default 85.00), `coproducer_percent` (default 15.00), timestamps. CHECK constraint forces the two percentages to sum to 100.

**`payments`** — one row per checkout attempt.
`id`, `internal_id` (UNIQUE, `PMT-YYYYMMDD-<12 hex>`, sent to the gateway as `externalReference`), `external_id` (the gateway charge id), `product_id` FK, `customer_name`, `customer_email`, `customer_phone`, `customer_doc`, `customer_external_id` (gateway customer id), `gross_cents`, `fee_cents`, `net_cents`, `method` ENUM(`pix`,`cartao`), `status` ENUM(`pendente`,`pago`,`recusado`,`cancelado`,`estornado`), `idempotency_key` (UNIQUE), `created_at`, `paid_at`, `updated_at`, plus `access_granted_at` added by `alter_payments_add_access_granted.sql`.

**`payment_splits`** — the forecast and settlement of each party's cut.
`id`, `payment_id` FK (CASCADE), `producer_id` FK, `role` ENUM(`produtor_principal`,`coprodutor`), `percentual` DECIMAL(5,2), `expected_cents`, `received_cents`, `status` ENUM(`previsto`,`recebido`,`cancelado`), timestamps.

**`payment_webhooks`** — the raw audit log and idempotency ledger for inbound events.
`id`, `gateway`, `event`, `idempotency_key` (UNIQUE — this is what blocks double processing), `payload` JSON, `process_status` ENUM(`recebido`,`processado`,`erro`), `received_at`.

**`rate_limits`** — one row per throttled attempt. `id`, `rl_key`, `created_at`, index on (`rl_key`,`created_at`). *(Defined at the bottom of `create_payment_webhooks_table.sql`, not in a file of its own.)*

**`users`** — everyone who can log in.
`id`, `name`, `email` (UNIQUE), `password_hash` (NULL until set via token), `role` ENUM(`admin`,`producer`,`student`), `status` ENUM(`active`,`inactive`,`blocked`), `last_login_at`, timestamps.

**`students`** — 1:1 extension of `users` for buyers. `id`, `user_id` (UNIQUE, FK CASCADE), `phone`, `document`, timestamps.

**`courses`** — the delivered product. `id`, `title`, `slug` (UNIQUE), `description`, `cover_image`, `banner_image`, `producer_id` FK (reuses `producers`), `status` ENUM(`draft`,`published`,`archived`), timestamps.

**`course_modules`** — sections within a course. `id`, `course_id` FK (CASCADE), `title`, `description`, `order_index`, timestamps.

**`lessons`** — individual video lessons. `id`, `course_id` FK (CASCADE), `module_id` FK (CASCADE), `title`, `description`, `video_url`, `video_provider` ENUM(`youtube`,`vimeo`,`bunny`,`file`,`other`), `thumbnail`, `duration_seconds`, `order_index`, `status` ENUM(`draft`,`published`,`archived`), timestamps.

**`enrollments`** — who may watch what. `id`, `user_id` FK (CASCADE), `course_id` FK (CASCADE), `payment_id` FK (SET NULL — NULL means an admin granted it manually), `status` ENUM(`active`,`pending`,`cancelled`,`expired`), `access_starts_at`, `access_expires_at` (NULL = lifetime), timestamps. **UNIQUE (user_id, course_id)** — the structural guarantee against duplicate enrollment.

**`lesson_progress`** — per-student, per-lesson watch state. `id`, `user_id` FK, `lesson_id` FK, `course_id` FK, `watched_seconds`, `completed` TINYINT, `completed_at`, `last_watched_at`, timestamps. UNIQUE (`user_id`,`lesson_id`).

**`password_reset_tokens`** — `id`, `user_id` FK (CASCADE), `token_hash` (SHA-256 of the raw token; the raw value only exists in the e-mail link), `expires_at`, `used_at`, `created_at`.

**`product_courses`** — many-to-many bridge deciding what a purchase unlocks. `id`, `product_id` FK (CASCADE), `course_id` FK (CASCADE), `created_at`, UNIQUE (`product_id`,`course_id`).

---

## 5. The payment flow, exactly as implemented

**A. Checkout page** — `public/checkout.php`
1. Reads `?p=<slug>`, strips everything outside `[a-z0-9-]`, loads the product via `Product::findBySlug` (active products only).
2. Emits a CSRF token and a per-page-load idempotency key (`bin2hex(random_bytes(16))`) into `window.CHECKOUT`.

**B. Submission** — `public/assets/checkout.js`
3. Builds a JSON payload: slug, csrf, idempotency_key, method (`pix` or `cartao`), name, email, phone (digits), document (digits), and — for card — installments plus the full card object (holder name, **PAN**, expiry, **CVV**, postal code, address number).
4. `POST` to `api/create_payment.php`.

**C. Server-side intake** — `CheckoutController::createPayment()`
5. `Csrf::check` → 419 on failure.
6. `RateLimiter::tooMany('checkout:' . clientIp, RATE_LIMIT_MAX, RATE_LIMIT_WINDOW)` → 429.
7. Validation: name ≥ 3 chars, valid e-mail, 10–11 digit phone, 11-or-14-digit tax ID, non-empty idempotency key; for card: PAN ≥ 13 digits, CVV ≥ 3, holder name present, 4-digit expiry year → 422 with a per-field error map.
8. `Product::findBySlug` → 404 if missing.

**D. Orchestration** — `PaymentService::checkout()`
9. **Idempotency gate**: `Payment::findByIdempotency`. On a hit it logs and returns the existing payment with `duplicate => true` — no second charge.
10. `MoneyCalculator::breakdown()` computes gross, estimated fee, estimated net, and the two producer shares in integer cents (main producer absorbs the rounding remainder).
11. Generates `internal_id` = `PMT-<YYYYMMDD>-<12 hex>`.
12. `gateway->createCustomer()` → Asaas customer id.
13. Card only: `gateway->createCardToken()` → `POST /creditCard/tokenizeCreditCard` with PAN/CVV + holder info + remote IP. The token is what is used from here on; the PAN is never written to the database.
14. **Inserts the payment row as `pendente` before calling the charge API** — so a crash mid-call still leaves a trace.
15. `SplitService::buildSplits()` builds the wallet/percentage list per `ISSUER_MODE`; `PaymentSplit::create()` writes one `previsto` row per party with the expected cents.
16. `gateway->createSplitPayment()` → `POST /payments` with `customer`, `billingType` (`PIX`/`CREDIT_CARD`), `value`, `dueDate` (today), `description`, `externalReference` = internal_id, and the `split` array. For card it adds `creditCardToken`, `remoteIp`, and — when installments > 1 — `installmentCount` + `totalValue` (dropping `value`).
17. Pix only: `GET /payments/{id}/pixQrCode` → base64 PNG + copy-and-paste payload.
18. `Payment::setExternalId()` then `Payment::updateStatus()` with the mapped status.

**E. Front-end outcome**
19. Pix → renders the QR and the copy-paste code, then polls `api/pix_status.php` every 4 s; on `pago` it redirects to `payment_success.php`. The endpoint throttles per IP with a budget derived from that poll interval.
20. Card → redirects immediately to `payment_success.php` / `payment_pending.php` / `payment_failed.php` based on the returned status.

**F. Confirmation** — `public/api/webhook.php` → `WebhookPaymentController::handle()`
21. `verifyWebhook()`: `hash_equals` on the `asaas-access-token` header against `ASAAS_WEBHOOK_TOKEN`. Empty configured token = always reject. Failure → 401 + log.
22. `handleWebhook()` normalises `{event, external_id, internal_id, status, net_value, raw}`.
23. **Webhook idempotency**: key = the Asaas event `id`, falling back to `externalId:status:event`. `PaymentWebhook::record()` inserts it; a UNIQUE violation means "already seen" → responds `200 {ok, duplicate}` so Asaas stops retrying.
24. `Payment::updateStatus()` writes the status and the **real** `netValue` from the gateway (overwriting the earlier estimate) and stamps `paid_at` when paid.
25. If status is `pago`: `PaymentSplit::markReceivedByPayment()` flips the split rows to `recebido`, then `StudentAccessService::grant()` runs.
26. `PaymentWebhook::markProcessed()` records `processado`; any thrown error records `erro` and returns 500 so Asaas retries — idempotency prevents double settlement.

**G. Access grant** — `StudentAccessService::grant()`
27. Returns early if `payments.access_granted_at` is already set.
28. `ProductCourse::coursesForProduct()`; if the product has no course, it logs and returns without stamping `access_granted_at` (leaving room for a manual grant).
29. Finds the user by e-mail or creates one with a NULL password hash; upserts the `students` row with phone and document.
30. `Enrollment::grant()` per course (UNIQUE-key upsert).
31. Stamps `access_granted_at` **before** mailing, so a mail failure cannot revoke access.
32. Issues a 24 h password token when the user has no password, and sends the access e-mail (`acesso-liberado` template) pointing at either `definir-senha.php?token=…` or `login.php`.

**Split semantics.** Asaas applies `percentualValue` to the **net** amount and leaves the remainder with the account that issued the charge — which is why the issuer's own wallet is never sent. `ISSUER_MODE=platform`: your account issues, the split carries 85% + 15%, remainder (a future platform fee) stays with you. `ISSUER_MODE=principal`: the main producer's account issues, the split carries only the co-producer's 15%, and the 85% remainder stays with the issuer automatically.

**Fee estimate vs. reality.** `FEE_*` is only an estimate used for display and the initial row. The authoritative net value arrives in the webhook as `netValue`, and Asaas itself computes the percentage split over that real net figure.

---

## 6. Security controls — verified, one by one

### Present

- **CSRF tokens** — `app/Support/Csrf.php` (`random_bytes(32)` hex, compared with `hash_equals`).
  Enforced in: `CheckoutController::createPayment` (`csrf` in the JSON body, 419), `AuthController::handleLogin` and `handleSetPassword` (`$_POST['_csrf']`), `public/aluno/api/progress.php` (`X-CSRF-Token` header or `_csrf` in body, 419), `admin/inc/admin.php` login, `admin/courses.php`, `admin/students.php`.
  **Not** enforced on `api/pix_status.php` (GET, read-only, throttled instead) or `api/webhook.php` (token-authenticated instead) — both defensible.

- **Rate limiting** — `app/Support/RateLimiter.php` + the `rate_limits` table. Applied to four paths:
  | Path | Key | Budget |
  |---|---|---|
  | Checkout creation | `checkout:<ip>` | `RATE_LIMIT_MAX` per window |
  | Student/buyer login | `login:<ip>` | `RATE_LIMIT_MAX` per window |
  | Admin panel login | `admin_login:<ip>` | `RATE_LIMIT_MAX` per window |
  | Pix status polling | `pixstatus:<ip>` | `ceil(window / 4) + RATE_LIMIT_MAX` |

  The Pix budget is larger on purpose: the checkout page polls every 4 seconds while a QR code is on screen, so reusing the checkout budget would 429 a legitimate buyer within a minute. Derivation and reasoning are in the docblock on `CheckoutController::pixStatus()`.
  **Still not** applied to `definir-senha.php` (token brute force) or the webhook. Keyed on `REMOTE_ADDR` only — no proxy header handling, so behind a load balancer every request shares one bucket.

- **Response security headers** — `app/Support/Security::headers()`, called from the bootstrap so every request that goes through `config/payment.php` gets them. Verified live with curl:
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Strict-Transport-Security: max-age=31536000; includeSubDomains` **only** when the request is HTTPS.
  A full script-src/style-src CSP is deliberately **not** shipped — every page carries inline `<style>` and inline `<script>`, plus Google Fonts and a jsDelivr icon font, so a real policy needs per-request nonces threaded through the views. The reasoning is written out in the class docblock rather than hidden.

- **Session cookie hardening** — `Security::session()`, also called from the bootstrap, before any `session_start()` in the codebase. Sets `httponly`, `samesite=Lax`, and `secure` when the request is HTTPS; also enables `session.use_strict_mode` and `session.use_only_cookies`. Verified live: `Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Lax` over HTTP, and `; secure` added under HTTPS.

- **Log redaction** — `Logger::scrub()` in `app/Support/Logger.php`. Redacts `creditCard`, `number`, `ccv`, `cvv`, `creditCardNumber`, `creditCardCcv`, `holderName`, `creditCardToken`, `password`, recursing into nested arrays. Verified: a nested `creditCard` array has its `number`, `ccv` and `holderName` masked. **`expiryMonth`/`expiryYear`, `cpfCnpj`, `email` and `mobilePhone` are not redacted** and will appear in `storage/logs/asaas.log` when the gateway returns a 4xx.

- **Card tokenization** — `AsaasGateway::createCardToken()` (`POST /creditCard/tokenizeCreditCard`), called from `PaymentService::checkout()`. The charge itself is created with `creditCardToken`. **No card data is ever written to the database.**
  ⚠️ **But tokenization is server-side, not client-side.** The raw PAN and CVV are POSTed from the browser to `public/api/create_payment.php`, validated in `CheckoutController` and passed through `PaymentService` before reaching Asaas. They transit application memory and the web server. This puts the deployment in PCI-DSS SAQ-D rather than SAQ-A territory. Asaas's hosted checkout or a browser-side tokenization widget would be the fix. **This is the single most important caveat in the repository and the README should state it plainly.**

- **Webhook token validation** — `AsaasGateway::verifyWebhook()`. Lower-cases the header names, reads `asaas-access-token`, compares against `ASAAS_WEBHOOK_TOKEN` with `hash_equals`. An empty configured token rejects everything. Runs as step 1 of `WebhookPaymentController::handle()`, before any parsing. Verified live: no token → 401, correct token → 200. There is **no HMAC signature check** — Asaas uses a shared static token, so TLS is what protects it.

- **Idempotency** — three independent layers, all verified live:
  1. *Charge creation*: `payments.idempotency_key` UNIQUE + the `Payment::findByIdempotency` check at the top of `PaymentService::checkout()`.
  2. *Webhook delivery*: `payment_webhooks.idempotency_key` UNIQUE; `PaymentWebhook::record()` catches SQLSTATE `23000`. Replaying the same event returns `{"ok":true,"duplicate":true}`.
  3. *Access grant*: `payments.access_granted_at` short-circuits redelivery, and `enrollments` has UNIQUE (`user_id`,`course_id`).

- **`.htaccess` blocking** — identical `Require all denied` + `Deny from all` in `app/`, `config/`, `database/` and `storage/`. Apache-only, and only effective with `AllowOverride` enabled. **There is no nginx equivalent in the repo.** The real defence is pointing DocumentRoot at `public/`; the `.htaccess` files are the fallback for the shared-hosting case.

- **Password hashing** — `AuthService::setPassword()` uses `password_hash($p, PASSWORD_BCRYPT)`; `AuthService::attempt()` uses `password_verify()` and refuses users whose `status !== 'active'` or whose `password_hash` is empty. Minimum length 8, enforced in `AuthController::handleSetPassword`.

- **Admin credentials** — both login paths are hashed. The database path is a normal `users` row with `role=admin`. The `.env` path uses `ADMIN_PASS_HASH`, a bcrypt hash verified with `password_verify`; leaving it empty disables that path entirely. Verified live: correct password in, wrong password out, empty hash rejects even the correct password, and the 11th attempt returns "Muitas tentativas".

- **Password reset tokens** — 32 random bytes, hex encoded; only the SHA-256 hash is stored; 24 h TTL; single-use via `used_at`; `findValid()` filters on both.

- **Session fixation** — `Auth::login()` calls `session_regenerate_id(true)`. `Auth::logout()` clears `$_SESSION`, expires the cookie and destroys the session.

- **Open-redirect protection** — `AuthController::safeNext()` rejects `//…` and any `scheme://` target, so `?next=` can only be a relative path.

- **SQL injection** — every query uses PDO prepared statements with `ATTR_EMULATE_PREPARES => false`. The only string interpolation into SQL is `Payment::updateStatus` (`$paidAt`, a fixed literal), `User::listAll` (`$where`, a fixed literal), and the two helper closures in `database/setup.php` (table/column names that are hard-coded constants, never user input). `LIMIT` values are bound with `PDO::PARAM_INT`.

- **Output escaping** — every view escapes through an `e()` helper wrapping `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

- **TLS** — cURL peer verification is never disabled (with a bundled-CA fallback path); `SmtpMailer` sets `verify_peer` and `verify_peer_name` to true and supports STARTTLS.

- **Authorisation on lesson progress** — `public/aluno/api/progress.php` checks `Enrollment::isActive($userId, $courseId)` and returns 403 otherwise. Verified live: valid CSRF → 200, bad CSRF → 419, no session → 401.

### Absent — say so plainly

- **`api/pix_status.php` is unauthenticated by design.** It is now throttled, but anyone holding a charge id can still read that charge's status. The id is an opaque gateway identifier that is never listed anywhere, and the buyer has no session while paying, so an ownership model would mean inventing one. The trade-off is written into the method's docblock. Verified live: the 26th call in a 60 s window returns 429.
- **No rate limiting on `definir-senha.php`** — a password-reset token could be brute-forced, though the token is 256 bits of entropy so this is theoretical.
- **No signature (HMAC) verification on webhooks** — shared static token only.
- **No CPF/CNPJ checksum validation** — `Validator::cpfCnpj` checks digit count only.
- **No full CSP.** Only `frame-ancestors` is enforced; see above for why.
- **The three payment-outcome pages get no security headers.** `payment_success.php`, `payment_pending.php` and `payment_failed.php` are pure static HTML and never load the bootstrap, so `Security::headers()` never runs for them. They contain no dynamic output, so the exposure is nil, but a reviewer may notice the inconsistency.
- **`watched_seconds` is client-supplied and trusted.** A student can mark any lesson complete by posting to the progress API. Acceptable for a course platform; worth stating.
- **No audit trail for admin actions.**
- **No CAPTCHA or bot mitigation** on checkout.
- **No account lockout** after repeated failures — only the IP-window limiter.

---

## 7. Environment variables

All are read in `config/payment.php` through `App\Support\Env`. Lookup order for the file itself: `<project-root>/.env`, then `<project-root>/config/.env`; **the first one found wins and the second is never read.**

An explicitly configured value is always honoured, including a falsy one: `RATE_LIMIT_MAX=0` really means zero. An *empty* value (`KEY=`) counts as "not set" for the numeric getters, so it falls back to the default rather than silently becoming `0`.

| Variable | Default | What it does |
|---|---|---|
| `APP_ENV` | `sandbox` | Label only — exposed as `config['env']`, not branched on anywhere in the code. |
| `APP_URL` | `''` | Public base URL of `public/`. Used to build the checkout's `apiBase`, the `checkout.js` script src, all e-mail links, and the target URL of `tools/simulate_webhook.php`. |
| `APP_DEBUG` | `false` | When true, `CheckoutController` returns the raw exception message to the client instead of a generic error. |
| `APP_KEY` | `''` | **Read into `config['app_key']` and never used anywhere else.** Dead configuration. |
| `DB_HOST` | `127.0.0.1` | MySQL host. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_NAME` | `coproducao` | Database name. |
| `DB_USER` | `root` | MySQL user. |
| `DB_PASS` | `''` | MySQL password. |
| `ASAAS_API_KEY` | `''` | Sent as the `access_token` header on every gateway call. |
| `ASAAS_BASE_URL` | `https://sandbox.asaas.com/api/v3` | Gateway base URL (trailing slash trimmed). Production: `https://api.asaas.com/v3`. |
| `ASAAS_WEBHOOK_TOKEN` | `''` | Shared secret compared against the `asaas-access-token` header. Empty = every webhook is rejected with 401. |
| `ISSUER_MODE` | `platform` | `platform` or `principal`. Decides whether the main producer's wallet is included in the split. Any other value behaves as `platform`. |
| `FEE_PIX_FIXED` | `1.99` | Estimated fixed Pix fee, used for the pre-webhook net estimate. |
| `FEE_CARD_PERCENT` | `2.99` | Estimated card fee percentage. |
| `FEE_CARD_FIXED` | `0.49` | Estimated fixed card fee. |
| `RATE_LIMIT_MAX` | `10` | Attempts allowed per window (checkout, both logins; the Pix poll budget is derived from it). |
| `RATE_LIMIT_WINDOW` | `60` | Window length in seconds. |
| `ADMIN_USER` | `admin` | Admin panel username for the `.env` login path. |
| `ADMIN_PASS_HASH` | `''` | **A bcrypt hash, never a plaintext password.** Generate with `php -r "echo password_hash('…', PASSWORD_BCRYPT), PHP_EOL;"`. Empty disables the `.env` login path (the `role=admin` database login still works). |
| `MAIL_DRIVER` | `log` | `log` (writes to `storage/logs/mail.log`) or `smtp`. Anything other than `smtp` falls back to `log`. |
| `MAIL_FROM` | `no-reply@localhost` | Envelope/From address. |
| `MAIL_FROM_NAME` | `Área de Aulas` | From display name; also the brand shown in the e-mail layout header. |
| `SMTP_HOST` | `''` | SMTP server. Empty + `MAIL_DRIVER=smtp` throws. |
| `SMTP_PORT` | `587` | SMTP port. |
| `SMTP_USER` | `''` | SMTP username. Empty skips AUTH LOGIN. |
| `SMTP_PASS` | `''` | SMTP password. |
| `SMTP_SECURE` | `tls` | `tls` (STARTTLS), `ssl` (implicit SMTPS), or empty for cleartext. |

---

## 8. Incomplete, stubbed, or known limitations

**Installation / schema**
1. **No migration runner or versioning.** Files are plain `.sql` and the order is hard-coded in `setup.php` (and mirrored in `schema_full.sql`). There is no `migrations` bookkeeping table, so the installer re-runs every file each time; it is safe because every CREATE is `IF NOT EXISTS` and the one ALTER is guarded by an `information_schema` check, but it does not scale to a real migration history.
2. **`rate_limits` has no migration file of its own** — it is appended to the bottom of `create_payment_webhooks_table.sql`.
3. The `rate_limits` table is only pruned inside the limiter's own window delete, which runs only when the limiter is called. A quiet site accumulates rows until the next call.
4. `database/schema_full.sql` is a one-shot "create from scratch" file: the ALTER in BLOCK 3 fails if the file is applied twice. `setup.php` is the re-runnable path. This is now stated in the file header.

**Tooling / project hygiene**
5. **No `composer.json`, no dependency manifest, no lockfile.** Autoloading is a hand-rolled `spl_autoload_register` in `config/payment.php`.
6. **No tests of any kind** — no PHPUnit, no fixtures, no CI configuration. (The verification described in §9 was performed manually against a real MySQL instance, not committed as a test suite.)
7. **No `.editorconfig`, linter or static-analysis config** (no PHPStan/Psalm/PHP-CS-Fixer).
8. `config/cacert.pem` is referenced by `AsaasGateway::request()` as a CA fallback but **is not present in the repo**. The code degrades gracefully (it only sets `CURLOPT_CAINFO` if the file exists).

**Payments**
9. **Only one gateway exists.** `GatewayFactory` has commented-out placeholders for Pagar.me and Mercado Pago; neither class exists. The interface abstraction is real, but it has never been exercised against a second implementation.
10. **`refundPayment()` is implemented on the gateway and declared on the interface, but nothing ever calls it.** There is no refund endpoint, no admin refund button, and no local state transition for a refund initiated outside the system (beyond the webhook status mapping).
11. **The idempotency key is generated by the browser** (`checkout.php` mints a fresh one per page load). A reload therefore produces a new key, so a user who refreshes and resubmits can create a second charge. It protects against double-clicks and network retries, not against re-submission.
12. **Split settlement is mirrored, not reconciled.** `PaymentSplit::markReceivedByPayment()` copies `expected_cents` into `received_cents`. The real per-wallet amounts are never fetched (Asaas exposes `/payments/{id}/splits`), so `received_cents` is an assumption, not a measurement — and it will drift from reality whenever the estimated fee differs from the real one.
13. **Installment count is passed through unvalidated** — no maximum, no interest handling, no per-product configuration.
14. **No cancellation, chargeback or dispute workflow.** The statuses exist in the enum and `mapStatus()` maps them, but nothing acts on `estornado` / `cancelado` beyond storing them — in particular, **access is never revoked on a refund or chargeback.**
15. Pix status polling has no timeout or backoff: `setInterval` every 4 s runs until the page is closed. The endpoint is now throttled server-side, but the client never gives up on its own.
16. There is no reconciliation job or cron — if a webhook is permanently lost, the payment stays `pendente` forever unless someone opens the checkout page again.

**Course area**
17. **Lesson videos in the seed are placeholder URLs** (`https://example.com/embed/lesson-0N`) with provider `other`, which is what triggers the "simulated" badge in the UI. Nothing plays until real embeds are set in the admin panel.
18. `public/aluno/inc/no_access.php` hard-codes a link to `checkout.php?p=curso-demo` — the demo product slug is baked into the UI.
19. `admin/inc/_lesson_form.php` is a shared partial; there is no bulk import, reordering UI beyond a numeric `order_index`, or media upload — video URLs are typed in by hand.
20. `minha-conta.php` is read-only: a student cannot change their name, e-mail, password or phone from the UI.
21. **No certificate generation**, despite the demo product description mentioning one.
22. `access_expires_at` exists on `enrollments` and is documented as "NULL = lifetime", but **no code ever reads it** — expiry is not enforced anywhere.
23. `enrollments.status` values `pending` and `expired` are settable from the admin UI but only `active` is ever checked.
24. `lessons.thumbnail` is populated by the seed and editable in the admin panel, but **no view ever renders it**.

**Language**
25. Internal status vocabulary and split roles are Portuguese by design and are **persisted values**: `pendente`, `pago`, `recusado`, `cancelado`, `estornado`, `previsto`, `recebido`, `ativo`, `inativo`, `pix`, `cartao`, `produtor_principal`, `coprodutor`, `percentual`. Also the `checkout_slug` `curso-demo`. These were deliberately not renamed. The README should explain the convention once so it does not read as an oversight.
26. The `public/aluno/`, `admin/` and e-mail-body UI is in **Portuguese**, intentionally — that is the end-user market.
27. **Customer-facing error strings inside `app/` are also still Portuguese, on purpose.** The `error`/`errors` values returned by `CheckoutController` and `AuthController` are rendered verbatim by the Portuguese checkout and login pages, so translating them would have shipped English errors into a Portuguese UI. Developer-facing text (exceptions, log lines) is English. The rule is written into the docblocks of both controllers.

**Other code-level notes**
28. `Database::init()` silently no-ops if called twice with different config.
29. `storage/logs` writes use `@file_put_contents`; a permissions problem fails silently and no logs appear.
30. `Http::clientIp()` reads `REMOTE_ADDR` only. Behind a reverse proxy or CDN, every visitor shares the proxy's IP and every rate limiter becomes a global throttle. `Security::isHttps()` does consult `X-Forwarded-Proto`, which is only trustworthy behind a proxy that overwrites it.

---

## 9. Notes on what changed during this cleanup

### Pass 1 — internationalisation
- Code comments, PHPDoc blocks and SQL comments translated to English across `app/**`, `config/payment.php`, `database/setup.php`, `database/migrations/*.sql`, `database/schema_full.sql`, `tools/*.php`, `index.php`.
- CLI `echo`/`fwrite` output in `database/setup.php` and `tools/simulate_webhook.php` translated.
- Column `COMMENT '…'` clauses in the migrations were also translated. They are schema documentation metadata; no code reads them.
- `config/env.example` became `.env.example` at the repo root, with an identical copy at `config/.env.example` (both locations are supported by the bootstrap).
- Seed data replaced with fictional international records. `products.checkout_slug` stays `curso-demo` because `public/aluno/inc/no_access.php` links to it. The course slug changed from `formacao-marketing-digital` to `demo-course` — nothing referenced it.
- The seed blocks embedded in `database/schema_full.sql` were regenerated from the two seed files so the two stay in sync.
- The Portuguese `README.md` was deleted. `.gitignore` and `LICENSE` (MIT, 2026, Carlito Daniel) were added.

### Pass 2 — defect fixes
1. **`database/setup.php` rewritten.** It now runs all 15 migrations in dependency order (checkout tables, then the course-delivery tables that reference them), guards the non-idempotent ALTER with an `information_schema` column check, and seeds both files only when their marker rows (`products.checkout_slug='curso-demo'`, `courses.slug='demo-course'`) are absent. Re-running is a no-op.
2. **`Env::get()` precedence fixed** — was `cache ?? getenv ?: default`, which parses as `(cache ?? getenv) ?: default` and discarded every falsy configured value. Now an explicitly set key is always returned. `Env::load()` no longer marks the reader as loaded when the candidate file does not exist. `Env::int()`/`float()` treat an *empty* value as unset so `RATE_LIMIT_MAX=` cannot become a lockout.
3. **Dead ternary removed** from `public/checkout.php` (`Csrf::check(null) ? Csrf::token() : Csrf::token()` → `Csrf::token()`).
4. **Developer-facing runtime messages translated to English** — exceptions in `Database`, `GatewayFactory`, `AsaasGateway`, `SmtpMailer`, and every `Logger::log()` message. Customer-facing strings stayed Portuguese; see limitation 27.
5. **`api/pix_status.php` throttled** with a poll-aware budget; the endpoint's deliberate lack of authentication is now documented in its docblock instead of being an unexplained gap.
6. **`app/Support/Security.php` added** and wired into the bootstrap: response security headers and session cookie hardening, both no-ops on the CLI. The absence of a full CSP is explained in the class docblock.
7. **Admin panel login rate-limited** via the existing limiter (`admin_login:<ip>`).
8. **`ADMIN_PASS` replaced by `ADMIN_PASS_HASH`**, verified with `password_verify`; an empty value disables that login path. Both `.env.example` copies document how to generate the hash.
9. **Seed artwork generated** with GD into `public/aluno/assets/img/` — one banner, one cover and five lesson thumbnails, plain solid colours with the title drawn on them, nothing branded. All seven paths referenced by the seed now resolve.
10. **`href="#"` placeholders removed** from the three payment-outcome pages. Success now links to `login.php`; pending and failed carry an HTML comment explaining why there is no useful static destination (neither page knows the product slug).

### How pass 2 was verified
- `php -l` on all 71 PHP files: 0 failures.
- Token-stream diff (comments stripped) of every PHP file against the pre-cleanup original: 16 files changed plus 1 new file, and each diff was inspected to confirm it contained only the intended edit.
- A real MariaDB 10.11 instance was started to exercise the installer: fresh run created 15 tables and seeded both files; two further runs skipped the ALTER and both seeds and left row counts unchanged. `mysqldump --no-data` of a `setup.php` database and a `schema_full.sql` database were **byte-identical**, proving the two paths agree.
- PHP's built-in server was used to verify live behaviour: all four security headers present (HSTS only under HTTPS), session cookie flags correct, Pix polling 429s on call 26, admin login accepts the bcrypt password / rejects a wrong one / rejects everything when the hash is empty / 429s on attempt 11, webhook 401s without a token and returns `duplicate:true` on replay, the full webhook→access-grant flow produced the user, enrollment, reset token and e-mail, the student area rendered and the previously-broken banner returned HTTP 200 `image/jpeg`, and the progress API returned 200/419/401 for valid/bad-CSRF/unauthenticated calls.
- The throwaway database, the test `.env` and all generated log files were removed afterwards; the working tree contains no `.env` and no `*.log`.
