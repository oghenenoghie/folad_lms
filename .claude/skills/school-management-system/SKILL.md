---
name: school-management-system
description: 'Project, domain, and engineering reference for a multi-tenant school management system for Nigerian schools, built as a Laravel 13 REST API (MySQL, cPanel over SSH) with a Next.js frontend on Vercel, in the IFS LMS mold. Use for any work on this system — modelling schools, academic sessions and terms, students, guardians, staff, class levels and arms, subjects, enrolment, results and grading, attendance, or fees and invoicing; building any API endpoint, Eloquent model, migration, resource, or policy; applying tenant isolation, roles, or the money and effective-dating conventions; wiring the GitHub Actions to cPanel deploy or Sanctum cross-subdomain auth. Trigger even without a project name — Nigerian JSS/SSS class structure, admission numbers, term-based results, form teachers, school fees in kobo, or student/guardian/enrolment modelling all qualify. Always confirm the multi-tenancy and naming decisions before scaffolding, and never store money as anything but integer minor units.'
---

# School Management System

A **multi-tenant school management platform** for Nigerian schools. Backend is a **Laravel 13 REST API** on MySQL, deployed to **cPanel over SSH** via GitHub Actions; frontend is **Next.js (App Router)** on Vercel, talking to the API over Sanctum. Same delivery shape as the IFS LMS, but built as a product (tenant-ready) rather than a single-institution deployment.

Organising idea: **the academic calendar is the spine.** Almost everything that matters — enrolment, results, attendance, fees — is scoped to a `school` → `academic_session` → `term`. Get that hierarchy right and the rest hangs off it cleanly.

## Read this first — two open decisions

1. **Codename is unset.** This skill is written brand-neutral. Do not invent a name in code or table names; the domain tables (`students`, `class_arms`, etc.) don't depend on it. Confirm the product name with Patrick before creating repos, package paths, or public-facing copy. (Note: this is a *standalone Laravel project*, distinct from the `business-platform` Supabase monorepo and its separate school app — don't conflate them.)
2. **Multi-tenancy is ON by default.** Every core table carries `school_id` and is isolated at the **application layer** (Eloquent global scope + `BelongsToSchool` trait), because MySQL has no RLS. If this is confirmed single-school-forever, tenancy can be stripped — but retrofitting it later is painful, so default to keeping it.

## Stack

| Layer | Choice |
|---|---|
| API | Laravel 13 (PHP 8.3+), REST, Sanctum auth |
| DB | MySQL 8 (cPanel) |
| Roles | `spatie/laravel-permission` with **teams = school_id** for per-school scoping |
| Frontend | Next.js App Router, TypeScript, Tailwind, shadcn/ui (see `nextjs-visual` skill) |
| API deploy | GitHub Actions → SSH → cPanel (`deploy-api.yml`) |
| Frontend deploy | Vercel (push-to-deploy) |
| Media | Cloudinary or cPanel storage + `storage:link` |

Domains: API at `api.<domain>`, app at `app.<domain>` — same apex so Sanctum stateful cookies work. `SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN=.<domain>`. If a shared apex isn't possible, fall back to bearer tokens and note the security trade-off.

## Roles

Seven, all school-scoped except super_admin:

- **super_admin** — platform owner, crosses tenants (no `school_id` scope).
- **school_admin** — full control within one school.
- **teacher** — staff; sees own classes, records results/attendance.
- **student** — self-service (results, timetable, fees owed).
- **guardian** — read access to their linked students.
- **accountant / bursar** — fees, invoices, payments.
- **head_teacher / principal** — optional; approvals, cross-class reporting.

Enforce with policies, never inline `if ($user->role === ...)` checks scattered through controllers.

## Core data model (this skill's migration set)

The delivered core migrations cover the skeleton everything else hangs off:

```
schools (tenant root)
  └── academic_sessions (e.g. "2025/2026", is_current)
        └── terms (First/Second/Third, is_current)

users (Laravel auth + school_id + phone; roles via spatie)
  ├── staff        (staff_number, designation → teachers live here)
  ├── students     (admission_number, dob, gender, status)
  └── guardians    (relationship, occupation, contact)

guardian_student  (pivot; many guardians ↔ many students, is_primary)

class_levels (JSS 1 … SS 3, ordered)
  └── class_arms   (JSS 1A, form_teacher → staff, capacity)

subjects
  └── class_subject (which subjects taught at which level)

enrollments (student ↔ class_arm ↔ academic_session; the key link)
```

