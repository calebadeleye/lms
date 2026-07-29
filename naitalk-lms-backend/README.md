# NAI TALK LMS — Backend API

Laravel 11 / PHP 8.3 modular monolith. Source of truth for authentication, tenancy, business rules,
database access, payments, and background jobs for the NAI TALK multi-tenant e-learning platform.

See [`../ARCHITECTURE.md`](../ARCHITECTURE.md) for the full system design (tenancy model, auth
pattern, API conventions, plan/entitlement model, domain resolution, payment architecture).

## Requirements

- PHP 8.3+ (this repo was built and tested against `8.3.24` via Homebrew — see note below if your
  system `php` resolves to a different version)
- Composer 2
- MySQL 8
- Redis (cache, queues, rate limiting)
- Node is **not** required here — this is an API-only application

### If your system PHP isn't 8.3+

```bash
brew install php@8.3
# then use the versioned binary explicitly rather than changing your global php symlink:
/usr/local/opt/php@8.3/bin/php artisan serve
/usr/local/opt/php@8.3/bin/php artisan migrate
```

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the databases (adjust credentials to match your local MySQL):
mysql -uroot -e "CREATE DATABASE naitalk_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -e "CREATE DATABASE naitalk_lms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
```

Seeding creates:

- The global permission catalog and four platform roles
- Five platform plans (Free, Starter Monthly, Growth Monthly/Annual, Enterprise)
- A platform super administrator: `platform-admin@naitalk-lms.test` / `password`
- The **HR GEMS** demonstration tenant on `hrgems.{NEUTRAL_PLATFORM_DOMAIN}`, with:
  - Branding matching the attached mockup (deep plum `#3B0F32`, gold `#E8A33D`)
  - Owner login: `admin@hrgems.test` / `password`
  - A 6-course catalogue matching the mockup's course cards (paid — browsable, not yet purchasable,
    see Phase 3), plus one free course ("Getting Started with HR") with a full lesson/quiz flow so
    enrolment → learning → completion can be exercised end-to-end today

## Running it

```bash
php artisan serve --port=8123          # or any port; see FRONTEND_URL/BACKEND_SERVER_URL wiring
php artisan horizon                    # queue worker (Phase 2+ jobs)
php artisan schedule:work               # runs subscriptions:process-expirations, domains:verify-pending
```

For local tenant-domain testing, add to `/etc/hosts`:

```
127.0.0.1 naitalk-lms.test hrgems.naitalk-lms.test api.naitalk-lms.test
```

Then hit `http://hrgems.naitalk-lms.test:8123/api/v1/tenant-config` directly, or run the frontend
(see `../naitalk-lms-frontend/README.md`) which calls this API server-to-server.

## API documentation

OpenAPI spec is generated (zero-annotation, from real routes/FormRequests) via
[`dedoc/scramble`](https://scramble.dedoc.co). With the server running:

- Interactive docs: `GET /docs/api`
- Raw spec: `GET /docs/api.json`

## Testing

```bash
php artisan test          # or: vendor/bin/pest
```

`tests/Feature/TenantIsolationTest.php` and `tests/Feature/AuthTest.php` are the load-bearing suites —
they assert unknown domains fail safely, one tenant cannot read or mutate another tenant's data
(including through route-model binding — see the standing rule in ARCHITECTURE.md §1), permission
middleware is enforced, and complimentary subscriptions never create an invoice.

Tests run against `naitalk_lms_testing` (configured in `phpunit.xml`) using `RefreshDatabase` — a
real MySQL connection, not sqlite, so behavior matches production (in particular, MySQL's
NULL-is-distinct unique-index semantics that `roles.tenant_id` relies on).

## Project structure

Modular monolith, organized by domain rather than by technical layer:

```
app/Domain/Tenancy/     Tenant, TenantDomain, TenantBranding, resolution middleware, provisioning
app/Domain/Identity/    User<->Tenant membership, roles, permissions, auth, invitations, sessions
app/Domain/Billing/     Platform plans, tenant subscriptions, entitlements
app/Domain/Platform/    Platform admin controllers, audit logging, support sessions
app/Domain/Learning/    Courses, modules, lessons, enrolment, progress, quizzes, assignments, reviews
app/Support/            Cross-cutting infra (API request-id, permission catalog)
```

Each future phase (coaching, memberships, commerce, certificates, portability) gets its own
`app/Domain/{Name}` following the same shape: `Models/`, `Services/`, `Http/Controllers`,
`Http/Requests`, `Http/Resources`, and `Policies/` where relevant.

## Known remaining work (beyond Phase 2)

- Paid-course checkout, memberships, coaching, community, both payment modes + 1% commission (Phase 3)
- Certificates, QR verification, full export/import, offboarding/deletion (Phase 4)
- Real SSL-provisioning webhook for custom domains (verification itself is implemented; the
  provider-specific hook — Cloudflare for SaaS / ACM / etc. — is infra, not application logic)
- Fully dynamic CORS for verified custom domains (currently a comma-separated env list plus a regex
  for the neutral platform domain — see `config/cors.php`)
- MFA is implemented (TOTP enrol/verify) but not yet exposed as a settings-page flow in the frontend
- Free-text quiz questions are recorded but not auto- or manually-graded yet (excluded from
  scoring — see `QuizGradingService`); a grading UI for instructors is future work
- Course drip scheduling only supports "N days after enrolment", not fixed calendar dates
- No video transcoding/streaming pipeline — `video_path` is a plain URL for now (Phase 2 media
  architecture, per the brief, is future work: presigned uploads, CDN delivery, transcoding)
