# Folad LMS

A **multi-tenant school management platform** for Nigerian schools. This repository is the **Laravel 13 REST API** backend (MySQL, deployed to cPanel); the client is a separate **Next.js (App Router)** app on Vercel that talks to this API over Laravel Sanctum.

Built as a product rather than a single-institution deployment — every core table carries a `school_id`, so one API can serve many schools with isolation enforced at the application layer.

> **Organising idea:** the academic calendar is the spine. Enrolment, results, attendance, and fees all scope to a `school → academic_session → term`. Get that hierarchy right and everything else hangs off it cleanly.

See [`.claude/skills/school-management-system/SKILL.md`](.claude/skills/school-management-system/SKILL.md) for the full domain model, stack, engineering conventions, and roadmap.

## Status

Core skeleton, auth, and enrolment are done, and so is assessment: schools, academic sessions/terms, class levels/arms, subjects, students, staff, guardians, and enrolments all have full CRUD APIs, plus grading scales, assessment components, results (including a computed report endpoint), and attendance. Finance (fee structures, invoices, payments), timetable & comms, and report-card generation are staged next (see [Roadmap](#roadmap)).

## Stack

| Layer            | Choice                                                                      |
| ---------------- | --------------------------------------------------------------------------- |
| API              | Laravel 13 (PHP 8.3+), REST, Sanctum auth                                   |
| Database         | MySQL 8 (cPanel)                                                            |
| Roles            | `spatie/laravel-permission`, with **teams = `school_id`** for per-school scoping |
| Frontend         | Next.js App Router + TypeScript + Tailwind + shadcn/ui (separate repo)      |
| API deploy       | GitHub Actions (test + build) → pull-based sync to cPanel (see [Deployment](#deployment)) |
| Frontend deploy  | Vercel (push-to-deploy)                                                     |
| Media            | Cloudinary or cPanel storage + `storage:link`                              |

**Auth topology:** API at `api.<domain>`, app at `app.<domain>` on a shared apex so Sanctum stateful cookies work (`SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN=.<domain>`). If a shared apex isn't available, fall back to bearer tokens.

## Architecture

### Multi-tenancy

Every core table carries `school_id`. MySQL has no row-level security, so isolation is enforced in the application via an Eloquent **global scope** plus a `BelongsToSchool` trait. `super_admin` is the only role that bypasses the scope, and it does so explicitly. A tenant-scoped query that forgets `school_id` is treated as a data-leak bug, not a style issue.

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
touch database/database.sqlite   # or point DB_* in .env at a local MySQL instance
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

Tests gate deployment — the CI `test` job must pass before the deploy-branch publish step runs.

## Deployment

The cPanel host's firewall silently drops inbound SSH connections from GitHub Actions' cloud IPs (confirmed: connections to both port 22 and a custom port time out even with a valid key), so deployment can't be push-based. Instead it's **pull-based**: the server fetches from GitHub itself over an outbound connection, which the firewall doesn't touch.

Pushes to `main` run tests, then (`.github/workflows/deploy-api.yml`, `publish-deploy-branch` job) build `vendor/` in CI — the server has no Composer either — and force-push a self-contained snapshot (app code + `vendor/`, no history) to a `cpanel-deploy` branch. A cron job on the server pulls that branch and runs the Laravel deploy steps locally.

**One-time server setup** (via SSH, from a connection that isn't firewalled — i.e. your own):

> **Confirm the real document root first.** `foladschool.com.ng` is the account's primary domain, so its document root is `public_html/` directly — **not** `public_html/foladschool.com.ng/`. A checkout at `public_html/foladschool.com.ng/folad_lms` looks plausible but isn't served by anything; it's happened before (deploys silently landing there while the live site kept running old code). Verify in cPanel → Domains → Document Root, or just check which path shows up in `storage/logs/laravel.log` stack traces from an actual HTTP request.

```bash
cd /home2/headpock/public_html/folad_lms   # wherever the app lives

# Point the existing clone at the deploy branch instead of main
git fetch origin cpanel-deploy
git checkout -B cpanel-deploy origin/cpanel-deploy

cp .env.example .env   # if not already present; then fill in real DB_* values, APP_KEY, etc.
php artisan key:generate
php artisan storage:link
```

Then add a cron job (cPanel → Cron Jobs) that keeps it in sync:

```
*/5 * * * * (cd /home2/headpock/public_html/folad_lms && git fetch origin cpanel-deploy -q && git reset --hard origin/cpanel-deploy -q && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan queue:restart) >> /home2/headpock/public_html/folad_lms/storage/logs/deploy.log 2>&1
```

Two details that matter here and have bitten this deploy before:

- **Wrap the whole chain in `( ... )` before the `>>` redirect.** Written as a flat `&&` chain, the redirect only applies to the *last* command — every earlier step (the `git fetch`/`reset`, `migrate`) can fail silently with nothing in the log. Use an absolute path for the log file too, so a failed `cd` doesn't strand the redirect somewhere unexpected.
- **Verify `which php` in cron's environment matches your interactive shell's.** cPanel's cron runs with a minimal `PATH` that may not include the same `ea-phpXX` override your SSH session's shell profile sets up — if cron resolves `php` to an older version than the app's Composer platform requirement, every step fails with a `Composer detected issues in your platform` fatal error. If in doubt, replace the three bare `php` calls above with the absolute binary path (e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`).

`git reset --hard` is safe here because the `cpanel-deploy` branch is a generated artifact (force-pushed fresh each time, not a real history) — the server's working copy is meant to exactly mirror it. `.env` isn't part of that branch (it's gitignored), so it survives the reset untouched.

Because shared cPanel hosting has no Supervisor, the queue worker also runs via cron rather than a long-lived process:

```
* * * * * cd /home2/headpock/public_html/folad_lms && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home2/headpock/public_html/folad_lms && php artisan queue:work --stop-when-empty --max-time=55
```

**Backups:** the `migrate --force` step in the sync cron runs against a live database with no separate dump step today — schedule an independent daily `mysqldump` off-server before relying on this in production.

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
