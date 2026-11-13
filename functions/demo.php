<?php

/*
 * ITFlow - Demo mode
 *
 * For a public demo anyone can sign in to, like demo.itflow.org. Turned on in config.php:
 *
 *   $config_demo_mode = true;
 *
 * With it on:
 *   - the sign-in page has a button for each demo account - Admin, Technician, Accountant and
 *     a contact in the client portal (DEMO_MODE_ACCOUNTS, demoModeSignInEmail())
 *   - every page says it's the demo, and when it goes back to fresh data (demoModeBannerHtml())
 *   - the Demo Reset cron job puts it back every night: an empty database, the starter content,
 *     the demo data and the demo accounts, and the uploads folder emptied (demoModeReset())
 *   - what would reach outside it, cost something, or lock other visitors out can't be changed
 *     (DEMO_MODE_LOCKS, demoModeGuard()) - everything else can, until the reset
 *   - links that open without signing in - an invoice's, a ticket's, the calendar feed - open
 *     only for someone signed in to the demo (demoModeGuestGuard())
 *   - email isn't sent: the Mail Queue job cancels it, so it still shows in Maintenance > Mail
 *     Queue. Texts aren't either - Text Messaging can't be set up
 *   - certificate checks reach public addresses on port 443 only (demoModePublicAddress())
 *
 * Off - the default - none of this exists. Nothing is kept in the database: what demo mode
 * needs is the reset, which makes everything it relies on.
 *
 * A new handler for something in DEMO_MODE_LOCKS has to add its request key there - the lock
 * goes by the keys post.php and ajax.php handlers answer to.
 */

/** The password every demo account signs in with - it's on the sign-in page's buttons anyway. */
const DEMO_MODE_PASSWORD = 'demo';

/*
 * The agent accounts the reset makes, in the order the sign-in page shows them. The client
 * portal account is the primary contact of the first demo client (demoModePortalUserId()).
 */
const DEMO_MODE_ACCOUNTS = [
    'admin' => ['label' => 'Admin', 'name' => 'Demo Admin', 'email' => 'demo@demo.com', 'role_id' => 3, 'icon' => 'fa-user-shield'],
    'technician' => ['label' => 'Technician', 'name' => 'Demo Technician', 'email' => 'technician@demo.com', 'role_id' => 2, 'icon' => 'fa-tools'],
    'accountant' => ['label' => 'Accountant', 'name' => 'Demo Accountant', 'email' => 'accountant@demo.com', 'role_id' => 1, 'icon' => 'fa-calculator'],
];

/*
 * What can't be changed in the demo - what the post.php and ajax.php handlers for it answer
 * to, and what to say instead. Everything else can be changed, and the reset puts it back.
 */
