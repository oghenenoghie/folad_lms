# Folad LMS

A **multi-tenant school management platform** for Nigerian schools. This repository is the **Laravel 13 REST API** backend (Postgres on [Neon](https://neon.tech), deployed to [Railway](https://railway.com)); the client is a separate **Next.js (App Router)** app on Vercel that talks to this API over Laravel Sanctum.

Built as a product rather than a single-institution deployment — every core table carries a `school_id`, so one API can serve many schools with isolation enforced at the application layer.

> **Organising idea:** the academic calendar is the spine. Enrolment, results, attendance, and fees all scope to a `school → academic_session → term`. Get that hierarchy right and everything else hangs off it cleanly.

See [`.claude/skills/school-management-system/SKILL.md`](.claude/skills/school-management-system/SKILL.md) for the full domain model, stack, engineering conventions, and roadmap.

## Status

Core skeleton, auth, and enrolment are done, and so is assessment: schools, academic sessions/terms, class levels/arms, subjects, students, staff, guardians, and enrolments all have full CRUD APIs, plus grading scales, assessment components, results (including a computed report endpoint), and attendance. Finance (fee structures, invoices, payments), timetable & comms, and report-card generation are staged next (see [Roadmap](#roadmap)).

## Stack

| Layer            | Choice                                                                      |
| ---------------- | --------------------------------------------------------------------------- |
| API              | Laravel 13 (PHP 8.3+), REST, Sanctum auth                                   |
| Database         | Postgres 18 (Neon, serverless)                                              |
| Roles            | `spatie/laravel-permission`, with **teams = `school_id`** for per-school scoping |
| Frontend         | Next.js App Router + TypeScript + Tailwind + shadcn/ui (separate repo)      |
| API deploy       | Railway (`web`/`queue`/`cron` services, GitHub-integration deploy); CI via GitHub Actions (see [Deployment](#deployment)) |
| Frontend deploy  | Vercel (push-to-deploy)                                                     |
| Media            | Cloudinary or S3-compatible storage + `storage:link`                       |

**Auth topology:** API at `api.<domain>`, app at `app.<domain>` on a shared apex so Sanctum stateful cookies work (`SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN=.<domain>`). If a shared apex isn't available, fall back to bearer tokens.

## Architecture

### Multi-tenancy

Every core table carries `school_id`, isolated in the application via an Eloquent **global scope** plus a `BelongsToSchool` trait. Postgres does support row-level security, but the app doesn't rely on it — app-layer scoping is the enforced boundary. `super_admin` is the only role that bypasses the scope, and it does so explicitly. A tenant-scoped query that forgets `school_id` is treated as a data-leak bug, not a style issue.

### Roles

Seven roles, all school-scoped except `super_admin`. Authorization goes through Policies — never inline role checks scattered across controllers.

| Role                        | Scope                                                          |
| --------------------------- | ------------------------------------------------------------- |
| `super_admin`               | Platform owner; crosses tenants (no `school_id` scope)        |
| `school_admin`              | Full control within one school                                |
| `teacher`                   | Own classes; records results and attendance                   |
| `student`                   | Self-service — results, timetable, fees owed                  |
| `guardian`                  | Read access to linked students                                 |
| `accountant` / `bursar`     | Fees, invoices, payments                                       |
| `head_teacher` / `principal`| Optional; approvals and cross-class reporting                 |

### Core data model

```
schools (tenant root)
  └── academic_sessions ("2025/2026", is_current)
        └── terms (First/Second/Third, is_current)

users (Laravel auth + school_id + phone; roles via spatie)
  ├── staff        (staff_number, designation — teachers live here)
  ├── students     (admission_number, dob, gender, status)
  └── guardians    (relationship, occupation, contact)

guardian_student   (pivot; many guardians ↔ many students, is_primary)

class_levels (JSS 1 … SS 3, ordered)
  └── class_arms   (JSS 1A, form_teacher → staff, capacity)

subjects
  └── class_subject (which subjects are taught at which level)

enrollments (student ↔ class_arm ↔ academic_session — the key link)
  ├── results     (grading_scales + assessment_components per subject/term)
  └── attendances
```

Class structure is two-tier, matching Nigerian schools: a `class_level` ("JSS 1") holds one or more `class_arms` ("A", "Gold", "Diamond"), displayed as `level.name + arm.name` → "JSS 1A". A student's **current class is derived from their active enrolment** for the current session — it is never denormalised onto the student row.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # or point DB_* in .env at a local Postgres instance
php artisan migrate
php artisan serve
```

Frontend assets (if working on Blade/Vite-served views):

```bash
npm install
npm run dev      # or: npm run build
```

## Testing

```bash
php artisan test        # or: ./vendor/bin/phpunit
```

CI (`.github/workflows/ci.yml`) runs this on every push/PR to `main`, independent of Railway's own deploy.

## Deployment

Production runs on **Railway** with a **Neon** Postgres database, as three services built from this repo:

| Service | Start command | Purpose |
|---|---|---|
| `web` | Railway default (php-fpm + Caddy, auto-detected) | Serves the API |
| `queue` | `bash railway/run-worker.sh` | Queue worker (`QUEUE_CONNECTION=database`), runs continuously |
| `cron` | `bash railway/run-cron.sh` | Loops `php artisan schedule:run` every minute |

All three share the same source and environment variables. The `web` service runs `railway/init-app.sh` as its **pre-deploy command** on every deploy: `migrate --force`, then `optimize:clear` and re-`cache` config/events/routes/views.

Required environment variables (set on each Railway service, not committed):

| Variable | Value |
|---|---|
| `APP_KEY` | Output of `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | Neon connection string (`postgresql://...?sslmode=require`) |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` (Railway's filesystem is ephemeral — logs must go to stderr to show up in `railway logs`) |
| `LOG_STDERR_FORMATTER` | `\Monolog\Formatter\JsonFormatter` |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URLS` | Same conventions as local, pointed at the production frontend domain |

Railway's own GitHub integration builds and deploys `main` directly — `.github/workflows/ci.yml` only runs tests and does not deploy.

**Neon:** create a branch per PR for a disposable preview database (Neon's GitHub integration can automate this), and keep a `production` branch as the source of truth. Neon handles backups/point-in-time recovery, so there's no separate dump step to maintain.

## Conventions

- **Money is integer minor units** (kobo as `bigint`, NGN exponent = 2) with a per-currency exponent lookup. Never floats or decimal-of-naira. Payment rows are append-only — reverse with a compensating entry, never UPDATE/DELETE.
- **Effective-dated calendar.** Sessions and terms are dated rows with `is_current` flags. Never hardcode a year; results and fees pin to the session/term in force.
- **Derive, don't denormalise.** Current class, term position, and outstanding balance are computed from source rows, not cached columns — until proven a real performance problem.
- **Thin controllers.** Policies for authorization, Form Requests for validation, API Resources for output shape.
- **Soft deletes** on `students`, `staff`, and `guardians` — records must be recoverable and auditable.
- Student data is **sensitive PII (minors)**: strict auth, access logging on results/records, and no cross-tenant exposure in any endpoint.

## Roadmap

Core skeleton, auth, enrolment, assessment & results, and attendance (done) → finance (fee structures, invoices, payments) → timetable & comms → report-card generation.

## License

Not yet specified — add a `LICENSE` file before treating this as open source. Until then, all rights reserved.
