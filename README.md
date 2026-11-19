# InternPulse

Internship management for a small team: task assignment, daily logbooks with
supervisor sign-off, attendance, evaluations, guides and reports — with three
roles that each get their own view of the same data.

| Role | What they do |
| --- | --- |
| **Superadmin** | Everything a supervisor can do, plus departments, people management, invitations and a 2FA reset for locked-out accounts. |
| **Supervisor** | Owns a team of interns: assigns and reviews tasks, signs off logbooks, records attendance, writes evaluations, exports reports. |
| **Intern** | Works the kanban board, logs progress, submits the daily logbook with photo proof, reads guides and their evaluation. |

Built on Laravel 13 (PHP 8.3+), SQLite or MySQL/Postgres, and plain Blade views
with hand-written CSS — no build step is needed to run the app.

## What is in here

- **Tasks** — list and kanban board, priorities, due dates, progress notes, comments,
  blockers, help requests, approve/changes-requested decisions, archive.
- **Logbook** — one entry per intern per day, optional photo, private documents with
  supervisor verify/return/resubmit and revision history. Files are served through
  authorised routes from the private disk, never from `public/`.
- **Attendance** — daily records with a per-intern percentage and streak.
- **Evaluations & reviews** — supervisor ratings, rating breakdown, review queue.
- **Insights** — dashboard KPIs, per-intern workload, internship timeline, logbook streak.
- **Reports** — filterable summary with a CSV export.
- **Notifications** — in-app bell for task decisions, comments and reminders.
- **Scheduled reminders** — `logbook:remind` (weekdays 17:30) and `tasks:overdue`
  (weekdays 09:00), Asia/Kuala_Lumpur, idempotent per person per day. Each person
  chooses in their profile whether the reminders also arrive by email.
- **Security** — password policy, two-factor authentication (TOTP with recovery
  codes), supervisor invitations by email, hashed invitation tokens, audited
  management actions, login throttling.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Open `/` for the public landing page, `/login` to sign in. Create accounts with
`php artisan tinker` or your own seeder — there is no default password.

## Tests

```bash
php artisan test
```

The suite runs against in-memory SQLite with `MAIL_MAILER=array` and
`QUEUE_CONNECTION=sync`, so no services or build step are required. It also runs
in CI on PHP 8.3 and 8.4 for every push and pull request
(`.github/workflows/tests.yml`).

Useful single-file runs while working:

```bash
php artisan test --filter=PageHeaderTest
php artisan test tests/Feature/TwoFactorTest.php
```

## Deploying

Checklist for a real deployment. Miss the scheduler or the queue worker and the
app still serves pages — it just silently stops sending mail and reminders.

1. **Environment** — `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the
   real HTTPS URL. Set `APP_KEY` once and keep it: it encrypts the 2FA secrets, so
   changing it locks every two-factor user out.
2. **Database** — set `DB_CONNECTION` and friends, then `php artisan migrate --force`.
   SQLite is fine for a preview; use MySQL or Postgres for real use.
3. **Permissions** — the web user needs write access to `storage/` and
   `bootstrap/cache/`. Uploaded logbook photos and documents live in
   `storage/app/private` (the `local` disk, served through controller routes), so
   no `storage:link` is needed — but that directory **is** part of your data.
4. **Scheduler** — one cron entry drives both reminder commands:

   ```cron
   * * * * * cd /path/to/internpulse && php artisan schedule:run >> /dev/null 2>&1
   ```

   Confirm with `php artisan schedule:list`.
5. **Queue worker** — invitations and password-reset mail are queued
   (`QUEUE_CONNECTION=database`). Keep a worker alive under systemd or supervisor:

   ```ini
   [Unit]
   Description=InternPulse queue worker
   After=network.target

   [Service]
   User=www-data
   Restart=always
   WorkingDirectory=/path/to/internpulse
   ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

   [Install]
   WantedBy=multi-user.target
   ```

   Without a worker, messages sit in the `jobs` table forever. Watch for failures
   with `php artisan queue:failed`.
6. **Mail** — `MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`
   instead of sending them. Set a real mailer (SMTP, Mailgun, SES) before inviting
   anyone: with the log mailer, an invited supervisor never receives the link and a
   forgotten password cannot be reset.
7. **Backups** — back up the database **and** `storage/app/private` together.
   Restoring one without the other gives you logbook rows pointing at missing files.
8. **Health** — `php artisan about` for a summary, `storage/logs/laravel.log` for
   errors. `php artisan down` / `up` for maintenance windows during migrations.

### Environment variables that matter

| Variable | Why |
| --- | --- |
| `APP_KEY` | Encrypts 2FA secrets and sessions. Set once, never regenerate. |
| `APP_DEBUG` | Must be `false` in production; `true` leaks stack traces and env values. |
| `APP_URL` | Used for invitation links, password-reset links and asset URLs. |
| `DB_*` | Database connection. |
| `QUEUE_CONNECTION` | `database` in production — requires the worker from step 5. |
| `MAIL_*` | Real mailer required for invitations and password resets. |
| `SESSION_DRIVER` | `database` by default; sessions end up in your backups. |

## Layout

```
app/Http/Controllers/   WorkspaceController (intern + management), ManagementController,
                        LogbookController, ProfileController, ProfileSecurityController,
                        PasswordRecoveryController, InvitationController, auth/*
app/Services/           Business rules: TaskStatus, Reminders, TwoFactor, PasswordPolicy,
                        SupervisorInvitations
app/Console/Commands/   logbook:remind, tasks:overdue
public/                 Hand-written CSS (landing/*, management.css, theme.css,
                        workspace-ui.css, then feature files, with ui-polish.css loaded last —
                        override there, not in the earlier files) and the static landing page
resources/views/        Blade views; the shared shell is management/shell.blade.php and the
                        page title band is components/page-header.blade.php
routes/                 web.php, console.php (schedule)
```

## Known gaps

- **No CI style check.** Pint passes on most files but not all; run
  `vendor/bin/pint` before gating it in CI.
- **No holiday awareness** — a weekday public holiday still sends "you have not
  logged today".
- **Pagination is partial** — people, tasks, records and the audit log paginate;
  notifications, approvals, guides and evaluations load every row.
- **`resources/views/layout.blade.php` and `welcome.blade.php` are unused** — the
  live shell is `management/shell.blade.php` and `/` serves the static landing page.
- **No QR code for 2FA setup** — the secret is shown as text and typed by hand.
