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

Pushes to `main` run tests, then (`.github/workflows/deploy-api.yml`, `publish-deploy-branch` job) build `vendor/` in CI — the server has no Composer either — and force-push a self-contained snapshot (app code + `vendor/`, no history) to a `cpanel-deploy` branch. A cron job on the server pulls that branch and runs the Laravel deploy steps locally.

**One-time server setup** (via SSH, from a connection that isn't firewalled — i.e. your own):

```bash
cd /home2/headpock/public_html/foladschool.com.ng/folad_lms   # wherever the app lives

# Point the existing clone at the deploy branch instead of main
git fetch origin cpanel-deploy
git checkout -B cpanel-deploy origin/cpanel-deploy

cp .env.example .env   # if not already present; then fill in real DB_* values, APP_KEY, etc.
php artisan key:generate
php artisan storage:link
```

Then add a cron job (cPanel → Cron Jobs) that keeps it in sync:

```
*/5 * * * * (cd /home2/headpock/public_html/foladschool.com.ng/folad_lms && git fetch origin cpanel-deploy -q && git reset --hard origin/cpanel-deploy -q && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan queue:restart) >> /home2/headpock/public_html/foladschool.com.ng/folad_lms/storage/logs/deploy.log 2>&1
```

Two details that matter here and have bitten this deploy before:

- **Wrap the whole chain in `( ... )` before the `>>` redirect.** Written as a flat `&&` chain, the redirect only applies to the *last* command — every earlier step (the `git fetch`/`reset`, `migrate`) can fail silently with nothing in the log. Use an absolute path for the log file too, so a failed `cd` doesn't strand the redirect somewhere unexpected.
- **Verify `which php` in cron's environment matches your interactive shell's.** cPanel's cron runs with a minimal `PATH` that may not include the same `ea-phpXX` override your SSH session's shell profile sets up — if cron resolves `php` to an older version than the app's Composer platform requirement, every step fails with a `Composer detected issues in your platform` fatal error. If in doubt, replace the three bare `php` calls above with the absolute binary path (e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`).

`git reset --hard` is safe here because the `cpanel-deploy` branch is a generated artifact (force-pushed fresh each time, not a real history) — the server's working copy is meant to exactly mirror it. `.env` isn't part of that branch (it's gitignored), so it survives the reset untouched.

Because shared cPanel hosting has no Supervisor, the queue worker also runs via cron rather than a long-lived process:

```
* * * * * cd /home2/headpock/public_html/foladschool.com.ng/folad_lms && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home2/headpock/public_html/foladschool.com.ng/folad_lms && php artisan queue:work --stop-when-empty --max-time=55
```
