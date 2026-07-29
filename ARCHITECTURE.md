# NAI TALK LMS — Architecture

Two independently deployable applications, connected only through a documented, versioned REST API.

```
naitalk-lms-backend/    Laravel 11, PHP 8.3 — source of truth
naitalk-lms-frontend/   Next.js (App Router), TypeScript — presentation + BFF auth
```

The frontend never touches the database and never imports backend source. All state crosses the
boundary through `GET/POST https://api.<neutral-domain>/api/v1/...` calls, documented via OpenAPI.

## 1. Tenancy model

**Shared database, row-level isolation.** Every tenant-owned table carries a non-null `tenant_id`
(UUID) as the *first* column of every composite index. There is no schema-per-tenant and no
database-per-tenant — this keeps the platform a modular monolith that can genuinely be split later
without a rewrite, per the brief.

**Tenant resolution is server-side only, in this order:**

1. **Hostname.** `TenantResolutionMiddleware` looks up the incoming `Host` header against
   `tenant_domains` where `verification_status = verified`. This is the primary source of truth
   for all public and tenant-scoped routes.
2. **Authenticated membership.** For authenticated requests, the resolved tenant (from hostname)
   is cross-checked against the user's `tenant_users` rows. A user authenticated on tenant A's
   domain who has no `tenant_users` row for tenant A is rejected (`403 tenant_mismatch`), even if
   they belong to tenant B. This is what stops session confusion across tenant domains.
3. **Platform routes** (`/api/v1/platform/*`) skip hostname tenant resolution entirely and instead
   require a `platform_staff` membership; they never resolve a `TenantContext`.

`TenantContext` is bound as a request-scoped singleton the moment it's resolved. A `BelongsToTenant`
Eloquent trait applied to every tenant model (a) adds a global scope filtering on
`tenant_id = TenantContext::id()`, and (b) auto-fills `tenant_id` on creation. **`tenant_id` is
never accepted from request input** — every store/update request strips it before mass assignment.

Queue jobs that touch tenant data serialize the `tenant_id` explicitly and re-establish
`TenantContext` inside `handle()` — the queue worker has no ambient hostname to resolve from.

**Standing rule: `attach()`/`sync()`/`detach()` on a tenant-owned pivot table need `tenant_id`
passed explicitly.** These methods write to the pivot table via the raw query builder — they never
instantiate the pivot as an Eloquent model, so `BelongsToTenant`'s `creating` hook (which normally
auto-fills `tenant_id`) never runs. `course_instructors` (Phase 2) is the first example; any future
tenant-scoped pivot (membership-plan-to-course, tag-to-course, etc.) needs the same explicit
`'tenant_id' => $tenantContext->id()` in the pivot attributes array, or it'll fail on a MySQL
NOT NULL constraint the same way this one originally did.

**Standing rule: never use implicit route-model-binding for a tenant-owned model.** Laravel's
`SubstituteBindings` middleware isn't guaranteed to run after a custom-aliased middleware like
`tenant` in the pipeline (framework middleware-priority sorting only orders middleware that appears
in its known priority list) — so a route parameter type-hinted as a tenant-owned Eloquent model can
resolve *before* `TenantContext` is set, silently returning another tenant's row with the scope
never applied. Controllers accept the raw id and call `Model::findOrFail($id)` themselves instead —
that lookup runs inside the controller body, strictly after every route middleware has executed, so
correctness never depends on pipeline ordering. `App\Domain\Tenancy\Http\Controllers\DomainController`
is the reference example; this bug was actually caught by
`tests/Feature/TenantIsolationTest.php`'s route-model-binding test during Phase 1 and is exactly why
that test exists — keep it passing as more tenant-scoped resources are added in later phases.

Cache keys for tenant-scoped data are always prefixed `tenant:{tenant_id}:...`. Storage paths follow
`tenants/{tenant_uuid}/{branding,courses,resources,certificates,exports}/...` on the configured
S3-compatible disk.

## 2. Authentication — Backend-for-Frontend

Tenants may sit on entirely unrelated custom domains, so one shared cross-domain cookie is not an
option. Pattern:

1. Browser submits credentials to a **Next.js route handler** (`/app/api/auth/login/route.ts`),
   never directly to Laravel.
2. The Next.js server calls `POST {LARAVEL}/api/v1/auth/login` server-to-server and receives a
   Sanctum personal-access token scoped with abilities (`web-session`).
