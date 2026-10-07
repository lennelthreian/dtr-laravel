# -*- coding: utf-8 -*-
"""Generate SYSTEM_DOCUMENTATION.pptx from the e-DTR system documentation.

Run:  python build_presentation.py
"""
import os

from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "SYSTEM_DOCUMENTATION.pptx")

# -- palette (malunggay green, matches the app theme) ------------------------
GREEN = RGBColor(0x2E, 0x7D, 0x32)
GREEN_DK = RGBColor(0x1B, 0x5E, 0x20)
GREEN_LT = RGBColor(0x66, 0xBB, 0x6A)
BAND = RGBColor(0xEC, 0xF5, 0xE9)
TEXT = RGBColor(0x21, 0x21, 0x21)
MUTED = RGBColor(0x5F, 0x6B, 0x5F)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)

W = Inches(13.333)
H = Inches(7.5)

prs = Presentation()
prs.slide_width = W
prs.slide_height = H
BLANK = prs.slide_layouts[6]

_slide_no = [0]


def _fill(shape, color):
    shape.fill.solid()
    shape.fill.fore_color.rgb = color
    shape.line.fill.background()
    shape.shadow.inherit = False


def _rect(slide, l, t, w, h, color):
    s = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, l, t, w, h)
    _fill(s, color)
    return s


def _txbox(slide, l, t, w, h):
    tb = slide.shapes.add_textbox(l, t, w, h)
    tf = tb.text_frame
    tf.word_wrap = True
    return tb, tf


def _run(p, text, size, color=TEXT, bold=False, italic=False):
    r = p.add_run()
    r.text = text
    r.font.size = Pt(size)
    r.font.color.rgb = color
    r.font.bold = bold
    r.font.italic = italic
    r.font.name = "Calibri"
    return r


def title_bar(slide, title):
    bar = _rect(slide, 0, 0, W, Inches(1.15), GREEN)
    tf = bar.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.margin_left = Inches(0.55)
    tf.margin_right = Inches(0.4)
    p = tf.paragraphs[0]
    _run(p, title, 30, WHITE, bold=True)
    # accent stripe
    _rect(slide, 0, Inches(1.15), W, Inches(0.07), GREEN_LT)


def footer(slide):
    _slide_no[0] += 1
    tb, tf = _txbox(slide, Inches(0.55), H - Inches(0.5), Inches(10), Inches(0.35))
    p = tf.paragraphs[0]
    _run(p, "MBLISTTDA e-DTR System — System Documentation", 10, MUTED)
    tb2, tf2 = _txbox(slide, W - Inches(1.3), H - Inches(0.5), Inches(0.8), Inches(0.35))
    p2 = tf2.paragraphs[0]
    p2.alignment = PP_ALIGN.RIGHT
    _run(p2, str(_slide_no[0]), 10, MUTED, bold=True)


def content(title, items):
    """items: list of (text, level). level 0 = bullet, 1 = sub-bullet."""
    slide = prs.slides.add_slide(BLANK)
    title_bar(slide, title)
    n = len(items)
    if n <= 6:
        size = 20
    elif n <= 9:
        size = 17
    elif n <= 12:
        size = 15
    else:
        size = 13
    tb, tf = _txbox(slide, Inches(0.7), Inches(1.5), W - Inches(1.4), H - Inches(2.2))
    first = True
    for text, level in items:
        p = tf.paragraphs[0] if first else tf.add_paragraph()
        first = False
        p.level = level
        p.space_after = Pt(7 if level == 0 else 4)
        prefix = "\u25aa  " if level == 0 else "\u2013  "
        if text.startswith("!") :  # "!" marks an emphasized (non-bullet) line
            _run(p, text[1:], size - 1, GREEN_DK, bold=True)
        else:
            _run(p, prefix + text, size - (2 if level else 0),
                 TEXT if level == 0 else MUTED,
                 bold=(level == 0))
    footer(slide)
    return slide


def section(number, title, subtitle=None):
    slide = prs.slides.add_slide(BLANK)
    _rect(slide, 0, 0, W, H, GREEN_DK)
    _rect(slide, Inches(0.9), Inches(3.05), Inches(1.6), Inches(0.09), GREEN_LT)
    tb, tf = _txbox(slide, Inches(0.9), Inches(2.0), W - Inches(1.8), Inches(1.0))
    p = tf.paragraphs[0]
    _run(p, number, 22, GREEN_LT, bold=True)
    tb2, tf2 = _txbox(slide, Inches(0.9), Inches(3.3), W - Inches(1.8), Inches(1.6))
    p2 = tf2.paragraphs[0]
    _run(p2, title, 40, WHITE, bold=True)
    if subtitle:
        tb3, tf3 = _txbox(slide, Inches(0.9), Inches(5.0), W - Inches(1.8), Inches(1.0))
        p3 = tf3.paragraphs[0]
        _run(p3, subtitle, 18, RGBColor(0xC8, 0xE6, 0xC9))
    footer(slide)
    return slide