const DEMO_MODE_LOCKS = [
    [
        'message' => "Users and roles can't be changed in the demo - everyone signs in with the same accounts.",
        'keys' => ['activate_user', 'add_user', 'archive_user', 'bulk_activate_users', 'bulk_disable_users', 'bulk_require_mfa_users', 'bulk_revoke_remember_users',
            'bulk_role_users', 'bulk_unrequire_mfa_users', 'cancel_user_invite', 'disable_user', 'edit_user', 'ir_reset_user_password', 'link_user_invite',
            'resend_user_invite', 'reset_user_mfa', 'restore_user', 'revoke_remember_me', 'add_role', 'archive_role', 'delete_role', 'edit_role', 'restore_role'],
    ],
    [
        'message' => "Your name, email, picture, password and two-step sign-in can't be changed in the demo - other visitors sign in with this account too.",
        'keys' => ['edit_your_user_details', 'clear_your_user_avatar', 'edit_your_user_password', 'enable_mfa'],
    ],
    [
        'message' => "Your password and two-step sign-in can't be changed in the demo - other visitors sign in with this account too.",
        'keys' => ['portal_password_change', 'portal_mfa_enable'],
    ],
    [
        'message' => "Sign-in security and identity providers can't be changed in the demo.",
        'keys' => ['edit_security_settings', 'edit_identity_provider'],
    ],
    [
        'message' => "API keys can't be made or changed in the demo.",
        'keys' => ['add_api_key', 'bulk_delete_api_keys', 'delete_api_key', 'edit_api_key', 'revoke_api_key'],
    ],
    [
        'message' => "Mail settings can't be changed in the demo - it sends no email.",
        'keys' => ['edit_mail_from_settings', 'edit_mail_imap_settings', 'edit_mail_oauth_settings', 'edit_mail_smtp_settings', 'oauth_connect_microsoft_mail',
            'send_email_preview', 'test_email_imap', 'test_email_smtp', 'test_oauth_token_refresh'],
    ],
    [
        'message' => "Backups and updates are turned off in the demo.",
        'keys' => ['backup_master_key', 'delete_backup', 'edit_backup_settings', 'queue_backup', 'check_update', 'queue_update'],
    ],
    [
        'message' => "Cron can't be changed in the demo - it runs the nightly reset.",
        'keys' => ['clear_cron_error', 'disable_cron', 'disable_cron_job', 'edit_cron_job', 'enable_cron', 'enable_cron_job', 'run_cron_job'],
    ],
    [
        'message' => "Card payments and payment providers are turned off in the demo.",
        'keys' => ['add_payment_provider', 'check_payment_provider', 'check_payment_provider_connection', 'delete_payment_provider', 'edit_payment_provider',
            'record_payment_provider_fees', 'turn_payment_provider_off', 'turn_payment_provider_on', 'delete_saved_payment', 'create_stripe_checkout',
            'create_stripe_customer', 'stripe_save_card', 'set_recurring_payment', 'portal_pay'],
    ],
    [
        'message' => "AI providers can't be set up in the demo.",
        'keys' => ['add_ai_provider', 'ai_provider_models', 'check_ai_provider', 'delete_ai_provider', 'edit_ai_provider', 'test_ai_provider',
            'add_ai_model', 'check_ai_model', 'delete_ai_model', 'edit_ai_model'],
    ],
    [
        'message' => "Text messaging can't be set up in the demo - it sends no texts.",
        'keys' => ['add_sms_provider', 'check_sms_provider', 'delete_sms_provider', 'edit_sms_provider', 'new_sms_inbound_key', 'send_sms_test',
            'sms_number_opt_in', 'sms_number_unblock', 'turn_sms_provider_off', 'turn_sms_provider_on'],
    ],
    [
        'message' => "RMM providers can't be set up in the demo.",
        'keys' => ['add_rmm_provider', 'check_rmm_provider', 'edit_rmm_provider', 'delete_rmm_provider', 'test_rmm_provider', 'sync_rmm', 'turn_rmm_provider_off',
            'turn_rmm_provider_on', 'link_rmm_client', 'link_rmm_suggested', 'unlink_rmm_client', 'rmm_bring_back'],
    ],
    [
        'message' => "Managed instances are turned off in the demo.",
        'keys' => ['managed_checkin_now', 'managed_connect', 'managed_disconnect', 'add_managed_instance', 'delete_managed_instance', 'edit_managed_instance',
            'managed_command', 'managed_instance_mode', 'managed_instance_new_code', 'managed_support_sign_in', 'remove_managed_instance'],
    ],
    [
        'message' => "Logs, log forwarding and telemetry can't be changed in the demo.",
        'keys' => ['edit_log_settings', 'test_log_forward', 'edit_telemetry_settings', 'clear_app_logs'],
    ],
    [
        'message' => "The company's details, modules and client portal settings can't be changed in the demo.",
        'keys' => ['edit_company', 'remove_company_logo', 'edit_module_settings', 'edit_client_portal_settings'],
    ],
    [
        'message' => "Custom links can't be changed in the demo.",
        'keys' => ['add_custom_link', 'archive_custom_link', 'copy_custom_link', 'delete_custom_link', 'edit_custom_link', 'move_custom_link',
            'reorder_custom_links', 'restore_custom_link'],
    ],
    [
        'message' => "Starter content, demo data and Get started can't be changed in the demo - the nightly reset loads them.",
        'keys' => ['load_demo_data', 'load_starter_content', 'remove_demo_data', 'update_starter_canned_responses', 'update_starter_document_templates',
            'update_starter_license_templates', 'update_starter_ticket_templates', 'update_starter_vendor_templates', 'getting_started_backups',
            'getting_started_billing', 'getting_started_content', 'getting_started_cron_on', 'getting_started_dismiss', 'getting_started_mail',
            'getting_started_skip', 'getting_started_skip_all', 'getting_started_support', 'getting_started_team'],
    ],
    [
        'message' => "Share links can't be made in the demo - what's in it is for signed-in visitors only.",
        'keys' => ['share_create'],
    ],
];

