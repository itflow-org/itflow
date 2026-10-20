<?php

/*
 * Admin > Settings - every settings page, every setting on them, and the
 * frame the pages are drawn in.
 *
 * settingsPages() is the list of pages: the Settings menu, the overview
 * (admin/settings.php) and each page's header come from it. settingsFields()
 * is every setting on them - its label, page and what else it might be
 * searched for by - which Find a setting searches, and which the audit log
 * names a change by. A page draws its fields with the settings*() helpers
 * below, so a field has its label, its place to say what is wrong, and an
 * anchor (#setting=<name>, js/settings.js) the search can take you to.
 *
 * Saving: a handler checks what it was sent; with something wrong it calls
 * settingsRefuse(), which sends the page back with what was typed and what is
 * wrong beside each field. All right, settingsSave() writes only what changed
 * and logs it - "Next number 4021 -> 4100" - and says so.
 */

/* The groups the pages are shown in, in order. */
function settingsGroups() {
    return [
        'company' => 'Your company',
        'work' => 'Work',
        'email' => 'Email and notifications',
        'access' => 'Sign-in and access',
        'system' => 'System',
    ];
}

/*
 * Every settings page - its group, title, icon, address, what it is for, and
 * the module it belongs to (hidden from the menu while that is off).
 */
function settingsPages() {
    return [
        'company' => ['group' => 'company', 'title' => 'Company details', 'icon' => 'fa-briefcase', 'url' => 'settings_company.php',
            'about' => 'Your company as it shows on invoices, quotes and emails - its name, address, contact details and logo.'],
        'localization' => ['group' => 'company', 'title' => 'Localization', 'icon' => 'fa-globe', 'url' => 'settings_localization.php',
            'about' => 'The language dates and numbers are written in, the currency you bill in, and your timezone.'],
        'theme' => ['group' => 'company', 'title' => 'Theme', 'icon' => 'fa-paint-brush', 'url' => 'settings_theme.php',
            'about' => 'The colour of the top bar, links, buttons and highlights, and the icon on the browser tab.'],
        'default' => ['group' => 'work', 'title' => 'Defaults', 'icon' => 'fa-cogs', 'url' => 'settings_default.php',
            'about' => 'Where ITFlow opens, and what new payments, expenses, transfers and clients start with.'],
        'ticket' => ['group' => 'work', 'title' => 'Tickets', 'icon' => 'fa-life-ring', 'url' => 'settings_ticket.php', 'module' => 'ticketing',
            'about' => 'How tickets are numbered, made from email, timed, billed and closed.'],
        'project' => ['group' => 'work', 'title' => 'Projects', 'icon' => 'fa-project-diagram', 'url' => 'settings_project.php', 'module' => 'ticketing',
            'about' => 'How projects are numbered.'],
        'quote' => ['group' => 'work', 'title' => 'Quotes', 'icon' => 'fa-comment-dollar', 'url' => 'settings_quote.php', 'module' => 'accounting',
            'about' => 'How quotes are numbered, what goes at the foot of them, and who hears when one is answered.'],
        'invoice' => ['group' => 'work', 'title' => 'Invoices and billing', 'icon' => 'fa-file-invoice', 'url' => 'settings_invoice.php', 'module' => 'accounting',
            'about' => 'Invoice numbering, late fees, reminders and statements, and how time, expenses and trips are billed.'],
        'mail' => ['group' => 'email', 'title' => 'Mail', 'icon' => 'fa-envelope', 'url' => 'settings_mail.php',
            'about' => 'The mailboxes ITFlow sends from and reads tickets from, the names emails come from, and tests.'],
        'notification' => ['group' => 'email', 'title' => 'Notifications', 'icon' => 'fa-bell', 'url' => 'settings_notification.php',
            'about' => 'Which alerts ITFlow raises, and which emails clients get.'],
        'security' => ['group' => 'access', 'title' => 'Security', 'icon' => 'fa-shield-alt', 'url' => 'settings_security.php',
            'about' => 'The sign-in page, two-step sign-in, failed sign-ins and shared links.'],
        'client_portal' => ['group' => 'access', 'title' => 'Client portal', 'icon' => 'fa-user-shield', 'url' => 'settings_client_portal.php', 'module' => 'portal',
            'about' => 'How your clients\' contacts sign in to the portal, and the announcements they see there.'],
        'identity' => ['group' => 'access', 'title' => 'Single sign-on', 'icon' => 'fa-fingerprint', 'url' => 'identity_providers.php', 'module' => 'portal',
            'about' => 'Contacts signing in to the client portal with their Microsoft 365 account.'],
        'module' => ['group' => 'system', 'title' => 'Modules', 'icon' => 'fa-cube', 'url' => 'settings_module.php',
            'about' => 'The parts of ITFlow in use - IT documentation, ticketing, invoicing and the client portal - and white-labelling.'],
        'logs' => ['group' => 'system', 'title' => 'Logs', 'icon' => 'fa-scroll', 'url' => 'settings_logs.php',
            'about' => 'How long the audit log and app logs are kept, and sending them on to a syslog server or SIEM.'],
        'telemetry' => ['group' => 'system', 'title' => 'Telemetry', 'icon' => 'fa-satellite-dish', 'url' => 'settings_telemetry.php',
            'about' => 'Whether ITFlow tells the ITFlow project, each night, how it is being used.'],
    ];
}

/* Whether a page's module is in use - a page for one that is off is hidden from the menu, and shown faded on the overview. */
function settingsPageAvailable(array $page) {
    global $config_module_enable_ticketing, $config_module_enable_accounting, $config_client_portal_enable;
    switch ($page['module'] ?? '') {
        case 'ticketing':
            return !empty($config_module_enable_ticketing);
        case 'accounting':
            return !empty($config_module_enable_accounting);
        case 'portal':
            return !empty($config_client_portal_enable);
    }
    return true;
}

/* The theme colours there are - each has its .theme-<name> block in css/itflow_custom.css. */
function settingsThemes() {
    return ['lightblue' => 'Light blue', 'blue' => 'Blue', 'navy' => 'Navy', 'indigo' => 'Indigo', 'purple' => 'Purple', 'fuchsia' => 'Fuchsia', 'pink' => 'Pink',
        'maroon' => 'Maroon', 'red' => 'Red', 'orange' => 'Orange', 'yellow' => 'Yellow', 'olive' => 'Olive', 'green' => 'Green', 'teal' => 'Teal', 'cyan' => 'Cyan',
        'gray' => 'Gray', 'black' => 'Black'];
}

/*
 * Every setting - by the name its field is sent as: its page, label, the
 * section it is in, what else it might be looked for by, and its kind -
 * switch (on/off), select (with its options), secret (never written to the
 * log), long (logged as changed, not by value), client, file, or text.
 * A field on a tab of its page names the tab.
 */
