# TaskFlow Notification Service

A Go gRPC microservice that delivers every email in [TaskFlow](../README.md) and **owns the task-reminder schedule**. The Laravel API calls it through queued jobs; it never talks to the Laravel database.

## Responsibilities

- Send OTP, invitation, and password-reset emails, and record each attempt in `notification_logs`.
- Store task reminders (`task_reminders`) and deliver them 24 hours before the due date, with retries.
- Choose the mail provider once at startup, and fail fast on bad configuration.

## Code Layout

```
go-notification-service/
├── main.go                            # env, database, migrations, gRPC server, health check, reflection
├── database/migration/                # golang-migrate runner and sql/ (.up.sql files)
├── internal/modules/notification/
│   ├── config.module.go               # wiring, starts the reminder worker
│   ├── contract/                      # interfaces (Service, Repository, Mailer) and DTOs
│   ├── controllers/                   # gRPC handlers
│   ├── services/                      # validation, scheduling, worker logic
│   ├── repositories/                  # MySQL access
│   ├── mailers/                       # smtp, mailtrap, log, and the factory
│   └── values/                        # notification types, delivery and reminder statuses
└── pb/                                # generated from ../proto (do not edit)
```

## gRPC API

Contract: [`proto/notification/v1/notification.proto`](../proto/notification/v1/notification.proto).

| RPC                      | Required input                                                        | Behaviour                                                                      |
| ------------------------ | --------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `SendVerificationEmail`  | `email`, `name`, `token` (OTP)                                        | Sends the OTP email                                                            |
| `SendInvitationEmail`    | `target_email`, `project_name`, **`accept_url`**                      | Rejects an empty `accept_url`                                                  |
| `SendPasswordResetEmail` | `email`, `name`, **`reset_url`**                                      | Rejects an empty `reset_url`                                                   |
| `ScheduleTaskReminder`   | `task_id`, `task_title`, `project_name`, `assignee_email`, `due_date` | `due_date` must be RFC 3339 **in UTC**; a due date in the past cancels instead |
| `CancelTaskReminder`     | `task_id`                                                             | Idempotent                                                                     |

**Failure semantics**: operational failures (invalid input, mail provider down, database error) are returned as `success: false` with a message and a `nil` gRPC error. The Laravel client treats both as failures, so its job retries.

The server also exposes the standard gRPC health service and server reflection, so you can explore it with `grpcurl`:

```bash
grpcurl -plaintext localhost:50051 list
grpcurl -plaintext -d '{"task_id": 7}' localhost:50051 notification.v1.NotificationService/CancelTaskReminder
```

## Mail Providers

`MAIL_PROVIDER` is read once at startup (`mailers.New(LoadConfigFromEnv())`). An unknown value, or missing required variables, stops the service from starting.

| `MAIL_PROVIDER` | Behaviour                                                            | Variables                                                               |
| --------------- | -------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| empty           | Writes the email to the log, status `SUCCESS_FALLBACK` (development) | none                                                                    |
| `smtp`          | SMTP with STARTTLS and PLAIN auth                                    | `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM` |
| `mailtrap`      | Mailtrap API                                                         | `MAILTRAP_API_TOKEN`, `MAILTRAP_FROM_EMAIL`, `MAILTRAP_FROM_NAME`       |

For development, point `smtp` at a Mailtrap Email Sandbox inbox (`SMTP_HOST=sandbox.smtp.mailtrap.io`, `SMTP_PORT=2525`, credentials from the Integration tab). The sandbox captures messages instead of delivering them.

Delivery details: a 10-second send timeout covers the whole SMTP conversation, a failing `QUIT` after the message was accepted is ignored (otherwise a retry would send a duplicate), and header values containing CR or LF are rejected to prevent header injection. Non-ASCII subjects are Q-encoded.

## Reminder Design

| Aspect         | Value                                                                                      |
| -------------- | ------------------------------------------------------------------------------------------ |
| Send time      | 24 hours before `due_date`; if less time remains, as soon as possible                      |
| Poll interval  | 15 seconds                                                                                 |
| Batch size     | 25 reminders per poll                                                                      |
| Claiming       | `SELECT ... FOR UPDATE SKIP LOCKED` (MySQL 8.0.1+), so several instances never double-send |
| Stuck recovery | `PROCESSING` for more than 5 minutes is claimed again                                      |
| Retry          | Linear backoff: attempt × 5 minutes                                                        |
| Give up        | 5 attempts, then `FAILED`                                                                  |

Reminder statuses: `SCHEDULED`, `PROCESSING`, `SENT`, `CANCELLED`, `FAILED`. The primary key of `task_reminders` is `task_id`, so scheduling the same task again is an upsert. If sending succeeds but marking it as `SENT` fails, the failure is only logged; the stuck-recovery path handles the leftover state.

## Database

The service uses its own MySQL database (default name in development: `taskflow_notification_db`) with `notification_logs` and `task_reminders`; the schema diagram is in the [root README](../README.md#data-model). Migrations are `.up.sql` files in `database/migration/sql/`, applied automatically at startup by golang-migrate. All timestamps are UTC (`parseTime=true&loc=UTC`).

## Configuration

Copy `.env.example` to `.env`:

| Variable                                                          | Purpose                                                                                                              |
| ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `DB_CONNECTION`                                                   | Database driver. Only `mysql` is supported (empty defaults to `mysql`; any other value stops the service at startup) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection (`DB_HOST=host.docker.internal` when MySQL runs on the host)                                        |
| `MAIL_PROVIDER`                                                   | `smtp`, `mailtrap`, or empty (log only)                                                                              |
| `SMTP_*`, `MAILTRAP_*`                                            | Provider settings (see above)                                                                                        |

## Running

Normally through the root `docker compose` (see the [root README](../README.md#local-development)). The Go code is **not** mounted into the container:

```bash
docker compose up -d --build go-notification-service        # after changing Go code
docker compose up -d --force-recreate go-notification-service  # after editing .env
```

Without Docker (needs MySQL and a `.env`):

```bash
go run .
```

The server listens on `GRPC_PORT` (default `50051`). In production, do not publish this port to the host: the service has no authentication and must be reachable only from inside the Docker network.

## Testing

```bash
go test ./... -count=1
go test ./... -count=1 -cover
go test ./... -v                      # per-test output
```

Unit tests live next to the code and use fakes for the repository and the mailer, so they need neither MySQL nor a network.

| Package        | Coverage | What it covers                                                                                                           |
| -------------- | -------- | ------------------------------------------------------------------------------------------------------------------------ |
| `controllers`  | 100%     | Every RPC, required URLs, error-to-`success:false` mapping                                                               |
| `services`     | 99%      | Input validation, the 24-hour schedule, past due dates, backoff, batching, per-reminder error isolation, worker shutdown |
| `mailers`      | 28%      | Message building (headers, encoding, injection rejection), configuration, the factory, the log mailer                    |
| `repositories` | 0%       | Not covered                                                                                                              |

**Not covered yet**: the SMTP conversation (STARTTLS and auth), the Mailtrap provider, and the MySQL repository (`SKIP LOCKED`, recovery of stuck reminders). They need a real SMTP server and MySQL, and are planned as integration tests behind a build tag.

## Regenerating the Stubs

```bash
cd ../proto
buf generate
```

This regenerates both the Go stubs in `pb/` and the PHP stubs used by Laravel.