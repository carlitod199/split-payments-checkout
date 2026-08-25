# Split Payments Checkout

A payment checkout that splits a single sale between two producers automatically, built on a swappable gateway interface with webhook-driven settlement and a course-delivery area that unlocks when the payment confirms.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1)
![License](https://img.shields.io/badge/License-MIT-blue)

---

## Overview

When two people co-produce and sell a digital product, the revenue has to be divided at the moment the customer pays — not reconciled by hand at the end of the month. This project implements that: a checkout page that accepts Pix or credit card, creates the charge with the revenue split already attached, and grants the buyer access to the product the moment the payment provider confirms it.

It is written in plain PHP 8 with no framework, running against MySQL over PDO. That is a deliberate constraint, not an accident — see [Technical decisions](#technical-decisions).

## Problem

A naive implementation of revenue sharing pays the seller in full and transfers the co-producer's share later. That approach fails in three ways: the money sits in the wrong account, the transfer is a manual step that gets forgotten, and the amounts drift because the provider's fee is not known at checkout time.

There are three further problems that only appear once real money is moving:

- **Double charges.** A customer double-clicks, a mobile network retries a POST, a payment provider redelivers a webhook. Each of these can create a second charge or a second product grant.
- **The fee is unknown when the charge is created.** The percentage split has to be applied to the *net* amount, but the net amount is only final after the provider settles.
- **Vendor lock-in.** Payment provider integrations tend to leak their vocabulary into the entire codebase, so changing provider means rewriting the application.

## Solution

- The charge is created **with the split already attached**, so the provider divides the money at settlement and each party is paid directly.
- The percentage is applied by the provider to the **real net value**, which arrives in the webhook. The fee shown at checkout is explicitly labelled as an estimate, and the estimate is overwritten by the authoritative figure when the webhook lands.
- **Three independent idempotency layers** protect charge creation, webhook delivery and access granting (see [Security](#security)).
- The provider sits behind `PaymentGatewayInterface`. `PaymentService` and the controllers speak only in provider-neutral arrays; nothing outside `AsaasGateway` knows which provider is in use.

## Key features

- **Pix and credit card** checkout, with instalments.
- **Automatic 85 / 15 revenue split**, configurable per product.
- **Two issuing models.** `ISSUER_MODE=platform` — the platform account issues the charge and the split carries both producers. `ISSUER_MODE=principal` — the main producer's account issues, the split carries only the co-producer, and the remainder stays with the issuer automatically. The issuer's own wallet is never sent to the provider, because the provider leaves the remainder with whoever issued the charge.
- **Integer-cent arithmetic** throughout. Floating point never touches a monetary value; the main producer absorbs the rounding remainder so the parts always sum to the whole.
- **Webhook receiver** with shared-token authentication, an idempotency ledger, and a deliberate `500` on failure so the provider retries.
- **Post-payment fulfilment**: buyer account created, enrolment granted, first-access password link e-mailed — all idempotent, all safe to replay.
- **Course delivery area**: modules, lessons, per-lesson progress, resume-where-you-left-off.
- **Admin panel** for payments, products, producers, webhook events, courses and students.

## Architecture

```
Browser
   │  POST /api/create_payment.php   (JSON)
   ▼
CheckoutController ── CSRF ── RateLimiter ── Validator
   │
   ▼
PaymentService  ◄── SplitService        (builds the split from ISSUER_MODE)
   │            ◄── MoneyCalculator     (integer cents, fee estimate, breakdown)
   │
   ▼
PaymentGatewayInterface
   │
   └── AsaasGateway ──► payment provider REST API
                                │
                                │  webhook
                                ▼
                    WebhookPaymentController
                                │
                                ├── PaymentWebhook   (idempotency ledger)
                                ├── Payment / PaymentSplit  (settle)
                                └── StudentAccessService    (grant access)
```

Four layers, no framework:

| Layer | Responsibility |
|---|---|
| `app/Support/` | Framework-free primitives: env reader, PDO singleton, session guard, CSRF, rate limiter, redacting logger, money maths, validation, security headers. |
| `app/Models/` | Thin static data access, one class per table. Raw SQL over prepared statements; every method returns arrays. No ORM, no entities. |
| `app/Services/` | The domain. Payments, split construction, access granting, authentication, mail. |
| `app/Controllers/` | HTTP intake and response. No business logic. |

## Tech stack

**Language** PHP 8.1+ (`declare(strict_types=1)` in every file)
**Database** MySQL 5.7 / 8.0, PDO with emulated prepares disabled
**Front end** Vanilla JavaScript, no build step
**Integration** REST over cURL, TLS peer verification always on
**Mail** Hand-written SMTP client (SMTPS and STARTTLS) with a log driver for development
**Dependencies** None. Autoloading is a small PSR-4-style `spl_autoload_register`.

## Project structure

```
├── app/
│   ├── Controllers/          Checkout API, webhook receiver, auth forms
│   ├── Models/               One class per table, static, array-returning
│   ├── Services/
│   │   ├── Payments/         Interface, gateway, factory, split, orchestrator
│   │   ├── Access/           Post-payment fulfilment
│   │   ├── Auth/             HTTP-free authentication core
│   │   ├── Mail/             Driver interface + SMTP and log drivers
│   │   └── Student/          Dashboard view model
│   ├── Support/              Env, Database, Auth, Csrf, RateLimiter, Logger,
│   │                         MoneyCalculator, Validator, Http, Security
│   └── Views/emails/         Transactional e-mail templates
├── admin/                    Minimal admin panel
├── config/
│   ├── payment.php           The single bootstrap: autoloader, .env, config array
│   └── .env.example
├── database/
│   ├── migrations/           15 SQL files
│   ├── schema_full.sql       The whole schema in execution order
│   ├── seed_sandbox.sql      Demo producers and product
│   ├── seed_ead_sandbox.sql  Demo course, modules, lessons
│   └── setup.php             Idempotent CLI installer
├── public/                   Web root — checkout, auth, outcome pages, JSON API
│   ├── api/                  create_payment · pix_status · webhook
│   └── aluno/                Student course area
├── storage/logs/             Runtime logs (git-ignored)
└── tools/simulate_webhook.php
```

## Database

Fifteen tables. The ones that carry the design:

| Table | Purpose |
|---|---|
| `producers` | Who gets paid. Holds the provider `wallet_id` used in the split. |
| `products` | What is sold and how revenue divides. A `CHECK` constraint forces the two percentages to sum to 100. |
| `payments` | One row per checkout attempt. `idempotency_key` is `UNIQUE` — the structural guarantee against a double charge. |
| `payment_splits` | Forecast and settlement of each party's cut: `expected_cents` at creation, `received_cents` at settlement. |
| `payment_webhooks` | Raw audit log **and** idempotency ledger. Its `UNIQUE` key is what makes webhook redelivery harmless. |
| `enrollments` | Who may watch what. `UNIQUE (user_id, course_id)` — the structural guarantee against a duplicate grant. |
| `password_reset_tokens` | Stores only the SHA-256 hash of the token; the raw value exists solely in the e-mail link. |

Full column-level documentation is in `database/migrations/`.

## API

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/checkout.php?p=<slug>` | Checkout page. Emits a CSRF token and a per-load idempotency key. |
| `POST` | `/api/create_payment.php` | Creates the Pix or card charge with the split attached. `419` CSRF · `429` rate limit · `422` validation · `404` unknown product · `500` gateway error. |
| `GET` | `/api/pix_status.php?external_id=…` | Re-reads the charge status from the provider and persists it. Rate limited. |
| `POST` | `/api/webhook.php` | Provider webhook receiver. `401` without a valid shared token. Returns `200` on a duplicate so the provider stops retrying, `500` on a processing error so it retries. |
| `POST` | `/aluno/api/progress.php` | Saves lesson progress. `403` when the student has no active enrolment for that lesson's course. |

## Security

Verified control by control against the code, and stated honestly in both directions.

**Present**

- **CSRF** — 32 random bytes per session, compared with `hash_equals`, enforced on every state-changing form and on the checkout and progress APIs.
- **Rate limiting** — database-backed sliding window on checkout creation, student login, admin login and the Pix status endpoint.
- **Idempotency, three layers** — `payments.idempotency_key` UNIQUE plus a pre-flight lookup; `payment_webhooks.idempotency_key` UNIQUE catching SQLSTATE 23000; `payments.access_granted_at` plus `enrollments` UNIQUE.
- **Card data never persisted** — the card is exchanged for a provider token and the charge is created with the token. No PAN or CVV reaches the database.
- **Log redaction** — the logger recursively masks card number, CVV, holder name, card token and password before writing.
- **Webhook authentication** — shared token compared with `hash_equals`; an unset token rejects everything rather than accepting everything.
- **Password handling** — bcrypt via `password_hash`; reset tokens are single-use, expiring, and stored only as a SHA-256 hash.
- **Session hardening** — `httponly`, `SameSite=Lax`, `secure` under HTTPS, and `session_regenerate_id(true)` on login.
- **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`, `frame-ancestors 'none'`, `Referrer-Policy`, and HSTS only over HTTPS.
- **SQL injection** — prepared statements everywhere, emulated prepares disabled, `LIMIT` values bound as integers.
- **Open redirect** — the post-login `?next=` target rejects absolute and protocol-relative URLs.
- **TLS** — peer verification is never disabled, on the payment API or the SMTP client.

**Known limitations — stated because a reviewer will find them anyway**

- **Card tokenization is server-side, not browser-side.** The raw card number and CVV are POSTed to this application and pass through its memory before reaching the provider. They are never written to disk or database, but they do transit the server, which places a deployment in PCI-DSS SAQ-D rather than SAQ-A. The fix is the provider's hosted checkout or a browser-side tokenization widget; it is not implemented here.
- The webhook uses a **shared static token, not an HMAC signature** — that is what the provider offers, so TLS is doing the real work.
- Rate limiting keys on `REMOTE_ADDR` only. Behind a proxy or CDN every visitor shares one bucket.
- Tax-ID validation checks digit count, not the checksum.
- Lesson progress is client-supplied and trusted; a student can mark any lesson complete.
- No audit trail for admin actions, no CAPTCHA on checkout, no full Content-Security-Policy (the checkout page uses inline script).

## Installation

Requires PHP 8.1+ with `pdo_mysql`, `curl` and `gd`, and a MySQL 5.7+ server.

```bash
git clone https://github.com/carlitod199/split-payments-checkout.git
cd split-payments-checkout
cp .env.example .env          # then fill it in
php database/setup.php --seed
```

The installer is idempotent: it creates the database, runs all fifteen migrations in dependency order, applies the ALTER, and seeds demo data without duplicating it on a second run. A schema built by `setup.php` is byte-identical to one built from `schema_full.sql`.

Point the web server's `DocumentRoot` at `public/`. If you cannot, the project root has a redirect and `app/`, `config/`, `database/` and `storage/` each carry a deny-all `.htaccess` as a fallback — but moving the document root is the real defence.

## Environment variables

Copy `.env.example` and fill it in. It is read from the project root first, then from `config/`; the first file found wins.

| Variable | Purpose |
|---|---|
| `APP_URL` | Public base URL of `public/`. Used for asset paths and e-mail links. |
| `APP_DEBUG` | When true, gateway exceptions are returned to the client instead of a generic error. |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | Database connection. |
| `ASAAS_API_KEY` | Sent as the `access_token` header on every provider call. |
| `ASAAS_BASE_URL` | Provider base URL. Sandbox by default. |
| `ASAAS_WEBHOOK_TOKEN` | Shared secret compared against the inbound webhook header. Empty rejects every webhook. |
| `ISSUER_MODE` | `platform` or `principal` — decides whether the main producer's wallet is included in the split. |
| `FEE_PIX_FIXED` `FEE_CARD_PERCENT` `FEE_CARD_FIXED` | Fee **estimates** for display only. The real figure comes from the webhook. |
| `RATE_LIMIT_MAX` `RATE_LIMIT_WINDOW` | Attempts per window, in seconds. |
| `ADMIN_USER` `ADMIN_PASS_HASH` | Admin panel login. The hash is generated with `password_hash`; an empty value disables this login path. |
| `MAIL_DRIVER` `MAIL_FROM` `MAIL_FROM_NAME` `SMTP_*` | Mail delivery. `log` writes to `storage/logs/mail.log` instead of sending. |

No value in `.env.example` is a real credential.

## Running locally

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000/checkout.php?p=curso-demo`.

To exercise the webhook path without the provider:

```bash
php tools/simulate_webhook.php
```

Use the provider's sandbox credentials. Sandbox Pix charges can be marked paid from the provider's dashboard, which triggers a real webhook against your local endpoint if you tunnel it.

## Technical decisions

**No framework.** The target deployment is managed shared hosting with no shell access, no Composer step and no process manager. A framework would have added a deployment story the environment cannot support. The cost is that everything a framework gives you — routing, DI, migrations, validation — is either hand-written or absent, and that cost is visible in the limitations below.

**No ORM.** Models are static classes returning arrays, with the SQL written out. For a schema this size the indirection of an ORM buys little, and having the exact query in front of you matters when you are reasoning about a `UNIQUE` violation as a control-flow mechanism. The trade-off is no type safety and no query composition.

**Money in integer cents.** Every internal calculation is `int`; reals appear only at the boundary for display. `MoneyCalculator::breakdown()` gives the co-producer the rounded share and the main producer the remainder, so the two parts always reconstruct the whole exactly.

**The fee is an estimate until the webhook.** The provider's fee is not knowable at charge time. Rather than pretending otherwise, the estimate is stored, labelled, and then overwritten with the provider's `netValue`. The percentage split is computed by the provider over the real net, so the money is correct even when the estimate was not.

**The issuer's wallet is never sent.** The provider applies each split percentage to the net and leaves the remainder with the issuing account. Sending the issuer's own wallet would double-count it. This single fact is why `ISSUER_MODE` exists and why the two modes send different split arrays.

**A gateway interface with one implementation.** The abstraction is real — `PaymentService` and the controllers never see a provider-specific field. But it has never been exercised against a second provider, so it is a hypothesis about where the seams are, not a proven one.

**`UNIQUE` violations as control flow.** Webhook idempotency is enforced by catching SQLSTATE 23000 on insert rather than by a check-then-insert. The check-then-insert version has a race; the database constraint does not.

**Persisted values stay in Portuguese.** Status values (`pendente`, `pago`, `estornado`), split roles (`produtor_principal`, `coprodutor`) and method names (`pix`, `cartao`) are stored in the database and were written in Portuguese. They were deliberately not renamed during this cleanup — a cosmetic rename of persisted enum values is a migration risk with no engineering benefit. Code comments, documentation and developer-facing log messages are in English; the customer-facing UI and transactional e-mails remain in Portuguese, which is the market this was built for.

## Challenges

**Making replay safe at three different layers.** Charge creation, webhook delivery and access granting each fail differently and each can be replayed by a different actor — the customer's browser, the payment provider, an operator clicking a manual grant. They needed three separate mechanisms rather than one, and the access grant in particular has an ordering constraint: `access_granted_at` is stamped *before* the e-mail is sent, so that a mail server failure can never revoke access the buyer already paid for.

**Deciding what the split is applied to.** The provider's documentation makes the percentage look like it applies to the gross amount. It does not — it applies to the net, and the remainder stays with the issuer. Getting this wrong produces a split that is quietly a few percent off in the co-producer's favour, in a way that only shows up in a monthly reconciliation. The `ISSUER_MODE` design falls directly out of this.

**Failing loudly at the right times.** The webhook returns `500` on a processing error specifically so the provider retries — the instinct to always return `200` would silently lose payments. Conversely, a mail failure inside the access grant is swallowed and logged, because a bounced e-mail must never break settlement.

## Limitations and future improvements

Honest list of what this repository does not do.

- **No automated tests and no CI.** The largest gap. Idempotency and money arithmetic are exactly the kind of logic that should be covered by tests.
- **No Composer manifest** — autoloading is hand-rolled and there is no dependency or lock file.
- **No migration versioning.** The installer is idempotent, but there is no bookkeeping table, so there is no way to ask a database which migrations it has.
- **One gateway.** Pagar.me and Mercado Pago are placeholders in the factory, not implementations.
- **`refundPayment()` is implemented on the gateway and declared on the interface, but nothing calls it.** There is no refund endpoint and no admin action.
- **Access is not revoked on refund or chargeback.** The statuses are stored and mapped; nothing acts on them.
- **Splits are mirrored, not reconciled.** On settlement, `received_cents` is copied from `expected_cents`. The provider exposes the real per-wallet amounts and they are never fetched, so this figure is an assumption that will drift whenever the fee estimate was wrong.
- **The idempotency key is minted by the browser** per page load. It protects against double-clicks and network retries, not against a user reloading and resubmitting.
- **No reconciliation job.** If a webhook is permanently lost, the payment stays `pendente` until someone reopens the checkout page.
- `access_expires_at` exists on enrolments and is never read — expiry is not enforced.
- Pix status polling has no backoff and no timeout; it runs every four seconds until the page closes.

## License

MIT — see [LICENSE](LICENSE).