def table_slide(title, headers, rows, widths, font=12, header_font=13):
    slide = prs.slides.add_slide(BLANK)
    title_bar(slide, title)
    nrows = len(rows) + 1
    ncols = len(headers)
    top = Inches(1.5)
    height = H - Inches(2.1)
    gtbl = slide.shapes.add_table(nrows, ncols, Inches(0.6), top, W - Inches(1.2), height)
    tbl = gtbl.table
    for i, w in enumerate(widths):
        tbl.columns[i].width = Inches(w)
    for c, htext in enumerate(headers):
        cell = tbl.cell(0, c)
        cell.text = htext
        cell.fill.solid()
        cell.fill.fore_color.rgb = GREEN
        cell.margin_left = Inches(0.08)
        cell.margin_right = Inches(0.08)
        cell.vertical_anchor = MSO_ANCHOR.MIDDLE
        r = cell.text_frame.paragraphs[0].runs[0]
        r.font.size = Pt(header_font)
        r.font.bold = True
        r.font.color.rgb = WHITE
        r.font.name = "Calibri"
    for ri, row in enumerate(rows, start=1):
        for c, val in enumerate(row):
            cell = tbl.cell(ri, c)
            cell.text = str(val)
            cell.fill.solid()
            cell.fill.fore_color.rgb = BAND if ri % 2 else WHITE
            cell.margin_left = Inches(0.08)
            cell.margin_right = Inches(0.08)
            cell.margin_top = Inches(0.02)
            cell.margin_bottom = Inches(0.02)
            cell.vertical_anchor = MSO_ANCHOR.MIDDLE
            r = cell.text_frame.paragraphs[0].runs[0]
            r.font.size = Pt(font)
            r.font.color.rgb = TEXT
            r.font.name = "Calibri"
    footer(slide)
    return slide


# ===========================================================================
# SLIDE 1 — TITLE
# ===========================================================================
s = prs.slides.add_slide(BLANK)
_rect(s, 0, 0, W, H, GREEN_DK)
_rect(s, 0, Inches(5.55), W, Inches(0.12), GREEN_LT)
tb, tf = _txbox(s, Inches(1.0), Inches(1.7), W - Inches(2.0), Inches(1.6))
p = tf.paragraphs[0]
_run(p, "MBLISTTDA e-DTR System", 46, WHITE, bold=True)
tb2, tf2 = _txbox(s, Inches(1.0), Inches(3.0), W - Inches(2.0), Inches(0.8))
p2 = tf2.paragraphs[0]
_run(p2, "System Documentation", 28, GREEN_LT, bold=True)
tb3, tf3 = _txbox(s, Inches(1.0), Inches(3.9), W - Inches(2.0), Inches(1.2))
p3 = tf3.paragraphs[0]
_run(p3, "Architecture  ·  Features  ·  Workflows  ·  Change History", 18,
     RGBColor(0xC8, 0xE6, 0xC9))
p4 = tf3.add_paragraph()
_run(p4, "Prepared from commit 54c3df6  ·  2026-10-06", 14, RGBColor(0x9C, 0xC8, 0x9E))
_slide_no[0] += 1

# ===========================================================================
# SLIDE 2 — AGENDA
# ===========================================================================
content("Agenda", [
    ("1.  System Overview", 0),
    ("2.  Architecture & Data Sources", 0),
    ("3.  Roles & Access Control", 0),
    ("4.  Data Model (Tables)", 0),
    ("5.  Functional Features", 0),
    ("6.  Console Commands & Scheduler", 0),
    ("7.  Configuration & Environment", 0),
    ("8.  Deployment (Docker)", 0),
    ("9.  Testing", 0),
    ("10. Feature & Change History", 0),
    ("11. Known Issues & Technical Debt", 0),
    ("12. Appendix: Route Reference", 0),
])

# ===========================================================================
section("01", "System Overview", "What the e-DTR system is and what it replaces")

