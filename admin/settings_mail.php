<?php

/*
 * Admin > Settings > Mail - sending (an SMTP server, or Google Workspace /
 * Microsoft 365 by OAuth), receiving the mailbox email to ticket reads, the
 * OAuth app both share, the addresses and names emails come from, tests, and
 * a preview of every email ITFlow sends. Each tab's form saves on its own
 * (admin/post/settings_mail.php), with the frame's save bar. Passwords, the
 * OAuth secret and its refresh token are never put back into the page: left
 * empty, they stay as saved.
 */

require_once "includes/inc_all_admin.php";

// ---- Tiny status dot for tab labels ----------------------------------------
function renderMailStatusDot($on) {
    return $on
        ? '<i class="fas fa-circle text-success ms-2" style="font-size:.5rem;vertical-align:middle;" title="Configured"></i>'
        : '<i class="far fa-circle text-muted ms-2" style="font-size:.5rem;vertical-align:middle;" title="Not configured"></i>';
}

$smtp_on = !empty($config_smtp_provider);
$imap_on = !empty($config_imap_provider);
$oauth_needed = in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)
             || in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true);

// ---- Active tab -------------------------------------------------------------
// The tab lives in the URL (?tab=receiving) so it can be linked, bookmarked, survives a
//  reload, and lets the POST handlers send you back to the tab you saved from
$mail_tabs = ['sending', 'receiving', 'oauth', 'from', 'tests', 'previews'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $mail_tabs, true) ? $_GET['tab'] : 'sending';

// A direct link to the OAuth tab reveals it even when no OAuth provider is selected yet
if ($active_tab === 'oauth') {
    $oauth_needed = true;
}

// ---- OAuth callback URI (for Entra App Registration) ------------------------
if (defined('BASE_URL') && !empty(BASE_URL)) {
    $mail_oauth_callback_uri = rtrim((string) BASE_URL, '/') . '/admin/oauth_microsoft_mail_callback.php';
} else {
    $mail_oauth_callback_uri = 'https://' . rtrim((string) $config_base_url, '/') . '/admin/oauth_microsoft_mail_callback.php';
}

// ---- Readiness checks (drive the Tests tab) --------------------------------
$smtp_standard_ready = !empty($config_smtp_host) && !empty($config_smtp_port)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name);

$smtp_oauth_ready = in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_smtp_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$imap_standard_ready = !empty($config_imap_username) && !empty($config_imap_password)
    && !empty($config_imap_host) && !empty($config_imap_port);

$imap_oauth_ready = in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true)
    && !empty($config_imap_username)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_imap_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$oauth_provider_for_test = '';
if (in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true)) {
    $oauth_provider_for_test = $config_imap_provider;
} elseif (in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)) {
    $oauth_provider_for_test = $config_smtp_provider;
}

$oauth_has_required_fields = !empty($oauth_provider_for_test)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($oauth_provider_for_test !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$send_ready = $smtp_standard_ready || $smtp_oauth_ready;
$imap_ready = $imap_standard_ready || $imap_oauth_ready;

// ---- What the fields offer -------------------------------------------------
// Sending or receiving through - off, a server signed in to with a password, or Google / Microsoft by OAuth
$provider_choices = function ($server) {
    return [
        '' => $server === 'smtp' ? 'Off - ITFlow sends no email' : 'Off - no mailbox is read',
        "standard_$server" => ($server === 'smtp' ? 'An SMTP server' : 'An IMAP server') . ' - with a username and password',
        'google_oauth' => 'Google Workspace - signed in with OAuth',
        'microsoft_oauth' => 'Microsoft 365 - signed in with OAuth',
    ];
};
// A server's encryption - and anything else saved long ago, kept as it is until changed
$encryption_choices = function ($saved, $starttls_port, $ssl_port) {
    $choices = ['' => 'None', 'tls' => "STARTTLS - usually port $starttls_port", 'ssl' => "SSL/TLS - usually port $ssl_port"];
    if ((string) $saved !== '' && !isset($choices[(string) $saved])) {
        $choices[(string) $saved] = (string) $saved;
    }
    return $choices;
};
// A password or secret field says whether one is saved, never what it is
$secret_placeholder = function ($saved, $empty) {
    return trim((string) $saved) !== '' ? 'Saved - type a new one to replace it' : $empty;
};

// The OAuth app, as far as it is set up - and what to say about it where a provider uses it
$oauth_app_set = !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret);
$oauth_connected = $oauth_app_set && !empty($config_mail_oauth_refresh_token);
$oauth_pointer = function ($server) use ($oauth_connected) {
    ob_start(); ?>
    <div class="itflow-settings-field" id="<?= $server ?>_oauth_pointer" style="display:none;">
        <div class="itflow-settings-note d-flex flex-wrap align-items-center gap-2" data-oauth-pointer="<?= $oauth_connected ? 'connected' : 'not-connected' ?>">
            <span class="me-auto">
                <?php if ($oauth_connected) { ?>
                    <i class="fas fa-fw fa-check-circle text-success me-1"></i>Signs in with the app on the OAuth tab - it is connected.
                <?php } else { ?>
                    <i class="fas fa-fw fa-exclamation-circle text-warning me-1"></i>Signs in with the app on the OAuth tab - it isn't connected yet.
                <?php } ?>
            </span>
            <button type="button" class="btn btn-sm btn-outline-primary goto-oauth text-nowrap"><i class="fas fa-fw fa-key me-1"></i>Open OAuth</button>
        </div>
    </div>
    <?php return ob_get_clean();
};

