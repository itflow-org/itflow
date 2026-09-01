# Changelog

This file documents all notable changes made to ITFlow.

## [26.09]

### Upgrading to 26.09

> **The database update has to be run from the command line one more time.** The web interface no longer applies database updates, and the new queued-update path needs a schema change that this release itself adds — so on this one upgrade there is nothing in the browser that can finish the job. After this, it goes back to being a single button.

1. **Back everything up.** Maintenance > Backup, or a full VM snapshot.
2. **Update the files from Maintenance > Update as normal.** The old page pulls the new files and reports success, then drops you into 26.09 running against the 26.08 database. Errors at that point are expected and stop as soon as step 3 completes.
3. **Run the database update from the command line.** Run it as the user that owns the ITFlow files — the script tells you which user if you get it wrong:
```bash
sudo -u www-data php /var/www/itflow.example.com/scripts/update_cli.php
```
It applies every pending version from 2.6.8 to 2.7.8 in order and reports each one as it goes. If a step fails it stops there without advancing the recorded version, so you can fix the cause and run it again.
4. **Check it took.** Maintenance > Update should show the database up to date and the Queue Update button available.

From here on the command line is optional. Maintenance > Update hands the work to cron, which updates the files and the database in one pass.

### Breaking Changes and Notes

- Updates are no longer applied by your browser. Maintenance > Update either hands the job to cron, which runs it in the background, or you run it from the shell. As a side effect ITFlow now updates cleanly on hardened hosts that disable PHP's shell functions, which it could not do before.
- Database updates have been removed from the web interface entirely. Cron applies them as part of a queued update, or you run `php scripts/update_cli.php` yourself.
- Running `php scripts/update_cli.php` with no arguments updates the files and then the database in one go, and the file update is forced — any local edits you have made to shipped files are discarded. Use `--update_db` if you only want the database half; it never touches your files.
- If you have added your own CSS or JavaScript under `agent/custom/`, expect it to need updating. The interface has moved to a new major version of the framework it is built on and most class names have changed. Details are in Developer Updates below.
- Deleting a payment now requires Full access to both Sales and Financial, up from Modify. Refunding requires the same. Deleting a payment removes money from the books, so it is treated as a delete rather than an edit — agents who could remove payments before may no longer be able to.
- The five built-in ticket statuses now have fixed SLA clock behaviour and can no longer be configured. New and Open always run; On Hold, Resolved and Closed always pause the resolution clock. Only custom statuses keep the SLA Clock dropdown, and the built-ins are marked "(fixed)" in the list.
- On Hold pauses the resolution clock, not the response clock, and only once the ticket has had its first reply. A ticket parked on hold before anyone replies still breaches its response target — reply first, then hold.
- Breaches already recorded against on-hold tickets under the old behaviour are left as they are. Change a ticket's priority or SLA to re-stamp it if you want it recalculated.
- Holidays and closure days are new and start empty. Until you add them, SLA clocks keep running through your closures exactly as they did before.
- Tickets default to Medium priority when none is given, and ticket replies default to Public.
- Closed tickets can no longer be deleted. A closed ticket is treated as a permanent record.
- Send Email on invoices and quotes now opens a contact picker rather than sending straight away. Quick Send, described below, keeps the old one-click behaviour, but any bookmarked direct links to the send action no longer work.

### New Features & Updates

- The interface has had a full visual overhaul, with dark mode carried properly through the calendar, the editor, confirmation dialogs, tables and form controls.
- Your theme colour and any custom CSS now apply everywhere, including the client portal, the guest pages, login and setup. Those pages previously ignored both and rendered in the default styling.
- Ticket: canned responses. Add them under Admin > Templates > Canned Responses, scoped to a ticket category or to all categories, and pick one from the reply form. It inserts at the cursor, so picking one into a half-written reply adds to it rather than replacing it.
- Ticket: SLA response targets now appear on ticket-created emails, rolled up to business days, with a note to call in on High and Urgent.
- Ticket: the reply card stays out of the way until you click Reply.
- SLA: holidays and closure days. Define the days you are closed and the SLA clock pauses through them, with a one-click importer for US federal holidays.
- Networks: a full IP address section under each subnet — address, hostname and description, with search, sort, bulk delete, CSV import and export. Addresses are checked against the subnet they are being added to and duplicates within a subnet are refused, so the same guard applies whether you type one in or import a thousand. Both IPv4 and IPv6, sorted numerically so .9 comes before .10.
- Designate one client record as your own organization, under Company Details. Clicking your company name at the top of the side navigation now takes you straight to it.
- Invoices and Quotes: Quick Send is a one-click send to the default contacts, the way Send Email used to work — primary and billing for invoices, primary for quotes. It sits at the top of the actions menu with a lightning bolt and asks for confirmation, and never opens the picker.
- Invoices and Quotes: Send Email, below it, opens a modal listing every contact on the client with their email addresses and the same defaults pre-checked, for when the invoice needs to go somewhere other than the usual place. Who it went to is recorded in the document history either way.
- Invoices and Quotes: Mark Sent asks how it was sent — snail mail, an email client, in person and so on — and records the reason in the history.
- Stripe: refund a card payment from the invoice itself. The refund goes through Stripe, is written to the invoice history, and the payment is removed, replacing the old routine of deleting the payment in ITFlow and refunding it by hand in the Stripe dashboard.
- Stripe: adding or editing the Stripe provider now checks the secret key against Stripe and tells you if it is wrong, instead of the key looking fine until the first client tries to pay.
- Stripe: a client paying from a guest invoice link no longer needs a Stripe customer record to exist first — Pay Now sets one up as part of the payment.
- Client account statements. Send one from the invoice list with a date range and an option to leave out paid invoices, view it on the guest invoice page, and download it as a PDF from the client portal.
- Files: documents and files are now told apart, with a filter for all documents or just files. Thumbnail view shows every file, previews work for documents, PDFs and text, and your choice of list or thumbnail view survives navigating between folders.
- Client Portal: the profile page has been rebuilt — department, location, title and phone with inline editing, PIN changes, recent sign-ins and recent activity, with a separate full activity page.
- Client Portal: empty tables now say what is missing instead of showing a bare header row, and the saved payment method wording is clearer about what saving a card actually does.
- Guest: the ticket task approval page has been rebuilt. The request being approved leads the page, the ticket body sits underneath as context, the approve action is a proper button rather than a link in a paragraph, and approving or declining lands on a real confirmation screen. Internal routing jargon that meant nothing to a client has been dropped.
- Guest and client portal pages now have proper footers, and the agent footer stays at the bottom of short pages instead of floating mid-screen.
- Phone numbers show their country code, and the country is kept when you edit the number. The international phone input is now used on every phone field.
- Maintenance > Update: Queue Update hands the update to cron, which runs it in its own process and updates the files and the database in one pass. Check now looks for new commits without needing shell access, listing the pending commits with their dates and descriptions.
- Mail Parser: you are now notified when the parser skips an autogenerated email rather than it disappearing silently.
- Demo data. Twenty fictional clients with two years of history — a mix of managed and break-fix, tickets, invoices, expenses, assets and contracts, including FOSS products — loaded from Maintenance > Starter Content and tagged so it can be cleared out again.
- API: reads accept an optional `client_id` filter on top of the key's own client scoping, so a key that can see everything can ask for one client's records. It can only narrow — a key with no access to that client still gets nothing.
- API: new endpoints to close a ticket and to delete a client. Closing resolves the ticket first if it has not been resolved already, so the SLA figures come out right. Thanks to @BoredManCodes for the client delete endpoint.
- Client pages load noticeably faster, running roughly a third fewer queries than before, and several pages that were slow on large installs have been sped up.
- Lists now say whether nothing matched your filters or there are no records at all, instead of showing an empty table either way.
- Assets and Contacts: an "Add primary" link where a client has no primary location or contact set.
- The client header at the top of client pages collapses, and stays collapsed as you move between pages.
- Page changes fade in rather than flashing, the calendar reserves its height so the page no longer jumps as it loads, and table listings line up consistently across the app.

### Security

- A restricted agent could open an asset, contact or location belonging to a client they have no access to by entering its id in the address bar. Those pages are now gated on the module permission that owns them and checked against the agent's client access rules, like every other record page.
- Client Portal: changing a password or a PIN now requires the current password. Contacts signing in through SSO are exempt — there is no local password to check and the identity provider has already done it.
- A user name containing HTML could inject markup into the page through the ticket task approver list. It no longer can.
- Deleting and refunding payments now require Full access to both Sales and Financial, as described under Breaking Changes.

### Bug Fixes

- Tickets could not be opened for clients marked as a lead.
- Client Portal: raising a ticket sent no new-ticket notification — the notification errored out instead.
- The readable password generator called a function that no longer existed, so generating one did nothing.
- Client pages flashed blank on every load.
- Invoice emails went to archived contacts, and every copy carried the primary contact's name in the greeting rather than the name of the contact receiving it.
- Guest: the confirmation dialog on ticket task approvals and on quote accept and decline rendered as plain text at the foot of the page instead of as a dialog, because the guest pages never loaded its stylesheet.
- Filtering the audit log by date was slow on large installs.
- API: deleting an asset reported how many interfaces it had removed rather than confirming the asset itself was deleted.
- API: updating a client failed outright if the request did not include the lead field.
- API: a request that authenticated correctly but then failed on the query wrote nothing to the app log, leaving nothing to debug from.

### Developer Updates

Front-end framework migration:
- AdminLTE 3.2.0 to 4.9.1 and Bootstrap 4.6.2 to 5.3.8. The layout skeleton is renamed throughout — `content-wrapper`, `main-sidebar` and `main-header` become `app-main`, `app-content`, `app-sidebar` and `app-header`.
- AdminLTE 4 dropped a number of v3 classes ITFlow relies on. `text-bold`, `text-sm`, `btn-default`, `img-circle`, the `.alert .icon` pairing, the sidebar badge positioning, the `small-box` watermark icon and all sixteen theme colours are reproduced in `css/itflow_custom.css` at v3's computed values, driven by a single `--itflow-accent` variable per theme.
- Bootstrap 5 split `.bg-*` from `.text-bg-*`, so every `bg-dark` card and modal header needed its text colour restored explicitly.
- `input-group-append` and `input-group-prepend` wrappers are deleted rather than renamed, selects moved from `form-control` to `form-select`, `data-toggle="buttons"` groups became `.btn-check`, and `custom-control`, `custom-select` and `custom-file` are gone.
- `.input-group > .form-control` at specificity (0,2,0) outranks `.form-control-color`'s `width:3rem` at (0,1,0), which collapsed every colour swatch. Fixed with a matching-specificity `.input-group > .form-control-color` rule rather than a `w-auto` override, which only hands the width to the UA default.
- Stylesheets and scripts were repeatedly found loaded by `includes/header.php` only. `css/itflow_custom.css` and `libs/sweetalert2/css/sweetalert2.min.css` are now loaded by the client portal, guest, login, setup and MFA headers as well — the SweetAlert2 gap is why guest dialogs rendered in normal flow, since all of its positioning lives in the stylesheet while `includes/footer.php` was loading the JS fine.

