# Contributing to ITFlow
 
Thanks for your interest in contributing! ITFlow is intentionally simple: plain procedural PHP, MySQL via `mysqli`, and vanilla Bootstrap/AdminLTE. There is no framework, no ORM, no template engine, and no build step. If you can read a PHP file top to bottom, you can read ITFlow.
 
That simplicity comes with a trade-off: **safety and correctness depend on following conventions at every call site.** This document is the list of those conventions. Read it once, fully, before opening a PR — most review feedback we give is a restatement of something on this page.
 
---
 
## Quick start (development)
 
1. Clone the repo into a webroot served by Apache/PHP 8.x with the `mysqli`.
2. Create a MySQL/MariaDB database and browse to `/setup/` — or import `db.sql` directly.
3. Rename/skip setup as prompted; `config.php` is generated at the root (and is gitignored).
There is no `composer install` or `npm install` step. All third-party libraries are vendored in `/libs/`. This is deliberate — ITFlow is distributed as "unzip and go" — so **never add a runtime Composer/npm dependency**. If a new library is truly needed, discuss it in an issue first; if accepted, it gets vendored into `/libs/`.
 
---
 
## Architecture map
 
| Path | Purpose |
|---|---|
| `agent/` | The main technician-facing app. Most feature work happens here. |
| `admin/` | Settings, configuration, roles, mail, migrations. Admin-only. |
| `client/` | The logged-in client portal (contacts of a client). |
| `guest/` | Unauthenticated flows via URL keys (view/pay invoice, view quote/ticket, view shared credentials/files/documents). |
| `api/v1/` | Key-authenticated JSON CRUD API, one directory per module. |
| `cron/` | Scheduled jobs. `cron.php` is the dispatcher and the only entry in the crontab; everything else in the directory is a job it runs, with `cron/includes/` for the parts only cron uses. See [Cron](#cron). |
| `functions.php` + `functions/` | Shared helper functions, split into topical files (`sanitize.php`, `auth.php`, `logging.php`, …) loaded by `functions.php`. New helpers go in the topical file that matches their concern. |
| `includes/` (root) | **Shared** across portals: session/auth bootstrap, DB, layout partials. |
| `post/` (root) | **Shared** POST handlers (logout, misc). |
| `modals/` (root) | **Shared** modals used by both agent and admin. |
| `js/`, `css/` (root) | Shared front-end assets (portals also have their own). |
| `libs/` | Vendored third-party libraries. Never edit these; update them wholesale. |
| `setup/` | First-run installer. |
| `scripts/` | Helper/utility scripts — `setup_cli.php`, `update_cli.php`, `restore_cli.php`. CLI only; the directory denies web access. |
 
Rule of thumb: **root-level `includes/`, `post/`, `modals/`, `js/`, `css/` are shared code; everything inside a portal directory is scoped to that portal.**
 
### `custom/` directories
 
`agent/`, `admin/`, `client/`, `guest/`, and `cron/` each contain a `custom/` directory. These are hook points for site-specific code that survives updates. `triggerCustomAction($trigger, $entity_id)` fires named triggers (e.g. `ticket_resolve`) into `custom/custom_action_handler.php` if one exists. Core code should **call** `triggerCustomAction()` at meaningful events but never depend on anything inside `custom/`.
 
---
 
## Request lifecycle (how a page works)
 
**Read pages** (`agent/tickets.php`, etc.) start by requiring an `inc_all*.php` from the portal's `includes/`. That chain loads, in order: `config.php` → `functions.php` (a loader that pulls in the topical helper files under `functions/` — security, sanitize, auth, logging, etc.) → `check_login.php` (auth) → header/nav/layout partials. It also establishes the implicit globals every page relies on: `$mysqli`, `$session_user_id`, `$session_name`, and — on client-scoped pages via `inc_all_client.php` — `$client_id` (already `intval()`'d).
 
