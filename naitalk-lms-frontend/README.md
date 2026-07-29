# NAI TALK LMS — Frontend

Next.js 16 (App Router) / TypeScript / Tailwind CSS 4. White-labelled public site, student portal,
instructor/coach portal, tenant administration, and platform administration for the NAI TALK
multi-tenant e-learning platform.

See [`../ARCHITECTURE.md`](../ARCHITECTURE.md) for the full system design. In particular, §2
(Backend-for-Frontend auth) explains why this app has no direct database access and no long-lived
tokens in the browser — every authenticated call is either made server-side (Server Components,
Route Handlers) or proxied through this app's own `/api/v1/[...path]` route so the Sanctum bearer
token never reaches client JavaScript.

## Requirements

- Node 20+
- The backend running and reachable (see `../naitalk-lms-backend/README.md`)

## Local setup

```bash
npm install
cp .env.example .env.local
```

Generate a session secret and put it in `.env.local`:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

Set `BACKEND_SERVER_URL` to wherever the Laravel API is listening (e.g. `http://127.0.0.1:8123`), and
`BACKEND_INTERNAL_SECRET` to match the backend's `FRONTEND_INTERNAL_SECRET`.

For tenant-hostname resolution to work locally, add to `/etc/hosts`:

```
127.0.0.1 naitalk-lms.test hrgems.naitalk-lms.test api.naitalk-lms.test
```

```bash
npm run dev
```

Then visit `http://hrgems.naitalk-lms.test:3000` for the seeded HR GEMS demo tenant, or
`http://naitalk-lms.test:3000/platform/login` for platform administration
(`platform-admin@naitalk-lms.test` / `password`).

## How tenant theming works

Every request resolves the tenant from the `Host` header (`lib/tenant.ts`), fetches its branding
from the backend, and injects it as CSS custom properties (`--tenant-primary`, `--tenant-secondary`,
`--tenant-accent`, `--tenant-font`) on the root `<html>` element in `app/layout.tsx`. Components use
Tailwind's arbitrary-value syntax (`bg-[var(--tenant-primary)]`) rather than build-time theme
tokens, since the actual colors are only known at request time — one build serves every tenant.
`app/manifest.ts` generates a per-tenant PWA manifest the same way.

The **platform admin** section (`app/platform/**`) deliberately does *not* use these tenant
variables — see the comment in `components/platform-shell.tsx`. It's NAI TALK's own internal tool,
never white-labelled, and must look identical regardless of which tenant's domain happens to be
serving the request in local development.

## Testing & builds

```bash
npm run lint
npm run build
```

There is no frontend automated test runner configured yet (Phase 1 priority was the backend's
tenant-isolation and auth test suites, which are the security-critical ones). Recommended next step:
Playwright for the checklist in the project brief (tenant theme rendering, auth flows, responsive
layouts, accessibility, error/empty states) — component-level logic here is intentionally thin
(mostly server-rendered pages calling the documented API), so end-to-end coverage will pay off more
than unit tests.

## Project structure

```
src/app/                    Routes (App Router) — public site, auth, dashboard, admin, platform
src/app/api/auth/*           BFF auth endpoints — set/clear the encrypted session cookie
src/app/api/v1/[...path]     Generic authenticated proxy for Client Components
src/components/             Shared UI (shells, forms) — mostly client components
src/lib/session.ts           iron-session config; the ONLY place the bearer token is readable
src/lib/api-server.ts        Direct server-to-server fetch to Laravel (Server Components)
src/lib/tenant.ts             Hostname resolution + per-request tenant config fetch
src/lib/auth-server.ts        requireUser() / requirePlatformStaff() — redirect-on-401 helpers
```

## Course catalogue, learning player & course builder (Phase 2)

- `/courses`, `/courses/[slug]` — public catalogue and detail page, real data from the backend
- `/my/courses`, `/dashboard` — enrolled/completed/wishlist tabs and a real "Continue Learning" widget
- `/learn/[lessonId]` — the learning player: video/audio position tracking (throttled, server
  validates and clamps — see `components/lesson-player.tsx`), rich-text/file/link/live lessons with
  an explicit mark-complete, and embedded quiz-taking / assignment-submission UI
- `/admin/courses/**` — instructor/admin course builder: course details, modules, lessons, an inline
  quiz question/option builder, an assignment builder, and a learner roster with completion %
- Enrolment only works for free courses this phase — `components/enroll-button.tsx` shows "Checkout
  coming soon" for paid courses rather than faking a purchase (see backend's `EnrolmentService`)

## Known remaining work (beyond Phase 2)

- Membership/coaching/community pages, checkout, payment gateway connection UI (Phase 3)
- Certificate display/verification, homepage builder, full data export UI (Phase 4)
- Nav items pointing at not-yet-built features route to `/coming-soon?feature=...` rather than a
  dead link or fake data — an honest placeholder, not a permanent one
- Offline PWA shell / service worker (manifest is generated; the service worker itself is Phase 3+)
- No E2E test suite yet (see Testing section above)
- No rich-text editor for lesson content yet — the course builder's rich-text lesson body is a plain
  textarea; a proper WYSIWYG (and file/video upload UI, once presigned uploads exist) is future work
