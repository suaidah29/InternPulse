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
- **Inbox** — one page, for supervisors and superadmins, listing everything waiting
  on a decision (work for review, due-date requests, logbook pages, attendance
  photos, blockers, overdue tasks) with the approve/verify action inline. The
  sidebar badge counts it. Interns have no inbox: their dashboard is the queue.
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
8. **Health** — sign in as superadmin and open **System health** (`/management/health`).
   It answers the four questions that decide whether this install is actually
   working, none of which show up on a page a user would complain about:

   | Check | Fails when | Fix |
   | --- | --- | --- |
   | Queue worker | jobs sit in `jobs` longer than 15 minutes | start the worker from step 5 |
   | Failed jobs | anything is in `failed_jobs` | `php artisan queue:failed`, then `queue:retry all` |
   | Mail | an invitation or reset ended up `delivery_status=failed` | fix `MAIL_*`, then resend from Overview → Invitations |
   | Scheduler | the heartbeat is older than 16 minutes | check the cron entry from step 4 |

   The heartbeat is a `Schedule::call` in `routes/console.php` that stores a
   timestamp in the cache every five minutes, so a missing cron entry is visible
   instead of showing up weeks later as "the reminders never arrived". The page
   also prints the live `schedule:list`, disk and upload-directory usage, and the
   versions in play. It is read-only on purpose — nothing on a web page should
   restart a worker.

   For a shell view: `php artisan about`, `storage/logs/laravel.log`,
   `php artisan down` / `up` around migrations.

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
| `SESSION_SECURE_COOKIE` | `true` in production — the session cookie must not travel over http. |
| `TRUSTED_PROXIES` | Comma-separated proxy IPs, or `*`, when nginx/Cloudflare sits in front. Without it every request looks like it came from the proxy, so throttling and audit logs are useless. |

### Response headers

Every web response carries a `SecurityHeaders` middleware policy: a
`Content-Security-Policy` with a per-response nonce, `X-Frame-Options: DENY`,
`nosniff`, a referrer policy, a permissions policy, and HSTS once the request
arrives over TLS.

Two consequences for future work:

- **A new inline `<script>` needs `nonce="{{ $cspNonce }}"`** or the browser
  silently refuses to run it. `tests/Feature/SecurityHeadersTest.php` fails when a
  Blade view gains one without the nonce.
- The policy lists the external hosts the pages load today (Google Fonts, cdnjs,
  the sign-in hero clip). Adding another third-party asset means editing
  `app/Http/Middleware/SecurityHeaders.php` — which is the point: the list is
  visible in one place. Self-hosting those assets would let it shrink to `'self'`.

The static landing page (`public/landing/index.html`) is served by the web server
directly, so it does not pass through the middleware; add the same headers in the
nginx `location` block if you need them there.

## Layout

```
app/Http/Controllers/   One controller per job: WorkspaceController (the dashboard
                        each role opens on), TaskController, ReviewController,
                        AttendanceController, TeamController, GuideController,
                        NotificationController, FileController, LogbookController,
                        InternSupportController, InboxController, Management/
                        (superadmin: people, departments, writing guides, reports),
                        ManagementController (dashboard, invitations, import,
                        activity), ProfileController, ProfileSecurityController,
                        PasswordRecoveryController, InvitationController, auth/*
                        Concerns/ScopesWorkspace holds the four "who may I see"
                        lookups those screens share
app/Services/           Business rules: TaskStatus, Reminders, TwoFactor, PasswordPolicy,
                        SupervisorInvitations, TeamScope (who is in whose team), Audit
app/Policies/           Who may do what. Subjects are query-builder rows, so each policy
                        publishes the ability names it answers to and
                        Providers/AuthorizationServiceProvider registers them with the Gate:
                        `Gate::authorize('task.decide', $task)`. UserPolicy is found by
                        convention because its subject is an Eloquent model
app/Console/Commands/   logbook:remind, tasks:overdue
public/                 Hand-written CSS (landing/*, management.css, theme.css,
                        workspace-ui.css, then feature files, with ui-polish.css loaded last —
                        override there, not in the earlier files) and the static landing page
resources/views/        Blade views; the shared shell is management/shell.blade.php and the
                        page title band is components/page-header.blade.php
routes/                 web.php, console.php (schedule)
```

## Type scale and breakpoints

`theme.css` defines the only type steps in the product. Declare `font-size` with one of
them rather than a new `px`:

| token | size | for |
| --- | --- | --- |
| `--text-label` | 11px | uppercase micro-labels only — `th`, sidebar captions. The one step under 12px, and only for text that renders in caps |
| `--text-meta` | 12px | the floor for everything else: eyebrows, badges, timestamps, chart captions, helper text, table cells |
| `--text-sm` | 13px | dense UI — buttons, inputs, nav, prose inside cards |
| `--text-base` | 14px | default body text |
| `--text-md` | 15px | ledes and emphasised prose |
| `--text-lg` / `--text-xl` | 17px / 20px | section headings |

Display headings above that still carry their own `clamp()`/`px` size; nothing else
should. Malay strings run 15–30 % longer than English, so the floor matters: sub-12px
body text is what made the bilingual tables hard to read.

Widths used to be fifteen different values across the stylesheets. There are four now —
**480, 720, 1000, 1280** — and nothing else. `720px` collapses the sidebar, `1000px` the
two-column grids, `480px` the phone layout. A `min-width` query pairs with the step above
it (`min-width:721px` beside `max-width:720px`) so the bands never overlap, and
`max-height` queries are a separate axis for short viewports.

## Authorization

Every decision goes through `app/Policies/`. Controllers may still answer `404` for a
row outside the caller's team (a scoped `firstOrFail`, which does not confirm that the
row exists) and `409` for a row in the wrong state, but the rule itself — *may this
account act on this person's work* — lives in one place per subject.

`tests/Feature/AuthorizationPolicyTest.php` asserts the matrix per role through the
Gate, so changing a rule means arguing with that file.

## Style

There is no `pint.json`: the Laravel preset is the whole standard. `vendor/bin/pint`
fixes, `vendor/bin/pint --test` reports, and the CI `Style (Pint)` job runs the latter on
the full tree — formatting is a build failure, not a review comment. Run
`vendor/bin/pint --dirty` before committing and CI should never be the one to tell you.

## Known gaps

- **No holiday awareness** — a weekday public holiday still sends "you have not
  logged today".
- **Pagination is partial** — people, tasks, records and the audit log paginate;
  notifications, approvals, guides and evaluations load every row.
- **`resources/views/layout.blade.php` and `welcome.blade.php` are unused** — the
  live shell is `management/shell.blade.php` and `/` serves the static landing page.
- **No QR code for 2FA setup** — the secret is shown as text and typed by hand.
