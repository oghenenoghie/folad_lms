# Folad LMS

A multi-tenant school management platform for Nigerian schools. Laravel 13 REST API (MySQL) deployed to cPanel over SSH, paired with a Next.js frontend on Vercel talking to the API over Sanctum.

See [`.claude/skills/school-management-system/SKILL.md`](.claude/skills/school-management-system/SKILL.md) for the full domain model, stack, engineering conventions, and roadmap.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # or point DB_* at a local MySQL instance
php artisan migrate
php artisan serve
```

## Deployment

Pushes to `main` run tests, then deploy to cPanel over SSH (`.github/workflows/deploy-api.yml`). The workflow expects these repository secrets:

| Secret | Purpose |
|---|---|
| `CPANEL_HOST` | SSH host for the cPanel account |
| `CPANEL_SSH_PORT` | SSH port (cPanel is often non-standard) |
| `CPANEL_USER` | SSH username |
| `CPANEL_SSH_KEY` | Private key for a deploy keypair (public half added to cPanel's `~/.ssh/authorized_keys`) |
| `CPANEL_DEPLOY_PATH` | Absolute path to the app on the server |

`.env` lives on the server only and is never committed. The deploy script backs up the database before migrating, then runs `artisan down → migrate --force → cache → queue:restart → up`.

Because shared cPanel hosting has no Supervisor, the queue worker runs via cron rather than a long-lived process:

```
* * * * * cd <path> && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd <path> && php artisan queue:work --stop-when-empty --max-time=55
```