// One mailbox's From address and name, on From addresses
$from_box = function ($key, $title, $about, $email, $name, $email_placeholder, $name_placeholder) {
    return settingsSectionStart("from-$key", $title, $about)
        . '<div class="row g-3"><div class="col-md-7">'
        . settingsInput("config_{$key}_from_email", $email, ['icon' => 'fa-envelope', 'type' => 'email', 'maxlength' => 200, 'label' => 'Address', 'placeholder' => $email_placeholder])
        . '</div><div class="col-md-5">'
        . settingsInput("config_{$key}_from_name", $name, ['icon' => 'fa-signature', 'maxlength' => 200, 'label' => 'Name', 'placeholder' => $name_placeholder])
        . '</div></div>'
        . settingsSectionEnd();
};

// Whether email to ticket reads the Receiving mailbox
if (!$config_module_enable_ticketing) {
    $parse_state = 'Ticketing is off in <a href="settings_module.php">Modules</a>.';
} elseif (!$config_ticket_email_parse) {
    $parse_state = 'Email to ticket is off - this mailbox isn\'t read until it is turned on in <a href="settings_ticket.php#setting=config_ticket_email_parse">Tickets</a>.';
} else {
    $parse_state = 'Email to ticket is on in <a href="settings_ticket.php#setting=config_ticket_email_parse">Tickets</a>.';
}
?>

<?= settingsPageStart('mail') ?>

