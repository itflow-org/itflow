<?php

/*
 * Admin > Settings > Modules - the parts of ITFlow in use: IT documentation,
 * ticketing, invoicing and the client portal, each switched off hiding it
 * (nothing is deleted); and white-labelling the client portal with a key.
 * Saved by edit_module_settings (admin/post/settings_module.php).
 */

require_once "includes/inc_all_admin.php";

// The white-label key as it stands - good, in its week's grace after it ran out, or not a key
$whitelabel_key = trim((string) $config_whitelabel_key);
$whitelabel = $whitelabel_key !== '' ? validateWhitelabelKey($whitelabel_key) : false;
$whitelabel_expires = $whitelabel ? strtotime((string) $whitelabel['expires']) : 0;

$modules = [
    'config_module_enable_itdoc' => [$config_module_enable_itdoc, 'fa-laptop', 'A client\'s assets, credentials, documents, networks, domains, certificates and the rest of their IT documentation.'],
    'config_module_enable_ticketing' => [$config_module_enable_ticketing, 'fa-life-ring', 'Tickets, recurring tickets and projects - and their settings.'],
    'config_module_enable_accounting' => [$config_module_enable_accounting, 'fa-file-invoice-dollar', 'Quotes, invoices, payments, expenses, accounts and the money reports - and their settings.'],
    'config_client_portal_enable' => [$config_client_portal_enable, 'fa-user-shield', 'Where your clients\' contacts sign in to raise tickets, see their invoices and pay.'],
];

echo settingsPageStart('module');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('modules', 'Modules', 'Switching one off takes it out of the menus for everyone. Nothing in it is deleted - switched back on, it is all there.') ?>
                <?php foreach ($modules as $name => [$on, $icon, $about]) { ?>
                    <div class="itflow-settings-module d-flex gap-3 align-items-start">
                        <span class="itflow-settings-tile-icon"><i class="fas fa-fw <?= $icon ?>"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <?= settingsSwitch($name, $on, ['help' => $about]) ?>
                        </div>
                    </div>
                <?php } ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('whitelabel', 'White-label', 'A key from the ITFlow project takes "Powered by ITFlow" off the sign-in page, the client portal, shared links and client exports.') ?>
                <?php if ($whitelabel && $whitelabel_expires >= time()) { ?>
                    <div class="alert alert-success d-flex gap-2 py-2" data-whitelabel-state="active">
                        <i class="fas fa-check-circle mt-1"></i>
                        <div>
                            <strong>White-labelled - thank you for supporting ITFlow.</strong>
                            <div class="small"><?= escapeHtml((string) ($whitelabel['description'] ?? '')) ?> · <?= escapeHtml((string) ($whitelabel['organisation'] ?? '')) ?> · until <?= escapeHtml(date('M j, Y', $whitelabel_expires)) ?></div>
                        </div>
                    </div>
                <?php } elseif ($whitelabel) { ?>
                    <div class="alert alert-warning d-flex gap-2 py-2" data-whitelabel-state="grace">
                        <i class="fas fa-exclamation-triangle mt-1"></i>
                        <div><strong>The key ran out on <?= escapeHtml(date('M j, Y', $whitelabel_expires)) ?>.</strong> White-labelling stops a week after - put a new key in to keep it.</div>
                    </div>
                <?php } elseif ($whitelabel_key !== '') { ?>
                    <div class="alert alert-secondary d-flex gap-2 py-2" data-whitelabel-state="expired">
                        <i class="fas fa-info-circle mt-1"></i>
                        <div>The key below has run out, so "Powered by ITFlow" is back.</div>
                    </div>
                <?php } ?>
                <?= settingsTextarea('config_whitelabel_key', $whitelabel_key, ['rows' => 3, 'placeholder' => 'Paste the key here', 'help' => 'Left empty, "Powered by ITFlow" shows.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_module_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
