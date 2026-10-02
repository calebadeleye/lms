# NAI TALK LMS — Architecture

Two independently deployable applications, connected only through a documented, versioned REST API.

```
naitalk-lms-backend/    Laravel 11, PHP 8.3 — source of truth
naitalk-lms-frontend/   Next.js (App Router), TypeScript — presentation + BFF auth
```

The frontend never touches the database and never imports backend source. All state crosses the
boundary through `GET/POST https://<app-domain>/api/v1/...` calls.

## 0. Single organization (2026-07-29 refactor)

This app originally served **multiple** white-labelled organizations as a multi-tenant SaaS platform
(hostname-resolved tenants, per-tenant branding/custom domains, platform-level billing plans, tenant
data export/import, scheduled tenant offboarding). The client that inspired the build — **HR GEMs**
(Great.Excellent.Minds Coach Network) — is a single coaching community, not a reseller of
white-labelled platforms, so all of that multi-tenant machinery was a solution to a problem this
product doesn't have. It has been **fully removed**, not hidden behind a hardcoded default tenant:

- `tenant_id` is gone from every table (was ~45 tables) and from every model.
- The tenant-resolution middleware, `TenantContext`, and the `BelongsToTenant` global-scope trait are
  deleted outright.
- Per-tenant branding, custom-domain management, platform admin (tenant CRUD, SaaS plans/billing,
  tenant export/import, scheduled tenant deletion) are deleted, not adapted.
- Branding is now a **static constant** (`naitalk-lms-frontend/src/lib/branding.ts`) — HR GEMs' deep
  plum (`#3B0F32`) / gold (`#E8A33D`) palette. Changing the logo or colours means editing that file
  and redeploying; there is deliberately no self-serve branding admin UI anymore.
- Roles collapsed from a platform-vs-tenant split (`platform_staff` + `tenant_users` + nullable
  `roles.tenant_id`) to a single flat set on `users` directly (§3).

Sections below describe the app **as it exists now** — single organization, no tenancy concept
anywhere in the codebase or vocabulary. If you're looking for the old multi-tenant design, it's in
git history prior to the `single-org-refactor` branch, not in this document.

### Membership onboarding + approval gate

The client's real-world onboarding was a manual WhatsApp broadcast + Google Form: a joiner
self-attests to 4 qualitative requirements, submits a form and a welcome photo, and an organizer
manually confirms them ("grand welcome") before they're really "in." This is now real product
behaviour, not a manual process:

- Registration (`POST /api/v1/auth/register`, multipart) collects name/email/password **and** the 4
  requirement acknowledgements plus an optional welcome photo and optional "why are you joining"
  note, in one submission — `AuthController::register()` creates the `User` (`status = pending`) and
  a `membership_applications` row in a single transaction.
- A `membership_applications` row (`ack_*` × 4 booleans, `photo_path` nullable, `motivation` nullable,
  `status` pending\|approved\|rejected, `reviewed_by`/`reviewed_at`/`review_note`) is the review
  record; `users.status` (pending\|active\|inactive\|rejected) is the actual **access gate**, kept in
  sync by `MembershipApplicationService::approve()/reject()` — the only writer of both, in one
  transaction, so they can't drift.
- `EnsureMemberApproved` middleware (alias `approved`) 403s with `{code: membership_pending}` or
  `{code: membership_rejected}` on every member-action route unless `users.status === 'active'`.
  Applied alongside `verified` on the authenticated student-action route group — not on `/auth/*`
  (a pending user must still reach `/auth/me`, `/auth/application`, logout, email verification) and
  not on `/admin/*` (staff/owner accounts are provisioned pre-approved, never self-registered).
- `GET /api/v1/auth/application` returns the caller's own application status so the frontend can show
  a pending/rejected holding page (`/onboarding/pending`) without needing `members.approve`.
- The review queue (`/api/v1/admin/applications*`, gated on the `members.approve` permission) lists
  pending applications, shows the full detail (acknowledgements, welcome photo, motivation), and
  approves or rejects (reject requires a note). Approval sends `MembershipApprovedNotification`
  ("You're in!" welcome email).