function settingsFields() {
    static $fields = null;
    if ($fields !== null) {
        return $fields;
    }
    $on_off = ['kind' => 'switch'];
    $fields = [
        // Company details
        'company_name' => ['page' => 'company', 'section' => 'Your company', 'label' => 'Company name', 'words' => 'business organisation organization'],
        'company_tax_id' => ['page' => 'company', 'section' => 'Your company', 'label' => 'Tax ID', 'words' => 'vat ein abn gst tax number'],
        'company_address' => ['page' => 'company', 'section' => 'Address', 'label' => 'Street address', 'words' => 'address street'],
        'company_city' => ['page' => 'company', 'section' => 'Address', 'label' => 'City', 'words' => 'town'],
        'company_state' => ['page' => 'company', 'section' => 'Address', 'label' => 'State / province', 'words' => 'state province county region'],
        'company_zip' => ['page' => 'company', 'section' => 'Address', 'label' => 'Postal code', 'words' => 'zip postcode'],
        'company_country' => ['page' => 'company', 'section' => 'Address', 'label' => 'Country', 'words' => 'nation'],
        'company_phone_country_code' => ['page' => 'company', 'section' => 'Contact', 'label' => 'Phone country code', 'search' => false],
        'company_phone' => ['page' => 'company', 'section' => 'Contact', 'label' => 'Phone', 'words' => 'telephone number call'],
        'company_email' => ['page' => 'company', 'section' => 'Contact', 'label' => 'Email', 'words' => 'email address contact'],
        'company_website' => ['page' => 'company', 'section' => 'Contact', 'label' => 'Website', 'words' => 'web site url domain'],
        'company_logo' => ['page' => 'company', 'section' => 'Logo', 'label' => 'Logo', 'kind' => 'file', 'words' => 'logo image picture branding invoice'],
        'config_internal_client_id' => ['page' => 'company', 'section' => 'Your own client record', 'label' => 'Your company\'s client record', 'kind' => 'client',
            'words' => 'internal client own company msp internal tickets'],

        // Localization
        'company_locale' => ['page' => 'localization', 'section' => 'Language and currency', 'label' => 'Language', 'kind' => 'select', 'options' => 'locales',
            'words' => 'locale language number date format'],
        'company_currency' => ['page' => 'localization', 'section' => 'Language and currency', 'label' => 'Currency', 'kind' => 'select', 'options' => 'currencies',
            'words' => 'currency money usd eur gbp'],
        'config_timezone' => ['page' => 'localization', 'section' => 'Timezone', 'label' => 'Timezone', 'words' => 'time zone clock utc'],

        // Theme
        'config_theme' => ['page' => 'theme', 'section' => 'Colour', 'label' => 'Theme colour', 'kind' => 'select', 'options' => 'themes',
            'words' => 'theme color colour accent top bar branding'],
        'favicon' => ['page' => 'theme', 'section' => 'Browser tab icon', 'label' => 'Browser tab icon', 'kind' => 'file', 'words' => 'favicon ico icon tab'],

        // Defaults
        'config_start_page' => ['page' => 'default', 'section' => 'Opening ITFlow', 'label' => 'Start page', 'kind' => 'select', 'options' => 'start_pages',
            'words' => 'home dashboard landing start page'],
        'config_default_calendar' => ['page' => 'default', 'section' => 'Opening ITFlow', 'label' => 'Calendar', 'kind' => 'calendar', 'words' => 'default calendar events'],
        'config_default_net_terms' => ['page' => 'default', 'section' => 'New clients', 'label' => 'Net terms', 'kind' => 'select', 'options' => 'net_terms',
            'words' => 'payment terms due days net 30'],
        'config_default_hourly_rate' => ['page' => 'default', 'section' => 'New clients', 'label' => 'Hourly rate', 'words' => 'rate per hour labour labor billing'],
        'config_default_payment_account' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Payments go into', 'kind' => 'account', 'words' => 'payment account bank deposit'],
        'config_default_payment_method' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Payment method', 'words' => 'payment method check card cash'],
        'config_default_expense_account' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Expenses come out of', 'kind' => 'account', 'words' => 'expense account'],
        'config_default_expense_payment_method' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Expense payment method', 'words' => 'expense payment method'],
        'config_default_transfer_from_account' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Transfers from', 'kind' => 'account', 'words' => 'transfer account'],
        'config_default_transfer_to_account' => ['page' => 'default', 'section' => 'Accounts', 'label' => 'Transfers to', 'kind' => 'account', 'words' => 'transfer account'],

        // Tickets
        'config_ticket_prefix' => ['page' => 'ticket', 'section' => 'Numbering', 'label' => 'Ticket prefix', 'words' => 'ticket number tck prefix'],
        'config_ticket_next_number' => ['page' => 'ticket', 'section' => 'Numbering', 'label' => 'Next ticket number', 'words' => 'ticket number counter'],
        'config_ticket_email_parse' => ['page' => 'ticket', 'section' => 'Email to ticket', 'label' => 'Turn emails into tickets', 'words' => 'email to ticket parser parse inbound imap mailbox'] + $on_off,
        'config_ticket_email_parse_unknown_senders' => ['page' => 'ticket', 'section' => 'Email to ticket', 'label' => 'From unknown senders too', 'words' => 'unknown senders domains anyone email'] + $on_off,
        'config_ticket_default_billable' => ['page' => 'ticket', 'section' => 'Time and billing', 'label' => 'New tickets are billable', 'words' => 'billable default bill'] + $on_off,
        'config_ticket_timer_autostart' => ['page' => 'ticket', 'section' => 'Time and billing', 'label' => 'Start the timer on opening a ticket', 'words' => 'timer autostart time tracking'] + $on_off,
        'config_ticket_autoclose_hours' => ['page' => 'ticket', 'section' => 'Closing', 'label' => 'Close resolved tickets after', 'words' => 'auto close autoclose resolved hours'],
        'config_ticket_new_ticket_notification_email' => ['page' => 'ticket', 'section' => 'New tickets', 'label' => 'Email new tickets to', 'words' => 'new ticket notification email distribution list alert'],
        'config_ticket_default_view' => ['page' => 'ticket', 'section' => 'The tickets page', 'label' => 'Tickets open as', 'kind' => 'select', 'options' => [0 => 'A list', 1 => 'A compact list', 2 => 'A board (kanban)'],
            'words' => 'default view list compact kanban board'],
        'config_ticket_ordering' => ['page' => 'ticket', 'section' => 'The tickets page', 'label' => 'Order tickets by hand on the board', 'words' => 'kanban ordering drag order'] + $on_off,
        'config_ticket_moving_columns' => ['page' => 'ticket', 'section' => 'The tickets page', 'label' => 'Rearrange the board\'s columns', 'words' => 'kanban columns move'] + $on_off,

        // Projects
        'config_project_prefix' => ['page' => 'project', 'section' => 'Numbering', 'label' => 'Project prefix', 'words' => 'project number prj prefix'],
        'config_project_next_number' => ['page' => 'project', 'section' => 'Numbering', 'label' => 'Next project number', 'words' => 'project number counter'],

        // Quotes
        'config_quote_prefix' => ['page' => 'quote', 'section' => 'Numbering', 'label' => 'Quote prefix', 'words' => 'quote number quo prefix estimate'],
        'config_quote_next_number' => ['page' => 'quote', 'section' => 'Numbering', 'label' => 'Next quote number', 'words' => 'quote number counter'],
        'config_quote_footer' => ['page' => 'quote', 'section' => 'Footer', 'label' => 'Quote footer', 'kind' => 'long', 'words' => 'quote footer terms text'],
        'config_quote_notification_email' => ['page' => 'quote', 'section' => 'Answers', 'label' => 'Email quote answers to', 'words' => 'accepted declined notification email'],

        // Invoices and billing
        'config_invoice_prefix' => ['page' => 'invoice', 'section' => 'Numbering', 'label' => 'Invoice prefix', 'words' => 'invoice number inv prefix'],
        'config_invoice_next_number' => ['page' => 'invoice', 'section' => 'Numbering', 'label' => 'Next invoice number', 'words' => 'invoice number counter'],
        'config_recurring_invoice_prefix' => ['page' => 'invoice', 'section' => 'Numbering', 'label' => 'Recurring invoice prefix', 'words' => 'recurring invoice number rec prefix'],
        'config_recurring_invoice_next_number' => ['page' => 'invoice', 'section' => 'Numbering', 'label' => 'Next recurring invoice number', 'words' => 'recurring invoice number'],
        'config_invoice_footer' => ['page' => 'invoice', 'section' => 'On the invoice', 'label' => 'Invoice footer', 'kind' => 'long', 'words' => 'invoice footer terms bank details text'],
        'config_invoice_show_tax_id' => ['page' => 'invoice', 'section' => 'On the invoice', 'label' => 'Show your tax ID', 'words' => 'tax id vat number on invoice'] + $on_off,
        'config_invoice_late_fee_enable' => ['page' => 'invoice', 'section' => 'Late fees', 'label' => 'Late fees', 'words' => 'late fee overdue charge penalty'] + $on_off,
        'config_invoice_late_fee_percent' => ['page' => 'invoice', 'section' => 'Late fees', 'label' => 'Late fee %', 'words' => 'late fee percent'],
        'config_invoice_paid_notification_email' => ['page' => 'invoice', 'section' => 'Paid online', 'label' => 'Email invoices paid online to', 'words' => 'paid notification email stripe payment'],
        'config_send_invoice_reminders' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Email payment reminders', 'words' => 'overdue reminder dunning past due'] + $on_off,
        'reminder_before' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Remind before it is due', 'words' => 'reminder days before due'],
        'reminder_after' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Remind after it is due', 'words' => 'reminder days late overdue'],
        'reminder_repeat' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Then remind every', 'words' => 'reminder repeat'],
        'config_invoice_reminder_days' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Reminder days', 'search' => false],
        'config_invoice_reminder_repeat' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Reminders repeat every (days)', 'search' => false],
        'config_statement_monthly' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Monthly statements', 'words' => 'statement balance monthly'] + $on_off,
        'config_statement_day' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Statement day', 'words' => 'statement day of the month'],
        'config_billing_email_attach_pdf' => ['page' => 'invoice', 'section' => 'Reminders and statements', 'label' => 'Attach the PDF to billing emails', 'words' => 'pdf attachment attach'] + $on_off,
        'config_time_increment' => ['page' => 'invoice', 'section' => 'Billing time', 'label' => 'Round time to', 'kind' => 'select',
            'options' => [6 => '6 minutes', 12 => '12 minutes', 15 => '15 minutes', 30 => '30 minutes', 60 => 'an hour'], 'words' => 'time rounding increment minutes billable time'],
        'config_time_rounding' => ['page' => 'invoice', 'section' => 'Billing time', 'label' => 'Rounding', 'kind' => 'select', 'options' => ['nearest' => 'to the nearest', 'up' => 'always up'],
            'words' => 'round up nearest'],
        'config_time_minimum' => ['page' => 'invoice', 'section' => 'Billing time', 'label' => 'Least a ticket bills', 'kind' => 'select',
            'options' => [0 => 'no minimum', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours'], 'words' => 'minimum time billable'],
        'config_time_item_name' => ['page' => 'invoice', 'section' => 'Billing time', 'label' => 'Invoice line for time', 'words' => 'support hourly line item name'],
        'config_expense_markup' => ['page' => 'invoice', 'section' => 'Billable expenses', 'label' => 'Expense markup', 'words' => 'markup billable expenses percent'],
        'config_mileage_unit' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Distance in', 'kind' => 'select', 'options' => ['mi' => 'miles', 'km' => 'kilometers'],
            'words' => 'miles kilometres km mileage unit'],
        'config_mileage_rate' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Paid back a mile', 'words' => 'mileage reimbursement rate irs'],
        'config_mileage_category_id' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Mileage goes under', 'kind' => 'category', 'words' => 'mileage category travel expense'],
        'config_trip_charge' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Trip charge', 'words' => 'trip charge travel fee call out'],
        'config_trip_bill_rate' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Trips billed a mile', 'words' => 'trip billing rate mile'],
        'config_trip_included' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'The trip charge covers the first', 'words' => 'trip included distance'],
        'config_trip_item_name' => ['page' => 'invoice', 'section' => 'Trips and mileage', 'label' => 'Invoice line for trips', 'words' => 'trip travel line item name'],

        // Mail
        'config_smtp_provider' => ['page' => 'mail', 'tab' => 'sending', 'section' => 'Sending', 'label' => 'Sending provider', 'words' => 'smtp outbound send email server provider'],
        'config_smtp_host' => ['page' => 'mail', 'tab' => 'sending', 'section' => 'Sending', 'label' => 'SMTP host', 'words' => 'smtp server host'],
        'config_smtp_port' => ['page' => 'mail', 'tab' => 'sending', 'section' => 'Sending', 'label' => 'SMTP port', 'words' => 'smtp port 587 465 25'],
        'config_smtp_username' => ['page' => 'mail', 'tab' => 'sending', 'section' => 'Sending', 'label' => 'SMTP username', 'words' => 'smtp user login'],
        'config_imap_provider' => ['page' => 'mail', 'tab' => 'receiving', 'section' => 'Receiving', 'label' => 'Receiving provider', 'words' => 'imap inbound receive mailbox email to ticket'],
        'config_imap_host' => ['page' => 'mail', 'tab' => 'receiving', 'section' => 'Receiving', 'label' => 'IMAP host', 'words' => 'imap server host'],
        'config_mail_oauth_client_id' => ['page' => 'mail', 'tab' => 'oauth', 'section' => 'OAuth', 'label' => 'OAuth client ID', 'words' => 'oauth microsoft 365 google workspace app'],
        'config_mail_from_email' => ['page' => 'mail', 'tab' => 'from', 'section' => 'From addresses', 'label' => 'System emails come from', 'words' => 'from address sender noreply'],
        'config_invoice_from_email' => ['page' => 'mail', 'tab' => 'from', 'section' => 'From addresses', 'label' => 'Invoices come from', 'words' => 'billing from address sender'],
        'config_quote_from_email' => ['page' => 'mail', 'tab' => 'from', 'section' => 'From addresses', 'label' => 'Quotes come from', 'words' => 'sales from address sender'],
        'config_ticket_from_email' => ['page' => 'mail', 'tab' => 'from', 'section' => 'From addresses', 'label' => 'Ticket emails come from', 'words' => 'support from address sender'],
        'test_email' => ['page' => 'mail', 'tab' => 'tests', 'section' => 'Tests', 'label' => 'Send a test email', 'words' => 'test email check smtp working'],

        // Notifications
        'config_enable_alert_domain_expire' => ['page' => 'notification', 'section' => 'Alerts for your team', 'label' => 'Domains about to expire', 'words' => 'domain expiry expiration alert'] + $on_off,
        'config_recurring_auto_send_invoice' => ['page' => 'notification', 'section' => 'Emails to clients', 'label' => 'Email the invoices recurring invoices make', 'words' => 'recurring invoice auto send email'] + $on_off,
        'config_ticket_client_general_notifications' => ['page' => 'notification', 'section' => 'Emails to clients', 'label' => 'Email clients about their tickets', 'words' => 'ticket notification client email opened closed'] + $on_off,

        // Security
        'config_login_message' => ['page' => 'security', 'section' => 'The sign-in page', 'label' => 'Sign-in page message', 'kind' => 'long', 'words' => 'login message banner notice'],
        'config_login_key_required' => ['page' => 'security', 'section' => 'The sign-in page', 'label' => 'Require a login key', 'words' => 'login key secret url hide sign in'] + $on_off,
        'config_login_key_secret' => ['page' => 'security', 'section' => 'The sign-in page', 'label' => 'Login key', 'quiet' => true, 'words' => 'login key secret url address'],
        'config_login_remember_me_expire' => ['page' => 'security', 'section' => 'Two-step sign-in', 'label' => 'Remember a browser for', 'words' => '2fa mfa remember me days trusted device'],
        'config_login_alert_failures' => ['page' => 'security', 'section' => 'Failed sign-ins', 'label' => 'Tell admins', 'kind' => 'select', 'options' => 'sign_in_alerts', 'words' => 'failed login brute force lockout alert'],
        'config_share_max_hours' => ['page' => 'security', 'section' => 'Shared links', 'label' => 'Longest a shared link can last', 'kind' => 'select', 'options' => 'share_max', 'words' => 'share link expiry guest'],
        'config_share_require_protection' => ['page' => 'security', 'section' => 'Shared links', 'label' => 'Shared passwords need a passphrase or code', 'words' => 'share passphrase code protect'] + $on_off,

        // Client portal
        'config_client_portal_require_mfa' => ['page' => 'client_portal', 'section' => 'Signing in', 'label' => 'Require two-step sign-in', 'words' => 'portal mfa 2fa two factor contacts'] + $on_off,

        // Single sign-on
        'config_azure_client_id' => ['page' => 'identity', 'section' => 'Microsoft Entra', 'label' => 'Application (client) ID', 'words' => 'sso entra azure microsoft 365 single sign-on portal'],
        'config_azure_client_secret' => ['page' => 'identity', 'section' => 'Microsoft Entra', 'label' => 'Client secret', 'kind' => 'secret', 'words' => 'sso entra azure secret'],

        // Modules
        'config_module_enable_itdoc' => ['page' => 'module', 'section' => 'Modules', 'label' => 'IT documentation', 'words' => 'module itdoc documentation assets credentials'] + $on_off,
        'config_module_enable_ticketing' => ['page' => 'module', 'section' => 'Modules', 'label' => 'Ticketing', 'words' => 'module tickets support'] + $on_off,
        'config_module_enable_accounting' => ['page' => 'module', 'section' => 'Modules', 'label' => 'Invoicing and accounting', 'words' => 'module accounting invoices billing financial'] + $on_off,
        'config_client_portal_enable' => ['page' => 'module', 'section' => 'Modules', 'label' => 'Client portal', 'words' => 'module portal clients'] + $on_off,
        'config_whitelabel_key' => ['page' => 'module', 'section' => 'White-label', 'label' => 'White-label key', 'kind' => 'secret', 'words' => 'whitelabel white label powered by banner'],
        'config_whitelabel_enabled' => ['page' => 'module', 'section' => 'White-label', 'label' => 'White-label', 'search' => false] + $on_off,

        // Logs
        'config_log_retention' => ['page' => 'logs', 'section' => 'Keeping logs', 'label' => 'Keep the audit log for', 'words' => 'audit log retention days keep'],
        'config_app_log_retention' => ['page' => 'logs', 'section' => 'Keeping logs', 'label' => 'Keep the app logs for', 'words' => 'app log retention days'],
        'config_log_forward_enabled' => ['page' => 'logs', 'section' => 'Sending logs on', 'label' => 'Send log entries to a syslog server', 'words' => 'syslog siem graylog splunk wazuh sentinel forward'] + $on_off,
        'config_log_forward_host' => ['page' => 'logs', 'section' => 'Sending logs on', 'label' => 'Syslog server', 'words' => 'syslog host'],

        // Telemetry
        'config_telemetry' => ['page' => 'telemetry', 'section' => 'Nightly report', 'label' => 'Send the ITFlow project a nightly report', 'words' => 'telemetry statistics usage data privacy'] + $on_off,
    ];
    return $fields;
}