content("What the System Does", [
    ("Internal web app producing Civil Service Form No. 48 DTRs for MBLISTTDA employees.", 0),
    ("Pulls punch logs from ZKTeco biometric devices (zkbiotime.iclock_transaction).", 0),
    ("Computes per-employee, per-month DTR: AM/PM in/out, total hours, late, undertime, remarks.", 0),
    ("Lets employees request DTR corrections through a multi-level approval chain.", 0),
    ("Gives admins control over offices, sections, roles, holidays, work arrangements, accounts, logs, backups.", 0),
    ("Lets the Commission on Audit (COA) view shared monthly DTRs.", 0),
    ("!Key design: the DTR is regenerated on every request from punches + approved edits — no persisted DTR table.", 0),
])

content("Technology Stack", [
    ("Framework:  Laravel 8.x      Language:  PHP ^7.3 (container: php:7.4-fpm)", 0),
    ("Main database:  MySQL — dtr_system", 0),
    ("Biometric database:  MySQL — zkbiotime (separate connection)", 0),
    ("Cache / session:  file      Queue:  sync", 0),
    ("Notifications:  Laravel database notifications (notifications table)", 0),
    ("External:  google/apiclient (Drive backups), guzzlehttp/guzzle, doctrine/dbal", 0),
    ("Containerization:  Docker Compose (php-fpm + nginx + phpMyAdmin)", 0),
    ("Testing:  PHPUnit ^9.3      Front-end:  Blade + plain CSS (public/dtr.css)", 0),
    ("!Note: DTR_FLOWCHART.md wrongly states SQL Server — the DB is MySQL.", 0),
])

# ===========================================================================
section("02", "Architecture & Data Sources", "Two MySQL connections plus the compute pipeline")

content("Data Flow", [
    ("ZKTeco device  \u2192  zkbiotime.iclock_transaction  (raw punches)", 0),
    ("+ zkbiotime.personnel_employee  \u2192  dtr_users  (employee master)", 0),
    ("These feed  DtrController::computeDtr()  along with:", 0),
    ("approved dtr_edit_requests", 1),
    ("global_holidays (holidays + work suspensions)", 1),
    ("dtr_day_overrides / dtr_settings", 1),
    ("Output: DTR views, dashboard calendar, bulk print", 0),
    ("!Punches are read live from iclock_transaction at page load; sync tables are for monitoring/integration.", 0),
])

content("Databases & Serving Topology", [
    ("mysql (main) — dtr_system: users, dtr_users, offices, sections, dtr_edit_requests, global_holidays, dtr_settings, user_logs, memos, dtr_monthly_shares, password_reset_requests, punch_sync_logs, sync_progress, sessions", 0),
    ("zkbiotime (biometric) — iclock_transaction, personnel_employee; falls back to main DB creds when unset", 0),
    ("!Docker topology:", 0),
    ("dtr-nginx (nginx:alpine, port 8082) — serves static assets, proxies PHP to app:9000", 1),
    ("dtr-app (dtr-laravel-app image) — php-fpm; source BAKED IN (COPY . .), not bind-mounted", 1),
    ("dtr-phpmyadmin (port 8081) — DB administration", 1),
    ("!Code changes require rebuilding the app image — editing host files is not enough.", 0),
])

# ===========================================================================
section("03", "Roles & Access Control", "Flags + foreign-key assignments; no roles table")

table_slide("Global role flags (users)", ["Role", "Column", "Effect"], [
    ["Super Admin", "is_super", "Full /admin/* access; can view/print all DTRs; only role that can share with COA (super middleware)"],
    ["COA", "is_coa", "Can view/print only months recorded in dtr_monthly_shares; COA badge in UI"],
    ["Regular employee", "(default)", "Own DTR only; submit edit requests; toggle work week; manage profile/password"],
    ["Active / Inactive", "is_active + SoftDeletes", "Deactivated or deleted accounts blocked at login"],
], widths=[2.1, 2.6, 7.4], font=13)

table_slide("Organizational roles (managed by admin)", ["Role", "Stored on"], [
    ["Section Supervisor", "sections.supervisor_id"],
    ["Section OIC", "sections.oic_id"],
    ["Office Supervisor", "offices.supervisor_id"],
    ["Office OIC", "offices.oic_id"],
    ["Senior Manager", "offices.senior_manager_id"],
    ["Senior Manager OIC", "offices.senior_manager_oic_id"],
    ["Agency Head", "dtr_settings.agency_head_user_id (a setting, not a column)"],
], widths=[4.5, 7.6], font=14)