/*
 * Pages whose changes are locked - true when all of them are, false when only some - so the
 * banner can say so. Their handlers are what DEMO_MODE_LOCKS stops.
 */
const DEMO_MODE_LOCKED_PAGES = [
    '/admin/users.php' => true, '/admin/roles.php' => true, '/admin/api_keys.php' => true, '/admin/backup.php' => true, '/admin/update.php' => true,
    '/admin/settings_mail.php' => true, '/admin/settings_security.php' => true, '/admin/identity_providers.php' => true, '/admin/settings_telemetry.php' => true,
    '/admin/cron.php' => true, '/admin/payment_providers.php' => true, '/admin/saved_payment_methods.php' => true, '/admin/ai_providers.php' => true,
    '/admin/ai_models.php' => true, '/admin/text_messaging.php' => true, '/admin/rmm_providers.php' => true, '/admin/rmm_clients.php' => true, '/admin/managed_by.php' => true, '/admin/managed_instances.php' => true,
    '/admin/managed_instance.php' => true, '/admin/settings_logs.php' => true, '/admin/settings_company.php' => true, '/admin/settings_module.php' => true,
    '/admin/settings_client_portal.php' => true, '/admin/custom_links.php' => true, '/admin/starter_content.php' => true, '/admin/getting_started.php' => true,
    '/agent/user/user_details.php' => true, '/agent/user/user_security.php' => false, '/client/profile.php' => false,
];

/* Pages the demo doesn't open at all - a backup's download, the server's health, and mail sign-in's return from Microsoft. */
const DEMO_MODE_CLOSED_PAGES = ['/admin/backup_download.php', '/admin/debug.php', '/admin/oauth_microsoft_mail_callback.php'];

/** Is this ITFlow the demo - config.php's $config_demo_mode? */
function demoModeOn(): bool
{
    global $config_demo_mode;
    return !empty($config_demo_mode);
}

/** What to say when one of these request keys is for something locked in the demo - null when none is. */
function demoModeLockedMessage(array $keys): ?string
{
    foreach (DEMO_MODE_LOCKS as $lock) {
        if (array_intersect($keys, $lock['keys'])) {
            return $lock['message'];
        }
    }
    return null;
}

/*
 * Stop a request for something locked in the demo - from includes/check_login.php for agents
 * and client/includes/check_login.php for the portal, so every page and handler passes here.
 * Pages only show things, so it's their handlers that are stopped (post.php and ajax.php),
 * apart from the few pages the demo doesn't open (DEMO_MODE_CLOSED_PAGES).
 */
function demoModeGuard(): void
{
    if (!demoModeOn()) {
        return;
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, DEMO_MODE_CLOSED_PAGES, true)) {
        $message = "That isn't open in the demo.";
    } elseif (in_array(basename($script), ['post.php', 'ajax.php'], true)) {
        $message = demoModeLockedMessage(array_map('strval', array_keys($_GET + $_POST)));
        if ($message === null) {
            return;
        }
    } else {
        return;
    }

    // ajax.php's handlers answer a refusal with {error} and a 200, which their callers show -
    // a 403 would leave them waiting
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || basename($script) === 'ajax.php';
    if ($ajax) {
        http_response_code(basename($script) === 'ajax.php' ? 200 : 403);
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => $message, 'message' => $message]));
    }

    flashAlert(escapeHtml($message), 'error');
    redirect();
}