- The welcome photo is **not** publicly readable like a course thumbnail — `MemberPhotoController`
  (`GET /api/v1/members/{userId}/photo`) is auth-gated to the owning user or a `members.approve`
  holder, since it's a photograph of a real person submitted for internal review, not a marketing
  asset.
- Login only rejects an `inactive` (deactivated) account outright — `pending`/`rejected` users can
  still log in (so they can check their status later); `EnsureMemberApproved` is what actually blocks
  their access to courses/community.

## 1. Backend structure

Modular monolith under `app/Domain/{Area}` (Identity, Learning, Membership, Commerce, Coaching, Site,
Audit) plus `app/Support` for cross-cutting concerns (`PermissionCatalog`, API middleware). Each
domain owns its own `Models/`, `Http/Controllers/`, `Services/`. There is no `Tenancy` or `Platform`
domain anymore — `Audit` (audit log) and `Site` (public homepage testimonials) are what's left of the
old `Platform`/`Tenancy` domains after removing everything that only existed to serve multiple tenants.

## 2. Authentication — Backend-for-Frontend

1. Browser submits credentials to a **Next.js route handler** (`/app/api/auth/login/route.ts`),
   never directly to Laravel.
2. The Next.js server calls `POST {BACKEND_SERVER_URL}/api/v1/auth/login` server-to-server and
   receives a Sanctum personal-access token.
3. Next.js encrypts `{ userId, token, expiresAt, ... }` and sets it as the payload of an **HttpOnly,
   Secure, SameSite=Lax** cookie. The raw Sanctum token never reaches client JS.
4. Every subsequent frontend data call from a Client Component goes through a Next.js proxy route
   (`/app/api/v1/[...path]/route.ts`) which decrypts the cookie server-side, attaches
   `Authorization: Bearer <token>`, forwards to Laravel, and relays the response. Server Components
   call the backend directly via `apiFetch()` (`src/lib/api-server.ts`) instead of round-tripping
   through this app's own routes.
5. MFA (TOTP) hooks into the same login endpoint as a second challenge step. Session revocation lists
   a user's `user_sessions` and lets them kill any of them, invalidating the Sanctum token.

Both sides authenticate an internal hop with a shared `X-Internal-Secret` header
(`VerifyFrontendSecret` middleware) — this replaces what used to also carry a per-tenant
`X-Tenant-Hostname` header; there's only one organization now; the header is gone entirely rather
than left as unused plumbing.

## 3. Roles & permissions

```
permissions      (id, key unique e.g. "courses.publish", scope enum[platform,tenant])
roles            (id, name, slug unique, is_system)
role_permissions (role_id, permission_id)
users            (..., role_id, status[pending,active,inactive,rejected], joined_at,
                   approved_at, approved_by)
```

One flat role set — `owner`, `administrator`, `finance-manager`, `content-manager`, `instructor`,
`coach`, `support-agent`, `student` — replacing the old platform-vs-tenant split (`platform_staff` +
`tenant_users` + nullable `roles.tenant_id`). `users.role_id`/`status` directly is what used to be a
separate `tenant_users` membership row; there's no longer a distinct "platform staff" concept to keep
structurally apart from it.

`PermissionCatalog` (`app/Support/Identity/PermissionCatalog.php`) is the single source of truth for
permission keys and each role's defaults; `PermissionSeeder` reads it. `PermissionService` resolves a
user's effective permission keys (one method, one cache key per user — no more tenant-vs-platform
branching) and is what `Policy` classes and the `permission:{key}` route middleware both call — no
`$user->role === 'admin'` string comparisons in controllers.

## 4. API conventions

- Prefix: `/api/v1/...`. Breaking changes ship as `/api/v2` alongside v1, never in place.
- Envelope: `{ "data": ..., "meta": { "request_id": "...", "pagination"?: {...} }, "errors"?: [...] }`.
- Validation failures: `422` with `{ "errors": { "field": ["message"] } }`.
- Every response carries `X-Request-Id`; it's also echoed in `meta.request_id` for support debugging.
- Financial POST endpoints (orders, refunds) require an `Idempotency-Key` header; the middleware
  persists `(key, request_hash) -> response` and replays it on retry instead of re-executing.

## 5. Branding

```ts
// naitalk-lms-frontend/src/lib/branding.ts
export const BRANDING: BrandingConfig = { tenant: {...}, branding: {...}, domain: {...} };
```