jQuery removal:
- jQuery, jQuery UI, select2, Inputmask, daterangepicker, Moment, Tempus Dominus, toastr, pdfmake, Dropzone and Popper are all gone. Replacements are Tom Select, Flatpickr, IMask, SweetAlert2 and Bootstrap's own toasts, plus `js/autocomplete.js` for the product and OS autocompletes.
- New helpers in `js/app.js`: `itflowPostForm()` reproduces jQuery's bracketed array encoding that `ajax.php` parses, `itflowBindOnce()` replaces the namespaced `.off().on()` pattern, and `initTomSelect` / `refreshTomSelect` / `clearTomSelect` / `setTomSelectValue` wrap Tom Select.
- `includes/modal_footer.php` re-executes `js/app.js` on every ajax modal open, so every initialiser needs a re-entry guard or it double-initialises.
- New `itflowReady()` restores jQuery's `.ready()` semantics. Scripts injected into an ajax modal run after `DOMContentLoaded` has already fired, so a bare listener never runs — this is what broke notification pagination, asset OS autocomplete, the contact authentication toggle, AI ticket summaries and AI document template generation mid-cycle.
- `js/ajax_modal.js` re-injects `<script>` tags explicitly, because `innerHTML` does not execute them and `.append()` did.

Schema and queries:
- `history_description` widens from `varchar(200)` to `text` (2.7.7). A send to several recipients overflows 200 characters and strict mode errors rather than truncating.
- Indexes added on the client-scoped columns (2.7.5), the per-parent child fetches and the mail queue loop (2.7.6), and `logs(log_user_id, log_client_id)` for the portal profile and activity pages (2.7.8). No new migrations after 2.7.8 — the Stripe work reuses `client_payment_provider`.
- `agent/includes/inc_all_client.php` rewritten so the sidebar badge counts are one query per table rather than one per number: 43 queries to 27 per client page load, with invoices scanned once instead of eight times.
- `admin/audit_logs.php`'s date filter was `DATE(log_created_at) BETWEEN`, which is not sargable and made `KEY log_created_at` unusable. It is now a half-open range.

API:
- `apiClientScopeSql()` now takes an optional caller-supplied `client_id` and appends it after the scope fragment, so the filter can only narrow what the key is already allowed to see. Reads only — writes take `client_id` as the target they act on and validate it separately.
- `api/v1/tickets/close.php` sets status 4 with `ticket_resolved_at` first where the ticket has not been resolved, calls `syncTicketSlaClock()` and `setTicketResolutionSlaMet()`, then sets status 5 and stamps `ticket_closed_by`. Every query is scoped by `ticket_client_id` and `ticket_closed_at IS NULL`, so a second call is a no-op rather than a re-close.
- The four `*_output.php` helpers now `logApp()` on query failure, which previously returned an error to the caller and left no trace server-side.

Other:
- The client portal PIN handler checked length before `escapeSql()`, whose `strip_tags()` then emptied the value, and stored the blank while flashing success. Length is now checked after sanitising.
- The client delete modal's script was blocking the parser mid-body, which is why every client page flashed blank; it is deferred now.
- The gating fix under Security covers `agent/asset.php`, `agent/contact.php`, `agent/contacts.php` and `agent/locations.php`.
- The ticket task approver picker built `<option>` markup by template literal into `innerHTML`; it uses `new Option()` now, which assigns text.
- Stripe refunds live in `agent/post/payment.php` behind `refund_payment_stripe`, pulling the payment intent out of the stored reference and calling `refunds->create()` for the full amount. Partial refunds are not supported.
- Payment provider post variables moved into a model to stop the add and edit paths drifting apart.
- New `functions/network.php` (subnet containment and IP normalisation, v4 and v6) and `functions/files.php`; `functions/sla.php` extended for holidays and closure days.
- Phone input handling moved out of `js/app.js` into `js/phone_inputs.js` so the client portal can load it without pulling in everything else.
- Update path: `admin/post/update.php` no longer shells out at all. `cron/update_check.php` does the fetch and stores the result, `cron/app_update.php` runs `scripts/update_cli.php` as a child process, and the database phase re-execs against the newly updated code so a migration calling a brand new helper does not hit an undefined function.
- Removed 29 dead or duplicate files, including 348KB of unused FullCalendar themes, and fixed two broken script paths.
- The debug page now recommends 512M for PHP's memory limit.
- README, CONTRIBUTING and SECURITY.md updated.

### Library Updates

- Bump AdminLTE from 3.2.0 to 4.9.1.
- Bump Bootstrap from 4.6.2 to 5.3.8.
- Bump intl-tel-input from 25.3.0 to 29.2.3.
- Bump ImapEngine from 1.25.4 to 1.25.6, along with its dependencies.
- Added Tom Select, Flatpickr, IMask and SweetAlert2, none of which need jQuery.
- DataTables now uses its Bootstrap 5 styling build.
- Removed jQuery, jQuery UI, select2, select2-bootstrap4-theme, Inputmask, daterangepicker, Moment, Tempus Dominus, toastr, pdfmake, Dropzone, Popper and Show-Hide-Passwords-Bootstrap-4.

## [26.08.2] Maint Release

### Upgrading to 26.08.2

Update the files from Settings > Update as normal. There is no database change in this release, so nothing else is required.

### Bug Fixes
- Calendar: fixed the agent calendar showing no events.
- Calendar: shared calendar feeds set to publish busy blocks only were publishing full event titles, locations and descriptions to anyone holding the subscription link.
- Cron: fixed Maintenance > Cron failing to load. Scheduled jobs themselves were unaffected and kept running.
- Exports: restored the missing columns on the ticket, quote, recurring invoice, software and user exports.
- API: restored the full record on the credentials list endpoint.
- Mail: switching an existing install from Standard SMTP/IMAP to Microsoft 365 or Google OAuth no longer leaves the old mail server behind, which stopped sending and ticket email fetching from working. The connection settings for OAuth providers are now fixed by the provider and cleared on save.

## [26.08.1] Maint Release

### Upgrading to 26.08.1

Update the files from Settings > Update as normal. This release moves the database to 2.6.7 and the web updater completes it for you — the command line step that 26.08 required is not needed again.

### Breaking Changes and Notes

- Client access: agents with restricted client access now see records that have no client assigned. Previously this varied by page — unassigned tickets and projects were visible, unassigned expenses and credentials were not. It is now consistent everywhere.

### Bug Fixes
- Setup: fixed the wizard closing itself after the first user, which left new 26.08 installs stuck in a redirect loop between `/setup` and `/login.php`.
- API: tightened client scoping on the expense read and record update endpoints.
- Income: revenue rows now respect restricted client access.
- Client PDF Export: fixed the export producing a CSV file, and each section is now gated on the module that owns it.
- AI: fixed model creation, per-use-case model selection, configurable temperature, and error reporting.
- Ticket: system-generated replies no longer record time worked that was never worked.
- Ticket: fixed an error when scheduling a ticket.
- Ticket: cancelling a schedule now cancels the calendar event on the recipient's calendar.
- Ticket: history no longer records a status change when the status did not change.
- Recurring Ticket: bulk priority changes no longer deny access to agents who are not administrators.
- Contact: deleting a contact now removes the linked portal user, and anonymizing now redacts the phone number.
- Calendar: fixed event deletion.

### New Features & Updates
- Performance: queries now select only the columns they use instead of `SELECT *`, cutting memory use and query time across the app and especially in the crons.
- Performance: removed client joins that were only there for scoping — side nav badge counts are significantly faster.
- Client scoping: added a `clientScopeSql()` helper so list queries scope on the owning column instead of a joined `clients.client_id`.
- Contributing: documented the column-selection and client-scoping conventions.


## [26.08]
 
### Upgrading to 26.08
 
> **Read this before you start.** Done out of order this update will break your instance. The database structure changes, every API key is deleted, and the whole cron setup is replaced.
 
1. **Back everything up.** Take a full VM backup or snapshot before you start.
2. **Remove every ITFlow line from your crontab** (or delete `/etc/cron.d/itflow`). The old per-minute jobs must not keep firing against a half-updated install. You put the new one in at step 5.
3. **Update the files with the normal web updater**, from Settings > Update. It will pull the new files, report the update as successful, and then error out as it drops you back into the app. That is normal — the new code is now running against the old database.
4. **Run the database update from the command line.** Just this once it cannot be done from the web interface. Run it as the user that owns the ITFlow files — the script tells you which user if you get it wrong:
```bash
sudo -u www-data php /path/to/itflow/scripts/update_cli.php --update_db
```
It applies every pending version in order and reports each one as it goes. On an install with a lot of ticket history it can take a minute or more, so let it finish. If a step fails it stops there without advancing the recorded version, so you can fix the problem and run it again. The 500s stop as soon as it completes.
 
5. **Add the new cron entry.** One line runs everything now, and the schedules are managed in ITFlow under Settings > Cron:
```
* * * * * www-data php /path/to/itflow/cron/cron.php >/dev/null
```
Drop the `www-data` column if this goes in a user crontab rather than `/etc/cron.d`.
 
6. **Recreate your API keys.** Every existing key is deleted by this update. Issue new ones and update anything that talks to the ITFlow API.
7. **Check it took.** Open Settings > Cron — the green "Cron last checked in" banner should appear within a couple of minutes and every job should pick up a schedule.
Only this release needs the command line for the database update. Normal updates go back to running from Settings > Update as usual.
 
### Breaking Changes and Notes
 
