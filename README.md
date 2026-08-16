# Folad LMS

A multi-tenant school management platform for Nigerian schools. Laravel 13 REST API (MySQL) deployed to cPanel, paired with a Next.js frontend on Vercel talking to the API over Sanctum.

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

The cPanel host's firewall silently drops inbound SSH connections from GitHub Actions' cloud IPs (confirmed: connections to both port 22 and a custom port time out even with a valid key), so deployment can't be push-based. Instead it's **pull-based**: the server fetches from GitHub itself over an outbound connection, which the firewall doesn't touch.

Pushes to `main` run tests, then (`.github/workflows/deploy-api.yml`, `publish-deploy-branch` job) build `vendor/` in CI — the server has no Composer either — and force-push a self-contained snapshot (app code + `vendor/`, no history) to a `deploy` branch. A cron job on the server pulls that branch and runs the Laravel deploy steps locally.

**One-time server setup** (via SSH, from a connection that isn't firewalled — i.e. your own):

```bash
cd /home/headpock_folad/public_html/foladschool.com.ng/folad_lms   # wherever the app lives

# Point the existing clone at the deploy branch instead of main
git fetch origin deploy
git checkout -B deploy origin/deploy

cp .env.example .env   # if not already present; then fill in real DB_* values, APP_KEY, etc.
php artisan key:generate
php artisan storage:link
```

Then add a cron job (cPanel → Cron Jobs) that keeps it in sync:

```
*/5 * * * * cd /home/headpock_folad/public_html/foladschool.com.ng/folad_lms && git fetch origin deploy -q && git reset --hard origin/deploy -q && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan queue:restart >> storage/logs/deploy.log 2>&1
```

`git reset --hard` is safe here because the `deploy` branch is a generated artifact (force-pushed fresh each time, not a real history) — the server's working copy is meant to exactly mirror it. `.env` isn't part of that branch (it's gitignored), so it survives the reset untouched.

Because shared cPanel hosting has no Supervisor, the queue worker also runs via cron rather than a long-lived process:

```
* * * * * cd <path> && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd <path> && php artisan queue:work --stop-when-empty --max-time=55
```
