<?php

/*
 * Admin > Settings - every settings page at a glance (functions/settings.php),
 * each with how it is set now; the settings that need looking at, at the top;
 * and Find a setting, which searches every setting by its name or what it is
 * for and goes straight to it (js/settings.js).
 */

require_once "includes/inc_all_admin.php";

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_city, company_state, company_country, company_logo, company_locale, company_currency, company_email
    FROM companies WHERE company_id = 1")) ?: [];
$localization = settingsLocalizationArrays();
$themes = settingsThemes();
$retention = appLogRetention();
$forward = logForwardSettings();
$on_off = function ($on) {
    return $on ? 'on' : 'off';
};
$days = function ($n) {
    return $n == 1 ? '1 day' : intval($n) . ' days';
};

// How each page is set now - a line or two, and whether it is switched off
$start_pages = ['dashboard.php' => 'the dashboard', 'clients.php' => 'clients', 'tickets.php' => 'tickets', 'invoices.php' => 'invoices'];
$modules_on = array_keys(array_filter([
    'IT documentation' => $config_module_enable_itdoc,
    'Ticketing' => $config_module_enable_ticketing,
    'Invoicing' => $config_module_enable_accounting,
    'Client portal' => $config_client_portal_enable,
]));
$where = array_filter([$company['company_city'] ?? '', $company['company_country'] ?? '']);
$summaries = [
    'company' => escapeHtml($company['company_name'] ?? '') . ($where ? ' · ' . escapeHtml(implode(', ', $where)) : '') . (empty($company['company_logo']) ? ' · <span class="text-warning-emphasis">no logo yet</span>' : ''),
    'localization' => escapeHtml(implode(' · ', array_filter([$localization['locales'][$company['company_locale'] ?? ''] ?? '', $company['company_currency'] ?? '', $config_timezone]))),
    'theme' => '<span class="itflow-settings-dot theme-' . escapeHtml($config_theme) . '"></span>' . escapeHtml($themes[$config_theme] ?? $config_theme) . (file_exists(dirname(__DIR__) . '/uploads/favicon.ico') ? ' · its own tab icon' : ''),
    'default' => 'Opens on ' . escapeHtml($start_pages[$config_start_page] ?? $config_start_page) . ' · ' . ($config_default_net_terms ? 'net ' . intval($config_default_net_terms) : 'due on receipt')
        . ($config_default_hourly_rate > 0 ? ' · ' . escapeHtml(numfmt_format_currency($currency_format, $config_default_hourly_rate, $session_company_currency)) . ' an hour' : ''),
    'ticket' => 'Next ' . escapeHtml($config_ticket_prefix . $config_ticket_next_number) . ' · email to ticket ' . $on_off($config_ticket_email_parse),
    'project' => 'Next ' . escapeHtml($config_project_prefix . $config_project_next_number),
    'quote' => 'Next ' . escapeHtml($config_quote_prefix . $config_quote_next_number),
    'invoice' => 'Next ' . escapeHtml($config_invoice_prefix . $config_invoice_next_number) . ' · reminders ' . $on_off($config_send_invoice_reminders) . ' · late fees ' . $on_off($config_invoice_late_fee_enable),
    'mail' => 'Sending ' . ($config_smtp_provider ? 'on' : '<span class="text-danger-emphasis">off</span>') . ' · receiving ' . $on_off($config_imap_provider),
    'notification' => 'Domain alerts ' . $on_off($config_enable_alert_domain_expire) . ' · ticket emails to clients ' . $on_off($config_ticket_client_general_notifications),
    'security' => 'Login key ' . $on_off($config_login_key_required) . ' · devices remembered ' . $days($config_login_remember_me_expire),
    'client_portal' => 'Two-step sign-in ' . ($config_client_portal_require_mfa ? 'required' : 'optional'),
    'identity' => 'Microsoft Entra ' . ($config_azure_client_id ? 'set up' : 'not set up'),
    'module' => $modules_on ? escapeHtml(implode(', ', $modules_on)) : 'None in use',
    'logs' => 'Audit log ' . ($retention['audit'] ? 'kept ' . $days($retention['audit']) : 'kept forever') . ' · ' . ($forward['enabled'] ? 'sent to ' . escapeHtml($forward['host']) : 'not sent on'),
    'telemetry' => $config_telemetry ? 'Nightly report on' : 'Off',
];