content("Approval Chain", [
    ("When an employee submits an edit request, notifications climb the chain:", 0),
    ("!Section OIC \u2192 Section Supervisor \u2192 Office OIC \u2192 Office Supervisor \u2192 Senior Manager OIC \u2192 Senior Manager \u2192 Agency Head \u2192 Super Admin", 0),
    ("ensureSupervisor() authorises approval / rejection", 0),
    ("preventSelfApproval() blocks approving your own request", 0),
    ("Identity: users = login accounts; dtr_users = employee/org records linked by emp_code", 0),
])

# ===========================================================================
section("04", "Data Model (Tables)", "Core application tables and integration tables")

table_slide("Core tables", ["Table", "Purpose"], [
    ["users", "Login accounts; role flags; soft-deletes"],
    ["dtr_users", "Employee records (emp_code, name, office/section, employee_status, default_work_week)"],
    ["offices / sections", "Organisation structure with supervisor / OIC / senior-manager FK assignments"],
    ["dtr_settings", "Key/value system config (cached 24h)"],
    ["dtr_edit_requests", "Employee DTR corrections and review state"],
    ["dtr_day_overrides", "Per-employee, per-date 4-day / 5-day work-week override"],
    ["global_holidays", "Holidays AND work suspensions (type = holiday | work_suspension)"],
    ["memos / dtr_monthly_shares", "Memos to employees; months shared with COA"],
    ["password_reset_requests", "Self-service password reset requests for admin approval"],
    ["user_logs / notifications", "Audit trail; database notifications"],
], widths=[3.6, 8.5], font=12)

table_slide("Integration & infrastructure tables", ["Table", "Purpose"], [
    ["iclock_transaction (connection zkbiotime)", "Raw biometric punches (read-only for DTR)"],
    ["punch_sync_logs", "Mirror of synced / webhook punches (monitoring)"],
    ["sync_progress", "Cursor for incremental punch sync"],
    ["sessions / failed_jobs / password_resets", "Laravel infrastructure tables"],
], widths=[4.6, 7.5], font=13)

content("Key Model Notes", [
    ("DtrSetting::getSettings() caches for 1440 minutes and busts cache on save/delete.", 0),
    ("DtrUser, Office, Section, DtrSetting, GlobalHoliday use the LogsUserActivity trait (auto audit-logging).", 0),
    ("GlobalHoliday type = holiday | work_suspension; value = whole_day | am | pm | specific_time (with start_time/end_time).", 0),
    ("DtrEditRequest scopes: pending, approved, forEmployee, forPeriod, deletionRequests, notDeletionRequests.", 0),
])

# ===========================================================================
section("05", "Functional Features", "The behaviours that make up the system")

content("DTR Computation Engine", [
    ("1. Fetch punches for employee + period from iclock_transaction", 0),
    ("2. Deduplicate on (minute, punch_state)", 0),
    ("3. Classify each punch AM/PM in/out by NEAREST scheduled time (not device state)", 0),
    ("4. Compute total hours (lunch subtracted), late (flexi thresholds), undertime (expected 8h / 10h)", 0),
    ("5. Apply global holidays / work suspensions", 0),
    ("6. Apply work arrangement (global 4/5-day, per-employee, per-day overrides)", 0),
    ("7. Apply approved edit requests", 0),
    ("8. Recompute half-day hours and undertime; re-derive UT remarks", 0),
    ("9. Aggregate present days, total minutes, late, undertime for the cut-off", 0),
    ("!Cut-off: whole month, 1\u201315, or 16\u2013end of month.", 0),
])

content("Biometric Data Integration", [
    ("Employee master sync — dtr:sync-employees reads active personnel_employee rows into dtr_users; --create-users also creates logins (username employee{bioId}). Scheduled every 6 hours.", 0),
    ("Incremental punch sync — dtr:sync-punches copies new iclock_transaction rows into punch_sync_logs and advances sync_progress. Scheduled every minute.", 0),
    ("Real-time webhook — POST /api/biometric/punch inserts device pushes into punch_sync_logs.", 0),
    ("GET /api/biometric/status reports sync progress.", 0),
    ("!DTR queries iclock_transaction directly; sync tables are for integration/monitoring only.", 0),
])

content("Holidays & Work Suspensions", [
    ("Managed at Admin \u2192 Holidays, stored in global_holidays.", 0),
    ("Type: holiday | work_suspension", 0),
    ("Scope: whole_day \u2192 placeholders HOLIDAY / WORK SUSPENSION", 1),
    ("am / pm \u2192 half-day placeholder on the affected half", 1),
    ("specific_time \u2192 work suspension only, with start_time/end_time", 1),
    ("!Latest rule (commit 54c3df6):", 0),
    ("Biometric punches take precedence — if the employee punched, punch_override is set, times stay intact, flags are skipped, day counts as worked, but the holiday text is kept in remarks (holiday_note).", 0),
    ("Approved edits also take precedence — releaseGlobalHolidayForEdit() clears placeholders/flags when a non-holiday edit is approved.", 0),
])