- Cron: the crontab collapses to a single entry. `cron/cron.php` is now a dispatcher that runs every minute and works out which jobs in `cron/` are due, and the old nightly work has moved to `cron/nightly_tasks.php` which it runs at 03:00.
- Cron: an existing crontab keeps working — the per-minute scripts still run and still lock correctly, and a single daily `cron.php` entry still runs the daily jobs — but any job added in this or a future release only runs once the dispatcher is scheduled.
- Cron: ticket SLAs need no entry of their own. `cron/ticket_sla.php` is in the dispatcher's job list and runs every minute once the new entry is in place. Without it SLA targets are still worked out and displayed, but warnings and breaches never fire.
- Backups: write down your backup encryption key. It is generated on first use and stored in `config.php`, never in the database, and without it a backup cannot be restored.
- API: every existing key is deleted by this update and must be recreated. Keys are now owned by a user and inherit that user's role, module and client permissions instead of carrying their own client scope, and existing keys cannot be safely mapped to a user.
- API: credential decrypt passwords are now read from the request body instead of the query string. Any caller passing that value in the URL needs updating.
- Client access permissions now support deny rules as well as allow, and the permissions UI will not load until the database update has run.
- Business hours are new and default to Monday to Friday, 09:00 to 17:00 in your configured timezone. SLA targets are measured against them, so set them before assigning SLAs.
- The `plugins` directory is now `libs`. Anything pointing at `plugins/` directly — custom scripts, reverse proxy rules, web server config — needs updating.
- Several pages dropped the `_details` suffix and moved to consistent singular and plural filenames, so old bookmarks and external links will 404.
- Credential passwords moved from `varbinary` to `varchar(500)` and now have a length guard. Existing credentials are migrated by the database update.
### New Features & Updates
 
- Backups are now encrypted, catalogued, schedulable and restorable from the command line. Three types — Full (database and uploads), Database Only, and Master Key — and every archive is an AES-256 encrypted zip. Open them with 7-Zip, WinZip, PeaZip or Keka; `unzip`, Windows Explorer and the macOS Archive Utility do not support AES.
- Backups: one encryption key per install, generated on first use and stored in `config.php`, never in the database and never in the file name. It is shown in Maintenance > Backup.
- Backups are built by cron rather than by your browser. The button queues the work and the dispatcher picks it up within the minute, then notifies you — a dump of a real install takes longer than a web request is allowed to live, which is why the old Download Backup button timed out on large instances.
- Backups: scheduled backups are a new `backup` cron job, off by default, turned on in Maintenance > Cron. Retention by age and by count runs in the nightly job and never deletes the newest backup.
- Backups are stored outside the web-served path under `uploads/backups/` with a deny-all rule and downloaded through an admin-only handler. Set `$config_backup_path` in `config.php` to keep them off the web root entirely.
- Backups: restore from the command line with `php scripts/restore_cli.php --file=/path/to/backup.zip`. This is the only restore path with no size limit — the setup wizard's restore is capped by PHP's upload limits and a full backup is usually larger. `--inspect` checks an archive without changing anything.
- Backups: restores validate before they destroy. The key is checked and the archive unpacked before any table is dropped, and the current database is dumped first and put back automatically if the import fails.
- Cron: one entry instead of five, and a page to manage it. Jobs are tracked in a new `cron_jobs` table, so a job whose slot was missed runs at the next opportunity rather than waiting a day, and each job locks for its own run so a slow mailbox or a long nightly pass no longer holds anything else up.
- Cron: new Settings > Cron page listing every job with its schedule, last run, duration, outcome and next due time. Jobs can be disabled, rescheduled, or run on demand — Run Now hands the job to the next dispatch so it starts within a minute and still runs on the command line. The last error is kept until dismissed rather than vanishing behind the next success, and the page says plainly when the crontab entry itself is missing.
- Cron: the nightly run is safe to repeat. Late fees, overdue invoice reminders and autopay retries now apply at most once per invoice per day, so a Run Now after the scheduled pass no longer stacks fees or re-emails clients. Nightly Tasks only accepts the daily schedule.
- Cron: the master enable switch moved out of Notifications and into the Cron settings where it belongs, and the unused overdue invoice setting has been removed.
- Ticket: major UI overhaul of the ticket list, the kanban board and the ticket detail page.
- Ticket: redesigned the task bar on ticket details and removed the redundant task count.
- Ticket SLAs, optional throughout. An SLA sets a response target and an optional resolution target, assigned per client and priority with a global default and an explicit "no SLA" override. Targets are measured against your business hours. Tickets show time remaining and turn yellow at a configurable warning threshold and red on breach, on both the ticket list and the kanban board, and can be filtered by SLA state. Nominated statuses pause the resolution clock for "waiting on customer", preserving the remaining budget. Two new reports, SLA Summary and SLA by Client. With no assignments defined nothing behaves any differently.
- Ticket: added an Urgent priority.
- Ticket: agents can attach files to tickets from inside the app, both when raising a ticket and on a reply, and attachments are emailed to the contact through the mail queue. A 10 MB ceiling applies per message; anything that does not fit stays on the ticket to download.
- Ticket: tasks can be added and edited inline in the add ticket and add recurring ticket modals.
- Ticket: the older add ticket modal has been retired, there is one add ticket modal now.
- Ticket: watchers and attachments have moved into the assignment section of the add ticket modal.
- Ticket: recurring tickets can be assigned a ticket template. Picking one fills in the subject and details and stamps the template's task list onto every ticket the schedule raises, from the nightly run and a forced run alike. The recurring ticket list shows which schedules carry a template and how many tasks it adds.
- Ticket: recurring tickets now own their task list. The template fills it in when picked but it can then be edited per schedule, and it is those edits the run reads. Existing schedules are backfilled from their template by the database update.
- API: added ticket reply endpoints for creating and reading replies.
- API: added an invoice_items endpoint for adding line items to an invoice.
- Calendar: calendars can be published as a read-only iCalendar (ICS) subscription feed and read by Google Calendar, Nextcloud, Apple Calendar, Thunderbird or anything else that takes a feed URL. The link carries a secret key and needs no login, can be regenerated or revoked at any time, and a busy only option publishes time blocks without titles, descriptions or locations. Refresh timing belongs to the subscribing client — Google refreshes on its own schedule and cannot be forced, and Nextcloud defaults to weekly and refuses feed URLs resolving to private IPs.
- Calendar: events can be marked all day, and the date and time are now separate fields. Previously all day was inferred from a midnight start, which made a genuine midnight appointment indistinguishable from an all-day event. Existing events are backfilled by the database update using the old rule, so nothing changes appearance.
- Calendar: repeating events now work. The Repeat field was present but disabled and the stored value was never drawn. It is now selectable daily, weekly, monthly or yearly, and monthly and yearly series skip dates that do not exist in a period rather than sliding into the next month. Recurrence is series-wide — editing any occurrence edits the whole series, and individual occurrences cannot yet be moved or cancelled. Repeating events are marked with an icon and a hover note, and the delete action reads Delete series and asks for confirmation.
- Calendar: clicking empty space creates an event there. Clicking a day or a time slot, or dragging across several, opens the New Event modal with the start and end already filled in and the All day switch set to match. A range dragged out or lengthened by hand is no longer overwritten by the end-time-follows-start behaviour.
- Exports: every export modal now has a Filter tab and a Selectable Columns tab with sensible defaults, and can export to PDF as well as CSV.
- Combined Payments and Revenues into a single Income page with CSV export. Revenue not tied to an invoice is still added there and payments are still added from invoices. The standalone Payments and Revenues pages are gone.
- Income: added a Category column and filter, carried through to the exports.
- Income: added bulk actions for account, payment method and category.
- New Transactions page — a per-account ledger of transfers, revenues, payments and expenses with filtering by type, category, client, payment method, amount range and date, a running balance, summary cards, account balances in the account picker, and CSV export.
- Products: added a basic product import via CSV.
- Added user based RBAC for API keys, so a key runs as a user and inherits that user's permissions.
- Added deny rules to client access permissions, so access can be granted broadly and revoked for specific clients.
- New secure file download handler for files and ticket attachments, with client and contact permission isolation on the client portal.
- Invoices: clicking the Paid or Partial status badge opens a read-only breakdown of the payments recorded against that invoice.
- Assets: multiple notes per asset, same as contact notes, with categorized note types (Maintenance, Repair, Configuration, Upgrade, Inspection, Note).
- Reworked the Maintenance > Update page, and fixed the branch handling on it.
- Categories and tag types moved from a top button nav to a left side nav.
- Dashboard: added expiring asset warranties and licenses, along with an "Expiring in" filter for assets, licenses, domains and certificates.
- Added bulk and single refresh actions for domains and certificates.
- Stripe gateway fees now come from the actual Stripe balance transaction rather than a static percentage and flat fee configured in ITFlow, with a nightly pass to backfill fees that were not available at payment time. The static fee fields are gone from payment provider settings.
- Database updates are now split into per-version files under `admin/database_updates/`, the latest version is derived from the directory listing, and one run applies everything pending. Migration history before 2.0.0 has been pruned.
- Reorganized the main, client, admin and reports side navigation menus.
- Mail settings tabs are now URL addressable and stay on the active tab after saving.
- Removed the legacy vendor contacts feature.
- `dig` and `whois` are no longer required, domain lookups use native DNS and RDAP.
- Tightened the `.htaccess` rules, and added one for `uploads/tmp`.
- Bumped the minimum supported PHP version.
### Security
 
- Rate limited 2FA code attempts and narrowed the TOTP acceptance window.
- Rotate the session ID on login to prevent session fixation.
- Stopped parallel login attempts from bypassing the login rate limits.
- Tightened validation on the recurring invoice frequency used by the billing run.
- Admin UI modals are now gated to admins. Previously any logged-in user could open them directly and read stored payment provider and AI provider API keys — rotate those keys when you update, as there is no record of who may have viewed them.
- Global search returned credentials to users without credential module access. It is now gated like every other credential surface.
- Credential password reveals are now written to the audit log, on both the reveal endpoint and the TOTP code.
- Swept module and client permission enforcement across modals and ajax endpoints to match the post handlers, closing a number of cases where a user restricted to certain clients could read another client's records by ID.
- Products: the CSV export now requires sales module read access.
- Neutralized CSV formula injection in generated exports.
- Fixed weak random number generation in TOTP secret generation.
- Shared item views are now claimed atomically so the view limit cannot be exceeded by simultaneous requests, and guest audit IPs are logged.
- Hardened CSRF handling and session cookies, and set `SameSite=Lax` on the session cookie.
- Hardened file upload handling to use random storage names.
- Client Portal: contacts can no longer edit their own contact record.
- The setup wizard's restore step is now closed on any install that has users, whatever `config.php` says. Previously, if `$config_enable_setup` was missing from `config.php` — a state an install can be left in when setup does not reach its final step — the restore step stayed reachable. Restoring over a live install is now done from the command line.
- A restore no longer takes ITFlow's `uploads/.htaccess` from the archive. The guards are rewritten afterwards regardless of what the backup contained, so restoring a backup taken before those guards existed no longer removes them.
- Tightened the directory guards under `uploads`.
### Bug Fixes
 