<div class="card itflow-settings-card">
    <div class="card-body">

        <ul class="nav nav-tabs pt-3" id="mailTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'sending') { echo 'active'; } ?>" href="?tab=sending" data-bs-target="#tab-sending">
                    <i class="fas fa-fw fa-paper-plane me-1"></i>Sending<?= renderMailStatusDot($smtp_on) ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'receiving') { echo 'active'; } ?>" href="?tab=receiving" data-bs-target="#tab-receiving">
                    <i class="fas fa-fw fa-inbox me-1"></i>Receiving<?= renderMailStatusDot($imap_on) ?>
                </a>
            </li>
            <li class="nav-item" id="tabitem-oauth" style="<?= $oauth_needed ? '' : 'display:none;' ?>">
                <a class="nav-link <?php if ($active_tab === 'oauth') { echo 'active'; } ?>" href="?tab=oauth" data-bs-target="#tab-oauth">
                    <i class="fas fa-fw fa-key me-1"></i>OAuth<?= renderMailStatusDot($oauth_connected) ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'from') { echo 'active'; } ?>" href="?tab=from" data-bs-target="#tab-from">
                    <i class="fas fa-fw fa-at me-1"></i>From Addresses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'tests') { echo 'active'; } ?>" href="?tab=tests" data-bs-target="#tab-tests">
                    <i class="fas fa-fw fa-vial me-1"></i>Tests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($active_tab === 'previews') { echo 'active'; } ?>" href="?tab=previews" data-bs-target="#tab-previews">
                    <i class="fas fa-fw fa-eye me-1"></i>Previews
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- ============================ SENDING / SMTP ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'sending') { echo 'show active'; } ?>" id="tab-sending" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off" data-settings-form>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="sending">

                    <?= settingsSectionStart('sending', 'How email goes out', 'Every email ITFlow sends - invoices, quotes, ticket replies, alerts, password resets - goes out through one mailbox. Each waits in the <a href="mail_queue.php">Mail Queue</a> and goes within a minute.') ?>
                        <?= settingsSelect('config_smtp_provider', (string) $config_smtp_provider, $provider_choices('smtp'), ['id' => 'config_smtp_provider', 'icon' => 'fa-paper-plane', 'label' => 'Send through',
                            'help' => '<span id="smtp_provider_hint"></span>']) ?>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSectionStart('smtp-server', 'The server', 'Your mail host\'s SMTP server, as its setup guide gives it.', 'smtp_conn_fields') ?>
                        <?= settingsInput('config_smtp_host', $config_smtp_host, ['icon' => 'fa-server', 'label' => 'Server', 'required' => true, 'maxlength' => 200, 'placeholder' => 'smtp.example.com']) ?>
                        <div class="row g-3 mt-0">
                            <div class="col-sm-5">
                                <?= settingsInput('config_smtp_port', !empty($config_smtp_port) ? intval($config_smtp_port) : '', ['icon' => 'fa-plug', 'type' => 'number', 'label' => 'Port', 'required' => true,
                                    'min' => 1, 'max' => 65535, 'inputmode' => 'numeric', 'placeholder' => '587']) ?>
                            </div>
                            <div class="col-sm-7">
                                <?= settingsSelect('config_smtp_encryption', (string) $config_smtp_encryption, $encryption_choices($config_smtp_encryption, 587, 465), ['icon' => 'fa-lock', 'label' => 'Encryption']) ?>
                            </div>
                        </div>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSectionStart('smtp-sign-in', 'Signing in', 'The account ITFlow signs in to send as.', 'smtp_sign_in') ?>
                        <?= settingsInput('config_smtp_username', $config_smtp_username, ['id' => 'config_smtp_username', 'icon' => 'fa-user', 'label' => 'Username', 'maxlength' => 200,
                            'placeholder' => 'Usually the mailbox\'s own address', 'help' => '<span id="smtp_user_hint"></span>']) ?>
                        <?= settingsInput('config_smtp_password', '', ['icon' => 'fa-key', 'type' => 'password', 'label' => 'Password', 'maxlength' => 200, 'autocomplete' => 'new-password',
                            'placeholder' => $secret_placeholder($config_smtp_password, 'The mailbox\'s password, or an app password'),
                            'help' => 'Many hosts want an app password here rather than the account\'s own.']) ?>
                        <?= $oauth_pointer('smtp') ?>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSaveBar('edit_mail_smtp_settings') ?>
                </form>
            </div>

            <!-- ============================ RECEIVING / IMAP ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'receiving') { echo 'show active'; } ?>" id="tab-receiving" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off" data-settings-form>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="receiving">

                    <?= settingsSectionStart('receiving', 'Where email comes in', 'Email to ticket reads this mailbox each minute: a new email becomes a ticket, and a reply goes on its ticket. ' . $parse_state) ?>
                        <?= settingsSelect('config_imap_provider', (string) $config_imap_provider, $provider_choices('imap'), ['id' => 'config_imap_provider', 'icon' => 'fa-inbox', 'label' => 'Read mail from',
                            'help' => '<span id="imap_provider_hint"></span>']) ?>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSectionStart('imap-server', 'The server', 'Your mail host\'s IMAP server, as its setup guide gives it.', 'imap_conn_fields') ?>
                        <?= settingsInput('config_imap_host', $config_imap_host, ['icon' => 'fa-server', 'label' => 'Server', 'required' => true, 'maxlength' => 200, 'placeholder' => 'imap.example.com']) ?>
                        <div class="row g-3 mt-0">
                            <div class="col-sm-5">
                                <?= settingsInput('config_imap_port', !empty($config_imap_port) ? intval($config_imap_port) : '', ['icon' => 'fa-plug', 'type' => 'number', 'label' => 'Port', 'required' => true,
                                    'min' => 1, 'max' => 65535, 'inputmode' => 'numeric', 'placeholder' => '993']) ?>
                            </div>
                            <div class="col-sm-7">
                                <?= settingsSelect('config_imap_encryption', (string) $config_imap_encryption, $encryption_choices($config_imap_encryption, 143, 993), ['icon' => 'fa-lock', 'label' => 'Encryption']) ?>
                            </div>
                        </div>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSectionStart('imap-sign-in', 'Signing in', 'The mailbox ITFlow reads - one kept for tickets, as support@ or help@, rather than someone\'s own.', 'imap_sign_in') ?>
                        <?= settingsInput('config_imap_username', $config_imap_username, ['id' => 'config_imap_username', 'icon' => 'fa-user', 'label' => 'Username', 'maxlength' => 200, 'required' => true,
                            'placeholder' => 'tickets@example.com', 'help' => '<span id="imap_user_hint"></span>']) ?>
                        <?= settingsInput('config_imap_password', '', ['icon' => 'fa-key', 'type' => 'password', 'label' => 'Password', 'maxlength' => 200, 'autocomplete' => 'new-password',
                            'placeholder' => $secret_placeholder($config_imap_password, 'The mailbox\'s password, or an app password')]) ?>
                        <?= $oauth_pointer('imap') ?>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSaveBar('edit_mail_imap_settings') ?>
                </form>
            </div>

            <!-- ============================ OAUTH ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'oauth') { echo 'show active'; } ?>" id="tab-oauth" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off" data-settings-form>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="oauth">
                    <?= settingsEnterSaves('edit_mail_oauth_settings') ?>

                    <?= settingsSectionStart('oauth-app', 'The app', 'Google Workspace and Microsoft 365 let ITFlow sign in through an app you register with them, so no mailbox password is kept. Sending and receiving share it.') ?>
                        <div class="itflow-settings-field">
                            <div class="itflow-settings-note" id="oauth_hint"></div>
                        </div>
                        <?= settingsInput('config_mail_oauth_client_id', $config_mail_oauth_client_id, ['id' => 'config_mail_oauth_client_id', 'icon' => 'fa-id-badge', 'label' => 'Client ID', 'maxlength' => 255,
                            'help' => 'Empty forgets the app - its secret and refresh token too.']) ?>
                        <?= settingsInput('config_mail_oauth_client_secret', '', ['id' => 'config_mail_oauth_client_secret', 'icon' => 'fa-key', 'type' => 'password', 'label' => 'Client secret', 'maxlength' => 255,
                            'autocomplete' => 'new-password', 'placeholder' => $secret_placeholder($config_mail_oauth_client_secret, 'The secret\'s value'),
                            'help' => 'A secret runs out - put the new one here before it does.']) ?>
                        <div id="tenant_row" class="mt-3" style="display:none;">
                            <?= settingsInput('config_mail_oauth_tenant_id', $config_mail_oauth_tenant_id, ['icon' => 'fa-building', 'label' => 'Tenant ID', 'maxlength' => 255,
                                'placeholder' => '00000000-0000-0000-0000-000000000000', 'help' => 'The Directory (tenant) ID, from the app registration\'s Overview.']) ?>
                        </div>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSectionStart('oauth-connect', 'Connecting the mailbox', 'Signing in to the mailbox once gives ITFlow a refresh token - lasting permission to use it. ITFlow renews what it signs in with from it, on its own.') ?>
                        <div class="itflow-settings-field" data-oauth-state="<?= $oauth_connected ? 'connected' : 'not-connected' ?>">
                            <span class="badge <?= $oauth_connected ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $oauth_connected ? 'Connected' : 'Not connected' ?></span>
                            <span class="small text-body-secondary ms-1">
                                <?php if ($oauth_connected) { ?>
                                    A refresh token is saved.<?= !empty($config_mail_oauth_access_token_expires_at) && strtotime($config_mail_oauth_access_token_expires_at) > time()
                                        ? ' What ITFlow signs in with now is good until ' . escapeHtml(date('g:i A', strtotime($config_mail_oauth_access_token_expires_at))) . ', then renewed.' : '' ?>
                                <?php } elseif (!$oauth_app_set) { ?>
                                    Set up the app first.
                                <?php } else { ?>
                                    No refresh token yet.
                                <?php } ?>
                            </span>
                        </div>
                        <div class="itflow-settings-field" id="ms_connect_group" style="display:none;">
                            <label class="form-label" for="mailOauthRedirect">Redirect address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                                <input type="text" class="form-control font-monospace" id="mailOauthRedirect" value="<?= escapeHtml($mail_oauth_callback_uri) ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-target="#mailOauthRedirect" title="Copy"><i class="fa fa-fw fa-copy"></i></button>
                            </div>
                            <div class="form-text">Under Authentication in the app registration, as a Web platform.</div>
                            <button type="submit" name="oauth_connect_microsoft_mail" class="btn btn-outline-primary mt-3">
                                <i class="fab fa-fw fa-microsoft me-2"></i>Connect Microsoft 365
                            </button>
                            <div class="form-text">Saves the app above, then you sign in to Microsoft as the mailbox - the refresh token is kept when you come back.</div>
                        </div>
                        <?= settingsInput('config_mail_oauth_refresh_token', '', ['icon' => 'fa-sync-alt', 'type' => 'password', 'label' => 'Refresh token', 'autocomplete' => 'new-password',
                            'placeholder' => $secret_placeholder($config_mail_oauth_refresh_token, 'Paste one in'), 'help' => '<span id="oauth_refresh_hint"></span>']) ?>
                    <?= settingsSectionEnd() ?>

                    <?= settingsSaveBar('edit_mail_oauth_settings') ?>
                </form>
            </div>

            <!-- ============================ FROM ADDRESSES ============================ -->
            <div class="tab-pane fade <?php if ($active_tab === 'from') { echo 'show active'; } ?>" id="tab-from" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off" data-settings-form>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="tab" value="from">

                    <div class="itflow-settings-note mt-4">
                        <i class="fas fa-fw fa-info-circle me-1"></i>Each address has to be one the sending mailbox may send as - itself, an alias of it, or a shared mailbox it can send as.
                    </div>

                    <?= $from_box('mail', 'System emails', 'Share links, password resets, alerts - anything without its own address below.', $config_mail_from_email, $config_mail_from_name, 'noreply@example.com', (string) ($session_company_name ?? 'Your company')) ?>
                    <?= $from_box('invoice', 'Invoices', 'Invoices, receipts, statements and payment reminders.', $config_invoice_from_email, $config_invoice_from_name, 'billing@example.com', 'Billing') ?>
                    <?= $from_box('quote', 'Quotes', 'Quotes sent to clients. Empty - they come from the invoices address.', $config_quote_from_email, $config_quote_from_name, 'sales@example.com', 'Sales') ?>
                    <?= $from_box('ticket', 'Tickets', 'Ticket emails to clients. Make it the mailbox Receiving reads, so their replies go on the ticket.', $config_ticket_from_email, $config_ticket_from_name, 'support@example.com', 'Support') ?>

                    <?= settingsSaveBar('edit_mail_from_settings') ?>
                </form>
            </div>

            <!-- ============================ TESTS ============================ -->
            <div class="tab-pane fade pt-4 pb-4 <?php if ($active_tab === 'tests') { echo 'show active'; } ?>" id="tab-tests" role="tabpanel">

                <?php $mail_queue = mailQueueSummary(); ?>
                <div class="d-flex flex-wrap align-items-center gap-2 border rounded p-3 mb-4" data-mail-queue-summary>
                    <i class="fas fa-fw fa-inbox text-body-secondary"></i>
                    <span class="me-auto">
                        <strong>Mail Queue</strong>
                        <span class="text-body-secondary">- <?= $mail_queue['last_sent'] ? 'last sent ' . escapeHtml(timeAgo($mail_queue['last_sent'])) : 'nothing sent yet' ?></span>
                        <?php if ($mail_queue['waiting']) { ?><span class="badge text-bg-primary ms-1"><?= $mail_queue['waiting'] ?> waiting</span><?php } ?>
                        <?php if ($mail_queue['retrying']) { ?><span class="badge text-bg-warning ms-1"><?= $mail_queue['retrying'] ?> retrying</span><?php } ?>
                        <?php if ($mail_queue['failed_day']) { ?><span class="badge text-bg-danger ms-1"><?= $mail_queue['failed_day'] ?> failed in the last day</span><?php } ?>
                    </span>
                    <a class="btn btn-sm btn-outline-secondary" href="mail_queue.php<?= $mail_queue['failed_day'] ? '?show=failed' : '' ?>">Open the Mail Queue</a>
                </div>

                <?php if (!$send_ready && !$imap_ready && !$oauth_has_required_fields) { ?>
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-fw fa-info-circle me-2"></i>Finish configuring Sending, Receiving, or OAuth (plus at least one From address) to unlock the tests.
                    </div>
                <?php } ?>

                <?php if ($send_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-paper-plane me-2"></i>Send a Test Email</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <div class="input-group">
                            <select class="form-select select2" name="test_email" required>
                                <option value="">- Select a From address -</option>
                                <?php if ($config_mail_from_email) { ?><option value="1"><?= escapeHtml($config_mail_from_name) ?> (<?= escapeHtml($config_mail_from_email) ?>)</option><?php } ?>
                                <?php if ($config_invoice_from_email) { ?><option value="2"><?= escapeHtml($config_invoice_from_name) ?> (<?= escapeHtml($config_invoice_from_email) ?>)</option><?php } ?>
                                <?php if ($config_quote_from_email) { ?><option value="3"><?= escapeHtml($config_quote_from_name) ?> (<?= escapeHtml($config_quote_from_email) ?>)</option><?php } ?>
                                <?php if ($config_ticket_from_email) { ?><option value="4"><?= escapeHtml($config_ticket_from_name) ?> (<?= escapeHtml($config_ticket_from_email) ?>)</option><?php } ?>
                            </select>
                            <input type="email" class="form-control" name="email_to" placeholder="recipient@example.com">
                                <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane me-2"></i>Send</button>
                        </div>
                    </form>
                </div>
                <?php } ?>

                <?php if ($imap_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-plug me-2"></i>Test IMAP Connection</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox me-2"></i>Test IMAP</button>
                    </form>
                </div>
                <?php } ?>

                <?php if ($oauth_has_required_fields) { ?>
                <div>
                    <h6 class="text-bold"><i class="fas fa-fw fa-sync-alt me-2"></i>Test OAuth Token Refresh</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="tab" value="tests">
                        <input type="hidden" name="oauth_provider" value="<?= htmlspecialchars($oauth_provider_for_test) ?>">
                        <p class="text-muted mb-2">Validates the refresh token and stores a new access token for <?= $oauth_provider_for_test === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace' ?>.</p>
                        <button type="submit" name="test_oauth_token_refresh" class="btn btn-success"><i class="fas fa-fw fa-sync-alt me-2"></i>Test OAuth Token Refresh</button>
                    </form>
                </div>
                <?php } ?>

            </div>

            <!-- ============================ PREVIEWS ============================ -->
            <?php
            // Every email ITFlow sends, made on sample records that are thrown away again (functions/email_preview.php)
            $preview_catalog = emailPreviewCatalog();
            $preview_groups = [];
            foreach ($preview_catalog as $preview_key => $preview_entry) {
                $preview_groups[$preview_entry['group']][$preview_key] = $preview_entry;
            }
            $preview_first = isset($_GET['email'], $preview_catalog[$_GET['email']]) ? (string) $_GET['email'] : (string) array_key_first($preview_catalog);
            ?>
            <div class="tab-pane fade pt-4 pb-4 <?php if ($active_tab === 'previews') { echo 'show active'; } ?>" id="tab-previews" role="tabpanel">

                <p class="text-body-secondary">
                    Every email ITFlow sends, as it goes out - your logo, your details and your From addresses. Each is made just now on sample records
                    (<?= escapeHtml(EMAIL_PREVIEW_PEOPLE['client']) ?>, its contact <?= escapeHtml(EMAIL_PREVIEW_PEOPLE['contact'][0]) ?> and an agent, <?= escapeHtml(EMAIL_PREVIEW_PEOPLE['agent'][0]) ?>)
                    that are thrown away again, so looking sends and saves nothing.
                </p>

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                            <input type="search" class="form-control" placeholder="Find an email" data-preview-filter aria-label="Find an email">
                        </div>
                        <div class="list-group itflow-mail-preview-list" style="max-height: 75vh; overflow-y: auto;">
                            <?php foreach ($preview_groups as $preview_group => $preview_entries) { ?>
                                <div class="list-group-item bg-body-tertiary small fw-semibold text-uppercase text-body-secondary py-1" data-preview-group><?= escapeHtml($preview_group) ?></div>
                                <?php foreach ($preview_entries as $preview_key => $preview_entry) { ?>
                                    <button type="button" class="list-group-item list-group-item-action <?= $preview_key === $preview_first ? 'active' : '' ?>" data-preview="<?= escapeHtml($preview_key) ?>"
                                        data-preview-text="<?= escapeHtml(strtolower($preview_group . ' ' . $preview_entry['label'] . ' ' . $preview_entry['to'] . ' ' . $preview_entry['when'])) ?>">
                                        <div class="fw-semibold"><?= escapeHtml($preview_entry['label']) ?></div>
                                        <div class="small opacity-75"><?= escapeHtml($preview_entry['to']) ?></div>
                                    </button>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card mb-0" id="mailPreview">
                            <div class="card-header">
                                <div class="d-flex flex-wrap align-items-start gap-2">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold text-break" data-preview-subject></div>
                                        <div class="small text-body-secondary text-break"><span class="fw-semibold">From</span> <span data-preview-from></span></div>
                                        <div class="small text-body-secondary text-break"><span class="fw-semibold">To</span> <span data-preview-to></span> <span data-preview-also></span></div>
                                        <div class="small text-body-secondary"><i class="fas fa-fw fa-bolt me-1"></i><span data-preview-when></span><span class="ms-2 badge text-bg-secondary d-none" data-preview-calendar><i class="fas fa-calendar-alt me-1"></i>Calendar file attached</span></div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Width">
                                            <button type="button" class="btn btn-outline-secondary active" data-preview-width="100%" title="As on a computer"><i class="fas fa-fw fa-desktop"></i></button>
                                            <button type="button" class="btn btn-outline-secondary" data-preview-width="390px" title="As on a phone"><i class="fas fa-fw fa-mobile-alt"></i></button>
                                        </div>
                                        <form action="post.php" method="post" class="m-0">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="email" value="<?= escapeHtml($preview_first) ?>" data-preview-key>
                                            <button type="submit" name="send_email_preview" class="btn btn-sm btn-primary" <?= emailReady() ? '' : 'disabled title="Set up Sending first"' ?>>
                                                <i class="fas fa-fw fa-paper-plane me-1"></i>Send me a copy
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0 bg-body-tertiary text-center">
                                <div class="alert alert-warning m-3 d-none" data-preview-error></div>
                                <iframe title="Email preview" sandbox="allow-same-origin" data-preview-frame style="width: 100%; height: 600px; border: 0; display: block; margin: 0 auto; background: #f1f3f5;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
    // Admin > Mail > Previews - each email fetched as it is picked (admin/mail_preview.php)
    (function () {
        var card = document.getElementById('mailPreview');
        if (!card) {
            return;
        }
        var frame = card.querySelector('[data-preview-frame]');
        var csrf = <?= json_encode($_SESSION['csrf_token']) ?>;
        var items = Array.from(document.querySelectorAll('[data-preview]'));
        var loading = 0;

        function fit() {
            try {
                var doc = frame.contentDocument;
                if (doc && doc.documentElement) {
                    frame.style.height = Math.max(400, doc.documentElement.scrollHeight) + 'px';
                }
            } catch (e) {
                // nothing to measure
            }
        }
        frame.addEventListener('load', fit);

        function show(key, push) {
            items.forEach(function (item) {
                item.classList.toggle('active', item.getAttribute('data-preview') === key);
            });
            card.querySelector('[data-preview-key]').value = key;
            var url = new URL(window.location.href);
            url.searchParams.set('tab', 'previews');
            url.searchParams.set('email', key);
            history.replaceState(history.state, '', url);
            var mine = ++loading;
            card.style.opacity = '0.6';
            fetch('mail_preview.php?email=' + encodeURIComponent(key) + '&csrf_token=' + encodeURIComponent(csrf), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (mine !== loading) {
                        return;
                    }
                    card.style.opacity = '';
                    card.querySelector('[data-preview-subject]').textContent = data.subject || data.label || '';
                    card.querySelector('[data-preview-from]').textContent = data.from || '-';
                    card.querySelector('[data-preview-to]').textContent = data.to || data.to_who || '-';
                    card.querySelector('[data-preview-also]').textContent = data.also && data.also.length ? '(and ' + data.also.join(', ') + ')' : '';
                    card.querySelector('[data-preview-when]').textContent = 'Sent when: ' + (data.when || '');
                    card.querySelector('[data-preview-calendar]').classList.toggle('d-none', !data.calendar);
                    var error = card.querySelector('[data-preview-error]');
                    error.textContent = data.error || '';
                    error.classList.toggle('d-none', !data.error);
                    frame.srcdoc = data.html || '';
                })
                .catch(function () {
                    card.style.opacity = '';
                    var error = card.querySelector('[data-preview-error]');
                    error.textContent = 'The preview could not be loaded.';
                    error.classList.remove('d-none');
                });
        }

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                show(item.getAttribute('data-preview'));
            });
        });
        card.querySelectorAll('[data-preview-width]').forEach(function (button) {
            button.addEventListener('click', function () {
                card.querySelectorAll('[data-preview-width]').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                frame.style.width = button.getAttribute('data-preview-width');
                setTimeout(fit, 50);
            });
        });

        // Find an email - its group heading stays while any of its emails show
        var filter = document.querySelector('[data-preview-filter]');
        filter.addEventListener('input', function () {
            var words = filter.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
            var heading = null;
            var any = false;
            Array.from(document.querySelector('.itflow-mail-preview-list').children).forEach(function (row) {
                if (row.hasAttribute('data-preview-group')) {
                    if (heading) {
                        heading.classList.toggle('d-none', !any);
                    }
                    heading = row;
                    any = false;
                    return;
                }
                var text = row.getAttribute('data-preview-text');
                var match = words.every(function (word) { return text.indexOf(word) !== -1; });
                row.classList.toggle('d-none', !match);
                any = any || match;
            });
            if (heading) {
                heading.classList.toggle('d-none', !any);
            }
        });

        // The first one, when the tab is opened
        var started = false;
        function start() {
            if (!started && document.getElementById('tab-previews').classList.contains('active')) {
                started = true;
                show(<?= json_encode($preview_first) ?>);
            }
        }
        document.querySelectorAll('#mailTabs .nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                setTimeout(start, 0);
            });
        });
        window.addEventListener('popstate', function () {
            setTimeout(start, 0);
        });
        start();
    })();