A static constant, not a database-backed lookup. `RootLayout` reads it directly (no fetch, no
request-time resolution) and injects `--brand-primary/secondary/accent/font` CSS custom properties;
`manifest.ts` reads the same constant for the PWA manifest. Field names deliberately mirror the old
per-tenant config shape (`config.tenant.name`, `config.branding.logo_url`, ...) so most call sites
needed no change beyond swapping the import.

HR GEMs palette: `primary_color` `#3B0F32` (deep plum), `accent_color` `#E8A33D` (gold, primary
CTAs), `secondary_color` `#F5B84B` (lighter gold). No logo/hero image asset has been supplied yet —
both are `null` in `branding.ts`, and every consumer already falls back gracefully (an initial-letter
badge in the dashboard sidebar, a plain-background hero on the homepage).

Homepage testimonials are the one piece of "branding" that's still data-backed, not static — they're
genuinely dynamic content an admin adds over time. `testimonials` table + `Testimonial` model
(`app/Domain/Site/`), listed publicly at `GET /api/v1/testimonials`, managed at
`POST/DELETE /api/v1/admin/testimonials` behind `settings.manage`.

## 6. Payments

`PaymentProviderInterface` (`initializePayment, verifyPayment, createSubaccount, createSubscription,
cancelSubscription, refundPayment, verifyWebhook, fetchTransaction, fetchSettlement`) with
`PaystackPaymentProvider` / `FlutterwavePaymentProvider` implementations, selected per
`payment_configs.provider`.

```
payment_configs (provider, mode[client_owned,managed], public_key, secret_key_encrypted,
                  webhook_secret_encrypted, webhook_token unique, subaccount_code, environment,
                  commission_percent nullable, fee_bearer[organization,learner,platform], status,
                  last_verified_at)
```

Single-row config table (no `tenant_id` — there's only one organization's gateway to configure).
`mode = client_owned` uses HR GEMs' own Paystack/Flutterwave credentials, money goes straight to
them. `mode = managed` — **kept** through the refactor, a deliberate decision, not an oversight —
lets NAI TALK collect payments on HR GEMs' behalf and remit net of `commission_percent` (null =
platform default `DEFAULT_MANAGED_COMMISSION_PERCENT`). `fee_bearer` decides who the provider's own
processing fee is charged to, resolved at checkout time by `CheckoutService`, shown to the learner
before payment. Secret keys are never returned by any API response after saving —
`PaymentConfigController` masks them (`pk_live_ab****ef`).

The old *platform-billing-vs-tenant-commerce* duality (NAI TALK charging tenants a SaaS fee,
separately from tenants charging their own learners) is gone along with the rest of the SaaS-plans
subsystem — `payments.manage`/managed-mode here is solely about who HR GEMs' own learners pay
through, not a second parallel billing relationship.

## 7. Webhook processing — verified, idempotent, queued

```
POST /api/v1/webhooks/{provider}/{webhookToken}
```

`webhookToken` (never a hostname — webhooks come directly from Paystack/Flutterwave's servers, never
through a browser) resolves the `payment_configs` row, which carries the correct encrypted secret for
signature verification. Flow: resolve config → verify signature → idempotency check against
`webhook_events.provider_event_id` (unique index; a duplicate delivery short-circuits to 200 without
reprocessing) → persist the raw event → **acknowledge immediately** → dispatch
`ProcessPaymentWebhookJob` on the queue. The job never trusts the webhook payload's amount or status
by itself — it calls `verifyPayment()`/`fetchTransaction()` against the provider's API directly,
matches the result to the `Order` by `provider_reference`, and only then transitions `Order`
`pending -> paid` (status-guarded, so redelivery after the job already ran is a no-op) and calls
`OrderFulfillmentService::fulfill()` to create the actual entitlement exactly once.

## 8. Learning domain