- Deleting a payment now correctly recalculates and sets the invoice status.
- Fixed contact notes, and several broken modal links in contacts, assets and file linking.
- Client Portal: fixed adding saved payment methods and cards following a Stripe API change.
- Fixed sending invoices and quotes over OAUTH2, which was reading the SMTP host instead of the SMTP provider — the host is not filled in when OAUTH2 is selected.
- Mail Parser: correctly work out whether the ITFlow folder belongs under the `INBOX` namespace or the root directory, fixing folder creation on cPanel Dovecot Maildir++ setups.
- Fixed possible duplicate emails caused by a race condition in the mail queue.
- Added a shared lock guard across every cron entry point, scoped per script and per install, replacing the mail queue's non-atomic lock file. Rows left in a sending state by a run that died are now recovered.
- Prevented duplicate Stripe payment bookings and overlapping cron runs.
- Mail bodies are cleared after successful delivery.
- Reworked `getFieldById` to stop escaping its return value, and reworked every caller to escape at the point of use — it was causing double escaping in a lot of places.
- Added missing `maxlength` attributes to forms backed by length-limited columns, so an overlong value no longer throws a 500.
- Fixed undefined variables in the audit log and flash messages for expenses, assets, contacts and several other handlers, which were logging blanks in place of the record name.
- Fixed the spelling of the expense description in audit logging.
- Fixed autofill on invoices, quotes and recurring invoices, where the tax field was not updating and a dash was being placed in front of the product.
- Fixed gaps in ticket history.
- Recurring Expense: fixed editing not keeping the client.
- Fixed client name truncation in the side navigation being applied after escaping.
- Side navigation counts are only shown to users with permission to see them.
- Invoice statistics now only reflect clients the user has permission to see.
- The agent category handler no longer drops the category description.
- Expenses: allowed negative amounts, and the current date is now prefilled.
- Certificates can now be searched by description.
- Fixed cents calculation rounding.
- Fixed guest view credential TOTP display, and removed the legacy OTP code path.
- Gated the SLA option in ticket details, which was gated everywhere else.
- Deleting a ticket template task or a payment provider recorded the wrong name in the audit log and the confirmation message, reading an unrelated record's id in place of the name.
- Bulk-creating tickets from a template against multiple assets only added the template's tasks to the first ticket, and dropped each task's completion estimate.
- Deleting a ticket template now unlinks it from any recurring ticket that referenced it, instead of leaving the schedule pointing at a template that no longer exists.
- Fixed the asset section in the recurring ticket modal when opened outside a client, and project selection when raising a ticket.
- Tickets raised by the nightly recurring schedule were created without a guest URL key, so the "View ticket" link in reply and task approval emails could not be opened. Cron now generates a key like every other path that raises a ticket, and existing tickets missing one are backfilled by the database update.
- Calendar: fixed the last day of a multi-day all-day event not being drawn or published to subscribed feeds. `event_end` holds the last day the event covers, which is what the event modal asks for, but FullCalendar and iCalendar both treat an all-day end as exclusive.
- Fixed `confirm-link` doing nothing inside an ajax modal, where the handler was only bound to links present at page load.
### Developer Updates
 
- Line endings normalized to LF across the codebase, with `.gitattributes` and `.editorconfig` added. Vendored code under `libs/` is marked so it stays byte identical to upstream.
- Converted to the short echo tag `<?=` throughout.
- `functions.php` is now a loader, with helpers split into topical files under `functions/`. Unused legacy functions removed, including an unused database wrapper layer.
- PHP functions renamed to camelCase throughout, including `nullable_htmlentities` to `escapeHtml`, `sanitizeInput` to `escapeSql`, `logAction` to `logAudit`, and `key32gen` to `generateTotpSecret`.
- Seed data is now shared between the setup wizard and `setup_cli.php` from one file, so a headless install gets the same starter content as a browser install.
- `CONTRIBUTING.md` added and expanded, covering the security rules, style conventions, database column prefix convention and migration pairing.
### Library Updates
 
- Bump TinyMCE from 8.6.0 to 8.8.2.
- Bump DataTables from 2.3.7 to 3.0.1.
- Bump FullCalendar from 7.0.0 to 7.0.2.
- Bump ImapEngine from 1.25.0 to 1.25.4, along with its dependencies — notably zbateson/mail-mime-parser 3.0.6 to 4.0.3 and guzzlehttp/psr7 2.12.3 to 3.0.0.


## [26.07.1]

### Bug fixes
- Fixed broken M365 and Google Workspaces OAUTH2 in Mail Settings.
- Security Fix.


## [26.07]

### Major Changes
- Migrated from Webklex php Library to IMAPEngine.
- Major Rewrite of the mail settings page to better support Microsoft 365 and Google OAUTH2.

### Bug fixes
- Many Security Fixes.
- Microsoft 365 and Google can now specify Licensed User.
- Clients - Only show 3 Tags per line instead of streching all the way across.
- Login: Make Email field email type instead of text.
- Fixed Invoice Late Overdue notices now shows correct balance when late fees are attached and if partial invoice was paid.
- User Preferences Avatar: Fix creating user upload directory if doesn't exist, and remove Avatar now properly deletes the old avatar image.
- Do not send an in-app alert on successful cron execution, keep it in logging only.
- Fix Issues with Clients Signing in via Entra in the client Portal, items were broken because CSRF token was not being generated.

### New Features & Updates
- Clients: Removed Entity Stat Counter was slow and unused.
- Added Monospace text in areas where it deserves it like, IPs, Amounts Costs in Tabular data forms etc.
- Cicking into the client section no longer turns the main nav and text gray, it keeps the configured theme across the app. It was implmented long ago to differentiate between the client section and the main section of ITFLow but didn't work very well. 
- Bump Sortablejs from 1.15.6 to 1.15.7.
- Bump TinyMCE from 8.5.0 to 8.6.0.
- Bump Fullcalendar from 6.1.20 to 7.0.0 amd convert existing code to comply with 7.0, also make calendar more printable.
- Bump PHPMailer from 7.0.2 to 7.1.1.

## [26.05.1] Stable Release
- Security Fixes.

## [26.05] Stable Release
### Bug Fixes
- Stripe Payment: Fix adding saved cards on client portal.
- Various client and module enforments fixes. 
- Projects: Fix slow load by using an optimized query to count tickets and tasks.
- Show correct currency for the account balance when adding payment to invoice.
- Expire all Password reset tokens nightly with cron.
- Shared Items via secure link: Do not delete shared items that have not been viewed before cron runs.
- Client: Fix Client Abbreviation being converted to an int on edit.

### New Features & Updates 
- Bump TinyMCE from 8.4.0 to 8.5.0.
- Bump TCPDF from 6.11.2 to 6.11.3.
- DeBump stripe-php from 20.0.0 to 19.4.1.

## [26.04] Stable Release
### Bug Fixes
- Racks: Fix Device Removal.
- Table Lists: replace class table-responsive-sm with just table-reponsive was causing ui issues with certain screen sizes.
- Client: Fix Edit erroring on certain characters.
- Category: Fix Add/Edit due to missing CSRF fields.
- Category: Fix Restore function and Icon and text color.
- Invoice: Do not apply late fee on first overdue reminder (1 day).
- Ticket: Fix issue with contact not being added with Add contact modal v1.
- Quote: Fix Copy was missing client.
- API: Don't set client ID from POST - this is properly done via require_post_method instead only if it's an all-clients key.
- API: Prevent error 500s when existing data can't be cleanly re-inserted to database.
- API: Add more helpful errors.
- API: Fix asset read uri_2 field.
- API: Various other field fixes.

### New Features & Updates 
- Categories: Add Description Field.
- Categories: Add DB Field for order.
- Categories: Move Asset Status and Network Interface Type to categories so custom ones can be created and edited.
- Categories: Moved note type, software type, rack type to be creatable/editable Categories with common defaults and descriptions
- Files: Allow .swb file for MikroTik Backup Files.
- Software: Added additonal License Types including Perpetual, Site, etc.
- API: Invoice Items: Add read endpoint.
- Networks: Added Import.
- Bump TinyMCE from 8.3.2 to 8.4.0.
- Bump stripe-php from 19.4.1 to 20.0.0.

## [26.03] Stable Release
### Bug Fixes
- Ticket Templates: Fix Task Sortinhahahg.
- Ticket: Lower autoclose setting minimum value from 48 to 24 Hours.
- Ticket: Fix Task Approval.
- Recurring Ticket: add empty value placeholder for Ticket Frequency.
- Documents/Files: Fix redirect after File Upload to redirect to files instead of the non existent documents.
- Setup: Fix base url tacking on /setup when not installing via script.

### New Features & Updates 
- Clients: Net Terms: Added common 45 and 15 Days, removed 14 Days not as common.
- Clients: Bulk Action Set Net Terms Added.
- Clients: Swapped location and contact column, add PopOver with Details such as created, abbreviation, DB ID instead of taking up space underneath client, rounded tag pills and increased padding, removed info badges and added one info badge that displays a popover with details.
- Clients: Added New Ticket to Client Top Header Menu.
- Clients: Client Overview: UI Sprucing.
- Invoice: Send reminder 1 day after due date.
- Invoices/Quotes/Recurring Invoices: Split Items tables into their own POST logic and Modal UIs and tables (quote_items, invoice_items, recurring_items).
- Tickets: New Ticket Parsing - Anyone CC'ed onto the original email that created the ticket is added as a ticket watcher.
- Ticket/Quotes: Quotes can now be associated with a ticket.
- Networks: Removed Subnet Mask Field, Use CIDR instead.
- Networks: Rearranged fields, Updated placeholders, Add/Edit/list for better flow.
- Networks: Renamed DHCP to IP Range to allow for you use of both DHCP and or Usable IPs.
- Assets: Rearranged fields, Updated placeholders, Add/Edit/list for better flow.
- Assets: Added IPv6 if available under IP, Make and Model are now one line with Serial Underneath. Added OS under Type. use pill for status.
- Calendar: Event thats are cut off can now be viewed as a tooltip on hover.
- Calendar: Renamed System Calendars to built-in calendars and added the names and color dot for reference.
- Calendar: You can now delete a custom calendar.
- Report: Client Ticket Time Detail Audit: Selectable Billing Time Increment, will later be avauilable globally.
- Roles/Permissions: Now complete and is out of beta all permission roles are strictly enforced, except for in Trips and Calendar, new enforce modules will be added for these at a later date.
- Project Templates: Ticket Template order can now be dragged and dropped.
- Global: Introduced new checkbox class to all Checkbox select columns to keep consistency and reduce space and enhance ui.
- Global: CSRF Checks everywhere instead of just deletion calls.
- Global: Renamed the rest of the unarchive post and label calls to restore.
- Files: Allow upload of .unifi extension.
- Bump Libraries:
  - stripe-php from 19.0.0 to 19.4.1.
  - fullcalendar from 6.1.19 to 6.1.20
  - TCPDF from 6.10.1 to 6.11.2

