# Changelog

This file documents all notable changes made to ITFlow.

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