/* What Find a setting searches - every page and setting, with where it is. */
function settingsSearchIndex() {
    $pages = settingsPages();
    $index = [];
    foreach ($pages as $key => $page) {
        $index[] = ['label' => $page['title'], 'where' => 'Settings', 'url' => $page['url'], 'icon' => $page['icon'], 'words' => strtolower($page['about']), 'page' => true];
    }
    foreach (settingsFields() as $name => $field) {
        if (($field['search'] ?? true) === false || !isset($pages[$field['page']])) {
            continue;
        }
        $page = $pages[$field['page']];
        $url = $page['url'] . (isset($field['tab']) ? '?tab=' . $field['tab'] : '') . '#setting=' . $name;
        $index[] = ['label' => $field['label'], 'where' => $page['title'] . ' › ' . $field['section'], 'url' => $url, 'icon' => $page['icon'], 'words' => strtolower($field['words'] ?? '')];
    }
    return $index;
}

/*
 * The settings that match what was typed - for the search window in the top
 * bar (agent/ajax.php?global_search), admins only. Scored as Find a setting
 * scores them (js/settings.js): every word has to be in the name, where it
 * is, or what else it is searched by; a name starting with it first. Pages
 * for a module that is off are left out. The best $limit, as title,
 * subtitle (where it is) and url.
 */