```
course_categories   (name, slug)
courses             (category_id, title, slug, excerpt, description, thumbnail_path, promo_video_path,
                      status[draft,published], pricing_type[free,paid,membership_only], price_cents,
                      currency, difficulty_level, tags JSON, prerequisite_course_ids JSON,
                      drip_type[none,scheduled], certificate_enabled, published_at)
course_instructors  (course_id, user_id, role[primary,co_instructor])
course_modules      (course_id, title, sort_order)
lessons             (course_module_id, title, type[video,rich_text,audio,file,external_link,quiz,assignment,live],
                      content JSON, video_path, duration_seconds, is_preview, is_mandatory,
                      available_after_days nullable, sort_order)
enrolments          (course_id, user_id, status[active,completed,cancelled], enrolled_at, completed_at, source)
lesson_progress     (enrolment_id, lesson_id, status[not_started,in_progress,completed],
                      video_position_seconds, completed_at)
quizzes             (lesson_id, passing_score_percent, max_attempts, time_limit_minutes, randomize_questions)
quiz_questions      (quiz_id, type[multiple_choice,multiple_answer,true_false,free_text], question_text, points, sort_order)
quiz_options        (quiz_question_id, option_text, is_correct, sort_order)
quiz_attempts       (quiz_id, user_id, enrolment_id, attempt_number, started_at, submitted_at, score_percent, passed)
quiz_answers        (quiz_attempt_id, quiz_question_id, selected_option_ids JSON, free_text_answer, is_correct, points_awarded)
assignments         (lesson_id, title, instructions, max_points, due_date)
assignment_submissions (assignment_id, user_id, enrolment_id, content_text, file_path, submitted_at,
                         grade, feedback, graded_at, graded_by)
reviews             (course_id, user_id, rating, comment)
wishlists           (user_id, course_id)
```

- **Progress is server-computed, never trusted from the client.** `ProgressService` clamps a reported
  video position to the lesson's known `duration_seconds`, requires ≥90% watched before marking a
  video lesson complete, and computes course completion from `lesson_progress` rows against the
  course's mandatory lessons — the frontend only ever sends "watched to second N," never "lesson X is
  complete." `CourseCompletionService` is the only place that flips an enrolment to `completed`.
- **Drip scheduling** uses `lessons.available_after_days` (nullable) checked against
  `enrolment.enrolled_at` at read time.
- File uploads (course thumbnails, lesson material, the membership-application welcome photo) share
  one storage disk, `uploads` (`config/filesystems.php`) — renamed from the old per-tenant `tenants`
  disk; paths no longer carry a `tenants/{uuid}/` prefix.

## 9. Commerce, memberships, coaching

### Commerce schema — deliberately no separate `products`/`prices` tables

`Course`, `learner_membership_plans`, and `coaching_services` each carry their own authoritative
price — an extra `products`/`prices` indirection layer would duplicate that data for no behavioural
gain (no bundling, no multi-currency price lists). `order_items` references the purchased thing
directly and polymorphically (`itemable_type`/`itemable_id`), snapshotting `name` and
`unit_price_cents` at purchase time — an order's record of what was sold never changes even if the
course's price changes tomorrow.

```
orders               (user_id, status[pending,paid,failed,refunded,partially_refunded,cancelled],
                       currency, subtotal_cents, fee_cents, total_cents, payment_mode, provider,
                       provider_reference unique, idempotency_key, paid_at)
order_items          (order_id, itemable_type, itemable_id, name, unit_price_cents, quantity)
payments             (order_id, provider, provider_reference unique, status, gross_amount_cents,
                       provider_fee_cents, platform_commission_cents, org_net_cents, fee_bearer,
                       paid_at, raw_response JSON — secrets stripped)
payment_allocations  (payment_id, allocatable_type, allocatable_id, amount_cents)
refunds              (payment_id, amount_cents, reason, status, provider_reference, processed_by, processed_at)
webhook_events       (provider, event_type, provider_event_id unique per provider, payload JSON,
                       status, processed_at, error_message)
```

`payment_allocations` is deliberately distinct from `order_items`: `order_items` is the **commercial**
record (what was sold), `payment_allocations` is the **fulfillment** record (which entitlement rows —
an enrolment, a `learner_subscriptions` row, a confirmed booking — resulted from this specific
payment).

### Memberships

```
learner_membership_plans (name, slug, billing_period[monthly,annual,free], price_cents, currency,
                           benefits JSON, is_active)
learner_subscriptions    (user_id, plan_id, status[active,cancelled,past_due,expired],
                           current_period_start, current_period_end, cancel_at_period_end, cancelled_at)
```