content("Work Arrangement & Cut-off", [
    ("Admin \u2192 Work Arrangement: global four_day_work_week flag + per-employee default_work_week (5-day / 4-day).", 0),
    ("4-day week uses its own start/end/flexi schedule settings.", 0),
    ("Per-day override — employees toggle a single day's work week (POST /dtr/toggle-day-work-week \u2192 dtr_day_overrides), shown as clickable 4d / 5d badges.", 0),
    ("Cut-off — DTR views and printing support whole-month, 1\u201315, and 16\u2013EOM periods.", 0),
])

content("Edit Request Workflow", [
    ("Employees request date changes; supervisors approve/reject; approved requests overlay the DTR.", 0),
    ("!Submission types:", 0),
    ("time_correction · absent · holiday · wfh · special_order · travel_order", 0),
    ("official_business · work_suspension · locator_slip · on_leave", 0),
    ("Internal/extra applied types: halfday_am, halfday_pm; deletion via request_deletion", 0),
    ("Sub-types: wfh/so/to/ob/ws \u2208 whole_day, am, pm; ls \u2208 official, personal", 0),
    ("Scoping: SO / TO / OB support date ranges (resolveTargetDates); SO/TO/OB require a number; locator slip requires whereabouts.", 0),
    ("Approval: approve, batchApprove, reject (reason), requestDeletion, destroy; self-approval blocked.", 0),
])

content("Locator Slip (LS)", [
    ("Fields on dtr_edit_requests: ls_time_left, ls_time_returned, ls_no_return, field = official | personal.", 0),
    ("Official slips do NOT generate undertime (guarded by an LS: \u2026 (Official) remark check).", 0),
    ("Personal slips deduct the elapsed time from the day's hours.", 0),
    ("\u201cNo Return\u201d personal slips deduct from the time left until PM end.", 0),
])

content("Approvals, Administration & COA", [
    ("Supervisor \u2192 /supervisor/pending lists pending requests grouped by employee; super-admins see all.", 0),
    ("Administration (/admin/*, super-admin): dashboard, offices, sections, employees, settings, backup, holidays, work arrangement, logs, password-reset requests, monitoring + memos, users, COA shares.", 0),
    ("COA sharing \u2192 POST /dtr/toggle-share writes dtr_monthly_shares; blocked while pending requests exist; COA sees only shared months.", 0),
    ("Audit logging \u2192 UserLogService + LogsUserActivity record create/update/delete and explicit actions (login, logout, approve, reject, print, export) with IP and user-agent, surfaced at /admin/logs.", 0),
])

content("Notifications, Printing & Backup", [
    ("!Notifications (database only):", 0),
    ("EditRequestSubmitted \u2192 supervisors in the chain", 1),
    ("EditRequestApproved / EditRequestRejected \u2192 requesting employee (rejection includes reason)", 1),
    ("!Printing:", 0),
    ("Per-employee print from the DTR view; bulk /dtr/print-all (super-admin) in Civil Service Form No. 48 layout", 1),
    ("print styles in public/dtr.css (@media print, print-color-adjust: exact); shared partial dtr/_content.blade.php", 1),
    ("!Backup & Google Drive:", 0),
    ("backup:run (BackupDatabase) runs mysqldump for dtr_system + zkbiotime into storage/app/backups, optionally uploads via GoogleDriveService, prunes beyond max_backups; daily at 23:00 when scheduled_backup is on", 1),
])

content("REST API", [
    ("GET  /api/user  \u2014  closure  \u2014  auth:api", 0),
    ("POST  /api/biometric/punch  \u2014  BiometricPunchController@webhook  \u2014  public", 0),
    ("GET  /api/biometric/status  \u2014  BiometricPunchController@status  \u2014  public", 0),
    ("!Note: the biometric endpoints are unauthenticated (see Known Issues).", 0),
])

# ===========================================================================
section("06", "Console Commands & Scheduler", "Background jobs that keep data fresh")

table_slide("Artisan commands", ["Command", "Class", "Purpose"], [
    ["dtr:sync-punches", "SyncBiometricPunches", "Mirror new iclock_transaction rows into punch_sync_logs; advance sync_progress"],
    ["dtr:sync-employees", "SyncZktecoEmployees", "Upsert personnel_employee into dtr_users; --create-users also creates logins"],
    ["backup:run", "BackupDatabase", "Dump configured DBs and upload to Google Drive"],
], widths=[2.7, 3.1, 6.4], font=12)

