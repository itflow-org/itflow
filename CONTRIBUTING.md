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
| `cron/` | Scheduled jobs: `cron.php`, mail queue, ticket email parser, domain/cert refreshers. |
| `functions.php` + `functions/` | Shared helper functions, split into topical files (`sanitize.php`, `auth.php`, `logging.php`, …) loaded by `functions.php`. New helpers go in the topical file that matches their concern. |
| `includes/` (root) | **Shared** across portals: session/auth bootstrap, DB, layout partials. |
| `post/` (root) | **Shared** POST handlers (logout, misc). |
| `modals/` (root) | **Shared** modals used by both agent and admin. |
| `js/`, `css/` (root) | Shared front-end assets (portals also have their own). |
| `libs/` | Vendored third-party libraries. Never edit these; update them wholesale. |
| `setup/` | First-run installer. |
| `scripts/` | Helper/utility scripts. |
 
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
 
---
 
## Security rules (non-negotiable)
 
ITFlow does not use prepared statements or an ORM; queries are built as strings. That works **only** if every value is neutralized before interpolation. The rules:
 
### 1. Every value interpolated into SQL is cast or sanitized. No exceptions.
 
- **Integers** (IDs, flags, counts): `intval($_POST['ticket_id'])`. Interpolate unquoted.
- **Strings**: `escapeSql($_POST['subject'])`. This normalizes encoding to UTF-8, then runs `strip_tags()`, `trim()`, and `mysqli_real_escape_string()`. Because it relies on SQL escaping, the value **must be placed inside quotes in the query** (`'$subject'`). An escaped string interpolated without quotes is still injectable.
- **Values read back from the database** get the same treatment before reuse in another query (you will see `escapeSql($row['ticket_prefix'])` throughout — this is why).
If you write a query and even one variable in it skipped these, that is a SQL injection. This is the single most common review rejection.
 
### 2. Every state-changing action validates CSRF.
 
`validateCSRFToken($_POST['csrf_token'])` (or `$_GET['csrf_token']` for link-style actions) is the first line of every action block. Forms and action links must include the token; copy how existing modals do it.
 
### 3. Every action enforces permissions.
 
`enforceUserPermission('module_x', level)` where level is `1` = read, `2` = write, `3` = full/delete. Current modules: `module_client`, `module_support`, `module_sales`, `module_financial`, `module_credential`, `module_reporting`. Read pages enforce level 1; create/edit enforce 2; destructive actions enforce 3. Admin pages have their own check via the admin include chain.
 
### 4. Client scoping is enforced, not assumed.
 
After loading a record, call `enforceClientAccess()` (optionally with the record's client ID) so technicians restricted to specific clients cannot touch other clients' data by editing an ID in the URL. Look at how `resolve_ticket` does it — including the "skip if the record has no client" case.
 
### 5. Escape on output.
 
Anything echoed into HTML goes through `escapeHtml()`. `escapeSql()` on the way in is **not** output escaping — data can enter the DB through other paths (API, email parser, older versions). Rich-text fields (TinyMCE content) are the exception and have their own handling; follow the existing pattern for the specific field rather than inventing one.
 
### 6. No shell-outs. No `eval`.
 
The project has deliberately eliminated `shell_exec`/`exec` in favor of native PHP (`dns_get_record()` instead of `dig`, RDAP instead of `whois`, etc.). PRs reintroducing shell execution will be declined.
 
### 7. Report vulnerabilities privately.
 
Per [SECURITY.md](SECURITY.md) — never in a public issue.
 
---
 
## Conventions
 
**Database naming.** Every column is prefixed with its table's singular name: `tickets.ticket_id`, `tickets.ticket_subject`, `clients.client_name`. This makes JOIN results unambiguous and is why queries can `SELECT *` across joins safely. New tables must follow it.
 
**Schema changes require two edits in one PR:**

1. `db.sql` — so fresh installs get the new schema.
2. `admin/database_updates/<x.y.z>.php` — a new file named for the version it upgrades **to**, containing only the queries that apply the change. Migrations are sequential and rolling-release; never edit a historical file.

That is the whole job. `LATEST_DATABASE_VERSION` is derived from the highest-numbered filename in `admin/database_updates/`, and the runner (`admin/database_updates.php`) steps `config_current_database_version` after each file succeeds — so there is no constant to bump and no version-bump query to write. Each migration file needs the standard `defined('FROM_DB_UPDATER') || die(...)` guard at the top; copy an existing file's header.

A single update run applies every pending migration in order, stopping at the first failure with the version left at the last file that completed, so a re-run resumes at the one that broke.
**After acting, log and notify.** State changes call `logAudit($type, $action, $description, $client_id, $entity_id)` for the audit trail. User-facing events may also call `appNotify()`. Fire `triggerCustomAction()` where a site might reasonably want a hook. Then call `flashAlert($message, $type)` and `redirect()` (defaults to the referer) rather than setting session keys or `header()` manually.

**Function names (post-rename).** Helpers were renamed for clarity in 2026; the old names **no longer exist** — code calling them fatals. If you're rebasing an old PR or following an old tutorial, translate: `sanitizeInput` → `escapeSql`, `nullable_htmlentities` → `escapeHtml`, `logAction` → `logAudit`, `flash_alert` → `flashAlert`, `customAction` → `triggerCustomAction`, `encryptLoginEntry`/`decryptLoginEntry` → `encryptCredentialEntry`/`decryptCredentialEntry`, `strtoAZaz09` → `toAlphanumeric`, `fetchUpdates` → `checkForUpdates`.
 
**Bulk vs. single actions.** If you change the behavior of a single action (e.g. resolving a ticket), check whether a `bulk_*` counterpart exists and update it too. They are currently parallel implementations and drift between them is a known bug source.
 
**UI.** Bootstrap 4 / AdminLTE, modals per-module under `<portal>/modals/<module>/`, DataTables for lists, monospace styling for technical data (IPs, serials, keys) and proportional for human text. Match the page you're standing in.
 
**Style.** Procedural PHP, 4-space indentation, LF line endings, code and comments in English. Match the surrounding code rather than importing a personal style. Don't reformat code you aren't changing — it buries the real diff.
 
---
 
## Pull requests
 
- **Small, focused diffs.** One feature or one fix per PR. Never mix relocation/reformatting with logic changes — split them into separate commits or PRs so each is reviewable on its own.
- Describe **what** and **why**, and note any schema changes prominently.
- CI runs PHP lint and db.sql lint; SonarCloud scans for security issues. Green checks are required but not sufficient — the conventions above are checked by human review.
- Test your change against a real install: fresh setup from `db.sql` **and** an upgrade via `database_updates.php` if you touched schema.
- For anything larger than a bug fix, **open an issue first** and discuss the approach. ITFlow's roadmap favors incremental modernization of the existing PHP codebase; large rewrites, framework introductions, and new runtime dependencies are out of scope.
## Getting help
 
Open a GitHub issue using the templates, or ask in the community forum linked from the README. When in doubt about a convention, find the closest existing example in the codebase and follow it — consistency beats novelty here.