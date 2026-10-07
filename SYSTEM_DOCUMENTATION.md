# MBLISTTDA e-DTR System — System Documentation

> Complete reference for the Daily Time Record (DTR) system: architecture, features,
> data model, workflows, and a full feature/change history from the initial commit
> (2026-05-12) to the current release (2026-10-06).

**Document status:** current as of commit `54c3df6` — "10/06 overwrite holiday with punchin-edit"
**Repository:** `https://github.com/lennelthreian/dtr-laravel`
**Application name:** "e-DTR Records" (labelled `DTR System` in the Docker environment)

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Repository Layout](#3-repository-layout)
4. [Architecture & Data Sources](#4-architecture--data-sources)
5. [Roles & Access Control](#5-roles--access-control)
6. [Data Model (Tables)](#6-data-model-tables)
7. [Functional Features](#7-functional-features)
8. [Console Commands & Scheduler](#8-console-commands--scheduler)
9. [Configuration & Environment](#9-configuration--environment)
10. [Deployment (Docker)](#10-deployment-docker)
11. [Testing](#11-testing)
12. [Feature & Change History (full changelog)](#12-feature--change-history-full-changelog)
13. [Known Issues & Technical Debt](#13-known-issues--technical-debt)
14. [Appendix: Route Reference](#14-appendix-route-reference)

---

## 1. System Overview

The **MBLISTTDA e-DTR System** is an internal web application that produces Civil Service
Form No. 48 *Daily Time Record* outputs for the employees of MBLISTTDA. It replaces a manual
attendance workflow by:

- Pulling punch logs from **ZKTeco biometric devices** (raw punches live in the `zkbiotime`
  database, table `iclock_transaction`).
- Computing a per-employee, per-month **DTR** (AM/PM arrival & departure, total hours, late,
  undertime, and remarks).
- Letting employees **request corrections** to their DTR (time correction, leave, WFH, official
  business, locator slip, etc.), which flow through a **multi-level approval chain**.
- Giving administrators control over **offices/sections, roles, holidays, work arrangements,
  user accounts, audit logs, and backups**.
- Letting the **Commission on Audit (COA)** view shared monthly DTRs.
- Rendering a printed output that mirrors the official Civil Service Form No. 48.

The core computed artifact — the DTR — is **regenerated on every request** from punches plus
approved edit requests. There is no persisted aggregate DTR table.

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel **8.x** (`laravel/framework ^8.0`) |
| Language | PHP `^7.3` locally; container runs `php:7.4-fpm` |
| Database (main) | **MySQL** — database `dtr_system` |
| Database (biometric) | **MySQL** — database `zkbiotime` (separate connection) |
| Cache / Session | `file` cache; session driver `file` (Docker) / `database` (XAMPP `.env`) |
| Queue | `sync` |
| Notifications | Laravel **database** notifications (`notifications` table) |
| Google API | `google/apiclient` (Google Drive database backups) |
| HTTP client | `guzzlehttp/guzzle` |
| Schema tooling | `doctrine/dbal` (column renames) |
| Dev launcher | `serve.py` (XAMPP + `php artisan serve` helper) |
| Containerization | Docker Compose (`php-fpm` + `nginx` + `phpMyAdmin`) |
| Testing | PHPUnit `^9.3` |
| Front-end | Blade templates + plain CSS (`public/dtr.css`); no JS framework |

> **Note:** `DTR_FLOWCHART.md` labels the main DB as "SQL Server" — that is **stale**. The
> configured main database is MySQL (`dtr_system`). The biometric DB is also MySQL.

---

## 3. Repository Layout

```
app/
  Console/Commands/        Artisan commands (sync, backup)
  Helpers/                 EnvOverride helper
  Http/Controllers/        Dtr, Admin, Supervisor, DtrEditRequest, Auth/*, Api/*
  Http/Middleware/         Authenticate, SuperUser, RedirectIfAuthenticated, ...
  Models/                  Eloquent models
  Notifications/           Database notifications
  Providers/               App, Auth, Route, Event, Broadcast service providers
  Services/                UserLogService, LogsUserActivity, GoogleDriveService
  Exceptions/
config/                    database.php (mysql + zkbiotime), google-drive.php, ...
database/
  migrations/              Schema + data migrations
  seeders/                 DatabaseSeeder, SuperUserSeeder
docker/                    Dockerfile, nginx/default.conf, php/php.ini, db/*.sql
public/                    index.php, dtr.css, user-guide.html
resources/views/
  admin/                   Admin pages
  dtr/                     DTR views (index, show, dashboard, print-all, _content)
  auth/                    login, register, forgot-password, profile, password
  supervisor/              pending
  layouts/                 app.blade.php
routes/                    web.php, api.php, console.php, channels.php
storage/app/               backups/, google-drive/, public/ (logos)
tests/                     Unit tests
DTR_FLOWCHART.md           High-level mermaid flow diagram
SYSTEM_DOCUMENTATION.md    This document
docker-compose.yml         Container stack
```

> **Housekeeping note:** the repository root also contains a large number of one-off debugging
> scripts (`fix_db*.php`, `test_conn*.php`, `envtest*.php`, `dbg*.php`, `tst*.php`,
> `appdebug*.php`, `config_override.php`, `env_override.php`, `clearcache.sh`, `serve.exe`,
> `update_env_docker.ps1`). These are operational/debug artifacts, not part of the Laravel
> runtime, and are **not** excluded by `.dockerignore` (so they are copied into the Docker image).

---

## 4. Architecture & Data Sources

### 4.1 Databases

Two MySQL connections are configured in `config/database.php`:

- **`mysql`** (main) — hosts `users`, `dtr_users`, `offices`, `sections`, `dtr_edit_requests`,
  `global_holidays`, `dtr_settings`, `user_logs`, `memos`, `dtr_monthly_shares`,
  `password_reset_requests`, `punch_sync_logs`, `sync_progress`, `sessions`, etc.
- **`zkbiotime`** (biometric) — hosts the raw device tables. The application reads
  `iclock_transaction` (punches) and `personnel_employee` (employee master).

The `zkbiotime` connection falls back to the main DB credentials when its own env vars are absent.

### 4.2 Data flow

```
ZKTeco device ──► zkbiotime.iclock_transaction ──┐
                                                 ├─► DtrController::computeDtr() ─► DTR views / print
zkbiotime.personnel_employee ──► dtr_users ──────┘        ▲
                                                          │
dtr_edit_requests (approved) ─────────────────────────────┤
global_holidays ──────────────────────────────────────────┤
dtr_day_overrides / dtr_settings ─────────────────────────┘
```

1. Punches are read **live** from `iclock_transaction` at page load (per employee, per period).
2. In parallel, a scheduled command mirrors punches into the local `punch_sync_logs` table and
   a real-time webhook can push device punches — but **the DTR computation itself reads the
   ZK table directly** (the sync tables are for monitoring/integration).

### 4.3 Serving topology (Docker)

- **`dtr-nginx`** (`nginx:alpine`, port `8082`) serves static assets from a bind mount of the
  repo and proxies PHP via `fastcgi_pass app:9000`.
- **`dtr-app`** (`dtr-laravel-app`, built from `docker/Dockerfile`) runs `php-fpm`. The source
  is **baked into the image** (`COPY . .`) — it is **not** bind-mounted, so code changes require
  a rebuild (`docker compose up -d --build app`).
- **`dtr-phpmyadmin`** (port `8081`) provides DB administration.
- The DB is the **host** MySQL, reached via `host.docker.internal:3306`.

---

## 5. Roles & Access Control

There is **no roles table and no permission package**. Roles are boolean flags plus foreign-key
assignments. Two separate identity concepts exist:

- **`users`** — login accounts (`App\Models\User`).
- **`dtr_users`** — employee/organization records (`App\Models\DtrUser`), linked to `users` by
  `emp_code`.

### 5.1 Global role flags (`users`)

| Role | Column | Effect |
|---|---|---|
| Super Admin | `is_super` | Full access to `/admin/*`; can view/print **all** employees' DTRs; only role allowed to share months with COA. Enforced by the `super` middleware (`app/Http/Middleware/SuperUser.php`) and inline checks. |
| COA | `is_coa` | Can view/print DTRs **only for months recorded in `dtr_monthly_shares`**. Shows a COA badge in the UI. |
| Regular employee | (default) | Can view only their own DTR, submit edit requests, toggle their work week, manage profile/password. |
| Active/Inactive | `is_active` + SoftDeletes (`deleted_at`) | Deactivated/deleted accounts are blocked at login. |

### 5.2 Organizational roles (foreign keys into `dtr_users`)

Approvals and DTR visibility are driven by these assignments (managed by the admin):

| Role | Stored on |
|---|---|
| Section Supervisor | `sections.supervisor_id` |
| Section OIC | `sections.oic_id` |
| Office Supervisor | `offices.supervisor_id` |
| Office OIC | `offices.oic_id` |
| Senior Manager | `offices.senior_manager_id` |
| Senior Manager OIC | `offices.senior_manager_oic_id` |
| Agency Head | **Setting**, not a column: `dtr_settings.agency_head_user_id` (+ `agency_head_name`, `agency_head_position`, `agency_name`) |

### 5.3 Approval chain

When an employee submits an edit request, notifications go up the chain
(`DtrEditRequestController::notifySupervisors`):

```
Section OIC → Section Supervisor → Office OIC → Office Supervisor
            → Senior Manager OIC → Senior Manager → Agency Head → Super Admin
```

`ensureSupervisor()` authorises approval/rejection and `preventSelfApproval()` blocks a user
from approving their own request.

---

## 6. Data Model (Tables)

### Core

| Table | Model | Purpose |
|---|---|---|
| `users` | `User` | Login accounts; role flags; soft-deletes. |
| `dtr_users` | `DtrUser` | Employee records; `emp_code`, name parts, honorifics, sex, position, `office_id`/`section_id`, `employee_status`, `is_active`, `default_work_week`. |
| `offices` | `Office` | Offices with supervisor/OIC/senior-manager FK assignments. |
| `sections` | `Section` | Sections under an office, with supervisor/OIC FKs. |
| `dtr_settings` | `DtrSetting` | Key/value system configuration (cached 24h). |
| `dtr_edit_requests` | `DtrEditRequest` | Employee-submitted DTR corrections and their review state. |
| `dtr_day_overrides` | `DtrDayOverride` | Per-employee, per-date 4-day/5-day work-week override. |
| `global_holidays` | `GlobalHoliday` | Holidays **and** work suspensions (discriminated by `type`). |
| `memos` | `Memo` | Memos issued to employees from the monitoring page. |
| `dtr_monthly_shares` | `DtrMonthlyShare` | Months shared with COA. |
| `password_reset_requests` | `PasswordResetRequest` | Self-service password reset requests for admin approval. |
| `user_logs` | `UserLog` | Audit trail of user/entity activity. |
| `notifications` | (Laravel) | Database notifications. |
| `sessions` | — | Session store (added with Docker). |

### Integration / infrastructure

| Table | Model | Purpose |
|---|---|---|
| `iclock_transaction` | `IclockTransaction` (**connection `zkbiotime`**) | Raw biometric punches (read-only for DTR). |
| `punch_sync_logs` | (query builder) | Mirror of synced/webhook punches (monitoring). |
| `sync_progress` | (query builder) | Cursor for incremental punch sync. |
| `failed_jobs` | — | Laravel failed jobs. |
| `password_resets` | — | Laravel token-based password resets. |

### Key model notes

- `DtrSetting::getSettings()` caches settings for 1440 minutes and busts the cache on save/delete.
- `DtrUser`, `Office`, `Section`, `DtrSetting`, `GlobalHoliday` use the `LogsUserActivity` trait
  (auto audit-logging).
- `GlobalHoliday` `type` is `holiday` **or** `work_suspension`; `value` is one of
  `whole_day`, `am`, `pm`, `specific_time` (with nullable `start_time`/`end_time`).
- `DtrEditRequest` scopes: `pending`, `approved`, `forEmployee`, `forPeriod`,
  `deletionRequests`, `notDeletionRequests`.

### Migration history (schema evolution)

| Area | Migrations (in order) |
|---|---|
| users | create; first/middle/last + `bio_id`; office/section/is_super; username; office_id/section_id; position; sex; honorific prefix/suffix; suffix length; is_coa; is_active + softDeletes; emp_code↔bio_id renames |
| dtr_users | create; section; office_id/section_id; sex; default_work_week; honorific; suffix length; emp_code↔bio_id renames |
| dtr_settings | create; system name/logo; flexi-time; 4-day work-week; fdww weeks |
| offices | create; supervisor; senior_manager; oic; senior_manager_oic |
| sections | create; supervisor; oic |
| dtr_edit_requests | create; type; nullable field/value; work_week_type; locator-slip fields; deletion_of_request_id |
| global_holidays | create; specific-time (`start_time`/`end_time`) |
| others | notifications; dtr_day_overrides; performance indexes; user_logs; password_reset_requests; memos; dtr_monthly_shares; punch_sync_logs + sync_progress; sessions; zkbiotime `iclock_transaction` index + rename |

---

## 7. Functional Features

### 7.1 Authentication & Account Management

- **Login** (`Auth\LoginController`) — session-based. Rejects soft-deleted/inactive users,
  writes a login audit entry, then redirects super-admins to `/admin` and everyone else to `/dtr`.
- **Registration** (`Auth\RegisterController`) — creates both a `users` login and a `dtr_users`
  employee record; captures sex, name parts, and the **Biometrics ID** (the label for `emp_code`).
- **Logout** (`Auth\LogoutController`) — logs the event and invalidates the session.
- **Forgot password** (`Auth\ForgotPasswordController`) — creates a `password_reset_requests`
  row (no email is sent); the admin approves/resets from `/admin/password-reset-requests`.
- **Profile & password** (`Auth\ProfileController`) — self-service profile update (kept in sync
  across `users` and `dtr_users`) and password change.

### 7.2 DTR Computation Engine

The heart of the system is `App\Http\Controllers\DtrController` (`computeDtr()`), which builds a
month of daily rows. Pipeline:

1. **Fetch** punches for the employee + period from `iclock_transaction`
   (`IclockTransaction::forEmployee()->forPeriod()->ordered()`).
2. **Deduplicate** punches on `(minute, punch_state)`.
3. **Classify** each punch into AM/PM arrival/departure by the **nearest scheduled time**
   (`am_start`/`am_end`/`pm_start`/`pm_end`) rather than by device punch-state.
4. **Compute** total hours (lunch break subtracted), **late** (AM/PM against flexi thresholds),
   and **undertime** (expected minutes − worked minutes; expected = 8h or 10h for 4-day weeks).
5. **Apply global holidays / work suspensions** (see 7.4).
6. **Apply work arrangement** — global 4/5-day setting, per-employee `default_work_week`, and
   per-day `dtr_day_overrides`.
7. **Apply approved edit requests** (see 7.6).
8. **Recompute** half-day hours and undertime; strip/re-derive `UT:` remarks.
9. **Aggregate** present days, total minutes, late and undertime for the cut-off.

A day's rendered fields are `am_in`, `am_out`, `pm_in`, `pm_out`, `total_hours`, `remarks`,
`has_punch`, plus flags (`is_holiday`, `is_suspension`, `is_wfh`, `so_number`, `to_number`,
`ob_number`, `edited_fields`, `holiday_note`, `punch_override`, `work_week_type`).

**Cut-off periods** (`getCutOffRange`): whole month, `1–15`, or `16–end of month`.

**Remarks** are regenerated from scratch (`recalcRemarks`) as `Late:` / `UT:` fragments; the
holiday/suspension note is preserved via `holiday_note` and prepended.

> **Performance/maintainability note:** the apply-edit switch and the half-day/UT post-processing
> are duplicated across the three render paths (`index`, `show`, `dashboard`) and the bulk paths,
> which is the primary maintenance hot-spot of the file.

### 7.3 Biometric Data Integration

Two ingestion paths, plus direct live reads:

- **Employee master sync** — `dtr:sync-employees` (`SyncZktecoEmployees`) reads active
  `personnel_employee` rows from `zkbiotime` and upserts them into `dtr_users`; with
  `--create-users` it also creates login accounts (username `employee{bioId}`, default password).
  Scheduled **every 6 hours**.
- **Incremental punch sync** — `dtr:sync-punches` (`SyncBiometricPunches`) copies new
  `iclock_transaction` rows (id > `sync_progress.last_synced_id`) into `punch_sync_logs` and
  advances the cursor. Scheduled **every minute**.
- **Real-time webhook** — `POST /api/biometric/punch` (`Api\BiometricPunchController@webhook`)
  accepts device pushes and inserts into `punch_sync_logs`. `GET /api/biometric/status` reports
  sync progress. Both API endpoints are **public** (`withoutMiddleware('auth:api')`).

> The DTR itself queries `iclock_transaction` **directly**, so the sync tables are used for
> integration/monitoring, not for rendering.

### 7.4 Holidays & Work Suspensions

Managed at **Admin → Holidays** (`/admin/holidays`), stored in `global_holidays`:

- **Type**: `holiday` or `work_suspension`.
- **Value / scope**:
  - `whole_day` — full-day placeholder (`HOLIDAY` / `WORK SUSPENSION`).
  - `am` / `pm` — half-day placeholder on the affected half.
  - `specific_time` — **work suspension only**, with `start_time`/`end_time`
    (e.g. 1:00 PM – 3:00 PM). *(Introduced in commit `2a461e9`, 2026-08-04.)*
- `description` is appended to remarks.

`applyGlobalHolidays()` overlays these onto the computed DTR, and (since commit `54c3df6`):

- **Biometric punches take precedence.** If the employee actually punched in the affected scope,
  `punch_override` is set, the punch times are left intact, the holiday/suspension flags are not
  applied, and the day counts as a normal worked day — **but the holiday/suspension text is kept
  in remarks** via `holiday_note`.
- **Approved edits also take precedence.** `releaseGlobalHolidayForEdit()` clears the
  `HOLIDAY`/`WORK SUSPENSION` placeholders and flags for any day carrying a `holiday_note` when a
  non-holiday/non-suspension edit is approved, so the edit is visible in the DTR. (Remarks retain
  the holiday/suspension label.)

### 7.5 Work Arrangement & Cut-off

- **Admin → Work Arrangement** (`/admin/work-arrangement`): sets the global
  `four_day_work_week` flag and per-employee `default_work_week` (5-day/4-day). The 4-day week
  uses its own start/end/flexi schedule settings.
- **Per-day override** — employees toggle a single day's work-week type
  (`POST /dtr/toggle-day-work-week` → `dtr_day_overrides`), shown as clickable `4d`/`5d` badges.
- **Cut-off** — DTR views and printing support whole-month, 1–15, and 16–EOM cut-offs.

### 7.6 Edit Request Workflow

Employees request changes against a date; supervisors approve/reject; approved requests are
overlaid onto the DTR. Submission types (`DtrEditRequestController::store`):

`time_correction`, `absent`, `holiday`, `wfh`, `special_order`, `travel_order`,
`official_business`, `work_suspension`, `locator_slip`, `on_leave`.

Additional applied types (internal): `halfday_am`, `halfday_pm`; deletion requests via
`request_deletion`.

Highlights:

- **Scoping**: `wfh`/`special_order`/`travel_order`/`official_business`/`on_leave`/`work_suspension`
  support `whole_day`/`am`/`pm`; SO/TO/OB support **date ranges** (`resolveTargetDates`).
- **Required identifiers**: SO/TO/OB require a number; locator slip requires whereabouts.
- **Approval**: `approve`, `batchApprove`, `reject` (with reason), `requestDeletion`, `destroy`.
- **Notifications**: submit → supervisors (chain), approve/reject → employee.
- **Authority**: enforced by `ensureSupervisor()`; self-approval blocked.

### 7.7 Locator Slip (LS)

- Fields on `dtr_edit_requests`: `ls_time_left`, `ls_time_returned`, `ls_no_return`, and
  `field` = `official` or `personal`.
- **Official** slips do **not** generate undertime (guarded by an `LS: … (Official)` remark check).
- **Personal** slips deduct the elapsed time from the day's hours.
- "No Return" personal slips deduct from the time left until PM end.

### 7.8 Approvals & Supervisor Hierarchy

- **`/supervisor/pending`** (`SupervisorController@pending`) lists pending requests, grouped by
  employee; super-admins see all, supervisors see their section/office scope.
- Approval authority is derived from the office/section FK chain (see 5.2/5.3).
- Pending requests surface as a banner on the employee's DTR.

### 7.9 Administration (`/admin/*`, super-admin only)

- **Dashboard**, **Offices**, **Sections** — CRUD plus role assignments
  (supervisor, senior manager, OIC, senior-manager-OIC, section supervisor/OIC).
- **Employees** — assign employees to office/section; reset passwords.
- **Settings** — system name, logo (upload), agency head, schedule times, flexi, 4-day schedule.
- **Backup** — trigger a manual database backup.
- **Holidays** — holidays and work suspensions (see 7.4).
- **Work Arrangement** — work-week configuration (see 7.5).
- **Logs** — filterable audit trail from `user_logs`.
- **Password Reset Requests** — approve/reset.
- **Monitoring** — per-employee monthly stats and **memo issuance** (`memos`).
- **Users** — list (incl. trashed); toggle super/COA; reset password; delete/deactivate/activate.
- **COA Shares** — view months shared with COA.

### 7.10 COA Sharing

- Super-admins share a month with COA (`POST /dtr/toggle-share` → `dtr_monthly_shares`).
- Sharing is **blocked while pending edit requests exist** for the affected period.
- COA users can view/print only the shared months.

### 7.11 Audit Logging

- `UserLogService` + `LogsUserActivity` trait record `created`/`updated`/`deleted` on key models,
  plus explicit actions (login, logout, approve, reject, print, export) with IP and user-agent.
- Stored in `user_logs`; surfaced at `/admin/logs`.

### 7.12 Notifications

Database notifications only (`via() = ['database']`):

- `EditRequestSubmitted` → supervisors in the chain.
- `EditRequestApproved` → requesting employee.
- `EditRequestRejected` → requesting employee (includes rejection reason).

Marked read via `/notifications/mark-read` and `/notifications/{id}/mark-single-read`.

### 7.13 Printing

- **Per-employee**: print from the DTR view (`window.print()`).
- **Bulk**: `/dtr/print-all` (super-admin) renders all employees' DTRs in the Civil Service
  Form No. 48 layout.
- Print styles in `public/dtr.css` (`@media print`, `print-color-adjust: exact`) hide navigation
  and controls and optimize the A4 two-column layout.
- The shared partial `dtr/_content.blade.php` renders the DTR table (also used by print-all).

### 7.14 Backup & Google Drive

- `backup:run` (`BackupDatabase`) runs `mysqldump` for each configured database
  (`config/google-drive.php`, list includes `dtr_system` and `zkbiotime`) into
  `storage/app/backups`, optionally uploads to Google Drive via `GoogleDriveService`, then prunes
  beyond `max_backups`.
- Scheduled daily at **23:00** when `google-drive.scheduled_backup` is true.
- Requires a Google service-account JSON at `storage/app/google-drive/service-account.json`.

### 7.15 REST API

| Method | URI | Handler | Auth |
|---|---|---|---|
| GET | `/api/user` | closure | `auth:api` |
| POST | `/api/biometric/punch` | `Api\BiometricPunchController@webhook` | public |
| GET | `/api/biometric/status` | `Api\BiometricPunchController@status` | public |

---

## 8. Console Commands & Scheduler

| Command | Class | Purpose |
|---|---|---|
| `dtr:sync-punches {--chunk=100} {--force}` | `SyncBiometricPunches` | Mirror new `iclock_transaction` rows into `punch_sync_logs`; advance `sync_progress`. |
| `dtr:sync-employees {--create-users}` | `SyncZktecoEmployees` | Upsert `personnel_employee` into `dtr_users`; optionally create login accounts. |
| `backup:run` | `BackupDatabase` | Dump configured DBs and upload to Google Drive. |

**Schedule** (`app/Console/Kernel.php`):

| Command | Frequency | Notes |
|---|---|---|
| `backup:run` | daily at 23:00 | only if `google-drive.scheduled_backup` |
| `dtr:sync-punches` | every minute | `withoutOverlapping`, `runInBackground` |
| `dtr:sync-employees --create-users` | every 6 hours | `withoutOverlapping`, `runInBackground` |

---

## 9. Configuration & Environment

Key `.env` values:

| Key | Purpose |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_KEY`, `APP_ENV`, `APP_DEBUG` | Application identity and mode |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Main DB (`dtr_system`) |
| `ZK_DB_CONNECTION`, `ZK_DB_HOST`, `ZK_DB_PORT`, `ZK_DB_DATABASE`, `ZK_DB_USERNAME`, `ZK_DB_PASSWORD` | Biometric DB (`zkbiotime`) |
| `CACHE_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | Cache/session/queue |

`config/google-drive.php` controls service-account path, folder id, `max_backups`, scheduled
backup, and which databases are dumped.

> **Production host override:** `app/Helpers/EnvOverride.php` force-sets `DB_HOST` and
> `ZK_DB_HOST` to `192.168.1.151` (via `putenv`/`$_ENV`/`$_SERVER` and the `mysql`/`zkbiotime`
> config). It runs from `AppServiceProvider::boot()` when `APP_ENV=production`. This exists to
> pin the DB hosts in the deployment environment.

---

## 10. Deployment (Docker)

`docker-compose.yml` defines:

| Service | Image | Port | Notes |
|---|---|---|---|
| `app` | built from `docker/Dockerfile` | — | `php-fpm`; source **baked in**; mounts `storage/app/public` and `php.ini` |
| `web` | `nginx:alpine` | `8082:80` | proxies PHP to `app:9000`; bind-mounts the repo for static files |
| `phpmyadmin` | `phpmyadmin/phpmyadmin` | `8081:80` | DB admin |

The Dockerfile installs PHP extensions (`gd`, `mbstring`, `pdo_mysql`, `mysqli`, `zip`,
`bcmath`, `calendar`), copies the source, symlinks `public/storage`, copies `.env.docker` to
`.env`, runs `composer install --no-dev`, and sets permissions on `storage`/`bootstrap/cache`.

**Rebuilding after a code change:**

```powershell
docker compose up -d --build app
docker restart dtr-nginx          # nginx must re-resolve the app upstream
docker exec dtr-app php artisan config:clear
docker exec dtr-app php artisan route:clear
docker exec dtr-app php artisan view:clear
docker exec dtr-app php artisan cache:clear
```

`.env.docker` points the app at the host MySQL (`host.docker.internal`) and sets
`APP_URL=http://192.168.1.151:8082`.

> Because the source is **baked into** the `app` image (not bind-mounted), editing files on the
> host does **not** change the running application until the image is rebuilt.

---

## 11. Testing

- `tests/Unit/OfficialLsNoReturnTest.php` — the only substantive test; documents/asserts the
  locator-slip and undertime math (official vs personal, no-return, PM-end fallback, 4-day week).
  It re-implements the logic in isolation rather than exercising `DtrController` directly, so it
  can drift from production code.
- `tests/Feature/ExampleTest.php` — stock `GET /` smoke test.
- `tests/Unit/ExampleTest.php` — stock placeholder.

Run with: `php vendor/bin/phpunit` (or `php artisan test`).

---

## 12. Feature & Change History (full changelog)

Chronological, oldest → newest. All 30 commits by `lennelthreian`.

| # | Commit | Date | Summary |
|---|---|---|---|
| 1 | `323979c` | 2026-05-12 | **Initial commit** — Laravel DTR system: auth, DTR view/compute, edit requests with supervisor approvals, offices/divisions, sections, agency-head & senior-manager roles, notifications. |
| 2 | `c2828cd` | 2026-05-12 | Fix senior-manager access to pending requests; dynamic notification links. |
| 3 | `dda9052` | 2026-05-12 | Add **OIC** (Officer-In-Charge) role on offices. |
| 4 | `555f7d6` | 2026-05-12 | Sidebar navigation on all admin pages. |
| 5 | `a85bb12` | 2026-05-12 | **Print all DTRs** for super-admin. |
| 6 | `a2af9c4` | 2026-05-12 | Fix undefined `totalMinutes` in print-all. |
| 7 | `28ed03b` | 2026-05-12 | Theme → **malunggay green**. |
| 8 | `8c6331b` | 2026-05-12 | Dark-mode background → grey. |
| 9 | `77f2ebc` | 2026-05-13 | Add **sex** field; system name/logo, flexi-time, and **4-day work-week** settings; revert to global work-week toggle. |
| 10 | `e5e25e7` | 2026-05-13 | **Punch classification by nearest scheduled time** (not device punch-state); header → "e-DTR Records". |
| 11 | `8aa0552` | 2026-05-13 | **Absent handling** (explicit `ABSENT` placeholders); mobile responsiveness. |
| 12 | `74478dd` | 2026-05-14 | **Per-day work-week overrides**, **global holidays/suspensions**, **Work Arrangement** page; expanded request types (on_leave, locator_slip, wfh, so, to) with AM/PM sub-types; performance indexes. |
| 13 | `4ff6bde` | 2026-05-16 | **Locator slip** fields; new **DTR dashboard** (calendar) view. |
| 14 | `6cb3801` | 2026-05-21 | **User activity logging** (`user_logs`), self-service **profile/password**, honorific prefix/suffix. |
| 15 | `6bea7f2` | 2026-05-25 | **Forgot-password request** workflow + admin approval page; suffix length; absence/UT refactor. |
| 16 | `d7a61bf` | 2026-06-03 | **`dtr:sync-employees`** (ZKTeco employee master sync); migration/view updates. |
| 17 | `3c4a9e2` | 2026-06-03 | Registration polish; label → **"Biometrics ID"**. |
| 18 | `9af70a7` | 2026-06-03 | **Printing adjustment** (`print-color-adjust: exact`). |
| 19 | `4bcee18` | 2026-06-09 | Admin **user management** (list, toggle-super); `serve.py` dev launcher. |
| 20 | `124f8e0` | 2026-06-09 | Revert edited-row color highlighting. |
| 21 | `681006b` | 2026-06-11 | **Senior-manager-OIC** on offices; **OIC on sections**; approver-resolution refactor. |
| 22 | `c93ecf4` | 2026-06-16 | **COA groundwork**: `is_coa`, `dtr_monthly_shares`, `memos`, monitoring page; deletion requests; flowchart docs. |
| 23 | `f1af9fc` | 2026-06-16 | Rename/expand the **user guide** page. |
| 24 | `4b2008f` | 2026-06-29 | **COA sharing** end-to-end + **Google Drive backup**; user activate/deactivate/delete; **`emp_code`→`bio_id`** rename. |
| 25 | `27130c2` | 2026-06-30 | **Biometric punch sync** (`dtr:sync-punches`) + **webhook API** + `punch_sync_logs`/`sync_progress`; **revert `bio_id`→`emp_code`** on users/dtr_users. |
| 26 | `104bd44` | 2026-07-16 | **Dockerization**; **cut-off** periods (1–15 / 16–EOM); SO/TO/OB exempt from undertime. |
| 27 | `2da3ebd` | 2026-07-22 | **Official Locator Slip produces no undertime**; unit test added. |
| 28 | `0308723` | 2026-08-03 | Pending-approval banner on DTR; period header restored. |
| 29 | `2a461e9` | 2026-08-04 | **Work-suspension `specific_time`** (start/end time) — the `global_holidays.start_time`/`end_time` feature. |
| 30 | `54c3df6` | 2026-10-06 | **Biometric punches / approved edits take precedence over holidays & work suspensions**; holiday/suspension retained in remarks; `EnvOverride` for production; debug scaffolding. |

### 12.1 Feature evolution narrative

- **Foundation (2026-05-12)** — DTR viewing/computation, edit requests, office/section structure,
  approval roles, notifications, bulk printing, green theme.
- **Attendance rules (2026-05-13 → 05-16)** — sex field; configurable schedule (flexi, 4-day
  week); punch classification by scheduled boundaries; explicit `ABSENT`; **holidays/suspensions**,
  **per-day work-week overrides**, and the **Work Arrangement** page; **locator slips** and the
  calendar **dashboard**.
- **Accountability & self-service (2026-05-21 → 05-25)** — audit logging, profile/password,
  honorifics, forgot-password workflow.
- **Biometric integration (2026-06-03 → 06-30)** — ZKTeco employee sync, admin user management,
  and the punch-sync command + real-time webhook; the `emp_code`/`bio_id` rename and its revert.
- **COA & operations (2026-06-16 → 06-29)** — COA flag, monthly sharing, memos/monitoring,
  deletion requests, Google Drive backups.
- **Deployment & accuracy (2026-07-16 → 08-03)** — Dockerization, cut-off periods, SO/TO/OB
  undertime exemption, official-LS undertime fix, pending-request visibility.
- **Holiday/suspension refinements (2026-08-04 → 10-06)** — `specific_time` suspensions, then
  the rule that **actual biometric punches and approved edits override scheduled holidays and
  suspensions** while the holiday/suspension remains documented in the DTR remarks.

### 12.2 `emp_code` ⇄ `bio_id` rename sequence

1. **2026-06-29 (`4b2008f`)** — `rename_emp_code_to_bio_id` renames `emp_code`→`bio_id` on
   `users`, `dtr_users`, and `zkbiotime.iclock_transaction`.
2. **2026-06-30 (`27130c2`)** — adds `punch_sync_logs` (with `bio_id`); adds an `emp_code` column
   to `punch_sync_logs`; and `rename_bio_id_to_emp_code` **reverts** `users`/`dtr_users` to
   `emp_code` (the `zkbiotime` column is *not* reverted by that migration — a known inconsistency).
3. **2026-10-06 (`54c3df6`)** — makes the 6-29 migration idempotent-ish with `Schema::hasColumn`
   guards around the renames.

**Net state:** `users` and `dtr_users` use `emp_code`; `punch_sync_logs` has both columns;
`iclock_transaction`'s column name depends on whether the 6-29 migration ran against the live
ZK DB (see Known Issues).

---

## 13. Known Issues & Technical Debt

1. **Stale SQL Server reference** — `DTR_FLOWCHART.md` says the main DB is SQL Server; it is MySQL.
2. **`iclock_transaction` column ambiguity** — models query `emp_code`, but the 6-29 migration may
   have renamed the live `zkbiotime` column to `bio_id` (guarded, silently swallowed), and the
   `idx_bio_id_punch_time` index targets `bio_id`. If the rename applied, DTR punches could vanish.
   Verify the live column name.
3. **Remarks are not persisted** — `remarks` is a computed runtime field; only `holiday_note` and
   `punch_override` are new runtime keys. No `remarks` column exists.
4. **`punch_sync_logs` is a dead write path for DTR** — both sync and webhook write it, but the DTR
   reads `iclock_transaction` directly.
5. **Public biometric endpoints** — `POST /api/biometric/punch` and `GET /api/biometric/status`
   are unauthenticated.
6. **Duplicated DTR pipeline** — the edit-apply switch and half-day/UT post-processing are
   duplicated across `index`, `show`, `dashboard`, and bulk paths; high maintenance risk.
7. **`is_suspension` vs `is_work_suspension` mismatch** — `applyGlobalHolidays()` writes
   `is_suspension`, but `_content.blade.php` tests `is_work_suspension` in places.
8. **Committed debug scaffolding** — dozens of root-level debug/ops scripts are tracked (and baked
   into the Docker image); they should be removed and/or added to `.dockerignore`.
9. **`EnvOverride` hard-codes a host** (`192.168.1.151`) and rewrites env/config at runtime in
   production — a deployment-specific hack.
10. **Tests do not exercise production code** — the main unit test forks the logic, so it can
    drift from `DtrController`.
11. **Default super-admin credentials** in `SuperUserSeeder` (`superadmin@dtr.com` /
    `superadmin` / `superadmin123`) — rotate for production.
12. **`DtrController.php` is ~2,900 lines** — a monolith that would benefit from extraction into a
    dedicated service class.

---

## 14. Appendix: Route Reference

### Public

| Method | URI | Name |
|---|---|---|
| GET | `/` | → redirect to `login` |
| GET/POST | `/register` | `register` |
| GET/POST | `/login` | `login` |
| GET/POST | `/forgot-password` | `password.request` / `password.request.store` |
| GET | `/forgot-password/submitted` | `password.request.submitted` |
| POST | `/logout` | `logout` |
| GET | `/sections-by-office/{office}` | `sections-by-office` |

### Authenticated (`auth`)

| Method | URI | Name |
|---|---|---|
| GET | `/dtr` | `dtr.index` |
| GET | `/dtr/dashboard` | `dtr.dashboard` |
| GET | `/dtr/show` | `dtr.show` |
| GET | `/dtr/print-all` | `dtr.print-all` |
| POST | `/dtr/edit-request` | `dtr.edit-request.store` |
| POST | `/dtr/edit-request/{edit_request}/approve` | `dtr.edit-request.approve` |
| POST | `/dtr/edit-request/{edit_request}/reject` | `dtr.edit-request.reject` |
| POST | `/dtr/edit-request/{edit_request}/request-deletion` | `dtr.edit-request.request-deletion` |
| DELETE | `/dtr/edit-request/{edit_request}` | `dtr.edit-request.destroy` |
| POST | `/dtr/edit-requests/batch-approve` | `dtr.edit-requests.batch-approve` |
| POST | `/dtr/toggle-share` | `dtr.toggle-share` |
| POST | `/dtr/toggle-work-week` | `dtr.toggle-work-week` |
| POST | `/dtr/toggle-day-work-week` | `dtr.toggle-day-work-week` |
| POST | `/notifications/mark-read` | `notifications.mark-read` |
| POST | `/notifications/{notification}/mark-single-read` | `notifications.mark-single-read` |
| GET | `/supervisor/pending` | `supervisor.pending` |
| GET/POST | `/profile` | `profile` / `profile.update` |
| GET/POST | `/password` | `password.form` / `password.update` |

### Admin (`auth` + `super`, prefix `/admin`, names `admin.*`)

Dashboard; offices CRUD + assign supervisor/senior manager/OIC/senior-manager-OIC; sections CRUD +
assign supervisor/OIC; employees + assign/reset-password; settings + backup; holidays CRUD;
work-arrangement (global + per employee); logs; password-reset-requests + reset; monitoring +
issue-memo; users + toggle-super/toggle-coa/reset-password/delete/deactivate/activate;
coa-shares.

### API (`/api`)

| Method | URI | Auth |
|---|---|---|
| GET | `/api/user` | `auth:api` |
| POST | `/api/biometric/punch` | public |
| GET | `/api/biometric/status` | public |

---

*End of documentation. See `DTR_FLOWCHART.md` for the high-level flow diagram.*