## [26.02.1] Maint Release
### Bug Fixes
- Credentials: Fix Password Generator.
- Calendar: Restrict Events for client restricted agents. 
- Ticket Merge: Fix.
- Asset Transfer: Fix.
- Ticket Listing: Restrict Tickets presented in ticket list view from client restricted agents.
- Ticket Details: Deny access to client restricted agents to view tickets without client_id in uri.
- Tickets: Allow agents with restricted client access to view and edit tickets without a client.
- Ticket Change client: Limit selection for agents with restricted client access.
- Ticket Details: Don't display updated at when null.

### New Features & Updates 
- Report: Added Client Detail Auditing.
- API: Added Endpoint to retrieve time worked by agent.
- ajax-modal: Revert to previous JS implementation before 26.02 release.
- Ticket: Move Subject from Ticket main ticket header to ticket details card header.

## [26.02] Stable Release
### Bug Fixes
- Mail Parser - Do not automatically send new ticket notifications to noreply/donotreply addresses.
- Ticket: removed newline \n on Parsed emails.
- Show Trips for everyone if accounting module is enabled.
- Fix Invoice Exporting.
- Fix Billable Column not sorting correctly in tickets.
- Fix Login flow where user agent and client user exists and agent has MFA but will not let them continue.
- Fix passing missing user_id var in client portal.
- Fix Ticket Templates not auto filling when selected.
- Fix Invoices not being sent to all billings contacts when manaully sent.
- Fix Documents and Files not able to be bulk deleted.
- Fix Role Archiving, can be archived as long as no users are assigned to the role.
- Fix showing Powered By ITFlow visibility on the login screen when Whitelabel is enabled.
- Missing username in audit log on successful login due to missing passed user_id to logging.
- API: Fix updating all documents instead of the intended document.
- Documents: Fix Document created at not showing the correct creation date of the master document.
- Ticket: Fixed Using edit ticket modal agent was not able to be set.
- Always check if a user is archived and or disabled instead of just during login.
- Report: Fix Collected tax report not totalling all tax categories.

### New Features & Updates 
- Task Approval System for ticket tasks: Once an approval is requested, the task cannot be marked as complete until approved. Internal Approvals Any other technician, or Specific technician, Client Approvals Anyone (usually the requestor) Tech contacts Billing contacts.
- Printable Invoice Packing Slips now available.
- Drastic Performance Bump: Up to 50% faster queries accross the board and reduced server memory usage by 40% by switching Database Query method from mysqli_fetch_array to mysqli_fetch_assoc.
- Added Connect to Microsoft 365 Button to mail settings.
- OAUTH2 support for Microsoft 365 and Google Workspaces is now considered stable and working.
- Favorites: Assets and Credentials now can be favorited singly or by Bulk action. Favorited items appear in the client overview now.
- Files/Documents: Collapsable folders feature, collapsed by default with a button to expand all.
- URL Keys and such are now set to a more manageable 32 Characters by default.
- Various UI/UX Updates throughout the app, with focus oin ticket details, contact details modal etc.
- Added Show Archived files and documents to the files section.
- Added Bulk Archive and restore options to files and documents.
- Rewrite of the Kanban Ticket view to match our procedural style of coding.
- All options are available in TinyMCE now in Mobile mode.
- Agent names appear now in Invoice History section.
- Mail Parser: Support flowed text.
- Assets: Keep Purchase reference when copying.
- Assets: Add basic tracking history: Archiving, restoring, name changes, transferimg to new clients.
- Mail Parser: NDR Parsing.
- Allow SVG files in mail attachments.
- Tickets: Use a more friendly time worked instead of 02:41:00 translates to 2h 41m.
- Update wording on ticket to invoice item details.
- Merge Tickets: Now wth a ticket merge dropdown list of tickets instead of a text field.
- Role Permissions can now be set during role creation, update Permission UI to use radio buttons instead of select boxes.
- Bump TinyMCE 8.2.2 to 8.3.2.
- Bump PHPMailer from 7.0.1 to 7.0.2.
- Bump Datatables from 2.3.4 to 2.3.7.

## [25.12.1] Maint Release

### Major Changes
- Unified the Client/Agent Login and process (Note only Client Users can Reset passwords from the login page, does not apply to agent users).

### Bug Fixes
- Fix Payment Provider not adding an account.
- Fix New ticket button in contact details in the related tickets section.

### New Features & Updates
- You can now Set Payment Provider income/expense account, expense vendor and expense category upond creation or editing.
- Moved Saved Payment Provider Methods away from admin side nav to the count link within Payment Providers page.
- Moved AI Models from the admin side nav to the model count link within AI Providers.
- Add Favicon Reset.

## [25.12] Stable Release

### Breaking Changes ###
- For Existing installs: **php-xml** extension needs to be installed for document creation and editing, new install script does this for you as of Dec 6th 2025. To install php-xml: `sudo apt install php-xml`

### Major Changes
- Consolidated "Files" and "Documents" into a single section called **Files**.

### Bug Fixes
- Resolved issue with updating asset notes in asset details.
- Fixed problem with bulk ticket merging.
- Corrected issue where decimal inputs (e.g., price, cost) weren’t displaying on iPhones in certain forms.
- Added CSV escaping to the sample export data in areas where a sample CSV template is provided.
- Fix a race condition where dupe tickets, invoices, recurring invoices, recurring tickets, quotes will be created using the same number if created in parallel espcecially when using the API.

### New Features & Updates
- Introduced automatic subject-based ticket merging/reply detection. Now, if an email comes from a known contact or domain and the subject matches 95% of a ticket opened in the last 7 days, it will be merged automatically.
- Added `cleanInput` function to sanitize data before inserting it into the database when using MySQLi prepared statements.
- Migrated client post functionality to use MySQLi prepared statements.
- Updated payment method post functionality to use MySQLi prepared statements.
- Implemented `saveBase64Images()` to convert base64-encoded `<img>` tags into actual image files stored under `/uploads/<module>/<id>/` with secure filenames. Added wrapper functions, and updated document creation to use processed image paths.
- For new documents and document templates, images are now stored in `/uploads/documents/$document_id` instead of being stored as base64 in the database, using the `saveBase64Images()` function.
- UI/UX improvements made to the document details page.
- Removed sidebar quick-add options.
- Created new folders in the uploads directory: `documents`, `document_templates`, and `recurring_tickets`.
- Reworked the bulk action function to pass the name arrays, instead of a generic `selected_ids` array. This allows multiple bulk name arrays to be passed at once, currently used for the new file-document merge.
- Big task: Converted the remaining modals to use the new `ajax-modal` system, enabling more flexible flow expansion going forward.
- Mail queue: Added a `--no-mx-validation` flag to bypass recipient domain MX validation.
- Bump PHPMailer from 7.0.0 to 7.0.1.
- Bump stripe-php from 18.1.0 to 19.0.0.
- Bump TCPDF from 6.10.0 to 6.10.1.
- Bump TinyMCE from 8.2.0 to 8.2.2.

## [25.11.1] Maint Release

### Fixes
- Fix broken edit Payment Method.
- Fix unable to delete Vendor Template.
- Fix Mail Queue link in flash alert for testing email and sending a quote.
- Add Show Category Type select if not defined.
- Add Show Product Type select if not defined.
- Fix add ticket watcher.
- Fix if Client isn't assigned to a ticket dont show client view.
- Fix missing session client id check when paying an invoice from client portal.
- Update Composer Webklex-IMAP library dependency symfony/http-foundation from 7.3.3 to 7.3.7 to fix security related issues.
- Add back delete Payment provider the database will handle cascade deletes to saved cards, recurring payments and client payment provider reference.
- Don't show Client Tickets Breadcrumb if no client is assigned to a ticket.
- Don't Show Contact or Assignment Tab in edit ticket if no Client is Assigned.
- Don't Show add contact, asset, vendor, watcher if not client is assigned to a ticket.
- Don't Show Public Comment & Email if contact email doesn't exist.
- Fixed IMAP Test whicn now uses RAW TCP Connection instead of the depracated php-imap extension.
- Fix Broken Link in Ticket Updates via Client Portal to agent.

### Added / Changed
- [Feature] Added Asset Tags.
- [Feature] Added Quick Add Links to most side bar navs example quickly add a client from sidebar.
- Migrate ticket template add to ajax modal.
- Add TOTP secret to Client Export PDF in Credential section.
- Add UserID on hover in users listing.
- Merge ticket now redirects to the new ticket details page.
- [Feature] Add Pay via saved card under invoice Listings.
- Ticket Related Side Items UI Cleanup to use btn-tool class. 

## [25.11] Stable

### Deprecation Notice:
- **Outdated CRON Scripts**: The following scripts are removed.
  - `/scripts/cron_mail_queue.php`
  - `/scripts/cron_ticket_email_parser.php`
  - `/scripts/cron.php`
  - `/scripts/cron_domain_refresher.php`
  - `/scripts/cron_certificate_refresher.php`
  
  **Action Required**: Transition to the new versions:
  - `/cron/mail_queue.php`
  - `/cron/ticket_email_parser.php`
  - `/cron/cron.php`
  - `/cron/domain_refresher.php`
  - `/cron/certificate_refresher.php`

- PHP Extensions php-imap and php-mime-mail-parser are no longer required.
---

