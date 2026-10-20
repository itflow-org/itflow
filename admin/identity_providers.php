<?php

/*
 * Admin > Settings > Single sign-on - client contacts signing in to the
 * portal with their Microsoft 365 account (client/login_microsoft.php),
 * through an app registered in Microsoft Entra. The secret is never put back
 * into the page: left empty it stays as saved. Saved by
 * edit_identity_provider (admin/post/identity_provider.php).
 */

require_once "includes/inc_all_admin.php";

$redirect_uri = 'https://' . preg_replace('#^https?://#', '', rtrim((string) $config_base_url, '/')) . '/client/login_microsoft.php';
$secret_saved = trim((string) $config_azure_client_secret) !== '';

echo settingsPageStart('identity');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">
            <?php if (!$config_client_portal_enable) { ?>
                <div class="alert alert-warning py-2 small"><i class="fas fa-exclamation-triangle me-2"></i>The client portal is off - turn it on in <a href="settings_module.php">Modules</a>.</div>
            <?php } ?>

            <?= settingsSectionStart('entra', 'Microsoft Entra', 'A contact with portal access signs in with their Microsoft 365 work account - its email has to be theirs in ITFlow. Register an app in Microsoft Entra for accounts in any organisation\'s directory, with the redirect address below, and put its ID and a secret here.') ?>
                <div class="itflow-settings-field" data-sso-state>
                    <span class="badge <?= $config_azure_client_id ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $config_azure_client_id ? 'Set up' : 'Not set up' ?></span>
                    <span class="small text-body-secondary ms-1"><?= $config_azure_client_id ? 'The portal\'s sign-in page offers Sign in with Microsoft.' : 'Contacts sign in with a password.' ?></span>
                </div>
                <div class="itflow-settings-field" data-setting="redirect_uri">
                    <label class="form-label" for="settingsRedirectUri">Redirect address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        <input type="text" class="form-control font-monospace" id="settingsRedirectUri" value="<?= escapeHtml($redirect_uri) ?>" readonly data-sso-redirect>
                        <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-target="#settingsRedirectUri" title="Copy"><i class="fa fa-fw fa-copy"></i></button>
                    </div>
                    <div class="form-text">Under Authentication in the app registration, as a Web platform.</div>
                </div>
                <?= settingsInput('config_azure_client_id', $config_azure_client_id, ['icon' => 'fa-fingerprint', 'maxlength' => 200, 'placeholder' => 'e721e3b6-01d6-50e8-7f22-c84d951a52e7',
                    'help' => 'From the app\'s Overview. Empty turns signing in with Microsoft off, and forgets the secret.']) ?>
                <?= settingsInput('config_azure_client_secret', '', ['icon' => 'fa-key', 'type' => 'password', 'maxlength' => 200, 'autocomplete' => 'new-password',
                    'placeholder' => $secret_saved ? 'Saved - type a new one to replace it' : 'From Certificates & secrets',
                    'help' => 'Its Value, not its ID. A secret runs out - put the new one here before it does.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_identity_provider') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