3. Next.js encrypts `{ userId, tenantId, token, expiresAt }` and sets it as the payload of an
   **HttpOnly, Secure, SameSite=Lax** cookie, scoped to the *current* hostname (`hrgems.<neutral>`,
   or the tenant's custom domain — whatever the browser is actually on). The raw Sanctum token
   never reaches client JS.
4. Every subsequent frontend data call goes through a Next.js proxy route
   (`/app/api/v1/[...path]/route.ts`) which decrypts the cookie server-side, attaches
   `Authorization: Bearer <token>`, forwards to Laravel, and relays the response.
5. Because the cookie is bound to the hostname it was issued on, logging in on tenant A's domain
   produces no usable session on tenant B's domain — there is nothing to steal cross-domain.
6. Mobile clients (future) skip the cookie step and use the same Sanctum bearer token directly —
   the `/auth/login` endpoint is shared.

Sanctum tokens are short-lived (2h) with silent refresh via `/auth/refresh` called by the Next.js
proxy on 401; refresh tokens are separate, rotating, stored server-side only. MFA (TOTP) hooks into
the same login endpoint as a second challenge step. Session revocation lists a user's
`user_sessions` and lets them (or platform support) kill any of them, invalidating the Sanctum token.

## 3. Roles & permissions

Custom tables (not a package), because platform roles and tenant roles are structurally different
and the brief calls for exact permission keys:

```
permissions      (id, key unique e.g. "courses.publish", scope enum[platform,tenant])
roles            (id, tenant_id nullable, name, slug, is_system)   -- tenant_id null = platform role
role_permissions (role_id, permission_id)
tenant_users     (id, tenant_id, user_id, role_id, status, invited_by, joined_at)
platform_staff   (id, user_id, role_id, status)   -- deliberate addition, see below
```

`platform_staff` is one addition beyond the brief's literal IDENTITY table list: it keeps platform
administrators structurally separate from `tenant_users` so a platform role can never be
accidentally evaluated as tenant-level access, and so platform-staff rows aren't swept up by any
tenant-scoped query or export. Documented here rather than left implicit.

A `PermissionService` resolves effective permissions for a user in the current context
(tenant or platform) and is what `Policy` classes and the `permission:{key}` route middleware both
call — no `$user->role === 'admin'` string comparisons in controllers.

Support impersonation writes a `support_sessions` row (staff id, target tenant/user, reason,
started_at, expires_at, ended_at) and every impersonated request is tagged in `audit_logs`. The
frontend renders a persistent impersonation banner sourced from a flag on the session cookie.

## 4. API conventions

- Prefix: `/api/v1/...`. Breaking changes ship as `/api/v2` alongside v1, never in place.
- Envelope: `{ "data": ..., "meta": { "request_id": "...", "pagination"?: {...} }, "errors"?: [...] }`.
- Validation failures: `422` with `{ "errors": { "field": ["message"] } }`.
- Every response carries `X-Request-Id`; it's also echoed in `meta.request_id` for support debugging.
- Financial POST endpoints (orders, refunds, subscription changes) require an `Idempotency-Key`
  header; the middleware persists `(key, request_hash) -> response` and replays it on retry instead
  of re-executing.
- OpenAPI spec is generated from route + FormRequest annotations (`dedoc/scramble` — zero-annotation
  generation from real Laravel code, avoids spec/code drift). The frontend's typed API client is
  generated from that spec (`openapi-typescript`).

## 5. Plans & entitlements (platform billing side)

```
platform_plans        (id, code, name, billing_period enum[monthly,annual,free,trial,custom],
                        price_cents, currency, is_public, trial_days)
plan_features          (plan_id, feature_key, value)          -- e.g. max_students=500
tenant_subscriptions   (tenant_id, plan_id, status, current_period_start/end,
                        grace_period_ends_at, cancel_at_period_end,
                        complimentary_until, complimentary_reason)
subscription_overrides (tenant_id, feature_key, value, expires_at)
```

Status enum: `trialing | active | grace_period | past_due | suspended | cancelled | complimentary`.
`complimentary` subscriptions have `plan_id` set (for feature limits) but no invoice is ever
generated — NAI TALK operations staff set this directly, optionally with `complimentary_until`.

`EntitlementService::value($tenant, $featureKey)` resolves, in order: unexpired
`subscription_overrides` row → `plan_features` for the tenant's current plan → hard-coded safe
default. This is the single call site the rest of the app uses; nothing else reads plan tables
directly, so entitlement logic never scatters into controllers.

Expiry handling (grace period, read-only mode, suspension) is a scheduled command
(`subscriptions:process-expirations`) driven entirely by these tables — no plan logic lives in the
frontend.

## 6. Domains

```
tenant_domains (id, tenant_id, hostname unique, domain_type enum[platform_subdomain,
                custom_subdomain, custom_domain], verification_token, verification_status,
                ssl_status, is_primary, verified_at, last_verification_error)
```

Every tenant gets a `{slug}.{NEUTRAL_PLATFORM_DOMAIN}` row created and verified automatically at
creation time (no DNS step needed — it's a subdomain of a domain NAI TALK already controls).
Optional custom subdomain/domain rows go through DNS TXT verification
(`domains:verify-pending` scheduled job using `dns_get_record`), then an SSL-provisioning hook
(interface defined now; concrete provider — e.g. Cloudflare for SaaS / ACM — wired in deployment
docs as it's infra-specific, not application logic). Unknown/unverified hostnames resolve to a
generic "site not found" response — never fall through to any tenant.

## 7. Branding & white-labelling

```
tenant_branding (tenant_id, logo_path, favicon_path, primary_color, secondary_color, accent_color,
                  font_family, homepage_json, contact_json, social_json, email_sender_name,
                  pwa_name, pwa_theme_color, pwa_icon_path)
```

`GET /api/v1/tenant-config` (public, cached, keyed by hostname) returns tenant + branding + the
public subset of plan features. The Next.js root layout fetches this server-side on every request
and injects it as CSS custom properties (`--color-primary`, `--color-accent`, ...) plus a generated
per-tenant PWA manifest (`/manifest.webmanifest` route handler reads the same config) — one frontend
build serves every tenant. HR GEMS ships as seeded *data* in this table, not as a code path.

HR GEMS mockup → seed values:
- `primary_color`: `#3B0F32` (deep plum, sidebar/nav/hero backgrounds)
- `accent_color`: `#E8A33D` (gold — primary CTAs, badges, "Most Popular" ribbon)
- `secondary_color`: `#F5B84B` (lighter gold, secondary highlights)
- Logo: gold diamond mark + "HR GEMS / COACH NETWORK" wordmark

## 8. Payments (interface now, providers wired in Phase 3)

`PaymentProviderInterface` (`initializePayment, verifyPayment, createSubaccount, createSubscription,
cancelSubscription, refundPayment, verifyWebhook, fetchTransaction, fetchSettlement`) with
`PaystackPaymentProvider` / `FlutterwavePaymentProvider` implementations, selected per
`tenant_payment_configs.provider`. Platform billing (NAI TALK's own SaaS revenue) uses a hard-coded
NAI TALK-owned config row that tenants can never see or edit — structurally the same table shape,
different access path, never joined into tenant-facing queries.

## 9. Learning domain (Phase 2)

```
course_categories   (tenant_id, name, slug)
courses             (tenant_id, category_id, title, slug, excerpt, description, thumbnail_path,
                      promo_video_path, status[draft,published], pricing_type[free,paid,membership_only],
                      price_cents, currency, difficulty_level, tags JSON, prerequisite_course_ids JSON,
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

Decisions worth calling out:

- **Tags are a JSON column on `courses`**, not a pivot table — Phase 2 doesn't need cross-tenant tag
  taxonomies or tag analytics, and a JSON array keeps the common case (filter by a few free-text tags)
  simple. Revisit if reporting ever needs "most-used tags across courses."
- **Quizzes and assignments are 1:1 with a `lesson`** (`lesson_id` FK) rather than freestanding —
  matches the brief's "lessons: quizzes, assignments" framing. A lesson of `type = quiz` has exactly
  one `quizzes` row; same for `type = assignment`.
- **Enrolment in Phase 2 only works for free courses.** `EnrolmentService::enroll()` throws a clear
  `CourseNotFreeException` for paid/membership-only courses — real checkout is Phase 3's
  `orders`/`payments` tables, and faking an enrolment without a real payment would be exactly the kind
  of "permanent placeholder" the brief says not to leave. The interface
  (`EnrolmentService::enroll($user, $course, $source)`) is already shaped so Phase 3's checkout success
  handler just calls it with `source: 'paid'` once payment clears.
- **Progress is server-computed, never trusted from the client.** `ProgressService` accepts a lesson
  id and a raw video position; it clamps the position to the lesson's known `duration_seconds`,
  requires ≥90% watched before marking a video lesson complete, and computes course completion
  percentage itself from `lesson_progress` rows against the course's mandatory lessons — the frontend
  only ever sends "the user watched to second N of lesson X," never "lesson X is complete."
  `CourseCompletionService` is the only place that flips an enrolment to `completed`.
- **Drip scheduling** uses `lessons.available_after_days` (nullable) checked against
  `enrolment.enrolled_at` at read time, rather than a separate scheduling table — sufficient for
  "unlock N days after enrolling," the common case; fixed-calendar-date drip per lesson is future
  work if a tenant needs it.

## 11. Commerce, memberships, coaching, community (Phase 3)

### Two billing domains stay structurally separate

Platform billing (what tenants pay NAI TALK — `platform_plans`/`tenant_subscriptions`, built in
Phase 1) and tenant commerce (what learners pay a tenant for courses/memberships/coaching, built
here) never share a table, a controller, or a payment credential. `tenant_payment_configs` holds
**only** the tenant's own gateway credentials — NAI TALK's platform-billing credentials live in
config/env, never in a tenant-readable table.

### Payment provider abstraction

```php
interface PaymentProviderInterface {
    initializePayment(array $params): array;   // -> [authorization_url, provider_reference]
    verifyPayment(string $reference): array;    // -> [status, amount_cents, currency, fees_cents, ...]
    createSubaccount(array $params): array;     // managed mode: split-settlement sub-account
    createSubscription(array $params): array;   // recurring charge plan (Paystack-native; Flutterwave
                                                 // implementation documents the repeated-charge fallback)
    cancelSubscription(string $code): bool;
    refundPayment(string $reference, ?int $amountCents): array;
    verifyWebhook(Request $request, string $secret): bool;
    fetchTransaction(string $reference): array;
    fetchSettlement(string $reference): array;  // stub — provider-specific settlement reporting is
                                                 // genuinely out of scope for Phase 3's acceptance
                                                 // criteria (commission *calculation*, not settlement
                                                 // *reconciliation*); interface shape is real,
                                                 // implementation documents the gap.
}
```

`PaystackPaymentProvider` and `FlutterwavePaymentProvider` implement this against each provider's
real REST API shape. No controller ever branches on provider name — `PaymentProviderFactory::forTenant($tenant)`
resolves the right implementation (and the right secret key — the tenant's own for client-owned
mode, NAI TALK's platform credentials for managed mode) at call time. This is a plain factory rather
than a container contextual binding because the choice genuinely depends on runtime data (which
tenant, which mode) that isn't known until a request is being handled — a static binding can't
express that.

### Tenant payment modes

`tenant_payment_configs` (one active row per tenant): `mode` is `client_owned` or `managed`.
Client-owned stores the tenant's own public/secret keys (secret encrypted via `Crypt` — envelope
encryption backed by an external KMS is the documented production upgrade, `APP_KEY`-backed
encryption is what's actually implemented). Managed mode stores NAI TALK's own credentials
(read from config, never from this table) plus a `subaccount_code` from `createSubaccount()` for
provider-native settlement splitting. `commission_percent` is nullable — null means "use the
platform default" (`DEFAULT_MANAGED_COMMISSION_PERCENT`, currently 1%); a non-null value is a
per-tenant override, exactly as the brief requires. `fee_bearer` (`tenant`/`learner`/`platform`)
decides who the provider's own processing fee is charged to — resolved at checkout time by
`CheckoutService`, never hidden from the learner (the checkout screen shows the fee breakdown before
payment).

Secret keys are never returned by any API response after saving — `PaymentConfigController` masks
them (`pk_live_ab****ef`) and the encrypted value is write-only from the client's perspective.

### Commerce schema — deliberately no separate `products`/`prices` tables

The brief's schema list includes `products`/`prices`, but Course (Phase 2), `learner_membership_plans`,
and `coaching_services` already each carry their own authoritative price — routing them through an
extra `products`/`prices` indirection layer would duplicate that data for no behavioural gain at
Phase 3's scope (no bundling, no multi-currency price lists yet). `order_items` references the
purchased thing directly and polymorphically (`itemable_type`/`itemable_id`), snapshotting `name` and
`unit_price_cents` at purchase time — that snapshot **is** the financial-immutability guarantee the
brief asks for: an order's record of what was sold and at what price never changes even if the
course's price changes tomorrow.

```
orders               (tenant_id, user_id, status[pending,paid,failed,refunded,partially_refunded,cancelled],
                       currency, subtotal_cents, fee_cents, total_cents, payment_mode, provider,
                       provider_reference unique, idempotency_key, paid_at)
order_items          (tenant_id, order_id, itemable_type, itemable_id, name, unit_price_cents, quantity)
payments             (tenant_id, order_id, provider, provider_reference unique, status,
                       gross_amount_cents, provider_fee_cents, platform_commission_cents,
                       tenant_net_cents, fee_bearer, paid_at, raw_response JSON — secrets stripped)
payment_allocations  (tenant_id, payment_id, allocatable_type, allocatable_id, amount_cents)
refunds              (tenant_id, payment_id, amount_cents, reason, status, provider_reference,
                       processed_by, processed_at)
webhook_events        (tenant_id nullable, provider, event_type, provider_event_id unique per provider,
                       payload JSON, status, processed_at, error_message)
tenant_payment_configs (tenant_id unique, provider, mode, public_key, secret_key_encrypted,
                         webhook_secret_encrypted, webhook_token unique, subaccount_code,
                         environment, commission_percent nullable, fee_bearer, status, last_verified_at)
```

`payment_allocations` is deliberately distinct from `order_items`: `order_items` is the **commercial**
record (what was sold), `payment_allocations` is the **fulfillment** record (which entitlement rows —
an enrolment, a `learner_subscriptions` row, a confirmed booking — resulted from this specific
payment). A single payment can fund more than one allocation; keeping them separate means "what did
this payment actually unlock" is answerable without re-deriving it from order state.

### Webhook processing — verified, idempotent, queued

```
POST /api/v1/webhooks/{provider}/{webhookToken}
```

`webhookToken` (not the tenant hostname — webhooks come directly from Paystack/Flutterwave's
servers, never through a tenant domain) resolves the `tenant_payment_configs` row, which carries the
correct encrypted secret for signature verification. Flow: resolve config → verify signature →
idempotency check against `webhook_events.provider_event_id` (unique index; a duplicate delivery
short-circuits to 200 without reprocessing) → persist the raw event → **acknowledge immediately** →
dispatch `ProcessPaymentWebhookJob` on the queue. The job never trusts the webhook payload's amount
or status by itself — it calls `verifyPayment()`/`fetchTransaction()` against the provider's API
directly, matches the result to the `Order` by `provider_reference`, and only then transitions
`Order` `pending -> paid` (a status-guarded update, so redelivery after the job already ran is a
no-op) and calls `OrderFulfillmentService::fulfill()` to create the actual entitlement exactly once.

### Memberships

```
learner_membership_plans (tenant_id, name, slug, billing_period[monthly,annual,free], price_cents,
                           currency, benefits JSON, is_active)
learner_subscriptions    (tenant_id, user_id, plan_id, status[active,cancelled,past_due,expired],
                           current_period_start, current_period_end, cancel_at_period_end, cancelled_at)
```

A course with `pricing_type = membership_only` (Phase 2 enum, unused until now) is unlocked by
**any** active `learner_subscriptions` row in the tenant — not tied to a specific plan tier. Per-plan
course restriction is a reasonable future refinement, not required by the brief's acceptance
criteria. Renewal/failed-payment handling reuses Phase 1's scheduled-command pattern
(`memberships:process-renewals`, alongside `subscriptions:process-expirations`) rather than
provider-native subscription webhooks, since Flutterwave has no real equivalent — each renewal is a
fresh charge attempt, consistent across both providers.

### Coaching

```
coaches             (tenant_id, user_id, bio, title, years_experience, timezone, is_active)
coaching_services    (tenant_id, coach_id, title, description, session_type[one_to_one,group],
                       duration_minutes, price_cents, currency, is_free, max_participants, is_active)
availability_rules   (tenant_id, coach_id, day_of_week, start_time, end_time, timezone)
coaching_sessions    (tenant_id, coaching_service_id, coach_id, scheduled_start, scheduled_end,
                       meeting_url, status, notes)
bookings             (tenant_id, coaching_session_id, user_id, status, booked_at, cancelled_at,
                       cancellation_reason)
session_attendance   (tenant_id, coaching_session_id, user_id, attended, joined_at, notes)
```

`coaching_sessions` is the schedulable occurrence (what a group of learners can book into);
`bookings` is one learner's reservation against it. For `one_to_one` services this is a 1:1 pair
created together at booking time; for `group` services many `bookings` share one `coaching_sessions`
row up to `max_participants`. `BookingService` rejects a new one-to-one session that overlaps an
existing `coaching_sessions` row for the same coach, or falls outside that coach's
`availability_rules` for the requested day/time — real conflict detection, not just a UI hint.

### Community

```
community_channels  (tenant_id, name, slug, description, course_id nullable, is_member_only, is_active)
community_posts      (tenant_id, channel_id, user_id, body, is_pinned)
community_comments    (tenant_id, post_id, user_id, body)
community_reactions   (tenant_id, reactable_type, reactable_id, user_id, type)
content_reports       (tenant_id, reportable_type, reportable_id, reported_by, reason, status,
                        reviewed_by, reviewed_at)
```

`is_member_only` channels require the same "any active membership" check as membership-only courses.
Moderation (`community.moderate` permission, already seeded in Phase 1) can delete posts/comments and
resolve reports — no separate moderation-queue table, `content_reports.status` is the queue.

## 12. Certificates, export/import, offboarding (Phase 4)

### Certificates — auto-issued at the single completion choke-point

```
certificates (tenant_id, user_id, course_id nullable, enrolment_id nullable unique,
               certificate_number unique per tenant, verification_code uuid unique globally,
               recipient_name, course_title, completed_at, issued_at, revoked_at, revoked_reason,
               metadata JSON)
```

`CourseCompletionService::recompute()` (Phase 2's *only* place an enrolment flips to `completed`) is
also the only place a certificate gets auto-issued — `CertificateService::issueForEnrolment()` is
called right after the status transition, gated on `course.certificate_enabled` and idempotent
(`firstOrCreate` on `enrolment_id`, so a re-run of `recompute()` — e.g. a lesson un/re-completing at
the boundary — never double-issues). `recipient_name`/`course_title` are snapshotted at issue time
rather than joined live: a certificate must keep saying what it said when it was earned even if the
learner later changes their display name or the tenant renames/deletes the course. `certificate_number`
is a tenant-scoped human-readable sequence (`{tenant-slug}-{year}-{padded count}`); `verification_code`
is a separate globally-unique UUID — deliberately unguessable and never derived from the sequential
number, so a verifier can't enumerate other learners' certificates by incrementing a number in the URL.

**Verification is public and provider-agnostic of tenant hostname.** `GET
/api/v1/certificates/verify/{code}` carries no `tenant` middleware — same reasoning as the Phase 3
webhook endpoint: whoever's scanning a QR code (an employer checking a printed certificate) isn't
browsing the tenant's site and has no hostname context. The controller resolves the tenant via
`Certificate::withoutTenancy(...)` by `verification_code` alone and returns tenant name, recipient
name, course title, dates, and a `valid` boolean (`false` once `revoked_at` is set — the row is never
deleted, so a revoked certificate still resolves and visibly shows revoked rather than 404ing, which
would be indistinguishable from "never existed" and less useful to a verifier).

**No server-side PDF/QR generation.** `spatie/browsershot` sits in `composer.json` unused — wiring
real PDF rendering needs a working headless-Chrome/Puppeteer install this environment doesn't have
configured, and it's a heavy, failure-prone dependency (network-fetched Chromium, sandboxing flags)
for what a browser already does natively. The certificate is a server-rendered, print-styled frontend
page (`/certificates/{code}`) — "Save as PDF" is the browser's own print dialog, not a new backend
capability. The QR code (encoding that same verification URL) is generated client-side in the
frontend via a small dependency-free npm package rather than a PHP QR library, keeping certificate
image generation off the backend entirely.

### Export/import — two permission scopes, deliberately asymmetric

```
export_jobs (tenant_id, requested_by nullable, status[pending,processing,completed,failed],
              file_path, file_size_bytes, error_message, completed_at)
```

`exports.request` (tenant permission, already seeded in Phase 1's `tenant-administrator` defaults)
lets a tenant request and download **their own** data — this is a data-portability feature a tenant
can reach for anytime, not something reserved for offboarding. `exports.manage` (platform permission,
already seeded in Phase 1) lets platform staff trigger/view/download **any** tenant's exports, which
is what offboarding actually uses. Same underlying `ExportTenantDataJob` and `export_jobs` row either
way — only the route scope and the tenant-id parameter source differ.

The job walks every Phase 1–3 domain under the target tenant's context and writes one structured JSON
file to `tenants/{tenant_uuid}/exports/{timestamp}.json` on the configured disk: users +
tenant_users + roles (password hashes excluded), courses/modules/lessons/quizzes/assignments,
enrolments + progress, membership plans + subscriptions, coaches/services/sessions/bookings,
community channels/posts/comments, orders/payments (decrypted payment secrets are **never** included
— `tenant_payment_configs.secret_key_encrypted` stays encrypted-or-omitted, never decrypted for
export). A single JSON manifest rather than a zip of many files: every export at this scope is well
under the size where that would matter, and one file is trivially easier to validate and re-import.

**Import only ever creates a brand-new tenant — it never merges into an existing one.**
`TenantImportService::importIntoNewTenant()` runs the whole restore inside one DB transaction,
provisioning a fresh `Tenant` via the existing `TenantProvisioningService` and re-inserting every
entity with a fresh auto-increment id, keeping an in-memory old-id → new-id map to rewrite foreign
keys as it goes (categories before courses before modules before lessons before enrolments, etc.).
This is a deliberate scope cut: merge-import into a tenant that already has its own
courses/users/orders is a genuinely different, much riskier feature (id collisions, dedup rules,
partial-failure semantics on someone's live data) that the brief's "export/import" requirement
doesn't actually need — the real use case is disaster recovery and tenant migration between
environments, both of which land on an empty tenant. A transaction means a mid-import failure leaves
nothing behind to clean up.

**What actually gets restored vs. what's export-only in this version.** The exported JSON is
comprehensive (every Phase 1–3 domain, for backup/audit completeness), but the importer restores
identity + branding + the full learning domain — users, roles, tenant memberships, branding, course
categories/courses/modules/lessons/quizzes/assignments, enrolments, lesson progress, and
certificates. Commerce (orders/payments/refunds), coaching (bookings/sessions), and community
(posts/comments/reactions, several of which are polymorphic) are captured in the export but not
reconstructed by the importer. Restoring those correctly means remapping polymorphic
`itemable_type`/`allocatable_type`/`reactable_type`/`reportable_type` columns against every other
remapped id table at once, and financial records in particular are the last thing that should be
rebuilt by a first-pass, lightly-tested importer — a real production version of this earns that
scope through dedicated engineering and testing time, not by being rushed alongside everything else
in this phase. A tenant being fully migrated still needs its commerce history preserved for
accounting purposes, which is exactly what the raw export JSON is for even before the importer grows
to consume it.

### Offboarding — reuses Phase 1's deletion-scheduling schema, adds the part that never ran

`Tenant.status`/`suspended_at`/`suspension_reason`/`deletion_scheduled_at`/`deleted_permanently_at`
and `PlatformTenantController::suspend/reactivate/scheduleDeletion` were already built in Phase 1 —
the schema anticipated this phase but nothing ever consumed `deletion_scheduled_at`. Phase 4 adds the
consuming side: `tenants:process-scheduled-deletions` (daily, same `Schedule::command()` pattern as
`memberships:process-renewals`) finds tenants with `status = deletion_scheduled` and
`deletion_scheduled_at <= now()`, and **refuses to hard-delete a tenant with zero completed
`export_jobs`** — logging an error and skipping rather than proceeding, since the entire point of the
grace period is "this tenant's data gets backed up before it's gone." Once an export exists, the
command calls `Tenant::forceDelete()` — every Phase 1–3 migration already declares
`->cascadeOnDelete()` on its `tenant_id` foreign key, so this single call cascades through all ~50
tenant-owned tables at the database level rather than needing an ORM-level deletion routine. A
`POST /api/platform/tenants/{tenant}/deletion/cancel` endpoint (`deletions.manage`) undoes a scheduled
deletion within the grace period — clears `status` back to `active` and nulls `deletion_scheduled_at`.

## 13. Phase plan

Matches the brief exactly. **Phase 1 (done)** — both app skeletons, tenancy, auth, roles, branding,
domains, platform plans/subscriptions, platform admin. **Phase 2 (done)** — courses, learning,
quizzes/assignments. **Phase 3 (done)** — memberships, coaching, community, both payment modes,
commission, checkout flow, payment gateway settings, membership/coaching/community frontend pages.
**Phase 4 (done)** — certificates with QR verification, tenant data export/import, offboarding
(scheduled deletion with a mandatory export prerequisite). This is the last phase in the brief.