### Fixes
- **Ticket Listing**: Resolved issue where the “Check All” checkbox was visible even when ticket status wasn’t set. Now hidden for closed tickets only.
- **Timer Auto-Start**: Show H/M/S placeholders when timer auto-start is disabled.
- **Ticket Guest URL**: Fixed email not including the ticket guest URL key.
- **EML Generation**: Resolved issue with EML not being generated in the new ticket parser.
- **New Ticket Mail Notification**: Included message when notifying the tech of a reply in the new ticket mail parser.
- **Advanced Filter Collapse**: Added clause to prevent collapse of advanced filters when the “from” date is set to the default (1970-01-01).
- **Recurring Invoice**: Fixed issue where email was marked as sent but not actually sent when forcing a recurring invoice to an invoice.
- **CSRF Token**: Fixed issue with deleting recurring ticket from asset details page due to missing CSRF check token.
- **Vendor Website Link**: Fixed missing `https://` prefix in the vendor website link on the vendor details modal.
- **Agent Select Box**: Resolved issue where agents sometimes didn’t appear in the agent select boxes.
- **TinyMCE**: Fixed TinyMCE editor issue on Bulk Create Ticket in Assets.
- **Ticket Timer**: Fixed ticket timer initialization after reload and when the tab is put to sleep (background tab).
- **Client Deletion**: Fixed issue with client deletion.
- **Domain Records**: Added flag for missing SOA record when adding a domain (prevents subdomain creation).
- **Domain Fetching**: Quits domain record fetching if no SOA record exists (prevents subdomains).
- **Domain Expiry**: Only show time to expiry when there’s an expiry date set; otherwise, display a dash.
- **Certificates**: Improved handling of empty date in the agent UI.
- **Certificates API**: Fixed bug with missing JS to fetch certificate details.
- **API Updates**:
  - Clients API: Added support for archiving/un-archiving clients, updating client data, and abbreviation support.
  - Contacts API: Added archiving/un-archiving and restriction to only allow one primary contact per client.
  - Mail Queue: Added recipient domain MX validation before sending emails.

---

### Added / Changed
- **Backup / Restore**: Improved backup and restore by streaming data to disk (to prevent memory issues), setting unlimited timeouts, checking for bad backup contents, and using PHP for DB import instead of shell exec. Added `.htaccess` to prevent PHP execution in `/uploads/` directory.
- **Ajax Modals**: Migrated all Add and Bulk modals to the new Ajax Modal for improved performance.
- **Recurring Ticket Sorting**: Default sorting of recurring tickets by `RunDate` instead of subject.
- **Recurring Ticket Enhancements**:
  - Added Billable column.
  - Added bulk actions for setting priority, agent, billable status, and next run dates.
  - Added filters for category, assigned agent, and billable status.
  - Added new frequency options: 3-day and biweekly.
- **Asset Select**: Updated asset select dropdown to separate asset types using opt groups (planned for wider use).
- **Expiring Domains & Certificates**: Added "30 Day" warning for expiring domains and certificates in the dashboard.
- **Ticket Search**: Allowed search using both ticket prefix and number.
- **Recurring Invoice**: Cancel recurring invoices when the associated client is archived.
- **Credentials Import/Export**: Now includes TOTP secrets when importing/exporting credentials.
- **Asset Notes Import**: Allowed importing of asset notes.
- **Ticket View**: Added a "View HTML Code" button in all ticket views for TinyMCE.
- **Date Range Picker**: Updated all date filters to use the improved DateRangePicker JS.
- **Bulk Ticket Creation**: Added bulk ticket creation for clients.
- **Sidebar Updates**: Updated all sidebars to use absolute paths for easier integration with custom code.
- **Document Actions**: Added Archive and Delete buttons to the Document Details view with improved redirect behavior.
- **Ticket Template Sorting**: Allowed sorting by task count in ticket templates.
- **Contact Modal UI**: Updated contact details modal to display contact information at the top.
- **API & Code Updates**: 
  - Separated out post files for recurring tickets, invoices, expenses, and payments.
  - Removed unused budget code.
- **Invoice Product Autocomplete**: Now allows searching for product codes as well as names.
- **Client Duplicate Check**: Flags duplicate clients or leads when using the client add modal.
- **Recurring Invoice Reference**: Added a column to invoices indicating if they were created from a recurring invoice.
- **Global Search Enhancements**: 
  - Allowed ticket details to be searchable in global search.
  - Allowed searching for quotes in global search.
- **UI/UX Improvements**:
  - Spruced up the ticket details page UI.
  - Added contact email validation to flag duplicates or invalid addresses.
- **API Debugging**: Log API endpoint/URL path for authentication failures to aid in debugging.
- **Image Upload Optimization**: Removed image optimization from uploads (this will be handled by a cron job in the future).
- **View Behavior Change**: Updated ticket/invoice/quote views to always be in the Client section, showing client-side navigation and top info bar.

---

### Library Updates:
- **DataTable**: Bumped from 2.3.3 to 2.3.4.
- **TinyMCE**: Bumped from 8.0.2 to 8.2.0.
- **Stripe-PHP**: Bumped from 17.6.0 to 18.1.0.
- **PHPMailer**: Bumped from 6.10.0 to 7.0.0.
- **Chart.js**: Bumped from 4.5.0 to 4.5.1.




## [25.10.1]
- Deprecation Notice: `/scripts/cron_mail_queue.php` , `/scripts/cron_ticket_email_parser.php` , `/scripts/cron.php` `/scripts/cron_domain_refresher.php`, `/scripts/cron_certificate_refresher.php` are being phased out. Please transition to `/cron/mail_queue.php` , `/cron/ticket_email_parser.php`, `/cron/cron.php`, `/cron/domain_refresher.php`, `/cron/certificate_refresher.php` These older scripts will be removed in the November 25.11 release—update accordingly. 25.10.1 installs have the script already configured.

### Fixes
- Fix regression missing custom Favicon.
- Update SMTP and IMAP provider to allow for empty strings, empty means disabled.
- Fix Client portal Microsoft SSO Logins.
- Fix regression in Vendor Templates.
- Fix refression in some broken links from user to agent.
- Fix Project edit.
- Prevent open redirects upon agent login.
- Fix regression on switching to Webklex IMAP to allow for no SSL/TLS in IMAP.
- Fix Setup Redirect not behaving properly when setup hasnt been performed.
- Added Server Document Root Var to several includes, headers, footers files to allow includes from deeper directory strutures such as the new custom directories.
- Fix edit contact in contact details.
- Add .htaccess to /cron/.

### Added / Changed
- Support for HTML Signatures.
- Add Edit Project Functionality in a ticket.
- Added more custom locations: /cron/custom/, /scripts/custom/, /api/v1/custom/, /setup/custom/.
- Copied `/scripts/cron.php` `/scripts/cron_domain_refresher.php`, `/scripts/cron_certificate_refresher.php` to `/cron/cron.php`, `/cron/domain_refresher.php`, `/cron/certificate_refresher.php`. See Above!
- Signatures is now handled in post ticket reply on Public Comments only.

## [25.10]

### Breaking Changes
- Renamed `/user/` directory to `/agent/`.
- Deprecation Notice: `/scripts/cron_mail_queue.php` and `/scripts/cron_ticket_email_parser.php` are being phased out. Please transition to `/cron/mail_queue.php` and `/cron/ticket_email_parser.php`. These older scripts will be removed in the November release—update accordingly. New Installs via the script will have this already configured.
- Custom is working now. Custom code should be placed in /admin/custom/ , /agent/custom/ , /client/custom/ /guest/custom/
We will provide example code with directory structure for each custom directory a week after this release.

### Fixes
- Resolved issue with "Restore from Setup" not functioning correctly.
- Corrected asset name display in logs and flash messages when editing an asset in a ticket.
- Fixed Payment Provider Threshold not being applied.
- Fixed issue where Threshold setting was not saving properly.
- Various minor fixes for Payment Provider issues.
- Removed leads from the client selection list in the "New Ticket" modal.
- Fixed issues with the MFA modal.
- Resolved MFA enforcement bugs.
- Fixed KeepAlive functionality to maintain user sessions longer.
- Fixed multiple broken links caused by the `/user/` to `/agent/` path migration.
- Fixed Custom code directories.

### Added / Changed
- Removed "ACH" as a payment method; added "Bank Transfer" instead.
- Replaced relative paths with absolute paths for web assets.
- Tickets can now be resolved via the API.
- Added a filter for Archived Users and an option to restore them.
- Introduced a modal when archiving users, allowing reassignment of open and recurring tickets to another agent.
- Improved logic for determining the index/root page.
- Added "Assigned Agent" column for recurring tickets.
- Introduced "Additional Assets" option when editing assets in tickets; modal now uses the updated AJAX method.
- Added Gibraltar to the list of supported countries.
- Added Custom Link Option for the Admin Nav.
- Added Custom Link Option for the Reports Nav.

### Other notes
- Major releases will happen on the first week of every Month.


## [25.09.2]

### Fixes
- Fix Payment Method Select box in Revenue.
- Remove Extra Feeback Wording When Invoice Sends.
- Updated all CSV exports to use escape parameters.
- Fix Missing First row on Asset interface export.
- Fix Edit User not working due to incorrect modal footer path.
- Fix Add Certificate breaking due spelling on function.
- Update all CSV Exports to include company name or client name depending on when its being exported from.
- Introduced new function sanitize_filename and implmented it in all exports.
- Spruced up UI/UX Saved Paymented section in Client Portal.
- Fix add Payment Link in client portal recurring invoice section.
- Better Logic handling for default page redirect.

### Features
- Introduced new Beta mail parser cron using webklex imap library instead of php-imap as this is deprecated --Not Enabled on existing installs, only new installs.
- Introduced Beta support for OAUTH2 Authentication for Microsoft 365 and Google Workspaces for both incoming ticket parsing and outgoing email but must use new mail parser and mail queue for this to work, and requires changing the cron jobs: scripts/cron_mail_queue.php to cron/mail_queue.php and scripts/cron_ticket_email_parser.php to cron/ticket_email_parser.php.

---

## [25.09.1]

### Fixes
- **Web Installer**: Resolved issue with broken installer caused by incorrect database schema file name.
- Hide the "Add Credit" button as the feature is not fully implemented yet.
- Corrected long invoice/quote notes that were overlapping with the footer in PDF exports.
- Fixed AI settings not appearing in the Admin Menu when the Billing module was disabled.
- Enabled wrapping of client tags when they are too long.
- Fixed an issue where AI was not functioning correctly.
- Removed extra spacing between the contact name and icon in the Ticket Details contact card.

### Features
- Redesigned **AI Ticket Summary**, now divided into 3 sections: Main Issue, Actions Taken, and Resolution/Next Steps.
- Updated the **AI Ticket Summary** prompt to include ticket status, reply author, source, category, and priority.

---

## [25.09]

***BACK UP*** before updating.

---