/*
 * In the demo, the pages a link opens without signing in - an invoice's or a quote's, a
 * ticket's, a contract's, the calendar feed - open only for someone signed in to the demo.
 * Otherwise the demo would show whatever a visitor wrote to anyone they sent the link to, from
 * its own address. From includes/load_global_settings.php, which the guest pages load.
 */
function demoModeGuestGuard(): void
{
    global $config_https_only;

    if (!demoModeOn() || !str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/guest/')) {
        return;
    }

    require_once dirname(__DIR__) . '/includes/session_init.php';
    if (!empty($_SESSION['logged'])) {
        return;
    }

    http_response_code(403);
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    exit('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Demo</title><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><link rel="stylesheet" href="' . staticFileUrl('/libs/fontawesome-free/css/all.min.css') . '"><link rel="stylesheet" href="' . staticFileUrl('/libs/adminlte/css/adminlte.min.css') . '">'
        . '<link rel="stylesheet" href="' . staticFileUrl('/css/itflow_custom.css') . '"></head><body class="hold-transition login-page"><div class="login-box"><div class="card" data-demo-guest><div class="card-body p-4 text-center">'
        . '<i class="fas fa-flask fa-2x text-info mb-3" aria-hidden="true"></i><h1 class="h4 mb-2">Sign in to the demo first</h1>'
        . '<p class="text-body-secondary">This is a demo, so links from it open only for someone signed in to it.</p>'
        . '<a href="/login.php" class="btn btn-primary"><i class="fas fa-fw fa-sign-in-alt me-2"></i>Sign in</a></div></div></div></body></html>');
}

/*
 * The time the Demo Reset job runs each night, as its row in Maintenance > Cron says, or as
 * the registry ships it before the job has run - '2:00 AM EST'.
 */
function demoModeResetTime(): string
{
    global $mysqli;

    $daily_at = '02:00';
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_daily_at FROM cron_jobs WHERE cron_job_name = 'demo_reset' LIMIT 1"));
        if (!empty($row['cron_job_daily_at'])) {
            $daily_at = substr((string) $row['cron_job_daily_at'], 0, 5);
        }
    } catch (Throwable $e) {
        // In the middle of the reset itself - the shipped time will do
    }

    return date('g:i A', strtotime(date('Y-m-d') . " $daily_at")) . ' ' . date('T');
}

/*
 * The notice along the top of each page in the demo - what it is, when it's put back, and
 * when the page's changes are locked. '' when it isn't the demo.
 */
function demoModeBannerHtml(): string
{
    if (!demoModeOn()) {
        return '';
    }

    $locked = DEMO_MODE_LOCKED_PAGES[(string) ($_SERVER['SCRIPT_NAME'] ?? '')] ?? null;
    $locked_text = $locked === null ? '' : ($locked ? ' <strong>Changes here are turned off in the demo.</strong>' : ' <strong>Some changes here are turned off in the demo.</strong>');

    return '<div class="alert alert-info d-flex gap-2 align-items-start py-2" data-demo-banner><i class="fas fa-fw fa-flask mt-1"></i><div><strong>This is a demo.</strong> '
        . 'Anyone can sign in, so don\'t put anything real in it. It goes back to fresh demo data every night at ' . escapeHtml(demoModeResetTime())
        . ', and sends no email or texts.' . $locked_text . '</div></div>';
}

/*
 * The client portal account the demo signs in as - the first portal sign-in on the install,
 * which the reset makes for the first demo client's primary contact. Put right if a visitor
 * has archived it, disabled it, turned on two-step sign-in or set another password through
 * the contact, so the button keeps working. 0 when there's none.
 */