function settingsSearch($raw_query, $limit = 3) {
    $terms = preg_split('/\s+/', mb_strtolower(trim((string) $raw_query)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$terms || mb_strlen(implode(' ', $terms)) < 2) {
        return [];
    }
    $pages = settingsPages();
    $off = [];
    foreach ($pages as $key => $page) {
        if (!settingsPageAvailable($page)) {
            $off[$page['url']] = true;
        }
    }
    $scored = [];
    foreach (settingsSearchIndex() as $order => $entry) {
        if (isset($off[strtok($entry['url'], '?#')])) {
            continue;
        }
        $label = mb_strtolower($entry['label']);
        $where = mb_strtolower($entry['where']);
        $total = 0;
        foreach ($terms as $term) {
            if (strpos($label, $term) === 0) {
                $total += 6;
            } elseif (strpos($label, ' ' . $term) !== false) {
                $total += 5;
            } elseif (strpos($label, $term) !== false) {
                $total += 4;
            } elseif (strpos($where, $term) !== false) {
                $total += 2;
            } elseif (strpos($entry['words'], $term) !== false) {
                $total += 1;
            } else {
                $total = 0;
                break;
            }
        }
        if ($total > 0) {
            $scored[] = [$total + (!empty($entry['page']) ? 0.5 : 0), $order, $entry];
        }
    }
    usort($scored, function ($a, $b) {
        return $b[0] <=> $a[0] ?: $a[1] <=> $b[1];
    });
    $items = [];
    foreach (array_slice($scored, 0, $limit) as [, , $entry]) {
        $items[] = ['title' => $entry['label'], 'subtitle' => $entry['where'] === 'Settings' ? 'Settings page' : 'Settings › ' . $entry['where'], 'url' => '/admin/' . $entry['url']];
    }
    return $items;
}

/*
 * ---------- Drawing a settings page ----------
 */

/*
 * The top of a settings page - back to the overview, its icon, title and what
 * it is for - and what a save that was refused sent back, for the fields.
 */
function settingsPageStart($page_key, $actions_html = '') {
    global $settings_kept;
    $page = settingsPages()[$page_key];
    $settings_kept = [];
    // Only straight after the refused save - not on coming back to the page later
    if (($_SESSION['settings_kept']['page'] ?? '') === $page_key && time() - ($_SESSION['settings_kept']['at'] ?? 0) < 300) {
        $settings_kept = $_SESSION['settings_kept'];
    }
    unset($_SESSION['settings_kept']);
    ob_start(); ?>
    <div class="itflow-settings-head" data-settings-page="<?= escapeHtml($page_key) ?>">
        <div class="itflow-settings-head-icon"><i class="fas fa-fw <?= escapeHtml($page['icon']) ?>"></i></div>
        <div class="flex-grow-1 min-w-0">
            <a href="settings.php" class="small text-body-secondary text-decoration-none"><i class="fas fa-sliders-h me-1"></i>Settings</a>
            <h1 class="h4 mb-1"><?= escapeHtml($page['title']) ?></h1>
            <p class="text-body-secondary mb-0"><?= escapeHtml($page['about']) ?></p>
        </div>
        <?php if ($actions_html !== '') { ?>
            <div class="flex-shrink-0"><?= $actions_html ?></div>
        <?php } ?>
    </div>
    <?php return ob_get_clean();
}

/* A section of a settings page - its title and what it is for beside its fields, on a wide screen. Closed by settingsSectionEnd(). */
function settingsSectionStart($id, $title, $about = '', $anchor = '') {
    ob_start(); ?>
    <section class="itflow-settings-section" id="<?= escapeHtml($anchor !== '' ? $anchor : "section-$id") ?>">
        <div class="row g-3 g-lg-4">
            <div class="col-lg-4 itflow-settings-section-about">
                <h2 class="h6 fw-semibold mb-1"><?= escapeHtml($title) ?></h2>
                <?php if ($about !== '') { ?>
                    <p class="small text-body-secondary mb-0"><?= $about ?></p>
                <?php } ?>
            </div>
            <div class="col-lg-8">
    <?php return ob_get_clean();
}

function settingsSectionEnd() {
    return "            </div>\n        </div>\n    </section>\n";
}

/* What a field shows - what was sent back with a refused save, or what is saved. */
function settingsValue($name, $saved) {
    global $settings_kept;
    if (!empty($settings_kept) && array_key_exists($name, $settings_kept['values'])) {
        return $settings_kept['values'][$name];
    }
    return $saved;
}

/* Whether a switch shows on - as it was sent back with a refused save, or as saved. */
function settingsChecked($name, $saved) {
    global $settings_kept;
    if (!empty($settings_kept)) {
        return !empty($settings_kept['values'][$name]);
    }
    return !empty($saved);
}

/* What is wrong with a field, from a refused save - '' when nothing. */
function settingsError($name) {
    global $settings_kept;
    return (string) ($settings_kept['errors'][$name] ?? '');
}

/* A field's label - from settingsFields(), unless given. */
function settingsLabel($name, $label = null) {
    return $label ?? (settingsFields()[$name]['label'] ?? $name);
}

/*
 * One field: its label, the control, what is wrong with it and what it is for.
 * $control is the input-group (or switch) already drawn.
 */
function settingsField($name, $label_html, $control, $help = '', $label_for = null) {
    $error = settingsError($name);
    ob_start(); ?>
    <div class="itflow-settings-field" data-setting="<?= escapeHtml($name) ?>" id="setting-<?= escapeHtml($name) ?>">
        <?php if ($label_html !== '') { ?>
            <label class="form-label" for="<?= escapeHtml($label_for ?? "settings_$name") ?>"><?= $label_html ?></label>
        <?php } ?>
        <?= $control ?>
        <?php if ($error !== '') { ?>
            <div class="invalid-feedback d-block" data-field-error="<?= escapeHtml($name) ?>"><?= escapeHtml($error) ?></div>
        <?php } ?>
        <?php if ($help !== '') { ?>
            <div class="form-text"><?= $help ?></div>
        <?php } ?>
    </div>
    <?php return ob_get_clean();
}

/* The attributes a field's options add to its control - required, maxlength, placeholder and the rest, by name. */
function settingsAttributes(array $options) {
    $html = '';
    foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'title', 'autocomplete', 'inputmode', 'accept', 'rows'] as $attribute) {
        if (isset($options[$attribute]) && $options[$attribute] !== '') {
            $html .= ' ' . $attribute . '="' . escapeHtml((string) $options[$attribute]) . '"';
        }
    }
    foreach (['required', 'disabled', 'readonly', 'autofocus'] as $flag) {
        if (!empty($options[$flag])) {
            $html .= " $flag";
        }
    }
    foreach ($options['data'] ?? [] as $key => $value) {
        $html .= ' data-' . escapeHtml($key) . '="' . escapeHtml((string) $value) . '"';
    }
    return $html;
}