If your code "can't find" a variable, check which include chain the page uses before adding a query. The variable probably already exists.
 
**Write actions** go through the portal's `post.php` dispatcher, which:
 
1. Requires config, functions, and the login check.
2. Defines the constant `FROM_POST_HANDLER`.
3. Loads the handler files in `post/` (excluding `*_model.php`).
Every handler file must start with:
 
```php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");
```
 
Handlers are a series of independent blocks, one per action:
 
```php
if (isset($_POST['edit_ticket_priority'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);
    // ... fetch, check client access, act, log, notify, redirect
}
```
 
**Copy the nearest existing block as your starting point** — but understand every line you copy. The next section explains why each one is there.
 
### The `_model.php` pattern
 
Files named `agent/post/*_model.php` hold shared field collection/sanitization logic used by both the create and edit blocks of a module (e.g. `asset_model.php` is included by both `add_asset` and `edit_asset`). If create and edit share more than a couple of fields, use this pattern rather than duplicating. Model files carry the same `FROM_POST_HANDLER` guard and are excluded from the dispatcher's auto-load.

**`_model.php` is a reserved suffix.** The exclusion is a filename match, so a *handler* named `*_model.php` is silently never loaded — its form posts, nothing claims the request, and the user gets a blank page with no error anywhere. This is what happened to `admin/post/ai_model.php`, which is why the AI Models handler is now `admin/post/ai_models.php`. Name entity handlers around the suffix (`ai_models.php`, `users.php`, `api_keys.php`).

A POST that reaches the end of `admin/post.php` or `agent/post.php` without a handler claiming it is logged to App Logs as a `Request` warning, which is the fastest way to spot this class of mistake.
 
---
 
## Cron

One crontab entry runs everything:

```
* * * * * php /path/to/itflow/cron/cron.php >/dev/null
```

`cron/cron.php` is a dispatcher. It wakes every minute, works out which scripts in `cron/` are due, and requires them into its own process. Adding a job is a new script in `cron/` plus an entry in `includes/cron_jobs.php`. The crontab never changes again.

That registry is the only thing that decides **which** scripts can run, and the schedule in it is only a default: it seeds the job's `cron_jobs` row the first time the dispatcher meets the job, and from then on the row is what runs, because Maintenance > Cron writes to it. The database therefore holds **when and whether**, never **what** — a row naming a script that is not in the registry is ignored, so nothing that reaches the database can point the dispatcher at an arbitrary file. Keep it that way.

Run Now in the admin UI does not execute anything in the web request: these scripts are CLI-only and some take minutes, so the button sets `cron_job_run_now` and the next dispatch picks it up, through the same lock and claim as a scheduled run.

Due-ness is recorded in the `cron_jobs` table rather than matched against the clock, so a job whose minute was missed — machine down, previous run still going — runs at the next opportunity instead of being skipped for the day. A job is claimed *before* it runs, not after: a run that dies half way through is not repeated, which matters because `nightly_tasks.php` generates invoices and charges cards. Each job is also locked individually for the length of its own run (`cron/includes/cron_lock.php`), so a long or hung job holds up only itself — the next minute's dispatch picks up everything else in a second process.

Because the jobs share one PHP process, job code has three rules:

1. **Never `exit()` or `die()`.** It ends the whole cycle and every job after it. Use `cronJobStop($message, $exit_code)` instead: it exits when the script was run directly and unwinds back to the dispatcher when it wasn't, so both paths behave as they always have.
2. **Never declare a function or class another job might declare.** Two jobs each declaring the same helper is a fatal `Cannot redeclare` the moment they share a process. Shared helpers belong in `functions/`.
3. **Be safe to run twice in one day.** The dispatcher's lock stops overlap, but nothing stops a repeat: an admin presses Run Now after the scheduled pass, or a schedule is misconfigured. Work selected by a date match (`... = CURDATE()`) fires again on every run of that day unless something records that it happened — nightly's late fees and overdue reminders guard on the history rows they write. A job whose work cannot be made repeat-safe declares `'interval_safe' => false` in `includes/cron_jobs.php`, which locks it to the daily schedule in Maintenance > Cron and in the dispatcher.
4. **Set what you read.** One global scope and one set of `require_once` includes are shared across the cycle — a job's own `require_once "../config.php"` is a no-op if an earlier job already loaded it, and any variable an earlier job left behind is still there. Do not rely on the state a fresh process would have given you.

