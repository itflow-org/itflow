<?php

/*
 * Admin > Settings > Telemetry - whether cron's nightly job (the TELEMETRY
 * block of cron/nightly_tasks.php) tells the ITFlow project how this install
 * is used, and exactly what that report holds. Basic (1) and Detailed (2)
 * were two choices that sent the same report, so it is one switch; on saves
 * Detailed, and an install on Basic stays on it until switched.
 * Saved by edit_telemetry_settings (admin/post/settings_telemetry.php).
 */

require_once "includes/inc_all_admin.php";

$company = settingsCurrent(['company_name', 'company_website', 'company_city', 'company_state', 'company_country', 'company_currency'], 'companies');

echo settingsPageStart('telemetry');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('report', 'Nightly report', 'Knowing how ITFlow is used - how many installs, how big, which parts of it - tells the ITFlow project where its time goes furthest. <a href="https://docs.itflow.org/telemetry" target="_blank" rel="noopener">More<i class="fas fa-external-link-alt small ms-1"></i></a>') ?>
                <?= settingsSwitch('config_telemetry', $config_telemetry, ['value' => 2, 'help' => 'Once a night, when cron runs the nightly jobs. Nothing is sent while it is off.']) ?>

                <div class="mt-3 small" data-telemetry-what>
                    <div class="fw-semibold mb-1">What the report holds</div>
                    <dl class="itflow-settings-list mb-2">
                        <dt>This install</dt>
                        <dd><span class="font-monospace"><?= escapeHtml((string) ($installation_id ?? '')) ?></span>, and the version it is on</dd>
                        <?php foreach (['company_name' => 'Company', 'company_website' => 'Website', 'company_city' => 'City', 'company_state' => 'State', 'company_country' => 'Country', 'company_currency' => 'Currency'] as $column => $label) { ?>
                            <dt><?= $label ?></dt>
                            <dd><?= trim((string) $company[$column]) !== '' ? escapeHtml($company[$column]) : '<span class="text-body-secondary">-</span>' ?></dd>
                        <?php } ?>
                        <dt>How many</dt>
                        <dd>clients, contacts, tickets, invoices, quotes, assets, credentials, documents, users and the rest - counts only, never what is in them</dd>
                        <dt>Switched on</dt>
                        <dd>which modules are in use, cron, email to ticket, and the theme</dd>
                    </dl>
                    <div class="text-body-secondary">No names of clients or contacts, no email addresses, nothing anyone wrote.</div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_telemetry_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