function demoModePortalUserId(): int
{
    global $mysqli;

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_password, user_status, user_archived_at, user_token, user_auth_method FROM users
        JOIN contacts ON contact_user_id = user_id AND contact_archived_at IS NULL
        JOIN clients ON client_id = contact_client_id AND client_archived_at IS NULL
        WHERE user_type = 2 ORDER BY user_id LIMIT 1"));
    if (!$row) {
        return 0;
    }

    $user_id = intval($row['user_id']);
    if (!password_verify(DEMO_MODE_PASSWORD, (string) $row['user_password']) || intval($row['user_status']) !== 1 || $row['user_archived_at'] !== null
        || (string) $row['user_token'] !== '' || $row['user_auth_method'] !== 'local') {
        $password = escapeSql(password_hash(DEMO_MODE_PASSWORD, PASSWORD_DEFAULT));
        mysqli_query($mysqli, "UPDATE users SET user_password = '$password', user_status = 1, user_archived_at = NULL, user_token = '', user_auth_method = 'local' WHERE user_id = $user_id");
    }

    return $user_id;
}

/** The portal contact the demo's Client portal button signs in as - ['name', 'client'], or null when there's none. */
function demoModePortalContact(): ?array
{
    global $mysqli;

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, client_name FROM users
        JOIN contacts ON contact_user_id = user_id AND contact_archived_at IS NULL
        JOIN clients ON client_id = contact_client_id AND client_archived_at IS NULL
        WHERE user_type = 2 ORDER BY user_id LIMIT 1"));

    return $row ? ['name' => (string) $row['contact_name'], 'client' => (string) $row['client_name']] : null;
}

/*
 * The email a demo sign-in button signs in with - 'admin', 'technician', 'accountant' or
 * 'portal'. Null when that account isn't there to sign in to: a visitor can't change the
 * agents', but could archive the portal contact's client.
 */
function demoModeSignInEmail(string $account): ?string
{
    global $mysqli;

    if ($account === 'portal') {
        $user_id = demoModePortalUserId();
        $where = "user_id = $user_id AND user_type = 2";
    } elseif (isset(DEMO_MODE_ACCOUNTS[$account])) {
        $where = "user_email = '" . escapeSql(DEMO_MODE_ACCOUNTS[$account]['email']) . "' AND user_type = 1";
    } else {
        return null;
    }

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email FROM users WHERE $where AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"));

    return $row ? (string) $row['user_email'] : null;
}

/*
 * Where a certificate check may connect in the demo - the host's public address, on port 443
 * only, so the demo can't be used to look around the network it runs on. Null when it isn't
 * one. The check connects to this address rather than the name, so the name can't point
 * somewhere else in between.
 */
function demoModePublicAddress(string $host, int $port): ?string
{
    if ($port !== 443) {
        return null;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $addresses = [$host];
    } else {
        $addresses = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $addresses[] = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        }
    }

    $addresses = array_values(array_filter($addresses));
    if (!$addresses) {
        return null;
    }
    foreach ($addresses as $address) {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
    }

    return $addresses[0];
}

/*
 * Put the demo back to fresh demo data - the Demo Reset cron job (cron/demo_reset.php), or
 * run by hand with php cron/demo_reset.php. Every table is emptied and made again from db.sql,
 * then filled the way a new install is, plus all of Starter Content, the demo data, the demo
 * accounts and a portal sign-in. Kept: the company's details (the demo can't change them),
 * the timezone, and each cron job's schedule and when it last ran. The uploads folder is
 * emptied, apart from the company's logo. Everyone signed in is signed out - their accounts
 * are new.
 *
 * What it made - ['clients', 'users', 'portal']. Throws when db.sql won't load; the next
 * minute's dispatch tries again.
 */