table_slide("Schedule", ["Command", "Frequency", "Notes"], [
    ["backup:run", "daily at 23:00", "only if google-drive.scheduled_backup"],
    ["dtr:sync-punches", "every minute", "withoutOverlapping, runInBackground"],
    ["dtr:sync-employees --create-users", "every 6 hours", "withoutOverlapping, runInBackground"],
], widths=[4.0, 3.0, 5.2], font=13)

# ===========================================================================
section("07", "Configuration & Environment", "Key .env values and the production host override")

content("Configuration", [
    ("App: APP_NAME, APP_URL, APP_KEY, APP_ENV, APP_DEBUG", 0),
    ("Main DB: DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE (dtr_system), DB_USERNAME, DB_PASSWORD", 0),
    ("Biometric DB: ZK_DB_CONNECTION, ZK_DB_HOST, ZK_DB_PORT, ZK_DB_DATABASE (zkbiotime), ZK_DB_USERNAME, ZK_DB_PASSWORD", 0),
    ("Runtime: CACHE_DRIVER, SESSION_DRIVER, QUEUE_CONNECTION", 0),
    ("config/google-drive.php \u2014 service-account path, folder id, max_backups, scheduled backup, databases dumped", 0),
    ("!Production host override: app/Helpers/EnvOverride.php force-sets DB_HOST and ZK_DB_HOST to 192.168.1.151 in production (APP_ENV=production).", 0),
])

# ===========================================================================
section("08", "Deployment (Docker)", "Services, ports, and the rebuild procedure")

table_slide("Docker Compose services", ["Service", "Image", "Port", "Notes"], [
    ["app", "built from docker/Dockerfile", "\u2014", "php-fpm; source baked in; mounts storage/app/public + php.ini"],
    ["web", "nginx:alpine", "8082:80", "proxies PHP to app:9000; bind-mounts repo for static files"],
    ["phpmyadmin", "phpmyadmin/phpmyadmin", "8081:80", "DB administration"],
], widths=[1.9, 4.3, 1.7, 4.2], font=12)

content("Rebuilding After a Code Change", [
    ("docker compose up -d --build app", 0),
    ("docker restart dtr-nginx        # re-resolve the app upstream", 0),
    ("docker exec dtr-app php artisan config:clear", 0),
    ("docker exec dtr-app php artisan route:clear", 0),
    ("docker exec dtr-app php artisan view:clear", 0),
    ("docker exec dtr-app php artisan cache:clear", 0),
    ("!.env.docker points at host MySQL (host.docker.internal) and APP_URL=http://192.168.1.151:8082", 0),
    ("Because source is baked into the app image, host edits do not affect the running app until rebuilt.", 0),
])

# ===========================================================================
section("09", "Testing", "What is covered — and what is not")

content("Testing", [
    ("tests/Unit/OfficialLsNoReturnTest.php \u2014 the only substantive test; asserts locator-slip and undertime math (official vs personal, no-return, PM-end fallback, 4-day week).", 0),
    ("It re-implements the logic in isolation rather than exercising DtrController \u2014 so it can drift from production code.", 0),
    ("tests/Feature/ExampleTest.php \u2014 stock GET / smoke test.", 0),
    ("tests/Unit/ExampleTest.php \u2014 stock placeholder.", 0),
    ("!Run with: php vendor/bin/phpunit   (or   php artisan test)", 0),
])

# ===========================================================================
section("10", "Feature & Change History", "30 commits from 2026-05-12 to 2026-10-06, all by lennelthreian")

table_slide("Changelog (1\u201310)", ["#", "Commit", "Date", "Summary"], [
    ["1", "323979c", "2026-05-12", "Initial commit \u2014 Laravel DTR: auth, DTR view/compute, edit requests + approvals, offices/divisions, sections, agency-head & senior-manager roles, notifications"],
    ["2", "c2828cd", "2026-05-12", "Fix senior-manager access to pending requests; dynamic notification links"],
    ["3", "dda9052", "2026-05-12", "Add OIC (Officer-In-Charge) role on offices"],
    ["4", "555f7d6", "2026-05-12", "Sidebar navigation on all admin pages"],
    ["5", "a85bb12", "2026-05-12", "Print all DTRs for super-admin"],
    ["6", "a2af9c4", "2026-05-12", "Fix undefined totalMinutes in print-all"],
    ["7", "28ed03b", "2026-05-12", "Theme \u2192 malunggay green"],
    ["8", "8c6331b", "2026-05-12", "Dark-mode background \u2192 grey"],
    ["9", "77f2ebc", "2026-05-13", "Add sex field; system name/logo, flexi-time, 4-day work-week settings; revert to global work-week toggle"],
    ["10", "e5e25e7", "2026-05-13", "Punch classification by nearest scheduled time (not device state); header \u2192 e-DTR Records"],
], widths=[0.5, 1.15, 1.35, 9.1], font=10, header_font=12)

