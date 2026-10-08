# TaskFlow API Core

The main REST API of [TaskFlow](../README.md), built with Laravel 13 (PHP 8.4) and MySQL. It owns users, projects, tasks, invitations, and the audit log, and delegates all email delivery and reminder scheduling to the [Go notification service](../go-notification-service/README.md) over gRPC.

## Request Flow

```
Route (routes/api.php)
  → Middleware: auth:sanctum, throttle, can:ability,param (Policy)
  → Form Request (validation)
  → Controller (thin: maps request to service, response to resource)
  → Service (business logic, DB transaction, locking, activity log, job dispatch)
  → Model
```

There is no Repository layer on top of Eloquent; services query models directly.

## Code Layout

```
app/
├── DTOs/              # Spatie Data objects (e.g. CreateTaskData)
├── Enums/             # ProjectRole, TaskStatus, InvitationStatus, ActivityAction
├── Grpc/Generated/    # Protobuf / gRPC PHP stubs (generated: do not edit)
├── Http/
│   ├── Controllers/   # Thin controllers
│   ├── Requests/      # Form Requests (validation rules)
│   └── Resources/     # API Resources (response shapes)
├── Jobs/              # Queued jobs that call the Go service
├── Models/
├── Policies/          # Authorization (ProjectPolicy, TaskPolicy, ...)
├── Services/          # Business logic; Grpc/NotificationGrpcClient
├── Support/           # TaskProgress (progress rule)
└── Traits/            # ApiResponser (uniform JSON envelope)
```

## Conventions

- **Transactions live in services**, never controllers. A job dispatched inside a transaction must use `->afterCommit()`. Never make a slow network call inside a transaction or lock.
- **Locking**: `lockForUpdate` only for read-check-write sequences that two concurrent requests could break, inside `DB::transaction`. Use `Project::lockRow()`, and lock the **project first, then other rows**.
- **Authorization** goes through Policies with `->middleware('can:ability,param')`. `Project` offers `hasMember`, `hasRole`, and `lockRow`.
- **List endpoints**: always paginated and ordered by `id`; the service returns an `Eloquent\Builder`, filters use `when()`, the controller calls `->paginate()` and `$this->paginated($paginator, XResource::class)`. Enum filters are validated with `Rule::enum(...)`.
- **Responses** use the `ApiResponser` trait (`success()`, `paginated()`, `noContent()`) and API Resources. `TaskResource` always eager-loads `assignee`.
- **Activity log**: every state change writes an `activity_logs` row through `ActivityLogger`, inside the same transaction. Metadata holds snapshots (titles, names, emails), and `activity_logs.task_id` has no foreign key so history outlives a deleted task.
- **Email jobs build their links in `handle()`**, not the constructor, from `config('app.frontend_url')` (with `rtrim` and `rawurlencode` / `urlencode`), so old payloads stay valid. `failed()` never logs a token.

## Authentication

Sanctum **SPA cookie session** (not Bearer tokens). Auth routes live at the root (no `/api`), resource routes under `/api`, all behind `auth:sanctum` except the public invitation preview.

| Method | Path                   | Access       | Response                                                                                   |
| ------ | ---------------------- | ------------ | ------------------------------------------------------------------------------------------ |
| GET    | `/sanctum/csrf-cookie` | public       | 204                                                                                        |
| POST   | `/register`            | guest        | 202 `{message, email}` (email must be lowercase and unique)                                |
| POST   | `/register/verify`     | guest, 6/min | 204, user created and logged in                                                            |
| POST   | `/login`               | guest        | 204 (failure: 422 on `email`; throttled 5 attempts per email + IP)                         |
| POST   | `/logout`              | logged in    | 204                                                                                        |
| POST   | `/forgot-password`     | guest, 5/min | always 200 `{status}`                                                                      |
| POST   | `/reset-password`      | guest, 6/min | 200 `{status}`; bad, expired, or reused token: 422 on `email`; mismatch: 422 on `password` |