A job can also ship switched off with `'enabled' => 0` in the registry. The row is seeded disabled and stays that way until somebody turns it on in Maintenance > Cron. Use it for work an install should opt into rather than inherit silently from an upgrade — `backup` ships this way, because a full backup can be gigabytes a night.

### The master switch

`config_enable_cron` is a second, coarser switch that sits above the per-job ones. It is **not** enforced by the dispatcher — every job checks it in its own header and stops itself with `cronJobStop()`. A new job has to make that check too; one that skips it keeps running on an install that believes cron is off, which is exactly the trap `ticket_email_parser` and `ticket_sla` sat in until 26.08.

Be precise about what it does, because it is easy to oversell. It is **not** a guard on restored data: a full backup dumps the `settings` table, so a restored copy comes back with `config_enable_cron = 1` alongside every enabled `cron_jobs` row, exactly as production had them. What it gives you is one reversible bit — the fastest way to stop an install acting on live data once you have noticed, and the only way to stop everything without editing seven rows.

That last part is the reason it is not redundant with `cron_job_enabled`. Turning the switch off and back on returns you to exactly the configuration you had. Sweeping all seven rows off and back on does not: `backup` ships `'enabled' => 0`, so the sweep quietly turns on nightly backups nobody asked for, along with anything else that was deliberately disabled.

It defaults to `0`. That is a weaker guard than it looks — an install with no crontab entry runs nothing whatever the switch says, so the entry is the real gate — but it does mean adding the crontab line to a half-configured install is not enough on its own to start emailing. Both setup paths name the step on the way out.

## Backups

`functions/backup.php` is the whole engine, and all three entry points go through it: Maintenance > Backup, `cron/backup.php`, and `scripts/restore_cli.php`. Nothing else should dump, zip, or import a database.

Archives are AES-256 encrypted zips. The key is one value per install, generated on first use and appended to `config.php` — **never** the database and **never** the file name. That is the point: a backup that leaks cannot be opened with anything the backup itself contains, and a URL or an access log never carries the key. The 32 random characters in the file name are an unguessable path component, nothing more. Note that `unzip`, Windows Explorer and the macOS Archive Utility cannot read AES zips; 7-Zip, WinZip, PeaZip and Keka can.

The web tier never builds an archive in the request. It writes a `Pending` row and `cron/backup.php` does the work, because a dump of a real install outlives `request_terminate_timeout` and `set_time_limit()` does not help. Same reasoning as Run Now.

Two rules for anything touching restore:

1. **Validate before you destroy.** The key is checked and the archive unpacked before a single table is dropped, and the live database is dumped to a rollback file first. If the import fails the rollback goes back in. `mysqli` throws rather than returning false under PHP 8.1's default report mode, so every statement in the import path is wrapped — an uncaught throw there leaves an install with no database at all.
2. **The archive does not get to decide what our guards say.** A restore wipes `uploads/`, and an archive is allowed to contain a `.htaccess`. `backupAssertUploadsGuards()` rewrites ours afterwards unconditionally, and the backup storage directory is preserved through the wipe so a restore cannot destroy every other archive on the box.

Retention lives in `nightly_tasks.php`, never in the backup job, so a failed backup cannot delete the archive it was supposed to replace. It never removes the newest complete backup, and an archive on disk with no row is **adopted** rather than deleted — after a restore the `backups` table is the old one, so everything made since looks unknown.