</script>


<script>
// Admin > Mail - the tabs, and the fields each provider takes
(function () {
    function setDisabled(c, d) { if (c) c.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !!d); }
    function show(el, v) { if (el) el.style.display = v ? '' : 'none'; }
    function toggle(el, v) { show(el, v); setDisabled(el, !v); }
    function val(s) { return (s && s.value) || ''; }
    function isStd(v) { return v === 'standard_imap' || v === 'standard_smtp'; }
    function isOauth(v) { return v === 'google_oauth' || v === 'microsoft_oauth'; }
    function byId(id) { return document.getElementById(id); }
    function setHtml(el, html) { if (el) el.innerHTML = html; }

    // ---- Self-contained tab controller (no dependency on the BS tab plugin) ----
    // Set when the page was opened directly on the OAuth tab - stops the provider pass hiding it
    const forcedOauthTab = <?= $active_tab === 'oauth' ? 'true' : 'false' ?>;
    const navLinks = Array.from(document.querySelectorAll('#mailTabs .nav-link'));
    const panes = ['tab-sending', 'tab-receiving', 'tab-oauth', 'tab-from', 'tab-tests', 'tab-previews']
        .map(id => document.getElementById(id)).filter(Boolean);

    // Server rendered the initial tab; keep the URL honest as the user clicks around
    function syncTabUrl(target, push) {
        const tab = target.replace('#tab-', '');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        // pushState, not replaceState, so back/forward step through the tabs
        // instead of jumping straight off the settings page
        history[push ? 'pushState' : 'replaceState']({ tab: tab }, '', url);
    }

    function activateTab(target, push) {
        syncTabUrl(target, push !== false);
        navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('data-bs-target') === target));
        panes.forEach(p => {
            const on = ('#' + p.id) === target;
            p.classList.toggle('active', on);
            p.classList.toggle('show', on);
        });
    }
    window.addEventListener('popstate', function () {
        const tab = new URL(window.location.href).searchParams.get('tab') || 'sending';
        activateTab('#tab-' + tab, false);
    });

    navLinks.forEach(l => l.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(l.getAttribute('data-bs-target'), true);
    }));

    // ---- Provider-driven fields ----
    // Each side: the server section (a server signed in to with a password), the
    // signing-in section (both), the password (a server only), and the pointer to
    // the OAuth tab (Google and Microsoft)
    function side(server) {
        return {
            select: byId('config_' + server + '_provider'),
            conn: byId(server + '_conn_fields'),
            signIn: byId(server + '_sign_in'),
            pass: byId('setting-config_' + server + '_password'),
            pointer: byId(server + '_oauth_pointer'),
            hint: byId(server + '_provider_hint'),
            userLabel: document.querySelector('label[for="config_' + server + '_username"]'),
            userInput: byId('config_' + server + '_username'),
            userHint: byId(server + '_user_hint')
        };
    }
    const smtp = side('smtp');
    const imap = side('imap');

    const oauthTabItem = byId('tabitem-oauth');
    const tenantRow = byId('tenant_row');
    const msConnect = byId('ms_connect_group');
    const oauthHint = byId('oauth_hint');
    const oauthClientId = byId('config_mail_oauth_client_id');
    const refreshHint = byId('oauth_refresh_hint');

    const words = {
        smtp: {
            off: 'Nothing is sent, and nothing is kept to send later - invoices, ticket replies and password resets included.',
            std: 'Any mail host - your own server, or a service like SMTP2GO, Mailgun or Amazon SES.',
            google_oauth: 'Signs in with the Google app on the OAuth tab.',
            microsoft_oauth: 'Signs in with the Microsoft app on the OAuth tab.',
            userStd: 'Empty if the server takes email without signing in - the password is then forgotten.',
            userOauth: 'The licensed user the app was connected with - not a shared mailbox. Empty: the address each email comes from.',
            labelStd: 'Username',
            labelOauth: 'Signs in as',
            placeholderStd: 'Usually the mailbox\'s own address',
            placeholderOauth: 'user@example.com'
        },
        imap: {
            off: 'Email to ticket has nothing to read.',
            std: 'Any mail host that offers IMAP.',
            google_oauth: 'Signs in with the Google app on the OAuth tab.',
            microsoft_oauth: 'Signs in with the Microsoft app on the OAuth tab.',
            userStd: 'Usually the mailbox\'s address.',
            userOauth: 'The mailbox\'s address - the account the app was connected with, or one it has full access to.',
            labelStd: 'Username',
            labelOauth: 'Mailbox',
            placeholderStd: 'tickets@example.com',
            placeholderOauth: 'tickets@example.com'
        }
    };

    function renderSide(s, server) {
        const v = val(s.select);
        const w = words[server];
        toggle(s.conn, isStd(v));
        toggle(s.signIn, isStd(v) || isOauth(v));
        toggle(s.pass, isStd(v));
        show(s.pointer, isOauth(v));
        if (s.hint) s.hint.textContent = isStd(v) ? w.std : isOauth(v) ? w[v] : w.off;
        if (s.userLabel) s.userLabel.firstChild.textContent = (isOauth(v) ? w.labelOauth : w.labelStd) + (s.userLabel.children.length ? ' ' : '');
        if (s.userInput) s.userInput.placeholder = isOauth(v) ? w.placeholderOauth : w.placeholderStd;
        if (s.userHint) s.userHint.textContent = isOauth(v) ? w.userOauth : w.userStd;
    }

    function render() {
        const sv = val(smtp.select), iv = val(imap.select);
        renderSide(smtp, 'smtp');
        renderSide(imap, 'imap');

        const anyOauth = isOauth(sv) || isOauth(iv);
        const anyMs = sv === 'microsoft_oauth' || iv === 'microsoft_oauth';
        const anyGoogle = sv === 'google_oauth' || iv === 'google_oauth';

        show(oauthTabItem, anyOauth || forcedOauthTab);
        toggle(tenantRow, anyMs);
        toggle(msConnect, anyMs);
        if (oauthClientId) oauthClientId.placeholder = anyMs ? 'Application (client) ID - 00000000-0000-0000-0000-000000000000' : anyGoogle ? 'xxxxxxxxxxxx.apps.googleusercontent.com' : '';
        setHtml(oauthHint, '<i class="fas fa-fw fa-info-circle me-1"></i>' + (anyMs && anyGoogle
            ? 'Sending and receiving each pick a different one - both have to use the same, as they share this app.'
            : anyMs
                ? 'Microsoft 365: register an app in Microsoft Entra (App registrations) with the redirect address below, the IMAP.AccessAsUser.All and SMTP.Send permissions, and a client secret. Its ID, secret and tenant go here.'
                : anyGoogle
                    ? 'Google Workspace: make an OAuth client ID (a Web application) in Google Cloud. Its ID and secret go here; the refresh token needs the https://mail.google.com/ scope.'
                    : 'Used when Sending or Receiving is set to Google Workspace or Microsoft 365.'));
        if (refreshHint) refreshHint.textContent = anyMs
            ? 'Connect fetches it - or paste in one made elsewhere.'
            : 'From signing in to the mailbox with this app - Google\'s OAuth 2.0 Playground can make one with its ID and secret.';

        if (!anyOauth && !forcedOauthTab) {
            const oauthLink = document.querySelector('#mailTabs .nav-link[data-bs-target="#tab-oauth"]');
            if (oauthLink && oauthLink.classList.contains('active')) activateTab('#tab-sending');
        }
    }

    if (smtp.select) smtp.select.addEventListener('change', render);
    if (imap.select) imap.select.addEventListener('change', render);
    // Undo changes puts the provider back - and with it the fields it shows
    document.querySelectorAll('#mailTabs ~ .tab-content form[data-settings-form]').forEach(f => f.addEventListener('settings:reset', render));
    document.querySelectorAll('.goto-oauth').forEach(b => b.addEventListener('click', () => activateTab('#tab-oauth')));
    render();
})();
</script>

<?php require_once "../includes/footer.php"; ?>