- **Verify-before-create**: `POST /register` stores a `pending_registrations` row with a 6-digit OTP (10 minutes, at most 5 wrong attempts). `SendRegistrationOtpJob` sends it through gRPC. Only `POST /register/verify` creates the `User` and logs in.
- Mutating requests need `X-XSRF-TOKEN`; the token changes after verify, login, and logout. Sessions last 120 minutes. `guest` routes redirect (302) to `/` when already logged in.
- **Password reset**: the token comes from Laravel's broker (`Password::sendResetLink` with a callback). The callback dispatches `SendPasswordResetEmailJob`, which builds the link and calls gRPC. The broker's outcome (sent, unknown email, 60-second throttle) is deliberately ignored so the response is always the same.

## Endpoints

Everything below is under `/api` and requires a session. Full request / response examples are in [Apidog](https://ygst4z3h51.apidog.io).

| Endpoint                                       | Who                                                   | Notes                                                                                                                     |
| ---------------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `GET /user`                                    | logged in                                             | The current user as a raw model object (including `timezone`), not wrapped in the response envelope.                      |
| `GET/POST /projects`                           | any user                                              | List shows only your projects, each with your `role` and `progress`. Filter: `name`.                                      |
| `GET /projects/{id}`                           | members                                               |                                                                                                                           |
| `PATCH/DELETE /projects/{id}`                  | owner                                                 | Update logs `project_updated` only when something changed.                                                                |
| `GET /projects/{id}/members`                   | members                                               | Ordered ascending. Filter: `name`.                                                                                        |
| `PATCH /projects/{id}/members/{user}`          | owner                                                 | Demoting the last owner: 422 on `role`.                                                                                   |
| `DELETE /projects/{id}/members/{user}`         | owner, or the member themselves                       | Unassigns their tasks and logs it.                                                                                        |
| `GET/POST /projects/{id}/invitations`          | owner                                                 | Filter: `status`. Email is lowercased.                                                                                    |
| `DELETE /projects/{id}/invitations/{id}`       | owner                                                 | Pending only (422 on `invitation`). Does not revoke membership.                                                           |
| `GET /invitations/{token}`                     | public, 30/min                                        | Preview without token / id / project_id. Unknown token: 404.                                                              |
| `GET /invitations`                             | logged in                                             | Your pending, unexpired invitations (includes `token`, which the email is verified to own).                               |
| `POST /invitations/{token}/accept` / `decline` | invitee                                               | The account email must match (403 otherwise); 422 on `invitation` if processed or expired.                                |
| `GET/POST /projects/{id}/tasks`                | members / owner, editor                               | Filters: `title`, `status`, `mine=1` (assigned to me). Status cannot be set on create.                                    |
| `GET/PATCH/DELETE /tasks/{id}`                 | members / see below / owner, editor                   | Owner and editor can patch any field; a viewer can patch only `status` (exactly `["status"]`) on their own assigned task. |
| `POST /tasks/{id}/checklist-items`             | owner, editor                                         | Max 50 items per task (422 on `title`). Returns `{item, task}`.                                                           |
| `PATCH/DELETE /checklist-items/{id}`           | owner, editor; assigned viewer may only set `is_done` | PATCH returns `{item, task}`; DELETE returns 204.                                                                         |
| `GET /tasks/{id}/activities`                   | members                                               | History of one task.                                                                                                      |
| `GET /projects/{id}/activities`                | owner                                                 | Project timeline. Filters: `action`, `actor_id`, `task_id`.                                                               |
| `PATCH /profile`, `PUT /profile/password`      | logged in                                             | Name and timezone; password change (6/min). Email cannot be changed here.                                                 |
| `GET /me/daily-summary`                        | logged in                                             | Your activity grouped by day, project, and task, in your timezone. `from` / `to` up to 31 days.                           |

Shapes: `Project {id, name, description, role, progress, created_at, updated_at}`, `Member {user_id, name, email, role}`, `Task {id, project_id, title, description, status, assigned_to, assignee, due_date, checklist_total, checklist_done, progress, checklist?, created_at, updated_at}` (`checklist` only on `GET /tasks/{id}`).

## Progress and Status Rules

Implemented in `TaskChecklistService` and `Support/TaskProgress`; task rows are locked while recomputing, so two simultaneous checks cannot race.

| Situation                            | Result                                        |
| ------------------------------------ | --------------------------------------------- |
| Task status is `done`                | progress 100, whatever the checklist says     |
| Otherwise, with a checklist          | `done / total`, rounded                       |
| Otherwise, no checklist              | 0                                             |
| First item checked while `todo`      | status becomes `in_progress`                  |
| Every item checked                   | status becomes `done`                         |
| Item unchecked or added while `done` | status goes back to `in_progress`             |
| Last unfinished item deleted         | status becomes `done` if the rest are checked |

Project progress is the average of its tasks' progress. Automatic status changes are logged as "(via checklist)" and re-sync the task's reminder.

## Background Jobs

Queue driver: `database`. The worker runs with `--tries=3`.

| Job                         | Purpose                                                                                                         |
| --------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `SendRegistrationOtpJob`    | gRPC `SendVerificationEmail`                                                                                    |
| `SendInvitationEmailJob`    | gRPC `SendInvitationEmail` (3 tries, 5 s backoff)                                                               |
| `SendPasswordResetEmailJob` | gRPC `SendPasswordResetEmail` (3 tries, 5 s backoff)                                                            |
| `SyncTaskReminderJob`       | Reads the task's current state, then gRPC `ScheduleTaskReminder` or `CancelTaskReminder` (3 tries, 5 s backoff) |

`App\Services\Grpc\NotificationGrpcClient` uses a 10-second timeout and throws a `RuntimeException` on a non-OK gRPC status or `success = false`, so the job retries and eventually lands in `failed_jobs` without failing the original API request.

## Configuration

Key variables (see `.env.example`):

| Variable                                            | Notes                                                                                                                                                                                |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `FRONTEND_URL`                                      | Base URL used in email links. **Must not be empty**: an empty value overrides the code default.                                                                                      |
| `DB_*`                                              | `DB_HOST=host.docker.internal` when MySQL runs on the host                                                                                                                           |
| `GRPC_NOTIFICATION_HOST` / `GRPC_NOTIFICATION_PORT` | `taskflow-notification` / `50051`                                                                                                                                                    |
| `QUEUE_CONNECTION`                                  | `database`                                                                                                                                                                           |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`        | Must match the frontend origin in production. `SESSION_DOMAIN` is in `.env.example`; `SANCTUM_STATEFUL_DOMAINS` is not, and Sanctum's default list already includes `localhost:3000` |

`MAIL_MAILER` is not used by any flow: all email goes through the Go service. Laravel's timezone is `UTC`, and every datetime is stored and returned in UTC.

## Running

Normally through the root `docker compose` (see the [root README](../README.md#local-development)). Useful commands:

```bash
docker exec -it taskflow-laravel php artisan migrate:fresh   # reset dev data (stop taskflow-queue first)
docker exec -it taskflow-laravel php artisan tinker
docker restart taskflow-queue                                 # after changing job code
```

The container runs `migrate --force` on startup, retrying until the database is reachable, then serves on port 8000. The health check is `GET /up`.

## Testing

```bash
docker exec -it taskflow-laravel php artisan test
docker exec -it taskflow-laravel php artisan test --filter=TaskChecklistTest
```

Pest with `RefreshDatabase` on in-memory SQLite, and `Queue::fake()` applied globally in `tests/Pest.php`. Helpers (`createProject`, `addMember`, `createInvitation`, `createTask`, `createChecklistItem`, `createTaskActivity`, `createProjectActivity`) keep tests short. Pest datasets must not call `now()` or facades.

What the tests do **not** prove: SQLite ignores `lockForUpdate`, so locking is untested, and the gRPC client is mocked (Mockery), so a real connection to the Go service is untested.

## gRPC Stubs

The PHP stubs in `app/Grpc/Generated/` and the Go stubs are generated from `../proto` with Buf:

```bash
cd ../proto
buf generate
```

Do not edit generated files.