table_slide("Changelog (11\u201320)", ["#", "Commit", "Date", "Summary"], [
    ["11", "8aa0552", "2026-05-13", "Absent handling (explicit ABSENT placeholders); mobile responsiveness"],
    ["12", "74478dd", "2026-05-14", "Per-day work-week overrides; global holidays/suspensions; Work Arrangement page; expanded request types (on_leave, locator_slip, wfh, so, to) with AM/PM sub-types; performance indexes"],
    ["13", "4ff6bde", "2026-05-16", "Locator slip fields; new DTR dashboard (calendar) view"],
    ["14", "6cb3801", "2026-05-21", "User activity logging (user_logs); self-service profile/password; honorific prefix/suffix"],
    ["15", "6bea7f2", "2026-05-25", "Forgot-password request workflow + admin approval page; suffix length; absence/UT refactor"],
    ["16", "d7a61bf", "2026-06-03", "dtr:sync-employees (ZKTeco employee master sync); migration/view updates"],
    ["17", "3c4a9e2", "2026-06-03", "Registration polish; label \u2192 Biometrics ID"],
    ["18", "9af70a7", "2026-06-03", "Printing adjustment (print-color-adjust: exact)"],
    ["19", "4bcee18", "2026-06-09", "Admin user management (list, toggle-super); serve.py dev launcher"],
    ["20", "124f8e0", "2026-06-09", "Revert edited-row color highlighting"],
], widths=[0.5, 1.15, 1.35, 9.1], font=10, header_font=12)

table_slide("Changelog (21\u201330)", ["#", "Commit", "Date", "Summary"], [
    ["21", "681006b", "2026-06-11", "Senior-manager-OIC on offices; OIC on sections; approver-resolution refactor"],
    ["22", "c93ecf4", "2026-06-16", "COA groundwork: is_coa, dtr_monthly_shares, memos, monitoring page; deletion requests; flowchart docs"],
    ["23", "f1af9fc", "2026-06-16", "Rename/expand the user guide page"],
    ["24", "4b2008f", "2026-06-29", "COA sharing end-to-end + Google Drive backup; user activate/deactivate/delete; emp_code\u2192bio_id rename"],
    ["25", "27130c2", "2026-06-30", "Biometric punch sync (dtr:sync-punches) + webhook API + punch_sync_logs/sync_progress; revert bio_id\u2192emp_code on users/dtr_users"],
    ["26", "104bd44", "2026-07-16", "Dockerization; cut-off periods (1\u201315 / 16\u2013EOM); SO/TO/OB exempt from undertime"],
    ["27", "2da3ebd", "2026-07-22", "Official Locator Slip produces no undertime; unit test added"],
    ["28", "0308723", "2026-08-03", "Pending-approval banner on DTR; period header restored"],
    ["29", "2a461e9", "2026-08-04", "Work-suspension specific_time (start/end time)"],
    ["30", "54c3df6", "2026-10-06", "Biometric punches / approved edits take precedence over holidays & suspensions; holiday retained in remarks; EnvOverride for production"],
], widths=[0.5, 1.15, 1.35, 9.1], font=10, header_font=12)

content("Feature Evolution Narrative", [
    ("Foundation (2026-05-12): DTR computation, edit requests, office/section structure, approval roles, notifications, bulk printing, green theme.", 0),
    ("Attendance rules (05-13 \u2192 05-16): sex field; configurable schedule; punch classification by scheduled boundaries; explicit ABSENT; holidays/suspensions, per-day work-week overrides, Work Arrangement; locator slips; calendar dashboard.", 0),
    ("Accountability & self-service (05-21 \u2192 05-25): audit logging, profile/password, honorifics, forgot-password workflow.", 0),
    ("Biometric integration (06-03 \u2192 06-30): ZKTeco employee sync, admin user management, punch-sync command + webhook; the emp_code/bio_id rename and its revert.", 0),
    ("COA & operations (06-16 \u2192 06-29): COA flag, monthly sharing, memos/monitoring, deletion requests, Google Drive backups.", 0),
    ("Deployment & accuracy (07-16 \u2192 08-03): Dockerization, cut-off periods, SO/TO/OB undertime exemption, official-LS fix, pending-request visibility.", 0),
    ("Holiday/suspension refinements (08-04 \u2192 10-06): specific_time suspensions; actual punches and approved edits override scheduled holidays/suspensions while remaining documented in remarks.", 0),
])