function demoModeReset($mysqli): array
{
    global $session_user_id, $session_name, $session_company_currency;
    global $config_ticket_prefix, $config_invoice_prefix, $config_quote_prefix, $config_recurring_invoice_prefix, $config_project_prefix;

    defined('FROM_SETUP') || define('FROM_SETUP', true);
    defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
    $root = dirname(__DIR__);
    require_once "$root/includes/database_version.php";
    require_once "$root/setup/setup_database.php";
    require_once "$root/setup/setup_install.php";
    require_once "$root/setup/seed_data.php";
    require_once "$root/admin/post/demo_data_model.php";

    // What carries over - none of it, after a reset that failed part way
    try {
        $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1")) ?: [];
        $kept_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_timezone, config_cron_last_dispatch_at FROM settings WHERE company_id = 1")) ?: [];
        $cron_jobs = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM cron_jobs"), MYSQLI_ASSOC);
    } catch (Throwable $e) {
        $company = [];
        $kept_settings = [];
        $cron_jobs = [];
    }
    $timezone = (string) ($kept_settings['config_timezone'] ?? '');

    setupDropTables($mysqli);
    if (!setupImportSchema($mysqli, "$root/db.sql", $import_error)) {
        throw new RuntimeException("db.sql didn't load - $import_error");
    }

    // The company as it was, or one for a demo that never had one, and what every install starts with
    $company = $company ?: [
        'company_id' => 1, 'company_name' => 'Summit Ridge IT', 'company_address' => '100 Summit Ridge Road', 'company_city' => 'Pittsburgh',
        'company_state' => 'PA', 'company_zip' => '15212', 'company_country' => 'United States', 'company_phone_country_code' => '1',
        'company_phone' => '4125550100', 'company_email' => 'hello@summitridgeit.example', 'company_website' => 'summitridgeit.example',
        'company_locale' => 'en_US', 'company_currency' => 'USD',
    ];
    demoModeInsertRow($mysqli, 'companies', $company);
    seedDefaultData($mysqli);
    $currency = (string) ($company['company_currency'] ?? '') ?: 'USD';
    $timezone = $timezone !== '' ? $timezone : date_default_timezone_get();
    setupSaveRegion($mysqli, [
        'locale' => (string) ($company['company_locale'] ?? '') ?: 'en_US',
        'currency_code' => $currency,
        'timezone' => $timezone,
    ]);
    // Both clocks on it, as includes/inc_set_timezone.php sets them, for the dates in the demo data
    date_default_timezone_set($timezone);
    mysqli_query($mysqli, "SET time_zone = '" . (new DateTime('now', new DateTimeZone($timezone)))->format('P') . "'");
    // Straight to the dashboard - no Get started - and the client portal on. When cron last ran
    // carries over, or Maintenance > Cron says it isn't running until the next minute
    $last_dispatch = empty($kept_settings['config_cron_last_dispatch_at']) ? 'NULL' : "'" . escapeSql($kept_settings['config_cron_last_dispatch_at']) . "'";
    mysqli_query($mysqli, "UPDATE settings SET config_getting_started = 0, config_client_portal_enable = 1, config_cron_last_dispatch_at = $last_dispatch WHERE company_id = 1");

    // Each cron job's schedule, and when it last ran - so this job isn't due again straight away
    foreach ($cron_jobs as $cron_job) {
        demoModeInsertRow($mysqli, 'cron_jobs', $cron_job);
    }

    // The demo accounts, each with the vault's new key under the demo password
    $master_key = randomString();
    $user_ids = [];
    foreach (DEMO_MODE_ACCOUNTS as $account => $details) {
        $name = escapeSql($details['name']);
        $email = escapeSql($details['email']);
        $password = escapeSql(password_hash(DEMO_MODE_PASSWORD, PASSWORD_DEFAULT));
        $ciphertext = escapeSql(setupFirstUserSpecificKey(DEMO_MODE_PASSWORD, $master_key));
        $role_id = intval($details['role_id']);
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$ciphertext', user_role_id = $role_id, user_type = 1, user_status = 1");
        $user_ids[$account] = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = {$user_ids[$account]}");
    }

    // The emptied uploads folder, before anything is put in it
    demoModeEmptyUploads("$root/uploads");
    foreach ($user_ids as $user_id) {
        mkdirMissing("$root/uploads/users/$user_id");
    }

    // Starter Content and the demo data are loaded as the admin, with the vault open - the way
    // Load everything and Load demo data do it in Maintenance > Starter Content
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_prefix, config_invoice_prefix, config_quote_prefix, config_recurring_invoice_prefix, config_project_prefix FROM settings WHERE company_id = 1")) ?: [];
    $session_user_id = $user_ids['admin'];
    $session_name = DEMO_MODE_ACCOUNTS['admin']['name'];
    $session_company_currency = $currency;
    $config_ticket_prefix = (string) ($settings['config_ticket_prefix'] ?? '');
    $config_invoice_prefix = (string) ($settings['config_invoice_prefix'] ?? '');
    $config_quote_prefix = (string) ($settings['config_quote_prefix'] ?? '');
    $config_recurring_invoice_prefix = (string) ($settings['config_recurring_invoice_prefix'] ?? '');
    $config_project_prefix = (string) ($settings['config_project_prefix'] ?? '');

    $vault_key = random_bytes(16);
    $vault_iv = random_bytes(16);
    $_SESSION['user_encryption_session_ciphertext'] = openssl_encrypt($master_key, 'aes-128-cbc', $vault_key, 0, $vault_iv);
    $_SESSION['user_encryption_session_iv'] = base64_encode($vault_iv);
    $_COOKIE['user_encryption_session_key'] = base64_encode($vault_key);

    try {
        foreach (array_keys(starterContentPacks()) as $pack) {
            starterContentLoad($mysqli, $pack);
        }
        $counts = demoDataLoad($mysqli);
    } finally {
        unset($_SESSION['user_encryption_session_ciphertext'], $_SESSION['user_encryption_session_iv'], $_COOKIE['user_encryption_session_key']);
    }

    // A portal sign-in for the first demo client's primary contact
    $portal = '';
    $first_client = escapeSql(demoDataProfiles()[0]['name'] ?? '');
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts
        JOIN clients ON client_id = contact_client_id WHERE client_name = '$first_client' AND contact_email != '' AND contact_archived_at IS NULL
        ORDER BY contact_primary DESC, contact_id LIMIT 1"));
    if ($contact) {
        $name = escapeSql($contact['contact_name']);
        $email = escapeSql($contact['contact_email']);
        $password = escapeSql(password_hash(DEMO_MODE_PASSWORD, PASSWORD_DEFAULT));
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_auth_method = 'local', user_type = 2, user_status = 1");
        $portal_user_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "UPDATE contacts SET contact_user_id = $portal_user_id WHERE contact_id = " . intval($contact['contact_id']));
        $portal = (string) $contact['contact_email'];
    }

    logAudit("Demo", "Reset", escapeSql("The demo went back to fresh demo data - {$counts['clients']} clients, " . count($user_ids) . " demo accounts" . ($portal !== '' ? " and a portal sign-in for $portal" : '')));

    return ['clients' => intval($counts['clients']), 'users' => count($user_ids), 'portal' => $portal];
}