/*
 * A text, email, number or other input - its icon in front, anything after
 * it (a unit) at the end. $options: icon, type, label, help, suffix, and the
 * attributes settingsAttributes() takes.
 */
function settingsInput($name, $saved, array $options = []) {
    $id = "settings_$name";
    $invalid = settingsError($name) !== '' ? ' is-invalid' : '';
    $value = settingsValue($name, $saved);
    $control = '<div class="input-group has-validation">'
        . '<span class="input-group-text"><i class="fa fa-fw ' . escapeHtml($options['icon'] ?? 'fa-pen') . '"></i></span>'
        . '<input type="' . escapeHtml($options['type'] ?? 'text') . '" class="form-control' . $invalid . (isset($options['class']) ? ' ' . escapeHtml($options['class']) : '') . '" id="' . escapeHtml($id) . '" name="' . escapeHtml($name) . '" value="' . escapeHtml((string) $value) . '"' . settingsAttributes($options) . '>'
        . (isset($options['suffix']) ? '<span class="input-group-text">' . $options['suffix'] . '</span>' : '')
        . '</div>';
    return settingsField($name, escapeHtml(settingsLabel($name, $options['label'] ?? null)) . (!empty($options['required']) ? ' <strong class="text-danger">*</strong>' : ''), $control, $options['help'] ?? '');
}