**Class structure is two-tier**, matching Nigerian schools: a `class_level` ("JSS 1") holds one or more `class_arms` ("A", "Gold", "Diamond"). Display is `level.name + arm.name` → "JSS 1A". A student's *current class* is derived from their active `enrollment` for the current session, **not** a column on `students` — never denormalise it onto the student row.

## Not in the core migration — staged next

Documented here so the model is understood end-to-end, but shipped in follow-up migrations:

- **Assessment** — `grading_scales`, `assessments` (CA/exam components per subject/term), `results`. Scores per student per subject per term; term/session position computed, not stored raw.
- **Attendance** — daily or per-period records per enrollment.
- **Finance** — `fee_structures` (per class_level per term), `invoices`, `payments`. **All money as integer minor units (kobo, `bigint`), never floats.** NGN exponent = 2. Append-only on payment rows; no UPDATE/DELETE on a recorded payment — reverse with a compensating entry.
- **Timetable, announcements, report-card generation** (background job — see queue note).

## Engineering conventions (Patrick's, carried forward)

- **Money** = integer minor units with a per-currency exponent lookup. Never floating point. Kobo as `bigint`.
- **Effective-dated calendar.** Sessions and terms are dated rows with `is_current` flags; never hardcode "2026". Results and fees pin to the session/term in force.
- **Tenant scope** on every query via global scope + `BelongsToSchool`. A query that forgets `school_id` is a data-leak bug, not a style nit. super_admin bypasses the scope explicitly.
- **Derive, don't denormalise.** Current class, term position, outstanding balance are computed from source rows, not cached columns (until proven a performance problem).
- **Policies for authorization**, form requests for validation, API Resources for output shape. Thin controllers.
- **Soft deletes** on `students`, `staff`, `guardians` — student records must be recoverable and auditable.
- **Frontend:** Server Components by default, `"use client"` pushed as low as possible; `cn()` for class merges; `generateMetadata()` on public pages; borders-only design, no shadows/gradients/glassmorphism; `prefers-reduced-motion` respected.

## Deployment & operations

- **API → cPanel** via `deploy-api.yml` (test job gates deploy; SSH script does `down → backup → pull → composer → migrate --force → cache → queue:restart → up`). `.env` lives on the server, never committed. cPanel SSH is often on a non-standard port.
- **Scheduler:** one cron — `* * * * * cd <path> && php artisan schedule:run >> /dev/null 2>&1`.
- **Queue reality (critical):** on shared cPanel with no root, there's no Supervisor. Run workers via cron: `* * * * * php artisan queue:work --stop-when-empty --max-time=55`. This means report-card generation, bulk imports, and guardian SMS/email run on a ~1-minute cadence, not instantly — design the UX around "processing, you'll be notified." On a VPS with root, use Supervisor + Redis for real-time workers.
- **Backups:** the deploy job `mysqldump`s before every migration; also schedule an independent daily dump off-server.

## Guardrails for Claude

- **Never store money as a float or decimal-of-naira.** Kobo integers only. A wrong fee balance is real financial harm to a real parent.
- **Never write a tenant-scoped query without `school_id`.** If unsure whether a table is tenant-scoped, it is. Cross-tenant leakage of student PII is the worst failure mode here.
- **Don't denormalise current class/position/balance** onto parent rows to "save a join" without asking — it creates silent correctness drift across terms.
- **Confirm the two open decisions** (name, tenancy) before scaffolding anything structural.
- **Student data is sensitive PII** (minors). Keep auth strict, log access to results/records, and don't expose student lists across tenants in any endpoint.
- This is not the monorepo's school app — don't import Supabase/`packages/core` patterns; this is Laravel + MySQL with app-layer isolation.

## Roadmap shape

Core skeleton (this set) → assessment & results → attendance → finance → timetable & comms → report-card generation. Ship enrolment-complete first: a school can be created, staff and students added, classes formed, and students enrolled for a session. Everything else assumes that spine exists.