Setup's restore step closes itself once the `users` table has rows, whatever `config.php` says. It used to default `$config_enable_setup` to `1` when the flag was absent, which fails the wrong way: the flag is only appended at the end of a successful install, so an install abandoned in between left an unauthenticated endpoint that dropped every table, imported an arbitrary archive, and rewrote `uploads/` including the `.htaccess` that stops PHP running there.

Every script in `cron/` still runs standalone (`php cron/mail_queue.php`) and still takes its own lock when it does, so anything can be run by hand for testing.

---

## Security rules (non-negotiable)
 
ITFlow does not use prepared statements or an ORM; queries are built as strings. That works **only** if every value is neutralized before interpolation. The rules:
 
### 1. Every value interpolated into SQL is cast or sanitized. No exceptions.
 
- **Integers** (IDs, flags, counts): `intval($_POST['ticket_id'])`. Interpolate unquoted.
- **Strings**: `escapeSql($_POST['subject'])`. This normalizes encoding to UTF-8, then runs `strip_tags()`, `trim()`, and `mysqli_real_escape_string()`. Because it relies on SQL escaping, the value **must be placed inside quotes in the query** (`'$subject'`). An escaped string interpolated without quotes is still injectable.
- **Values read back from the database** get the same treatment before reuse in another query (you will see `escapeSql($row['ticket_prefix'])` throughout — this is why).
- **`logAudit()`, `appNotify()`, `logHistory()` and `logTicketHistory()` are queries too.** They interpolate their `$description` / `$details` / `$status` arguments straight into an `INSERT` — the SQL is just hidden inside the helper. A DB-read value passed into one of them (`logAudit("Asset", "Delete", "$asset_name ...", ...)`) must be `escapeSql`'d first, exactly as if you had written the `INSERT` by hand. This is easy to miss precisely because the call doesn't *look* like a query. Note that `escapeHtml()` is **not** a substitute here: it encodes `'` and `"` so it happens to block a quote-breakout, but it leaves backslashes untouched, so a value ending in `\` still escapes the closing quote. All four sinks now trim an odd trailing backslash as a backstop, but the value still owes `escapeSql` — the guard is defence-in-depth, not the fix.
If you write a query and even one variable in it skipped these, that is a SQL injection. This is the single most common review rejection.

**Fetch helpers return raw values — you escape them.** `getFieldById()` and `getTicketStatusName()` hand back exactly what is in the column. Escaping is the call site's job, the same as any other row you read:

```php
$client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));    // into a query
$client_name = escapeHtml(getFieldById('clients', $client_id, 'client_name'));   // into a page
$client_id   = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));  // an id
```

Both helpers used to escape internally, via an `$escape_method` argument. It went badly: most call sites wrapped them in `escapeSql()` anyway and got a double-escaped value, so a client named `O'Brien` came back as `O\'Brien` and the backslash reached export filenames, PDF headings, flash messages and — on the user restore path — the database itself. A value that is already safe cannot be made safer, only wrong.
 
### 2. Every state-changing action validates CSRF.
 
`validateCSRFToken()` is the first line of every action block. It takes no argument — it reads `csrf_token` from `$_POST`, then `$_GET`, itself, so the same call covers form posts and link-style actions. (The signature still accepts an explicit token for callers that need one, but no call site in the tree passes one; use the bare form.) Forms and action links must include the token; copy how existing modals do it.
 
### 3. Every action enforces permissions.
 
`enforceUserPermission('module_x', level)` where level is `1` = read, `2` = write, `3` = full/delete. Current modules: `module_client`, `module_support`, `module_sales`, `module_financial`, `module_credential`, `module_reporting`. Read pages enforce level 1; create/edit enforce 2; destructive actions enforce 3. CSV/PDF exports are reads — gate them with the bare one-argument form, e.g. `enforceUserPermission('module_sales')`.

Two portals are gated differently, which is why their handlers look like they are missing the call:

- **Admin.** `admin/post.php` only loads anything in `admin/post/` when `$session_is_admin` is set, so admin handlers inherit the gate from the dispatcher and do not call `enforceUserPermission()` themselves.
- **Client portal.** `client/post.php` is a single file of action blocks rather than a dispatcher, and gates on the contact's own capabilities with `enforceContactCan('accounting'|'contacts'|'itdoc')`.

Everywhere else — anything under `agent/post/` — the call belongs in the block.
 
### 4. Client scoping is enforced, not assumed.
 
A user can be restricted to a subset of clients through `user_client_permissions`. Enforcing that has two halves, and a page usually needs both.

**One record — `enforceClientAccess()`.** After loading a record, call it (optionally with the record's client ID) so technicians restricted to specific clients cannot touch other clients' data by editing an ID in the URL. Look at how `resolve_ticket` does it.

**A list — `clientScopeSql()`.** Any query returning more than one row appends the fragment for that resource's own client column:

```php
$sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_description
    FROM expenses
    WHERE expense_archived_at IS NULL
    " . clientScopeSql('expense_client_id') . "
    ORDER BY expense_date DESC");
```

It returns `" AND ..."` or `""`, so it needs a `WHERE` to hang off — add `WHERE 1=1` if the query has no other condition. It is column-aware and takes an alias fine (`clientScopeSql('t.ticket_client_id')`). The API calls the same helper through the `apiClientScopeSql()` wrapper.

Scope on the resource's **own** column, not on a joined `clients.client_id`. Joining `clients` just to scope makes the filter depend on the join: with a `LEFT JOIN`, a row whose client column is `0` produces `NULL`, and `NULL IN (...)` is neither true nor false, so the row silently vanishes. If the query joins `clients` for `client_name`, keep the join for that — but still scope on the owning column.

**Records with no client (`0`) stay visible to restricted users.** `clientScopeSql()` emits `IN (0,...)` deliberately. Client restrictions partition *client* data, and a record belonging to no client is not any client's data to withhold. Do not hand-roll a variant that drops the `0` — the tree had accumulated several before this helper existed, disagreeing with each other, and reconciling them is what surfaced the inconsistency.
 
### 5. Escape on output.
 
Anything rendered into HTML goes through `escapeHtml()`. `escapeSql()` on the way in is **not** output escaping — data can enter the DB through other paths (API, email parser, older versions).

In practice the escaping happens **where the row is read, not where it is echoed**. A page or modal fetches its row and assigns each field through `escapeHtml()` once, then echoes the resulting variable raw:

```php
$row = mysqli_fetch_assoc($sql);
$asset_id   = intval($row['asset_id']);          // ints: intval, not escapeHtml
$asset_name = escapeHtml($row['asset_name']);
...
<strong><?php echo $asset_name; ?></strong>
```

Follow that pattern. Escaping at the echo instead would double-escape a value that is already safe, and mixing the two is how fields get missed. If you introduce a view variable that does not come from a row, escape it at assignment so the rule still holds at the top of the file.

A value that arrives through a fetch helper rather than a row read follows the same rule — `escapeHtml(getFieldById(...))` at the assignment, not at the echo. See rule 1.

Rich-text fields (TinyMCE content) are the exception and have their own handling; follow the existing pattern for the specific field rather than inventing one.
 
### 6. No shell-outs. No `eval`.
 
The project has deliberately moved off `shell_exec`/`exec` in favor of native PHP — `dns_get_record()` instead of `dig`, RDAP instead of `whois`, and so on. **Do not add new shell execution or `eval`.** PRs introducing either will be declined.

A handful of legacy call sites survive, all of them wrapping `git` or `which` in the self-update and diagnostics paths: `admin/debug.php`, `admin/update.php`, `admin/post/update.php`, `admin/post/backup.php`, `cron/cron.php`, `functions/app.php`, `scripts/update_cli.php`, `setup/index.php`. They are on the list to be replaced with direct `.git` file reads; treat them as debt, not as precedent.
 
### 7. Report vulnerabilities privately.
 
Per [SECURITY.md](SECURITY.md) — never in a public issue.
 
---
 
## Conventions

**Only technician-entered time is time worked.** `ticket_replies.ticket_reply_time_worked` is billable labour and feeds ticket totals, the technician and client time reports, project totals, invoicing and the API. A reply the *system* writes — assignment, priority change, merge, close, invoice/quote created, schedule edited, task completed or reopened — is an audit trail, not work, and records `'00:00:00'`. Only a value the technician actually typed goes in that column. Task completion estimates are planning information and stay on the task; they are never converted into time worked. `agent/ticket.php` hides the clock badge on a reply whose time is exactly `00:00:00`, so a zero renders as no time rather than as "0m".

**Database naming.** Every column is prefixed with the singular name of the entity it belongs to: `tickets.ticket_id`, `tickets.ticket_subject`, `clients.client_name`. This makes JOIN results unambiguous, so a `SELECT *` across joins is never *wrong*. New tables must follow it.

**Select the columns you use, not `*`.** Unambiguous is not the same as cheap. `SELECT *` across three joined tables fetches every column of all three, including the `*_notes` and `*_details` TEXT columns, and throws away whatever the page never renders. A search result list that shows five fields was pulling sixty. List the columns instead:

```php
$sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, client_name
    FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    WHERE ticket_archived_at IS NULL
    " . clientScopeSql('ticket_client_id') . "");
```

Two things follow from that:

- A query whose result only feeds `mysqli_num_rows()` needs no columns at all — write `SELECT 1`. Do not select a primary key "just in case": if the query joins two tables that both carry that column name, an unqualified `SELECT ticket_template_id` is an ambiguous-column error.
- Keep the join even when no column of the joined table survives into the `SELECT`, if the join is doing work — supplying a `WHERE` term, an `ORDER BY`, or the client column you scope on. Dropping a join is a separate decision from trimming the column list.

The trade is real and worth stating: `SELECT *` picks up new columns for free, an explicit list does not. Add a column to a table and every query that needs it must be updated by hand, and the failure mode is a blank field or a PHP 8 undefined-key warning rather than an error. That is the price of not fetching data nobody reads, and the project has decided to pay it on anything that loops or touches a TEXT column.

The exception is `api/v1/*/read.php`. Those endpoints hand the whole row to `read_output.php`, which serialises it straight into the JSON response — there the row *is* the output contract, so `SELECT *` is correct and trimming it would silently drop fields from every consumer.

The prefix is the entity name, which is usually but not always the singular of the table name. Where a table is named for its container rather than its row, the prefix follows the row: `calendar_events` → `event_*`, `asset_interfaces` → `interface_*`, `invoice_items` / `quote_items` → `item_*`, `rack_units` → `unit_*`, `user_roles` → `role_*`, `product_stock` → `stock_*`. Pick the prefix your columns will read best as and use it for every column in the table.

Two standing exceptions: junction tables (`client_tags`, `service_assets`, …) carry the two parent FK names unprefixed, and `settings` / `user_settings` use `config_*` / `user_config_*`.
 
**Schema changes require two edits in one PR:**

1. `db.sql` — so fresh installs get the new schema.
2. `admin/database_updates/<x.y.z>.php` — a new file named for the version it upgrades **to**, containing only the queries that apply the change. Migrations are sequential and rolling-release; never edit a historical file.

That is the whole job. `LATEST_DATABASE_VERSION` is derived from the highest-numbered filename in `admin/database_updates/`, and the runner (`admin/database_updates.php`) steps `config_current_database_version` after each file succeeds — so there is no constant to bump and no version-bump query to write. Each migration file needs the standard `defined('FROM_DB_UPDATER') || die(...)` guard at the top; copy an existing file's header.

A single update run applies every pending migration in order, stopping at the first failure with the version left at the last file that completed, so a re-run resumes at the one that broke.
**After acting, log and notify.** State changes call `logAudit($type, $action, $description, $client_id, $entity_id)` for the audit trail. User-facing events may also call `appNotify()`. Fire `triggerCustomAction()` where a site might reasonably want a hook. Then call `flashAlert($message, $type)` and `redirect()` (defaults to the referer) rather than setting session keys or `header()` manually.

**Function names (post-rename).** Helpers were renamed for clarity in 2026; the old names **no longer exist** — code calling them fatals. If you're rebasing an old PR or following an old tutorial, translate: `sanitizeInput` → `escapeSql`, `nullable_htmlentities` → `escapeHtml`, `logAction` → `logAudit`, `flash_alert` → `flashAlert`, `customAction` → `triggerCustomAction`, `encryptLoginEntry`/`decryptLoginEntry` → `encryptCredentialEntry`/`decryptCredentialEntry`, `strtoAZaz09` → `toAlphanumeric`, `fetchUpdates` → `checkForUpdates`, `sanitize_url` → `escapeUrl`.

One removed **variable** deserves its own warning: the old `$access_permission_query` global is gone, replaced by `clientScopeSql()` (security rule 4). Unlike a removed function, it does not fatal — an undefined variable interpolates as an empty string, so a rebased query keeps running with **no client scoping at all**. Grep for it before rebasing anything that touches a list query.
 
**Helpers that fetch data return it raw.** If you add a `getXById()`-style helper, return the column value untouched and let callers escape it (security rule 1). Validating what the helper interpolates into its *own* query — table and column names, the id — is still the helper's job; that is query construction, not output escaping, and the two are not the same thing.

**Bulk vs. single actions.** If you change the behavior of a single action (e.g. resolving a ticket), check whether a `bulk_*` counterpart exists and update it too. They are currently parallel implementations and drift between them is a known bug source.
 
**UI.** Bootstrap 4 / AdminLTE, modals per-module under `<portal>/modals/<module>/`, DataTables for lists, monospace styling for technical data (IPs, serials, keys) and proportional for human text. Match the page you're standing in.

**Modals post to the portal you are standing in, not the one they live in.** Modal forms use `action="post.php"`, which the browser resolves against the *page* URL, not the modal's own path. A modal under `admin/modals/` that an agent page opens by relative path therefore submits to `agent/post.php` and is handled by `agent/post/`, not `admin/post/`. If you reuse a modal across portals, every portal that can open it needs a handler that accepts the same field set — otherwise fields are silently dropped on one side.
 
**Style.** Procedural PHP, 4-space indentation, LF line endings, code and comments in English. Match the surrounding code rather than importing a personal style. Don't reformat code you aren't changing — it buries the real diff.

Line endings and indentation are enforced by `.gitattributes` and `.editorconfig` at the repo root, so an editor that respects EditorConfig needs no configuration. `.gitattributes` marks `libs/` as `-text`: vendored code is preserved byte-for-byte as shipped upstream and must never be normalized, or the next wholesale library update turns into an unreviewable diff.
 
---
 
## Pull requests
 
- **Small, focused diffs.** One feature or one fix per PR. Never mix relocation/reformatting with logic changes — split them into separate commits or PRs so each is reviewable on its own.
- Describe **what** and **why**, and note any schema changes prominently.
- CI runs PHP lint and db.sql lint; SonarCloud scans for security issues. Green checks are required but not sufficient — the conventions above are checked by human review.
- Test your change against a real install: fresh setup from `db.sql` **and** an upgrade via `database_updates.php` if you touched schema.
- For anything larger than a bug fix, **open an issue first** and discuss the approach. ITFlow's roadmap favors incremental modernization of the existing PHP codebase; large rewrites, framework introductions, and new runtime dependencies are out of scope.

## Getting help

Ask in the community forum linked from the README. When in doubt about a convention, find the closest existing example in the codebase and follow it — consistency beats novelty here.