/* A textarea - no icon, as elsewhere. */
function settingsTextarea($name, $saved, array $options = []) {
    $id = "settings_$name";
    $invalid = settingsError($name) !== '' ? ' is-invalid' : '';
    $control = '<textarea class="form-control' . $invalid . '" id="' . escapeHtml($id) . '" name="' . escapeHtml($name) . '"' . settingsAttributes($options + ['rows' => 3]) . '>' . escapeHtml((string) settingsValue($name, $saved)) . '</textarea>';
    return settingsField($name, escapeHtml(settingsLabel($name, $options['label'] ?? null)), $control, $options['help'] ?? '');
}

/*
 * A select - $choices value => label, or value => [label, extra option
 * attributes html]. Searchable (.select2) when there are many. $options also
 * take append (html after it - a + button) and options_html (the options
 * ready made, as categoryOptionsHtml() makes them, in place of $choices).
 */
function settingsSelect($name, $saved, array $choices, array $options = []) {
    $id = "settings_$name";
    $invalid = settingsError($name) !== '' ? ' is-invalid' : '';
    $value = (string) settingsValue($name, $saved);
    $html = '';
    foreach ($choices as $choice_value => $choice) {
        [$choice_label, $choice_attributes] = is_array($choice) ? $choice : [$choice, ''];
        $html .= '<option value="' . escapeHtml((string) $choice_value) . '"' . ((string) $choice_value === $value ? ' selected' : '') . $choice_attributes . '>' . escapeHtml((string) $choice_label) . '</option>';
    }
    if (isset($options['options_html'])) {
        $html = $options['options_html'];
    }
    $select2 = ($options['search'] ?? count($choices) > 12) ? ' select2' : '';
    $control = '<div class="input-group has-validation">'
        . '<span class="input-group-text"><i class="fa fa-fw ' . escapeHtml($options['icon'] ?? 'fa-list') . '"></i></span>'
        . '<select class="form-select' . $select2 . $invalid . '" id="' . escapeHtml($id) . '" name="' . escapeHtml($name) . '"' . settingsAttributes($options) . '>' . $html . '</select>'
        . ($options['append'] ?? '')
        . '</div>';
    return settingsField($name, escapeHtml(settingsLabel($name, $options['label'] ?? null)) . (!empty($options['required']) ? ' <strong class="text-danger">*</strong>' : ''), $control, $options['help'] ?? '');
}

/* An on/off switch, its label beside it and what it does under it. $options: label, help, disabled, value (sent when on, 1), id. */
function settingsSwitch($name, $saved, array $options = []) {
    $id = $options['id'] ?? "settings_$name";
    $checked = settingsChecked($name, $saved);
    $control = '<div class="form-check form-switch mb-0">'
        . '<input type="checkbox" class="form-check-input" role="switch" id="' . escapeHtml($id) . '" name="' . escapeHtml($name) . '" value="' . escapeHtml((string) ($options['value'] ?? 1)) . '"' . ($checked ? ' checked' : '') . settingsAttributes($options) . '>'
        . '<label class="form-check-label" for="' . escapeHtml($id) . '">' . escapeHtml(settingsLabel($name, $options['label'] ?? null)) . '</label>'
        . '</div>';
    return settingsField($name, '', $control, $options['help'] ?? '');
}

/*
 * The bar at the foot of a settings form - Save, and Undo changes, which
 * js/settings.js shows with "Unsaved changes" once something is changed.
 */
function settingsSaveBar($button_name, $label = 'Save', $extra_html = '') {
    ob_start(); ?>
    <div class="itflow-settings-savebar" data-settings-savebar>
        <span class="itflow-settings-savebar-note text-body-secondary small me-auto" data-settings-dirty-note><i class="fas fa-circle text-warning me-2"></i>Unsaved changes</span>
        <?= $extra_html ?>
        <button type="reset" class="btn btn-link text-decoration-none" data-settings-undo>Undo changes</button>
        <button type="submit" name="<?= escapeHtml($button_name) ?>" class="btn btn-primary"><i class="fas fa-check me-2"></i><?= escapeHtml($label) ?></button>
    </div>
    <?php return ob_get_clean();
}

/*
 * ---------- Saving ----------
 */

/* The saved values of these columns - settings, or the company's row in companies. */
function settingsCurrent(array $columns, $table = 'settings') {
    global $mysqli;
    $table = $table === 'companies' ? 'companies' : 'settings';
    $list = implode(', ', array_map(function ($column) {
        return '`' . preg_replace('/[^a-z0-9_]/', '', $column) . '`';
    }, $columns));
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $list FROM $table WHERE company_id = 1")) ?: array_fill_keys($columns, null);
}

/*
 * Whether two values are the same setting - 30 and '30', 0.7 and '0.70' are,
 * NULL and 0, and text saved with Windows line ends (as a form sends them)
 * and without.
 */
function settingsSame($a, $b) {
    if ($a === null || $b === null) {
        // Nothing saved yet is the same as none picked - 0, or empty
        $a = (string) $a;
        $b = (string) $b;
        return $a === $b || (in_array($a, ['', '0'], true) && in_array($b, ['', '0'], true));
    }
    if (is_numeric($a) && is_numeric($b)) {
        return (float) $a === (float) $b;
    }
    return rtrim(str_replace("\r\n", "\n", (string) $a)) === rtrim(str_replace("\r\n", "\n", (string) $b));
}