// What needs looking at
$attention = [];
if (empty($config_smtp_provider)) {
    $attention[] = ['danger', 'fa-envelope', 'Mail sending is off', 'No email leaves ITFlow - not invoices, reminders, ticket replies or password resets.', 'settings_mail.php?tab=sending#setting=config_smtp_provider', 'Set up sending'];
}
if ($config_ticket_email_parse && $config_module_enable_ticketing && empty($config_imap_provider)) {
    $attention[] = ['warning', 'fa-inbox', 'Email to ticket has no mailbox', 'Turning emails into tickets is on, but no mailbox is set up for ITFlow to read.', 'settings_mail.php?tab=receiving#setting=config_imap_provider', 'Set up receiving'];
}
if ($config_login_key_required && trim((string) $config_login_key_secret) === '') {
    $attention[] = ['warning', 'fa-key', 'A login key is required, but there isn\'t one', 'The sign-in page asks for a key nobody has been given.', 'settings_security.php#setting=config_login_key_secret', 'Set the key'];
}
if (trim((string) $config_timezone) === '') {
    $attention[] = ['warning', 'fa-clock', 'No timezone', 'Dates and times are in the server\'s timezone until one is picked.', 'settings_localization.php#setting=config_timezone', 'Pick one'];
}

$pages = settingsPages();
$groups = settingsGroups();

?>

<div class="itflow-settings">

    <div class="itflow-settings-head">
        <div class="itflow-settings-head-icon"><i class="fas fa-fw fa-sliders-h"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 mb-1">Settings</h1>
            <p class="text-body-secondary mb-0">How ITFlow is set up, for everyone in it. Your own preferences are under your name, top right.</p>
        </div>
    </div>

    <!-- Find a setting -->
    <div class="itflow-settings-find mb-4" data-settings-find>
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
            <input type="search" class="form-control" placeholder="Find a setting - late fee, logo, two-step, timezone..." aria-label="Find a setting" autocomplete="off" data-settings-find-input autofocus>
        </div>
        <div class="list-group itflow-settings-results" data-settings-results hidden></div>
        <script type="application/json" data-settings-index><?= json_encode(settingsSearchIndex(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
    </div>

    <?php if ($attention) { ?>
        <div class="mb-4" data-settings-attention>
            <?php foreach ($attention as [$tone, $icon, $title, $text, $url, $action]) { ?>
                <div class="alert alert-<?= $tone ?> d-flex flex-wrap align-items-center gap-3 py-2 mb-2">
                    <i class="fas fa-fw <?= $icon ?>"></i>
                    <div class="flex-grow-1 min-w-0"><strong><?= escapeHtml($title) ?></strong> - <?= escapeHtml($text) ?></div>
                    <a href="<?= escapeHtml($url) ?>" class="btn btn-sm btn-<?= $tone ?>"><?= escapeHtml($action) ?><i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php foreach ($groups as $group_key => $group_title) { ?>
        <section class="mb-4" data-settings-group="<?= $group_key ?>">
            <h2 class="h6 text-uppercase text-body-secondary fw-semibold mb-2"><?= escapeHtml($group_title) ?></h2>
            <div class="itflow-settings-tiles">
                <?php foreach ($pages as $page_key => $page) {
                    if ($page['group'] !== $group_key) {
                        continue;
                    }
                    $available = settingsPageAvailable($page); ?>
                    <a href="<?= escapeHtml($page['url']) ?>" class="itflow-settings-tile<?= $available ? '' : ' is-off' ?>" data-settings-tile="<?= $page_key ?>">
                        <span class="itflow-settings-tile-icon"><i class="fas fa-fw <?= escapeHtml($page['icon']) ?>"></i></span>
                        <span class="min-w-0">
                            <span class="itflow-settings-tile-title"><?= escapeHtml($page['title']) ?></span>
                            <span class="itflow-settings-tile-about"><?= escapeHtml($page['about']) ?></span>
                            <span class="itflow-settings-tile-now" data-settings-now><?= $available ? $summaries[$page_key] : 'Not in use - turned off in Modules' ?></span>
                        </span>
                    </a>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

</div>

<?php
require_once "../includes/footer.php";