A course with `pricing_type = membership_only` is unlocked by **any** active `learner_subscriptions`
row — not tied to a specific plan tier. Renewal/failed-payment handling is a scheduled command
(`memberships:process-renewals`) — each renewal is a fresh charge attempt, consistent across both
payment providers (Flutterwave has no native subscription-webhook equivalent to Paystack's).

### Coaching

```
coaches             (user_id, bio, title, years_experience, timezone, is_active)
coaching_services   (coach_id, title, description, session_type[one_to_one,group], duration_minutes,
                      price_cents, currency, is_free, max_participants, is_active)
availability_rules  (coach_id, day_of_week, start_time, end_time, timezone)
coaching_sessions   (coaching_service_id, coach_id, scheduled_start, scheduled_end, meeting_url,
                      status, notes)
bookings            (coaching_session_id, user_id, status, booked_at, cancelled_at, cancellation_reason)
session_attendance  (coaching_session_id, user_id, attended, joined_at, notes)
```

`coaching_sessions` is the schedulable occurrence; `bookings` is one learner's reservation against
it. `BookingService` rejects a new one-to-one session that overlaps an existing `coaching_sessions`
row for the same coach, or falls outside that coach's `availability_rules` for the requested
day/time — real conflict detection, not just a UI hint.

> **Not implemented**: an earlier draft of this document described a community domain (channels,
> posts, comments, reactions, content moderation). No such tables, models, or routes exist in the
> codebase — that was stale documentation, not a removed feature. If community features are wanted,
> they're new work.

## 10. Certificates

```
certificates (user_id, course_id nullable, enrolment_id nullable unique, certificate_number unique,
               verification_code uuid unique, recipient_name, course_title, completed_at, issued_at,
               revoked_at, revoked_reason, metadata JSON)
```

`CourseCompletionService::recompute()` (the only place an enrolment flips to `completed`) is also the
only place a certificate gets auto-issued — `CertificateService::issueForEnrolment()` runs right after
the status transition, gated on `course.certificate_enabled` and idempotent (`firstOrCreate` on
`enrolment_id`). `recipient_name`/`course_title` are snapshotted at issue time rather than joined
live: a certificate must keep saying what it said when it was earned even if the learner later
changes their display name or the course is renamed. `certificate_number` is a human-readable
sequence; `verification_code` is a separate globally-unique UUID, deliberately unguessable, so a
verifier can't enumerate other learners' certificates.

**Verification is public**, no auth required: `GET /api/v1/certificates/verify/{code}` resolves by
`verification_code` alone (an employer scanning a printed QR code has no session) and returns
recipient name, course title, dates, and a `valid` boolean (`false` once `revoked_at` is set — the
row is never deleted, so a revoked certificate still resolves and visibly shows revoked rather than
404ing).

**No server-side PDF/QR generation.** The certificate is a server-rendered, print-styled frontend
page (`/certificates/{code}`) — "Save as PDF" is the browser's own print dialog. The QR code is
generated client-side.

## 11. What was removed in the single-org refactor, for reference

Deleted outright, not adapted: `app/Domain/Tenancy/` (tenant resolution middleware, `TenantContext`,
`BelongsToTenant`, branding/domain/export controllers), `app/Domain/Platform/` (platform tenant
CRUD, platform auth, managed-payments oversight — `AuditLog`/`AuditLogger` were salvaged into a new
`app/Domain/Audit/`), `app/Domain/Billing/` (platform SaaS plans/subscriptions/usage-entitlements —
this app has no second organization to sell a plan to), `TenantUser`/`PlatformStaff` models,
`ProcessScheduledTenantDeletions` (+ the two other now-orphaned scheduled commands it shipped
alongside), and the entire `naitalk-lms-frontend/src/app/platform/` route tree plus its admin
branding/domains pages. ~45 migrations lost their `tenant_id` column and tenant-scoped indexes;
`tenant_payment_configs` → `payment_configs`, `tenant_testimonials` → `testimonials`. Frontend
`--tenant-*` CSS variables were renamed `--brand-*` throughout; the `X-Tenant-Hostname` BFF header is
gone. See git history on `single-org-refactor` for the full diff.
