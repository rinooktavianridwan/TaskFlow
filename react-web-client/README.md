# TaskFlow Web Client

React single-page app for [TaskFlow](../README.md): projects, tasks, members and invitations, with per-project roles (owner / editor / viewer). It talks to the Laravel API over a Sanctum cookie session.

## Tech Stack

| Concern | Choice |
|---|---|
| Build / dev server | Vite, TypeScript (strict) |
| UI | React 19, Tailwind CSS 4 |
| Routing | React Router 7 (`createBrowserRouter`) |
| Server state | TanStack Query 5 |
| Forms / validation | React Hook Form + Zod 4 |
| HTTP | axios (cookies + CSRF) |
| Tests | Vitest |

## Getting Started

Requires Node 24+ and pnpm. The pnpm version is pinned through the `packageManager` field in `package.json` (run `corepack enable` once and Corepack picks it up). The Laravel API must be running (see the root README).

```bash
pnpm install
cp .env.example .env   # VITE_API_URL=http://localhost:8000
pnpm dev               # http://localhost:3000
```

The dev server uses `strictPort`: the port is fixed at **3000** because the API only accepts that origin (CORS and `SANCTUM_STATEFUL_DOMAINS`). If something else uses it, Vite fails instead of silently switching ports.

## Scripts

| Command | Purpose |
|---|---|
| `pnpm dev` | Dev server with HMR |
| `pnpm build` | Type-check (`tsc -b`) and production build |
| `pnpm preview` | Serve the production build locally |
| `pnpm lint` | ESLint |
| `pnpm test` | Run unit tests once |
| `pnpm test:watch` | Tests in watch mode |

## Features

- Registration with OTP email verification, login, logout, and password reset (forgot / reset). The "forgot password" screen always shows the same neutral message, so it never reveals whether an email is registered.
- Projects with search, pagination and a progress bar per project, plus project detail with Tasks, Members, Invitations and Activity tabs (Invitations and Activity are owner-only).
- Tasks with search, status filter, an "All tasks / My tasks" toggle, create / edit / delete, status changes and an activity history.
- Optional task checklists: owners and editors add, rename and delete items; an assigned viewer can only check them. The task status follows the checklist automatically, with progress bars on task cards and project cards.
- Members: change roles, remove members, leave a project.
- Invitations: owners send and revoke them. Invitees open the emailed link (`/invitations/:token`) or use their inbox (`/invitations`, with a badge in the header) to accept or decline.
- Owner-only project activity timeline (who did what), filterable by action type and member.
- Daily summary (`/daily-summary`): your activity per day, grouped by project and task, with a date range picker (up to 31 days, times shown in your profile timezone).
- Profile page: change name, timezone and password. While the account is still on the default UTC, the browser timezone is applied once automatically.
- Toast notifications for completed actions, a show / hide password toggle, and list state (search, filter, page) kept in the URL.

## Routes

| Path | Access | Page |
|---|---|---|
| `/login`, `/register`, `/register/verify` | Guests | Sign in, sign up, OTP verification |
| `/forgot-password`, `/password-reset/:token` | Guests | Password reset |
| `/invitations/:token` | Anyone | Invitation preview and accept / decline (link from the email) |
| `/projects`, `/projects/:projectId/{tasks,members,invitations,activity}` | Signed in (`invitations` and `activity`: owners) | Project list and detail tabs |
| `/tasks/:taskId` | Signed in | Task detail and activity |
| `/invitations` | Signed in | Invitations you have received |
| `/daily-summary` | Signed in | What you did per day, by project and task |
| `/profile` | Signed in | Name, timezone and password |

After login the app returns you to where you were headed through `?redirect=`. Only internal paths are accepted (see `src/lib/redirect.ts`) to prevent open redirects. An invitation can only be answered by the account whose email matches the invitation; the page detects a mismatch and offers a log out instead.

## Project Structure

Feature-based. Each feature owns its API calls, query hooks, schemas and types:

```
src/
├── api/           # axios instance (CSRF handling, 419 retry)
├── app/           # providers and router
├── components/    # shared UI (ui/), feedback (states, toasts), navigation
├── features/
│   └── <feature>/ # api.ts (plain functions), queries.ts (TanStack Query),
│                  # schemas.ts (Zod), types.ts, components/, pages/
├── layouts/
├── lib/           # dates, form errors, redirect, URL state, debounce helpers
└── types/         # shared API envelope types
```

Conventions:

- `api.ts` has no React; `queries.ts` owns query keys (hierarchical, one root per feature) and invalidation.
- Role rules live in one place, `features/projects/permissions.ts`. They only hide buttons; the backend enforces access (403).
- Search, filter and page state of lists live in the URL (`?q=`, `?status=`, `?page=`) and are written with `replace`, so Back does not step through every keystroke.
- A 401 from any request clears the session; a 419 refreshes the CSRF cookie and retries once.
- Forms use React Hook Form + Zod. Server 422 errors are mapped onto fields; anything else becomes a banner.
- No `enum` (`erasableSyntaxOnly`) and `import type` for types (`verbatimModuleSyntax`).

## Testing

Unit tests are colocated (`*.test.ts`) and run in a plain Node environment with Vitest, without a DOM. They cover the pure logic that is easiest to get subtly wrong:

- role permissions (`permissions.ts`)
- which fields a task edit sends in its PATCH (`diffTask`)
- Zod schemas for login, registration, OTP and password reset
- redirect validation against open redirects (`getSafeRedirect`)
- how an invitation's display status is derived (`getDisplayStatus`)
- checklist permissions, activity action labels, the daily-summary date range rules and sentence builder
- profile and change-password schemas, timezone options, and avatar initials

## Docker

The root `docker-compose.yml` includes this app as `taskflow-web`: a production build served by nginx on port 3000. `VITE_API_URL` is baked in at build time (build arg, default `http://localhost:8000`).

`taskflow-web` and `pnpm dev` both use port 3000, so run only one of them.