### Breaking Changes and Notes
- We strongly recommend updating from the command line, however if performed via the webui and after performed it will return a 404. thats normal as the directory structure has changed, just close your browser then log back in then go back to update to perform the many database updates. 
- This is a major release with significant changes. While the community has done a great job identifying bugs, some may still remain — continued testing is encouraged.
- All AI settings will be **reset** and must be reconfigured using the new AI provider backend.
- The `xcustom` directory has been renamed to `custom`. All custom libraries and post-processing scripts should now be placed here.

---

### Added / Changed
- Numerous UI improvements and refinements across the application.
- Enhanced visual clarity by thickening the left border on ticket comments to help identify comment types.
- Ticket details UI redesigned to use less space at the top of the screen.
- Introduced tracking for the **first response date/time** on tickets.
- New reporting feature: **Average time to first response** on tickets.
- Stripe integration rebuilt using the new **payment provider backend**.
- Clients can now save and manage **multiple payment methods**.
- Support for selecting saved cards for **recurring invoices** in both the client and agent portals.
- Initial database structure and logic added for **credit management** (feature not yet enabled).
- Major **backend directory restructuring**.
- Introduced **stock/inventory management**, including a stock ledger backend.
- Stock quantities now update automatically when invoice items are added or removed.
- Invoice autocomplete now includes: **name, description, price, tax, stock levels**, and links `product_id` to `item_id`.
- Added a **category filter** to invoices.
- Linked stock to related expenses.
- New product fields: **location, code, and type**.
- Products now separated into two types: **Service** and **Product**.
- **Dark mode** introduced.
- Projects: Now support linking **closed tickets**.
- Clients: Added bulk actions for tags, referral source, industry, hourly rate, email, archive, and restore.
- Invoices: Bulk action added to **assign categories**.
- Assets: New `client_uri` field, visible in both the agent and client portals.
- Client Portal: Clients can now **select an asset** during ticket creation.
- Client Portal: Company logo now **displays in the header**.
- Client Portal: Dashboard cards are now **clickable** for more detail.
- Assets: Option added to include **MAC Address** in additional columns.
- Asset Interface: Bulk actions added — set DHCP, network type, and delete.
- API:
  - Added `/location` endpoint.
  - Ticket content now supports **HTML formatting**.
- New option to filter and display **500 records per page** in the footer.
- Payment methods are now treated as a **separate entity** instead of being grouped under categories.
- Updated libraries:
  - **TinyMCE**
  - **Chart.js** (major upgrade)
  - **DataTables**
  - **Bootstrap**
  - **FullCalendar**
  - **php-stripe**

---

### Fixed
- Several security vulnerabilities patched (with thanks to www.helx.io).
- Ticket status is no longer updated when scheduling.
- Client Portal: Tech contacts can no longer edit their own details.
- Fixed overlapping logo issue in Invoice/Quote PDF exports.
- Refactored `check_login.php` into multiple files for modular login functionality.
- Removed redundant logging comments for redirects.
- Renamed `get_settings.php` to `load_global_settings.php`.
- Simplified syntax for `ajax-modal` and updated usage throughout the app.
- Fixed issue where primary contact text wasn’t displaying.
- Corrected client **Net Terms** display.
- Fixed logic for recurring expense **next run date**.
- Resolved broken **IMAP test button**.
- Archived clients can no longer log into the portal.
- Searching closed tickets no longer reverts to open tickets.
- Fixed project search filter not showing completed projects.
- Fixed issue where company logo was not being removed correctly.
- Resolved API bugs:
  - Default rate and net terms.
  - Contact location.
  - Document endpoint.

---

### Developer Updates
- Replaced legacy code with newer functions like `redirect()`, `getFieldById()`, and `flash_alert()`.
- Significantly improved performance of queries used for filter selection boxes.


## [25.06.1]

### Fixed
- Fixed a regression in setup causing it to crash and never complete, due to missing default for currency.

## [25.06]

### Breaking CHANGES
- Old Document Verions will be deleted due to the major backend rewrite how document versions work.

### Added / Changed
- Improved function for retrieving remote IP address for logging purposes.
- Ticket categories are now sorted alphabetically.
- Visiting a deleted invoice or recurring invoice now redirects to the listing page; delete option added to invoice details page.
- Added "Mark as Sent" and "Make Payment" actions directly on the invoice listing page.
- Introduced Ticket Category UI for recurring tickets.
- In Project Details, bulk actions and sorting are now available for tickets.
- Updated ticket details UI to use full card stacks with edit icons for stackable items (e.g., asset, watchers, contact).
- Added a new setting to toggle AutoStart Timer in ticket details (disabled by default).
- Applied gray accent theme in the client section to visually distinguish from the global view.
- Introduced Ticket Due Date functionality (currently supports add/edit only; more updates coming next release).
- Added settings option to display Company Tax ID on invoices.
- Client overview now displays badge counts for all entities.
- Overhauled UI for Invoice, Quote, and Recurring Invoice details; switched PDF generation to TCPDF PHP from PDFMake JS.
- Document versioning has been moved to a separate backend table to resolve permanent link issues -- SEE Breaking CHANGES.
- Migrated Document Templates, Vendor Templates, and Software/License Templates to dedicated tables.
- Added functionality to mark all tasks in a ticket as complete or incomplete.
- Asset CSV import now supports a purchase date field.
- Recurring Payments have been restructured to auto-charge on the invoice due date instead of at generation time.
- Added "Base Template" label for vendor templates when available.
- Backup and restore processes now use a temporary directory; files are cleaned up automatically if operations fail.
- Added confirmation prompt when accepting or declining a quote.
- Other minor code UI/UX cleanups and refactoring throughout the app.

### Fixed
- Resolved issue with enabling MFA.
- Fixed UI regression where ticket listing columns would misalign.
- Non-billable invoices are no longer included in calculations.
- Addressed multiple minor reported security vulnerabilities.
- Tickets with open tasks are no longer resolved in bulk; a warning is shown along with a count of affected tickets.


## [25.05.1]

### Added / Changed
- Added Domain Expiring Card to Client Portal Dashboard for Primary and Technical Users.
- Added Balance and Monthly Recurring Amount to Client Portal Dahboard for Primary and Technical Users.
- Added Archive Searching to network and certificates also added unarchive capabilities to them as well.

### Fixed
- Add Payment not showing in Invoice.
- Updated Client Overview Entities to not show archived client's Entities even though the entity may not be archived.


## [25.05]

### Added / Changed
- Expanded file upload allow-list to include .bat and .stk file types.
- Added full backup/restore functionality. Backup downloads a zip that includes the SQL dump and uploads folder, setup now has option to restore from zip backup.
- Migrated Asset and Contact Links to modals to resolve variable overlap issue.
- Added Pagination to Notification Modal.
- Removed 500 Records Per Page option.
- Removed unused old DB checks in the top nav.
- Clients can now use the portal to setup Stripe automatic payments themselves for recurring invoices
- Automatic payments are now disabled for all recurring invoices if the saved payment method is removed
- Added Card Details and Payment added to Client Stripe.
- UI / UX updates to guest pay Make use of cards.
- Don't show Checkbox columns when ticket is closed, compact ticket list now matches round pills for status and priority.
- Ticket UI/UX update allow the ticket toolbar to be a little more mobile-friendly
- UI / UX Updates to Expenses - Combine Category and Description into 1 column.
- Country information is now displayed in Invoices, Quotes, Recurring Invoices, Clients, Locations, and the client top header.
- Added country-based search filters in Locations and Clients sections.
- Changed the settings name from Integrations to Identity Providers to make room for future iDPs (e.g. Google).
- Bump FullCalendar from 6.1.15 to 6.1.17.
- Bump DataTables from 2.2.2 to 2.3.1.
- Bump TCPDF from 6.8.2 to 6.9.4.
- Bump tinyMCE from 7.7.1 to 7.9.0.
- Bump phpMailer from 6.9.2 to 6.10.0.
- Bump stripe-php from 16.4.0 to 17.2.1.


### Fixed
- "None" option for SMTP encryption now functions correctly.
- Debug table row counts now reflect actual counts instead of relying on SHOW TABLE STATUS.
- Archived Categories now display properly.
- Stripe saved payment methods are now limited to credit/debit cards only.

## [25.03.6]

### Fixed
- Set default to date to 2035-12-31 as 9999-12-31 and 2999-12-31 broke certain browsers.
- Update Client PDF Export, add header added company logo.
- Present Larger clearer Warning about updates on update page.
- Allow to search by project reference.

## [25.03.5]

### Fixed
- Fixed the user listing issue when copying a trip.
- Corrected the display of recurring invoice amounts on the dashboard.
- Fixed the linking of entities with assets and contacts.
- Resolved the issue with displaying the correct mobile country code in the contact listing.
- Set the default date to `9999-12-31` to ensure future items (like invoices) are displayed by default.
- Fixed the display issue where file folders were not showing properly during document creation.
- Migrated from Dragula to SortableJS for a more modern, mobile-friendly solution.
- Added Handlebars icons for drag-and-drop items.
- Changed behavior to open Contact and Asset Details pages directly instead of using a modal.

## [25.03.4]

### Fixed
- Ability to remove additional assets from the ticket details screen.
- Fix the ability to remove assets from edit ticket not working when only 1 asset exists.
- Fix Database Backup corruption.
- Client Portal - show ticket number instead of ticket id in ticket listing.
- Add Purchase Reference to copy asset.
- Add Link to asset details from the global search.
- Fix Bulk assign ticket only showing contacts instead of ITFlow users.


## [25.03.3]

### Fixed
- Fix adding ITFlow user.
- Do not alert on inactive recurring invoices.
- Fix ticket user assignment including bulk assignment.
- Fix adding a location phone extension.
- Do not default to +1 Country code, instead default to null.
- Do not format numbers unless a country code is entered.
- Fix editing network location.
- Fix ticket redaction on client replies.
- Remove more from user activity as it requires admin privledges.
- Fix MFA Enforcement page.

## [25.03.2]

### Fixed
- Revert DB.sql change

## [25.03.1]

### Fixed
- Phone number missing in various sections.
- Match Database.
- Client Export Only display licenses users and assets from the selected client only.

## [25.03]

### Fixed
- Resolved missing attachments in ticket replies processed via the email parser.
- Fixed issue where the top half of portrait image uploads appeared cut off at the bottom.
- Ensured all tables and fields use `CHARACTER SET utf8mb4` and `COLLATE utf8mb4_general_ci` for updates and new installations.
- Converted `service_domains` table to use InnoDB instead of MyISAM.
- Fixed the initials function to properly handle UTF-8 characters, preventing contact-related issues.
- Interfaces can now start with `0`.
- Adjusted AI prompt handling to focus solely on content, avoiding unnecessary additions.