/* A value, as the audit log says it - on/off, an option's name, a client's name. */
function settingsLogValue($name, $value) {
    global $mysqli;
    $field = settingsFields()[$name] ?? [];
    $kind = $field['kind'] ?? 'text';
    if ($kind === 'switch') {
        return intval($value) ? 'on' : 'off';
    }
    if ($kind === 'client') {
        if (!intval($value)) {
            return 'none';
        }
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = " . intval($value)));
        return $row ? $row['client_name'] : '#' . intval($value);
    }
    if ($kind === 'account' || $kind === 'calendar') {
        if (!intval($value)) {
            return 'none';
        }
        $row = mysqli_fetch_row(mysqli_query($mysqli, $kind === 'account'
            ? "SELECT account_name FROM accounts WHERE account_id = " . intval($value)
            : "SELECT calendar_name FROM calendars WHERE calendar_id = " . intval($value)));
        return $row ? $row[0] : '#' . intval($value);
    }
    if ($kind === 'category') {
        if (!intval($value)) {
            return 'none';
        }
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_id = " . intval($value)));
        return $row ? $row['category_name'] : '#' . intval($value);
    }
    if ($kind === 'select' && isset($field['options'])) {
        $options = $field['options'];
        if ($options === 'themes') {
            $options = settingsThemes();
        } elseif ($options === 'start_pages') {
            $options = settingsStartPages();
        } elseif ($options === 'net_terms') {
            $options = settingsNetTerms();
        } elseif ($options === 'sign_in_alerts') {
            $options = signInAlertOptions();
        } elseif ($options === 'share_max') {
            $options = shareMaxOptions();
        } elseif (is_string($options)) {
            $options = settingsLocalizationArrays()[$options] ?? [];
        }
        if (isset($options[$value])) {
            return $options[$value];
        }
    }
    $value = (string) $value;
    if ($value === '') {
        return 'blank';
    }
    if (is_numeric($value) && strpos($value, '.') !== false) {
        $value = rtrim(rtrim($value, '0'), '.');
    }
    return mb_strlen($value) > 60 ? mb_substr($value, 0, 57) . '...' : $value;
}

/* What changed, a line each - "Next ticket number 4021 → 4100", "Late fees off → on", "Invoice footer changed". */
function settingsChangeLines(array $before, array $changed) {
    $fields = settingsFields();
    $lines = [];
    foreach ($changed as $name => $value) {
        $label = $fields[$name]['label'] ?? $name;
        $kind = $fields[$name]['kind'] ?? 'text';
        if (in_array($kind, ['secret', 'long', 'file'], true) || !empty($fields[$name]['quiet'])) {
            $lines[] = "$label changed";
        } else {
            $lines[] = $label . ' ' . settingsLogValue($name, $before[$name] ?? '') . ' → ' . settingsLogValue($name, $value);
        }
    }
    return $lines;
}

/*
 * Write the settings that changed - settings, or the company's row in
 * companies - each value as given: a number as a number, null as NULL. The
 * changed ones, by name. $before is what settingsCurrent() read.
 */
function settingsWrite($table, array $before, array $after) {
    global $mysqli;
    $table = $table === 'companies' ? 'companies' : 'settings';
    $changed = [];
    foreach ($after as $column => $value) {
        if (!array_key_exists($column, $before) || !settingsSame($before[$column], $value)) {
            $changed[$column] = $value;
        }
    }
    if ($changed) {
        $sets = [];
        foreach ($changed as $column => $value) {
            $column_sql = '`' . preg_replace('/[^a-z0-9_]/', '', $column) . '`';
            if ($value === null) {
                $sets[] = "$column_sql = NULL";
            } elseif (is_int($value) || is_float($value)) {
                $sets[] = "$column_sql = " . (is_float($value) ? rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') : $value);
            } else {
                $sets[] = "$column_sql = '" . escapeSql((string) $value) . "'";
            }
        }
        mysqli_query($mysqli, "UPDATE $table SET " . implode(', ', $sets) . " WHERE company_id = 1");
    }
    return $changed;
}

/* Save a page's settings in one table, log what changed and say so - the changed ones, by name. */
function settingsSave($page_key, array $before, array $after, $table = 'settings', array $also_changed = []) {
    $changed = settingsWrite($table, $before, $after);
    settingsSaid($page_key, array_merge(settingsChangeLines($before, $changed), $also_changed));
    return $changed;
}

/* Log and say what a save changed - or that it changed nothing. */
function settingsSaid($page_key, array $lines) {
    global $session_name;
    $title = settingsPages()[$page_key]['title'] ?? 'Settings';
    if (!$lines) {
        flashAlert("Nothing changed on " . escapeHtml($title), 'info');
        return;
    }
    logAudit("Settings", "Edit", escapeSql("$session_name changed $title: " . implode('; ', $lines)));
    flashAlert("<strong>" . escapeHtml($title) . "</strong> saved - " . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes'));
}

/*
 * A save with something wrong: back to the page with what was typed (bar
 * secrets and files) and what is wrong beside each field - nothing is saved.
 */
function settingsRefuse($page_key, array $errors) {
    $fields = settingsFields();
    $values = [];
    foreach ($_POST as $name => $value) {
        if ($name === 'csrf_token' || in_array($fields[$name]['kind'] ?? '', ['secret', 'file'], true) || is_array($value)) {
            continue;
        }
        $values[$name] = (string) $value;
    }
    $_SESSION['settings_kept'] = ['page' => $page_key, 'values' => $values, 'errors' => $errors, 'at' => time()];
    flashAlert('Not saved - ' . (count($errors) === 1 ? 'one thing' : count($errors) . ' things') . ' to put right', 'error');
    redirect();
}

/*
 * A picture sent as the logo or favicon - '' when it is all right or nothing
 * was sent, else what is wrong. $types are IMAGETYPE_* constants; an ICO is
 * known by its first four bytes, as getimagesize() reads it but not reliably.
 */
function settingsImageProblem($file, array $extensions, array $types, $kinds, $max_bytes = 5242880) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return '';
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > $max_bytes) {
        return 'That file is too large - up to ' . round($max_bytes / 1048576) . ' MB.';
    }
    if (!checkFileUpload($file, $extensions)) {
        return "That isn't a $kinds file.";
    }
    if ($types === ['ico']) {
        $head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 4);
        return $head === "\x00\x00\x01\x00" ? '' : "That isn't a $kinds file.";
    }
    $image = @getimagesize($file['tmp_name']);
    return $image && in_array($image[2], $types, true) ? '' : "That isn't a $kinds picture.";
}

/* What a numbered record's prefix and number are kept in - the next one's setting names, and the table. */
function settingsNumbering($kind) {
    $numbering = [
        'ticket' => ['prefix' => 'config_ticket_prefix', 'number' => 'config_ticket_next_number', 'table' => 'tickets', 'column' => 'ticket', 'what' => 'ticket'],
        'project' => ['prefix' => 'config_project_prefix', 'number' => 'config_project_next_number', 'table' => 'projects', 'column' => 'project', 'what' => 'project'],
        'quote' => ['prefix' => 'config_quote_prefix', 'number' => 'config_quote_next_number', 'table' => 'quotes', 'column' => 'quote', 'what' => 'quote'],
        'invoice' => ['prefix' => 'config_invoice_prefix', 'number' => 'config_invoice_next_number', 'table' => 'invoices', 'column' => 'invoice', 'what' => 'invoice'],
        'recurring_invoice' => ['prefix' => 'config_recurring_invoice_prefix', 'number' => 'config_recurring_invoice_next_number', 'table' => 'recurring_invoices', 'column' => 'recurring_invoice', 'what' => 'recurring invoice'],
    ];
    return $numbering[$kind];
}