/*
 * A row put back exactly as it was, as read with SELECT * (so not through escapeSql(), which
 * would take out tags) - the columns the table still has. The code may have been updated since
 * the row was read, and db.sql dropped one.
 */
function demoModeInsertRow($mysqli, string $table, array $row): void
{
    $columns = array_column(mysqli_fetch_all(mysqli_query($mysqli, "SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "`"), MYSQLI_ASSOC), 'Field');
    $sets = [];
    foreach (array_intersect_key($row, array_flip($columns)) as $column => $value) {
        $sets[] = "`" . str_replace('`', '``', (string) $column) . "` = " . ($value === null ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, (string) $value) . "'");
    }
    if ($sets) {
        mysqli_query($mysqli, "INSERT INTO `" . str_replace('`', '``', $table) . "` SET " . implode(', ', $sets));
    }
}

/*
 * Empty the uploads folder - everything in each of its folders but their index.php and
 * .htaccess - apart from settings/, which holds the company's logo, and the favicon.
 */
function demoModeEmptyUploads(string $uploads): void
{
    foreach (glob("$uploads/*", GLOB_ONLYDIR) ?: [] as $folder) {
        if (basename($folder) === 'settings') {
            continue;
        }
        foreach (array_diff(scandir($folder) ?: [], ['.', '..', 'index.php', '.htaccess']) as $item) {
            $path = "$folder/$item";
            if (is_dir($path) && !is_link($path)) {
                $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($items as $inside) {
                    $inside->isDir() && !$inside->isLink() ? @rmdir($inside->getPathname()) : @unlink($inside->getPathname());
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