### Added / Changed
- Introduced bulk delete functionality for assets.
- Added the ability to redact ticket replies after a ticket is closed.
- Added support for redacting specific text while a ticket is open.
- Switched file upload hashing from SHA256 to MD5 to significantly improve performance.
- Enabled assigning multiple assets to a single ticket.
- Updated all many-to-many tables to support cascading deletes using foreign key associations, improving efficiency, performance, and data integrity.
- Enabled caching for AJAX modals to reduce repeated reloads and enhance browser performance.
- Upgraded DataTables from 2.2.1 to 2.2.2.
- Upgraded TinyMCE from 7.6.1 to 7.7.1, providing a significant performance boost.
- Added “Copy Credentials to Clipboard” button in AJAX asset and contact views.
- Renamed and reorganized several tables.
- Improved theme color organization by grouping primary colors and their related shades.
- Displayed a user icon next to contacts who have user accounts.
- New image uploads are now converted to optimized `.webp` format by default; original files are no longer saved. Existing images remain unchanged.
- Added international phone number support throughout the system.
- Introduced user signatures in preferences, which are now appended to all ticket replies.
- Optimized search filters to only display defined tags.
- Added “Projects” to the client-side navigation.
- Enabled “Create New Ticket” from within project details.
- Reintroduced batch payment functionality in client invoices.
- Included client abbreviations in both client and global search options.
- Added assigned software license details (User/Asset) to the client PDF export.
- Replaced client-side `pdfMake` with the PHP-based `TCPDF` library for generating client export runbooks.
- Introduced the ability to download documents as PDFs.
- Added a “Reference” field to tickets and invoices generated from recurring templates (not yet in active use).

### Breaking Changes
> **Important:** To update to this version, you **must** run the following commands from the command line from the scripts directory:
>
> ```bash
> php update_cli.php
> php update_cli.php --db_update
> ```
>
> Repeat `--db_update` until no further updates are found.
>
> **Back up your system before upgrading.**  
> This version includes numerous backend changes critical for future development.

## [25.02.4]

### Fixed
- Resolved issue preventing the addition or editing of licenses when no vendor was selected.
- Fixed several undeclared variables in AJAX contact details.
- Corrected the contact ticket count display.
- Addressed an issue where clicking "More Details" in AJAX contact/asset details failed to include the `client_id` in the URL.
- Fixed an issue with recurring invoices in the client URL: clicking "Inactive" or "Active" would unexpectedly navigate away from the client section.
- Added new php function getFieldById() to return a record using just an id and sanitized as well.

## [25.02.3]

### Fixed
- Fixed notifications being reversed as dismissed notifications.

## [25.02.2]

### Fixed
- Corrected some edit modals not showing notes correctly.
- Bugfix: When exporting to CSV, the first asset wasn't being shown.
- Fix broken create / edit credentials.
- Fixed missing Notificatons link.
- Fixed a few dead links.
- Fixed Overdue count also counting Non-Billable Invoices.
- Fix Edit Client Notes.

### Added / Changed
- Implemented SSL certificate history tracking.
- Added Inactive / Active Filter to Recurring Invoices.
- Merged Dismissed notifications and notification in one.
- Added Link Button to addd / edit Document WYSIWYG.
- Added Physical location to the asset export / import.

## [25.02.1]
### Fixed
- Resolved broken links in the client overview, project and client listings, and rack details.
- Corrected asset transfer functionality to clients.
- Fixed the ticket scheduling redirect.
- Corrected the ticket link in the Scheduled Ticket Agent Notification email.
- Addressed issues with credentials and ticket actions in the Contact Detail Modal.
- Fixed text wrapping in notifications.
- Adjusted notifications so that they are sorted with the newest first.
- Fixed drag-and-drop functionality for tickets in the Kanban view on mobile devices.
- Resolved a weird issue with TinyMCE that prevented using links referencing your ITFlow instance url.
- Corrected image orientation issues during upload and the preview optimization process.

### Added / Changed
- Introduced entity link indicator icons and counts in the contacts and credentials section.
- Implemented a fade animation for the new AJAX modal.
- Removed the Client Overview Expire Day Select and replaced it with simplified 1, 7, or 45-day options.
- Added the ability to link and unlink entities within asset details.
- Introduced quick tag/category creation across the app.
- Added a Vendor Quick Details Modal.
- Enabled vendor linking and added a License Purchase Reference in the Software Licenses section.
- Added download original, optimized and thumbnail option for images.
- Added Paid status to the top corner of Invoice PDFs.

## [25.02]
### Fixed
- Migrated several reports to the new permissions/roles system.
- Resolved issue with empty task box showing for closed/resolved tickets.
- Corrected ticket priority sorting.
- Cloned asset interfaces when transferring assets between clients.

### Added / Changed
- Restored max number of records per page option back to 500 since we dont have repeating modals.
- Bulk Categorize Tickets feature.
- Renamed "Interface port" to "Interface Description." "Interface Name" should now refer to port name and/or number.
- Changed "Transfer Asset to Client" from a single action to a bulk action.
- Updated Filter Footer UI to show "Showing x to x of x records" instead of just the total records.
- Added Client Overview section to view client assets, contacts, licenses, credentials, etc.
- Introduced Quick Peek for asset details, contact information, and document viewing throughout the ITFlow App, all made possible by AJAX.
- Enabled Simple Drag-and-Drop Ordering for Invoices, Recurring Invoices, Quotes, Ticket Tasks, and Ticket Template Tasks.
- Added new Ticket View options: Kanban and Simple View.
- Migrated all repeating modals to the new AJAX modal function for faster loading times and quicker development.
- Allowed clients to upload PDF documents to accepted quotes.
- Client Portal now shows ticket category.
- Custom links can now be added to the Client Portal navbar.
- Lots of little tweaks to UI, performance, bugs, etc.

### Breaking Changes
- Cron scripts have officially been moved to the /scripts folder and are no longer in the root directory; they must be updated to function properly.

## [25.01.3]
### Fixed
- Fixed ticket assignment modal showing client contacts.

## [25.01.2]
### Fixed
- Fixed app version.

## [25.01.1]

### Added / Changed
- Redesigned the Multi-Factor Authentication (MFA) Setup and Enforcement Flow UI/UX for a more intuitive user experience.
- Added a "Member" column in the user roles listing for improved visibility.
- General UI/UX improvements, along with minor performance optimizations and cleanups.

### Fixed
- Fixed an issue where Stripe was not appearing as a recurring payment option.
- Corrected inaccurate Quarter 2 Expense results in the Profit & Loss Report.
- Resolved TOTP code not displaying correctly on hover in the Contact or Asset Details sections.
- Archived contacts no longer appear in the Bulk Mail section.
- Fixed an issue where the Ticket Assign Modal was showing both ITFlow and client users.
- Fixed issue with login key redirecting to legacy client portal page.

## [25.01]

### Added / Changed
- Added support for saving cards in Stripe for automatic invoice payments.
- Page titles now display detailed information (e.g., page name, client selection, company name, ticket and invoice info) for easier multi-tab navigation.
- Reintroduced the new admin role-check for admin pages.
- Admin roles can now be archived.
- Debug mode now shows the current Git branch.
- The auto-acknowledgment email for email-parsed tickets now includes a guest link.
- Recurring tickets no longer require a contact.
- Stripe online payment setup now prompts you to set the income/expense account.
- New cron/CLI scripts have been moved to the `/scripts` subfolder — remember to update your cron configurations!
- Moved modal includes to `/modals` to tidy up the root directory.
- Moved most include files to `/includes` to improve directory structure.
- Moved guest pages to `/guest` for better organization.
- Renamed the include file `pagination.php` to `filter_footer.php`, as it is used in conjunction with `filter_header.php` for page filtering.
- Guest ticket feedback now shows the ticket prefix and number, not just the ID.
- Individual POST handler logic pages are no longer directly accessible.
- Added the ability to delete payments on the Payments and Client Payments pages.
- Implemented domain history tracking.
- Added Asset Interface Linking/Connections to show what interface is connected to which interface port of another asset.
- Added Force Recurring Ticket option in more locations, not just for recurring tickets.
- Implemented row spanning and centered devices that occupy multiple units in a rack.
- Added tooltips to main navigation badge counts to clarify what is being counted.
- Reduced max records per page from 500 to 100 to prevent performance issues.
- Updated several plugins:
  - `stripe-php` from 10.5.0 to 16.4.0
  - `Inputmask` from 5.0.8 to 5.0.9
  - `DataTables` from 2.1.8 to 2.2.1
  - `pdfmake` from 0.2.8 to 0.2.18
  - `php-mime-mail-parser` to 9.0.1
  - `TinyMCE` from 7.5.1 to 7.6.1
- Removed unused libraries from the vendor folder and moved Stripe to the plugins folder, eliminating the vendor folder.
- Merged the MFA TOTP functionality files `base32static.php` and `rfc6238.php` into a single file (`totp`) and moved it to the plugins folder.
- No longer need to pass the DB connection (`$mysqli`) to the `addToMailQueue` function.
- Disabled HTML Purifier caching.
- Replaced the `nullable_htmlentities` function with `htmlspecialchars`.
- Updated filter variable naming.
- Implemented other minor UI updates, performance optimizations, and directory cleanups.

### Fixed
- Fixed an issue where the ticket edit modal didn't show multi-client or no-client projects.
- Fixed asset interface losing DHCP settings.
- Fixed a 500 error when creating or editing recurring expenses due to an incorrect variable name.
- Fixed tickets created via the portal/email not being marked as billable.
- Fixed issues with editing recurring expenses.
- Resolved a regression where the TinyMCE editor didn’t display when adding or editing ticket templates.
- Fixed a TinyMCE license issue.

### Removed / Deprecated
- Deprecated the cron scripts in the root directory. Cron jobs should now use the ones in the `/scripts` subfolder, which no longer require a cron key and must be run via CLI.

### BREAKING CHANGES
- The client portal has been moved from `/portal` to `/client`:
  - Links in previous emails will be broken.
  - The Azure Entra ID SSO Redirect URI needs to be updated to `/client`.
  - You may need to update other links (e.g., website, support page).
- Guest links have been moved from `/` to `/guest`. Previous links will be broken.

## [24.12]

### Added / Changed
- Introduced versioned releases for the first time!
