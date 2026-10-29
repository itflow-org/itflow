<?php

/*
 * Admin > Get started (admin/getting_started.php) - what each step saves.
 * Checked as the settings pages check the same settings: something wrong
 * comes back to the step saying so beside the field, and nothing is saved
 * (settingsRefuse()). A step saved goes on to the next; skipping one puts
 * it off, Skip all puts every one off, and dismissing stops it being offered
 * on the dashboard. What
 * changed is in the audit log. The first client and the demo data go to
 * their own handlers (agent/post/client.php, starter_content.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['getting_started_cron_on'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_enable_cron']);
    if (!intval($before['config_enable_cron'])) {
        mysqli_query($mysqli, "UPDATE settings SET config_enable_cron = 1 WHERE company_id = 1");
        logAudit("Settings", "Edit", "$session_name turned cron on in Get started");
    }
    flashAlert('Cron is on - each job runs on its schedule, in Maintenance &gt; Cron');

    redirect(gettingStartedUrl(gettingStartedSteps()['cron']['done'] ? gettingStartedAfter('cron') : 'cron'));

}

if (isset($_POST['getting_started_mail'])) {

    validateCSRFToken();

    $columns = ['config_mail_from_email', 'config_mail_from_name', 'config_ticket_from_email', 'config_ticket_from_name', 'config_ticket_email_parse'];
    foreach (['smtp', 'imap'] as $server) {
        foreach (['provider', 'host', 'port', 'encryption', 'username', 'password'] as $column) {
            $columns[] = "config_{$server}_$column";
        }
    }
    $before = settingsCurrent($columns);
    $after = $before;
    $errors = [];

    // Sending - unless Microsoft 365 or Google sends it, which the step leaves to Admin > Mail
    if (isset($_POST['config_smtp_provider'])) {
        $_POST['config_smtp_provider'] = 'standard_smtp';
        [$after, $found] = settingsMailServerCheck('smtp', $after, $_POST);
        $errors += $found;
    }

    $from_email = trim((string) ($_POST['config_mail_from_email'] ?? ''));
    $from_name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($_POST['config_mail_from_name'] ?? ''))));
    if ($from_email === '') {
        $errors['config_mail_from_email'] = 'The address ITFlow sends as - help@ your domain, or the like.';
    } elseif (($problem = settingsEmailProblem($from_email)) !== '') {
        $errors['config_mail_from_email'] = $problem;
    } elseif (mb_strlen($from_email) > 200) {
        $errors['config_mail_from_email'] = 'Up to 200 characters.';
    }
    if (mb_strlen($from_name) > 200) {
        $errors['config_mail_from_name'] = 'Up to 200 characters.';
    }
    $after['config_mail_from_email'] = $from_email;
    $after['config_mail_from_name'] = $from_name;

    // The support mailbox, read into tickets - only asked with the ticketing module
    $parse = false;
    if (intval($config_module_enable_ticketing)) {
        $parse = !empty($_POST['config_ticket_email_parse']);
        $after['config_ticket_email_parse'] = $parse ? 1 : 0;
        if ($parse) {
            if (!in_array((string) ($_POST['config_imap_provider'] ?? ''), ['standard_imap', 'google_oauth', 'microsoft_oauth'], true)) {
                $_POST['config_imap_provider'] = 'standard_imap';
            }
            [$after, $found] = settingsMailServerCheck('imap', $after, $_POST);
            $errors += $found;
            if (!isset($errors['config_imap_username']) && !filter_var($after['config_imap_username'], FILTER_VALIDATE_EMAIL)) {
                $errors['config_imap_username'] = "The mailbox's address - support@ your domain, or the like.";
            }
        }
    }

    if ($errors) {
        unset($_POST['config_smtp_password'], $_POST['config_imap_password']);
        settingsRefuse('getting_started_mail', $errors, gettingStartedUrl('mail'));
    }

    // Ticket emails come from the support mailbox, when they had no address of their own
    if ($parse && trim((string) $before['config_ticket_from_email']) === '') {
        $after['config_ticket_from_email'] = $after['config_imap_username'];
        if (trim((string) $before['config_ticket_from_name']) === '') {
            $after['config_ticket_from_name'] = $from_name;
        }
    }

    $changed = settingsWrite('settings', $before, $after);
    $lines = settingsChangeLines($before, $changed);
    if ($lines) {
        logAudit("Settings", "Edit", "$session_name set up email in Get started: " . escapeSql(implode('; ', $lines)));
    }

    if (($_POST['getting_started_mail'] ?? '') === 'test') {
        $cron_note = gettingStartedSteps()['cron']['done'] ? 'it goes within a minute' : 'it goes once cron is running';
        if (emailTestSend($session_email, 'system')) {
            flashAlert(($lines ? 'Saved, and a' : 'A') . ' test email is queued to <strong>' . escapeHtml($session_email) . "</strong> - $cron_note. Maintenance &gt; Mail Queue shows how it went");
        } else {
            flashAlert(($lines ? 'Saved - but the' : 'The') . ' test could not be queued: <strong>' . escapeHtml($session_email) . '</strong>, your own address, isn\'t an email address. Change it in Your account', 'warning');
        }
        redirect(gettingStartedUrl('mail'));
    }

    flashAlert($lines ? 'Email saved - ITFlow sends as <strong>' . escapeHtml($from_email) . '</strong>' : 'Nothing about email changed', $lines ? 'success' : 'info');
    redirect(gettingStartedUrl(gettingStartedAfter('mail')));

}

if (isset($_POST['getting_started_backups'])) {

    validateCSRFToken();

    $job = gettingStartedBackupJob();
    $enabled = !empty($_POST['backup_enabled']);
    $daily_at = trim((string) ($_POST['backup_daily_at'] ?? ''));
    $type = (string) ($_POST['config_backup_cron_type'] ?? '');
    $keep_count = trim((string) ($_POST['config_backup_retention_count'] ?? ''));
    $keep_days = trim((string) ($_POST['config_backup_retention_days'] ?? ''));

    $errors = [];
    if ($enabled && backupEncryptionKey() === '') {
        $errors['backup_enabled'] = "There is no backup key - ITFlow couldn't write one to config.php.";
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $daily_at)) {
        $errors['backup_daily_at'] = 'A time of day, as 02:00.';
    }
    if (!in_array($type, backupUnattendedTypes(), true)) {
        $errors['config_backup_cron_type'] = 'Pick one of these.';
    }
    if (!ctype_digit($keep_count) || intval($keep_count) < 1 || intval($keep_count) > 1000) {
        $errors['config_backup_retention_count'] = 'A whole number, 1 or more.';
    }
    if (!ctype_digit($keep_days) || intval($keep_days) > 3650) {
        $errors['config_backup_retention_days'] = 'A whole number of days - 0 for no limit.';
    }
    if ($enabled && !$job['enabled'] && empty($_POST['backup_key_saved'])) {
        $errors['backup_key_saved'] = 'Keep a copy of the backup key first - without it, no backup can be restored.';
    }
    if ($errors) {
        settingsRefuse('getting_started_backups', $errors, gettingStartedUrl('backups'));
    }

    $lines = gettingStartedBackupSave($enabled, $daily_at, $type, intval($keep_count), intval($keep_days));
    if ($lines) {
        logAudit("Settings", "Edit", "$session_name set up backups in Get started: " . escapeSql(implode('; ', $lines)));
    }
    if ($enabled) {
        flashAlert('A backup is taken every night at <strong>' . escapeHtml(date('g:i A', strtotime($daily_at))) . '</strong>' . (gettingStartedSteps()['cron']['done'] ? '' : ', once cron is running'));
    } else {
        flashAlert('Nightly backups are off - take one any time in Maintenance &gt; Backup', 'info');
    }
    redirect(gettingStartedUrl(gettingStartedAfter('backups')));

}

if (isset($_POST['getting_started_billing'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_default_hourly_rate', 'config_default_net_terms', 'config_invoice_prefix', 'config_invoice_next_number', 'config_quote_prefix', 'config_quote_next_number']);
    $errors = [];

    $rate_text = str_replace([',', ' '], '', trim((string) ($_POST['config_default_hourly_rate'] ?? '')));
    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $rate_text) || floatval($rate_text) <= 0) {
        $errors['config_default_hourly_rate'] = 'What you charge an hour, as 125 or 125.50.';
    }
    $net_terms = (string) ($_POST['config_default_net_terms'] ?? '');
    if (!ctype_digit($net_terms) || !array_key_exists(intval($net_terms), settingsNetTerms())) {
        $errors['config_default_net_terms'] = 'Pick one of these.';
    }
    [$invoice_prefix, $invoice_number, $found] = settingsNumberingCheck('invoice', $_POST, $before);
    $errors += $found;
    [$quote_prefix, $quote_number, $found] = settingsNumberingCheck('quote', $_POST, $before);
    $errors += $found;

    // A rate for new lines - only asked while there is no default
    $tax = null;
    $tax_name = trim((string) ($_POST['tax_name'] ?? ''));
    $tax_percent = trim((string) ($_POST['tax_percent'] ?? ''));
    if (!taxSettings()['default_id'] && ($tax_name !== '' || $tax_percent !== '')) {
        [$tax, $tax_error] = taxFromInput(['name' => $tax_name, 'percent' => $tax_percent]);
        if (!$tax) {
            $errors[$tax_name === '' ? 'tax_name' : 'tax_percent'] = $tax_error;
        }
    }

    // Stripe - only asked while it isn't set up; the keys are put to Stripe once everything else is right
    $stripe = null;
    $stripe_set_up = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1")) > 0;
    if (!$stripe_set_up && !empty($_POST['stripe_on'])) {
        [$stripe, $found] = paymentProviderCheck(['provider' => 'Stripe', 'public_key' => $_POST['stripe_public_key'] ?? '', 'private_key' => $_POST['stripe_private_key'] ?? '',
            'account' => $_POST['stripe_account'] ?? 0]);
        foreach ($found as $field => $error) {
            $errors[['provider' => 'stripe_public_key', 'public_key' => 'stripe_public_key', 'private_key' => 'stripe_private_key', 'account' => 'stripe_account'][$field] ?? 'stripe_public_key'] = $error;
        }
        if (!$errors) {
            $asked = paymentProviderAsk('Stripe', $stripe['payment_provider_private_key']);
            if (!$asked['ok']) {
                $errors['stripe_private_key'] = $asked['error'];
            }
        }
    }

    if ($errors) {
        unset($_POST['stripe_private_key']);
        settingsRefuse('getting_started_billing', $errors, gettingStartedUrl('billing'));
    }

    $changed = settingsWrite('settings', $before, [
        'config_default_hourly_rate' => round(floatval($rate_text), 2),
        'config_default_net_terms' => intval($net_terms),
        'config_invoice_prefix' => $invoice_prefix,
        'config_invoice_next_number' => $invoice_number,
        'config_quote_prefix' => $quote_prefix,
        'config_quote_next_number' => $quote_number,
    ]);
    $lines = settingsChangeLines($before, $changed);

    if ($tax) {
        $tax_id = taxCreate($tax);
        taxSetDefault($tax_id);
        logAudit("Tax", "Create", "$session_name added tax " . escapeSql(taxLabel(taxRow($tax_id))) . " in Get started, the default for new lines", 0, $tax_id);
        $lines[] = 'new lines taxed ' . taxLabel(taxRow($tax_id));
    }

    if ($stripe) {
        $provider_id = paymentProviderWrite($stripe);
        $mode = paymentProviderMode($stripe['payment_provider_private_key']);
        logAudit("Payment Provider", "Create", "$session_name added payment provider Stripe ($mode mode) in Get started, payments into " . escapeSql((string) (paymentProviderRow($provider_id)['account_name'] ?? '')), 0, $provider_id);
        $lines[] = 'Stripe added' . ($mode === 'test' ? ' with test keys' : '');
    }

    if ($changed) {
        logAudit("Settings", "Edit", "$session_name set up billing in Get started: " . escapeSql(implode('; ', settingsChangeLines($before, $changed))));
    }
    flashAlert($lines ? '<strong>Billing</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes') : 'Nothing about billing changed', $lines ? 'success' : 'info');
    redirect(gettingStartedUrl(gettingStartedAfter('billing')));

}

if (isset($_POST['getting_started_support'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_business_days', 'config_business_hours_start', 'config_business_hours_end', 'config_ticket_prefix', 'config_ticket_next_number', 'config_client_portal_enable']);
    $errors = [];

    $days = [];
    foreach ((array) ($_POST['business_days'] ?? []) as $day) {
        $day = intval($day);
        if ($day >= 1 && $day <= 7 && !in_array($day, $days, true)) {
            $days[] = $day;
        }
    }
    sort($days);
    if (!$days) {
        $errors['business_days'] = 'At least one day - response times only count on these.';
    }
    $start = trim((string) ($_POST['business_hours_start'] ?? ''));
    $end = trim((string) ($_POST['business_hours_end'] ?? ''));
    $time = '/^([01]\d|2[0-3]):[0-5]\d$/';
    if (!preg_match($time, $start)) {
        $errors['business_hours_start'] = 'A time of day, as 09:00.';
    }
    if (!preg_match($time, $end)) {
        $errors['business_hours_end'] = 'A time of day, as 17:00.';
    } elseif (!isset($errors['business_hours_start']) && $end <= $start) {
        $errors['business_hours_end'] = 'After the day starts.';
    }
    [$ticket_prefix, $ticket_number, $found] = settingsNumberingCheck('ticket', $_POST, $before);
    $errors += $found;

    if ($errors) {
        // The days go back as a list settingsRefuse() keeps
        $_POST['business_days_kept'] = implode(',', $days);
        settingsRefuse('getting_started_support', $errors, gettingStartedUrl('support'));
    }

    $changed = settingsWrite('settings', $before, [
        'config_business_days' => implode(',', $days),
        'config_business_hours_start' => "$start:00",
        'config_business_hours_end' => "$end:00",
        'config_ticket_prefix' => $ticket_prefix,
        'config_ticket_next_number' => $ticket_number,
        'config_client_portal_enable' => intval($_POST['config_client_portal_enable'] ?? 1) ? 1 : 0,
    ]);
    $hours_changed = array_intersect_key($changed, array_flip(['config_business_days', 'config_business_hours_start', 'config_business_hours_end']));
    $lines = settingsChangeLines($before, array_diff_key($changed, $hours_changed));
    if ($hours_changed) {
        $lines[] = 'Business hours ' . gettingStartedDaysText($days) . ' ' . date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
    }

    // Response times count business hours - open tickets are stamped afresh with the ones just saved
    getSlaSettings(true);
    $restamped = $hours_changed ? restampOpenSlaTickets() : 0;
    if (!empty($_POST['standard_slas'])) {
        $given = gettingStartedApplySlas();
        if ($given) {
            $lines[] = 'Standard response times for ' . implode(', ', array_keys($given));
        }
    }

    if ($lines) {
        logAudit("Settings", "Edit", "$session_name set up support in Get started: " . escapeSql(implode('; ', $lines) . ($restamped ? "; $restamped open tickets restamped" : '')));
    }
    flashAlert($lines ? '<strong>Support</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes') : 'Nothing about support changed', $lines ? 'success' : 'info');
    redirect(gettingStartedUrl(gettingStartedAfter('support')));

}

if (isset($_POST['getting_started_team'])) {

    validateCSRFToken();

    $name = trim((string) ($_POST['team_name'] ?? ''));
    $email = trim((string) ($_POST['team_email'] ?? ''));

    // An invite waiting for them holds a seat already - it is this one
    $open = userInviteFor($email);
    if (agentUserLimitReached($open && userInviteState($open) === 'pending' ? intval($open['invite_id']) : 0)) {
        flashAlert(agentUserLimitMessage(), 'error');
        redirect(gettingStartedUrl('team'));
    }

    $errors = [];
    if ($name === '') {
        $errors['team_name'] = 'Their name, as it shows on tickets.';
    } elseif (mb_strlen($name) > 200) {
        $errors['team_name'] = 'Up to 200 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
        $errors['team_email'] = 'The address they sign in with.';
    }
    $identity = null;
    if (!$errors) {
        $identity_error = '';
        $identity = userCheckIdentity(['name' => $name, 'email' => $email], 0, $identity_error);
        if (!$identity) {
            $errors['team_email'] = html_entity_decode(strip_tags($identity_error), ENT_QUOTES, 'UTF-8');
        }
    }
    $role_error = '';
    $role = userCheckRole($_POST['team_role'] ?? 0, 0, $role_error);
    if (!$role) {
        $errors['team_role'] = $role_error;
    }
    if ($errors) {
        settingsRefuse('getting_started_team', $errors, gettingStartedUrl('team'));
    }
    [$name, $email] = $identity;

    // With mail, an invite - they choose their own password from the link
    if (userInvitesReady() && userInviteMailReady()) {
        $saved = userInviteSave(['name' => $name, 'email' => $email, 'role_id' => $role['role_id'], 'force_mfa' => 0, 'access' => []]);
        $send_error = '';
        if (!userInviteSend($saved['invite_id'], true, $send_error)) {
            if (!$saved['again']) {
                mysqli_query($mysqli, "DELETE FROM user_invites WHERE invite_id = " . intval($saved['invite_id']));
            }
            flashAlert($send_error, 'error');
            redirect(gettingStartedUrl('team'));
        }
        logAudit("User", "Invite", "$session_name invited " . escapeSql("$name ($email) as {$role['role_name']}") . " in Get started" . ($saved['again'] ? ', again' : ''));
        flashAlert('<strong>' . escapeHtml($name) . '</strong> is invited - an email with a link to join is on the way to <strong>' . escapeHtml($email) . '</strong>'
            . (gettingStartedSteps()['cron']['done'] ? '' : ', once cron is running') . '. They choose their own password');
        redirect(gettingStartedUrl('team'));
    }

    // Without, their password - made here and shown once, for you to give them and them to change
    $password = generateReadablePassword();
    mysqli_query($mysqli, "INSERT INTO users SET user_name = '" . escapeSql($name) . "', user_email = '" . escapeSql($email) . "', user_password = '', user_role_id = " . intval($role['role_id']));
    $user_id = intval(mysqli_insert_id($mysqli));
    userSetPassword($user_id, $password);
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = 0");
    if ($open) {
        userInviteCancel($open['invite_id']);
    }

    logAudit("User", "Create", "$session_name created user " . escapeSql("$name ({$role['role_name']})") . " in Get started", 0, $user_id);

    flashAlert('<strong>' . escapeHtml($name) . '</strong> is added. Their password is <code>' . escapeHtml($password) . '</code> - give it to them to sign in with and change. It isn\'t shown again', 'warning');
    redirect(gettingStartedUrl('team'));

}

if (isset($_POST['getting_started_content'])) {

    validateCSRFToken();

    require_once __DIR__ . '/starter_content_model.php';

    $packs = starterContentPacks();
    // Registry order is load order - categories before products, ticket templates before project templates
    $picked = array_values(array_intersect(array_keys($packs), array_map('strval', (array) ($_POST['packs'] ?? []))));
    if (!$picked) {
        flashAlert('Tick what to add - or Skip for now', 'info');
        redirect(gettingStartedUrl('content'));
    }

    $added = 0;
    $summary = [];
    $none_ticked = 0;
    foreach ($picked as $pack) {
        // The items ticked under Pick which - the whole pack when its list wasn't sent
        $only = isset($_POST['picked'][$pack]) ? array_map('strval', array_filter((array) ($_POST['items'][$pack] ?? []), 'is_string')) : null;
        if ($only === []) {
            $none_ticked++;
            continue;
        }
        $pack_added = starterContentLoad($mysqli, $pack, false, $only);
        if ($pack_added) {
            $added += $pack_added;
            $summary[] = "$pack_added " . $packs[$pack]['label'];
        }
    }

    if (!$added && $none_ticked === count($picked)) {
        flashAlert('Nothing is ticked under Pick which - tick what to add, or Skip for now', 'info');
        redirect(gettingStartedUrl('content'));
    }
    if ($added) {
        logAudit("Starter Content", "Create", "$session_name loaded starter content in Get started - " . escapeSql(implode(', ', $summary)));
        flashAlert('Added <strong>' . $added . '</strong> things to start from: ' . escapeHtml(implode(', ', $summary)));
    } else {
        flashAlert('Nothing to add - everything ticked is here already', 'info');
    }
    redirect(gettingStartedUrl(gettingStartedAfter('content')));

}

if (isset($_POST['getting_started_skip'])) {

    validateCSRFToken();

    $step = (string) $_POST['getting_started_skip'];
    if (!isset(GETTING_STARTED_STEPS[$step])) {
        redirect(gettingStartedUrl());
    }
    gettingStartedSkip($step);

    redirect(gettingStartedUrl(gettingStartedAfter($step)));

}

if (isset($_POST['getting_started_skip_all'])) {

    validateCSRFToken();

    $left = gettingStartedSkipAll();
    logAudit("Settings", "Edit", "$session_name skipped all of Get started" . ($left ? ' - ' . escapeSql(implode(', ', $left)) . ' left to do' : ''));
    flashAlert('Get started is skipped - each step is in Admin &gt; Get started whenever you want it', 'info');

    redirect('/agent/dashboard.php');

}

if (isset($_POST['getting_started_dismiss'])) {

    validateCSRFToken();

    gettingStartedDismiss();
    logAudit("Settings", "Edit", "$session_name stopped Get started showing on the dashboard");
    flashAlert('Get started is off the dashboard - it is in Admin whenever you want it', 'info');

    redirect('/agent/dashboard.php');

}
