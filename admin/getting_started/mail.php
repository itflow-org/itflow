<?php

/*
 * Get started - Email: the SMTP server ITFlow sends through and the address
 * it sends as, and - if you like - a support mailbox it reads and turns into
 * tickets (getting_started_mail, checked as Admin > Mail checks it -
 * settingsMailServerCheck()). Microsoft 365 and Google Workspace sign in with
 * an app registered with them, so those are set up in Admin > Mail; with one
 * in use, this step keeps it and asks only for the address.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_mail = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_smtp_provider, config_smtp_host, config_smtp_port, config_smtp_encryption, config_smtp_username, config_smtp_password,
    config_imap_provider, config_imap_host, config_imap_port, config_imap_encryption, config_imap_username, config_imap_password,
    config_mail_from_email, config_mail_from_name, config_ticket_from_email, config_ticket_email_parse FROM settings WHERE company_id = 1")) ?: [];
$gs_mail_oauth = in_array($gs_mail['config_smtp_provider'] ?? '', ['google_oauth', 'microsoft_oauth'], true);
$gs_encryption = function ($starttls_port, $ssl_port) {
    return ['tls' => "STARTTLS - usually port $starttls_port", 'ssl' => "SSL/TLS - usually port $ssl_port", '' => 'None'];
};
$gs_secret = function ($saved, $empty) {
    return trim((string) $saved) !== '' ? 'Saved - type a new one to replace it' : $empty;
};
$gs_parse = intval($gs_mail['config_ticket_email_parse'] ?? 0) === 1;

?>
<form action="post.php" method="post" autocomplete="off" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <?php if ($gs_mail_oauth) { ?>
        <div class="alert alert-success d-flex gap-2 align-items-start" data-gs-mail-oauth>
            <i class="fas fa-check-circle mt-1"></i>
            <div>Email goes out through <?= ($gs_mail['config_smtp_provider'] === 'microsoft_oauth') ? 'Microsoft 365' : 'Google Workspace' ?>, set up in <a href="settings_mail.php">Admin &gt; Mail</a>.</div>
        </div>
    <?php } else { ?>
        <h2 class="h6 fw-semibold">Sending</h2>
        <p class="small text-body-secondary">Your mail host's SMTP server, as its setup guide gives it. Microsoft 365 or Google Workspace sign in with an app you register with them instead - set that up in <a href="settings_mail.php?tab=oauth">Admin &gt; Mail</a>, then come back.</p>
        <input type="hidden" name="config_smtp_provider" value="standard_smtp">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <?= settingsInput('config_smtp_host', (string) ($gs_mail['config_smtp_host'] ?? ''), ['icon' => 'fa-server', 'label' => 'Server', 'required' => true, 'maxlength' => 200, 'placeholder' => 'smtp.example.com']) ?>
            </div>
            <div class="col-sm-5">
                <?= settingsInput('config_smtp_port', !empty($gs_mail['config_smtp_port']) ? intval($gs_mail['config_smtp_port']) : 587, ['icon' => 'fa-plug', 'type' => 'number', 'label' => 'Port', 'required' => true, 'min' => 1, 'max' => 65535, 'inputmode' => 'numeric']) ?>
            </div>
            <div class="col-sm-7">
                <?= settingsSelect('config_smtp_encryption', !empty($gs_mail['config_smtp_host']) ? (string) $gs_mail['config_smtp_encryption'] : 'tls', $gs_encryption(587, 465), ['icon' => 'fa-lock', 'label' => 'Encryption']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('config_smtp_username', (string) ($gs_mail['config_smtp_username'] ?? ''), ['icon' => 'fa-user', 'label' => 'Username', 'maxlength' => 200, 'placeholder' => "Usually the mailbox's own address", 'autocomplete' => 'off']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('config_smtp_password', '', ['icon' => 'fa-key', 'type' => 'password', 'label' => 'Password', 'maxlength' => 200, 'autocomplete' => 'new-password',
                    'placeholder' => $gs_secret($gs_mail['config_smtp_password'] ?? '', 'Or an app password')]) ?>
            </div>
        </div>
    <?php } ?>

    <h2 class="h6 fw-semibold">Sent as</h2>
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <?= settingsInput('config_mail_from_email', (string) ($gs_mail['config_mail_from_email'] ?? ''), ['icon' => 'fa-envelope', 'type' => 'email', 'label' => 'From address', 'required' => true, 'maxlength' => 200, 'placeholder' => 'help@example.com']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('config_mail_from_name', trim((string) ($gs_mail['config_mail_from_name'] ?? '')) !== '' ? (string) $gs_mail['config_mail_from_name'] : (string) ($gs_company['company_name'] ?? ''), ['icon' => 'fa-signature', 'label' => 'From name', 'maxlength' => 200]) ?>
        </div>
    </div>
    <p class="small text-body-secondary">Invoices, quotes and ticket emails come from it too, until you give them their own in Admin &gt; Mail.</p>

    <?php if (intval($config_module_enable_ticketing)) { ?>
    <div class="itflow-gs-optional mb-4" data-gs-mail-parse>
        <?= settingsSwitch('config_ticket_email_parse', $gs_parse, ['id' => 'gsTicketParse', 'label' => 'Turn email to a support mailbox into tickets', 'data' => ['gs-reveal' => 'gsImap'],
            'help' => 'ITFlow reads the mailbox every minute: a new email is a new ticket, a reply goes on its ticket.' . (trim((string) ($gs_mail['config_ticket_from_email'] ?? '')) === '' ? ' Ticket emails then come from this address.' : '')]) ?>
        <div id="gsImap" class="row g-3 mt-0" data-gs-reveal-target<?= settingsChecked('config_ticket_email_parse', $gs_parse) ? '' : ' hidden' ?>>
            <input type="hidden" name="config_imap_provider" value="<?= in_array($gs_mail['config_imap_provider'] ?? '', ['google_oauth', 'microsoft_oauth'], true) ? escapeHtml($gs_mail['config_imap_provider']) : 'standard_imap' ?>">
            <?php if (!in_array($gs_mail['config_imap_provider'] ?? '', ['google_oauth', 'microsoft_oauth'], true)) { ?>
                <div class="col-sm-7">
                    <?= settingsInput('config_imap_host', (string) ($gs_mail['config_imap_host'] ?? ''), ['icon' => 'fa-server', 'label' => 'IMAP server', 'maxlength' => 200, 'placeholder' => 'imap.example.com']) ?>
                </div>
                <div class="col-sm-5">
                    <?= settingsInput('config_imap_port', !empty($gs_mail['config_imap_port']) ? intval($gs_mail['config_imap_port']) : 993, ['icon' => 'fa-plug', 'type' => 'number', 'label' => 'Port', 'min' => 1, 'max' => 65535, 'inputmode' => 'numeric']) ?>
                </div>
                <div class="col-12">
                    <?= settingsSelect('config_imap_encryption', !empty($gs_mail['config_imap_host']) ? (string) $gs_mail['config_imap_encryption'] : 'ssl', $gs_encryption(143, 993), ['icon' => 'fa-lock', 'label' => 'Encryption']) ?>
                </div>
            <?php } ?>
            <div class="col-sm-6">
                <?= settingsInput('config_imap_username', (string) ($gs_mail['config_imap_username'] ?? ''), ['icon' => 'fa-inbox', 'type' => 'email', 'label' => 'Support mailbox', 'maxlength' => 200, 'placeholder' => 'support@example.com', 'autocomplete' => 'off']) ?>
            </div>
            <?php if (!in_array($gs_mail['config_imap_provider'] ?? '', ['google_oauth', 'microsoft_oauth'], true)) { ?>
                <div class="col-sm-6">
                    <?= settingsInput('config_imap_password', '', ['icon' => 'fa-key', 'type' => 'password', 'label' => 'Its password', 'maxlength' => 200, 'autocomplete' => 'new-password',
                        'placeholder' => $gs_secret($gs_mail['config_imap_password'] ?? '', 'Or an app password')]) ?>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" name="getting_started_mail" value="continue" class="btn btn-primary" data-gs-save>Save and continue<i class="fas fa-arrow-right ms-2"></i></button>
        <button type="submit" name="getting_started_mail" value="test" class="btn btn-outline-primary" data-gs-test><i class="fas fa-paper-plane me-2"></i>Save and send me a test</button>
    </div>
</form>