# ===========================================================================
section("11", "Known Issues & Technical Debt", "Items to address next")

content("Known Issues & Technical Debt", [
    ("1. DTR_FLOWCHART.md says SQL Server \u2014 actually MySQL.", 0),
    ("2. iclock_transaction column ambiguity \u2014 6-29 rename may have changed the live zkbiotime column to bio_id (guarded, swallowed); verify the live column name.", 0),
    ("3. Remarks are not persisted \u2014 only holiday_note and punch_override are runtime keys.", 0),
    ("4. punch_sync_logs is a dead write path for DTR \u2014 the DTR reads iclock_transaction directly.", 0),
    ("5. Public biometric endpoints are unauthenticated.", 0),
    ("6. Duplicated DTR pipeline across index/show/dashboard/bulk paths \u2014 high maintenance risk.", 0),
    ("7. is_suspension vs is_work_suspension mismatch between applyGlobalHolidays() and _content.blade.php.", 0),
    ("8. Committed debug scaffolding (dozens of root-level scripts) baked into the Docker image.", 0),
    ("9. EnvOverride hard-codes a host (192.168.1.151) in production.", 0),
    ("10. Tests do not exercise production code \u2014 the main test forks the logic.", 0),
    ("11. Default super-admin credentials in SuperUserSeeder \u2014 rotate for production.", 0),
    ("12. DtrController.php is ~2,900 lines \u2014 extract into a dedicated service class.", 0),
])

# ===========================================================================
section("12", "Appendix", "Route reference")

table_slide("Route reference (key routes)", ["Method", "URI", "Name / Handler"], [
    ["GET", "/", "redirect to login"],
    ["GET/POST", "/register", "register"],
    ["GET/POST", "/login", "login"],
    ["GET/POST", "/forgot-password", "password.request / .store"],
    ["POST", "/logout", "logout (no auth required)"],
    ["GET", "/dtr", "dtr.index"],
    ["GET", "/dtr/dashboard", "dtr.dashboard"],
    ["GET", "/dtr/show", "dtr.show"],
    ["GET", "/dtr/print-all", "dtr.print-all"],
    ["POST", "/dtr/edit-request", "dtr.edit-request.store"],
    ["POST", "/dtr/edit-request/{id}/approve|reject", "dtr.edit-request.approve / reject"],
    ["POST", "/dtr/edit-requests/batch-approve", "dtr.edit-requests.batch-approve"],
    ["POST", "/dtr/toggle-share | toggle-work-week | toggle-day-work-week", "dtr.toggle-*"],
    ["GET", "/supervisor/pending", "supervisor.pending"],
    ["GET/POST", "/profile  \u00b7  /password", "profile / password"],
    ["\u2014", "/admin/*  (auth + super)", "admin.* (offices, sections, employees, settings, holidays, work-arrangement, logs, monitoring, users, coa-shares, \u2026)"],
    ["GET", "/api/user  \u00b7  /api/biometric/punch  \u00b7  /api/biometric/status", "user (auth:api) \u00b7 biometric webhook / status (public)"],
], widths=[1.5, 5.4, 5.2], font=11)

# ===========================================================================
# CLOSING
# ===========================================================================
s = prs.slides.add_slide(BLANK)
_rect(s, 0, 0, W, H, GREEN_DK)
_rect(s, Inches(0.9), Inches(3.55), Inches(1.6), Inches(0.09), GREEN_LT)
tb, tf = _txbox(s, Inches(0.9), Inches(2.3), W - Inches(1.8), Inches(1.2))
p = tf.paragraphs[0]
_run(p, "Thank You", 44, WHITE, bold=True)
tb2, tf2 = _txbox(s, Inches(0.9), Inches(3.8), W - Inches(1.8), Inches(1.6))
p2 = tf2.paragraphs[0]
_run(p2, "MBLISTTDA e-DTR System \u2014 System Documentation", 18, RGBColor(0xC8, 0xE6, 0xC9))
p3 = tf2.add_paragraph()
_run(p3, "See SYSTEM_DOCUMENTATION.md for the full reference.", 14, RGBColor(0x9C, 0xC8, 0x9E))
_slide_no[0] += 1

prs.save(OUT)
print("Saved:", OUT)
print("Slides:", len(prs.slides._sldIdLst))