/* The highest number already used with this prefix - 0 when none is. */
function settingsNumberInUse($kind, $prefix) {
    global $mysqli;
    $numbering = settingsNumbering($kind);
    $column = $numbering['column'];
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX({$column}_number) FROM {$numbering['table']} WHERE {$column}_prefix = '" . escapeSql((string) $prefix) . "'"));
    return intval($row[0] ?? 0);
}

/*
 * A prefix and next number, checked - [prefix, number, errors by field]. The
 * prefix is needed (a ticket's only letters and hyphens, as replies by email
 * find their ticket by it); the number has to be past the last one used with
 * that prefix, or two would share it. Left as saved ($saved: prefix and
 * number setting => value), the numbering is not what is being changed, so a
 * clash already there (the page warns of it) doesn't stop the rest saving.
 */
function settingsNumberingCheck($kind, array $post, array $saved = []) {
    $numbering = settingsNumbering($kind);
    $prefix = mb_substr(trim((string) ($post[$numbering['prefix']] ?? '')), 0, 200);
    $number_text = trim((string) ($post[$numbering['number']] ?? ''));
    $number = ctype_digit($number_text) ? intval($number_text) : -1;
    $errors = [];
    if ($prefix === '') {
        $errors[$numbering['prefix']] = 'Every ' . $numbering['what'] . ' number starts with one - TCK-, INV- or the like.';
    } elseif ($kind === 'ticket' && !preg_match('/^[A-Za-z-]+$/', $prefix)) {
        $errors[$numbering['prefix']] = 'Only letters and hyphens - replies by email find their ticket by it.';
    } elseif (preg_match('/\s/', $prefix)) {
        $errors[$numbering['prefix']] = 'No spaces in it.';
    }
    if ($number < 1 || $number > 2000000000) {
        $errors[$numbering['number']] = 'A whole number, 1 or more.';
    } elseif (!isset($errors[$numbering['prefix']])
        && !($saved && $prefix === (string) $saved[$numbering['prefix']] && $number === intval($saved[$numbering['number']]))) {
        $in_use = settingsNumberInUse($kind, $prefix);
        if ($number <= $in_use) {
            $errors[$numbering['number']] = $prefix . $in_use . ' is already in use - the next one has to be ' . ($in_use + 1) . ' or more.';
        }
    }
    return [$prefix, $number, $errors];
}

/* What is wrong with an email address that may be left empty - '' when nothing is. */
function settingsEmailProblem($email) {
    return $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL) ? 'That is not an email address.' : '';
}

/*
 * The prefix and next number fields, side by side, and the number the next
 * one will get as they are typed (js/settings.js) - with the last one in use.
 */
function settingsNumberingFields($kind, $prefix, $number) {
    $numbering = settingsNumbering($kind);
    $in_use = settingsNumberInUse($kind, (string) $prefix);
    $prefix_shown = (string) settingsValue($numbering['prefix'], $prefix);
    $number_shown = (string) settingsValue($numbering['number'], $number);
    ob_start(); ?>
    <div class="row g-3">
        <div class="col-sm-5"><?= settingsInput($numbering['prefix'], $prefix, ['icon' => 'fa-barcode', 'required' => true, 'maxlength' => 200, 'label' => 'Prefix',
            'pattern' => $kind === 'ticket' ? '[A-Za-z-]+' : '[^\s]+', 'title' => $kind === 'ticket' ? 'Only letters and hyphens' : 'No spaces']) ?></div>
        <div class="col-sm-7"><?= settingsInput($numbering['number'], $number, ['icon' => 'fa-hashtag', 'type' => 'number', 'required' => true, 'min' => 1, 'label' => 'Next number']) ?></div>
    </div>
    <div class="itflow-settings-sample itflow-settings-next mt-3" data-settings-next="<?= $numbering['prefix'] ?>,<?= $numbering['number'] ?>"
        data-settings-in-use-prefix="<?= escapeHtml((string) $prefix) ?>" data-settings-in-use-number="<?= $in_use ?>">
        <span class="text-body-secondary">The next <?= escapeHtml($numbering['what']) ?> is</span>
        <span data-settings-next-number><?= escapeHtml($prefix_shown . $number_shown) ?></span>
        <?php if ($in_use) { ?>
            <span class="text-body-secondary fw-normal" data-settings-in-use>- <?= escapeHtml($prefix . $in_use) ?> is the last one made</span>
            <div class="text-danger small fw-normal mt-1" data-settings-clash<?= $prefix_shown !== (string) $prefix || intval($number_shown) > $in_use || settingsError($numbering['number']) !== '' ? ' hidden' : '' ?>>
                <i class="fas fa-exclamation-triangle me-1"></i>That one is already in use - the next one has to be <?= $in_use + 1 ?> or more.
            </div>
        <?php } ?>
    </div>
    <?php return ob_get_clean();
}

/* The pages ITFlow can open on, as page => name - any other page of /agent/ can be typed. */
function settingsStartPages() {
    return ['dashboard.php' => 'Dashboard', 'clients.php' => 'Clients', 'tickets.php' => 'Tickets', 'calendar.php' => 'Calendar', 'projects.php' => 'Projects',
        'invoices.php' => 'Invoices', 'quotes.php' => 'Quotes', 'assets.php' => 'Assets'];
}

/*
 * What is wrong with a start page - '' when nothing is. It is a page of
 * /agent/ (dashboard.php, reports/income_summary.php?year=2026), as it is put
 * after /agent/ wherever ITFlow opens.
 */
function settingsStartPageProblem($page) {
    if (!preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*\.php(\?[A-Za-z0-9_=&%.+-]*)?$#', $page)) {
        return 'A page of ITFlow, as it is after /agent/ in its address - dashboard.php, or tickets.php?status=Open.';
    }
    if (!is_file(dirname(__DIR__) . '/agent/' . strtok($page, '?'))) {
        return 'There is no such page.';
    }
    return '';
}

/* The net terms a client can have, as days => name. */
function settingsNetTerms() {
    return [0 => 'On receipt', 7 => '7 days', 10 => '10 days', 15 => '15 days', 30 => '30 days', 45 => '45 days', 60 => '60 days', 90 => '90 days'];
}

/* The countries, currencies and languages ITFlow offers (includes/settings_localization_array.php), wherever this is called from. */
function settingsLocalizationArrays() {
    static $arrays = null;
    if ($arrays === null) {
        include dirname(__DIR__) . '/includes/settings_localization_array.php';
        $arrays = ['countries' => $countries_array, 'currencies' => $currencies_array, 'locales' => $locales_array, 'country_iso2' => $country_iso2_array];
    }
    return $arrays;
}

/* A website as it is kept - without its http:// and a trailing slash. */
function settingsWebsite($website) {
    return rtrim(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', trim((string) $website)), '/');
}
