<?php

/*
 * Admin > Settings > Security - the sign-in page (its message, and a login
 * key agents need in its address), how long two-step sign-in remembers a
 * device, when admins hear of failed sign-ins, and shared links. Saved by
 * edit_security_settings (admin/post/settings_security.php).
 */

require_once "includes/inc_all_admin.php";

// Read here rather than with every page's settings, so a page still opens before the update that adds it
$config_login_alert_failures = signInAlertLimit();

$login_url = rtrim('https://' . preg_replace('#^https?://#', '', (string) $config_base_url), '/') . '/login.php?key=';
$login_key_shown = (string) settingsValue('config_login_key_secret', $config_login_key_secret);

echo settingsPageStart('security');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('sign-in', 'The sign-in page', 'Agents and client contacts sign in on the same page. A login key keeps the agents\' way in out of sight - without the key in its address, the page is for client contacts only.') ?>
                <?= settingsTextarea('config_login_message', $config_login_message, ['rows' => 3, 'maxlength' => 65000, 'placeholder' => 'Authorised users only',
                    'help' => 'Shown above the sign-in form - plain text, each new line kept.']) ?>
                <?= settingsSwitch('config_login_key_required', $config_login_key_required, ['help' => 'Agents sign in only from the address with the key in it.']) ?>
                <div data-settings-depends-on="config_login_key_required">
                    <?= settingsInput('config_login_key_secret', $config_login_key_secret, ['icon' => 'fa-key', 'maxlength' => 99, 'pattern' => '\w{3,99}',
                        'title' => '3 to 99 letters, numbers and underscores', 'placeholder' => 'MYSECRET', 'autocomplete' => 'off',
                        'help' => '3 to 99 letters, numbers and underscores - easy for your agents to remember.']) ?>
                    <div class="itflow-settings-note mt-2 text-break" data-login-url>Agents sign in at <span class="font-monospace"><?= escapeHtml($login_url) ?><span data-login-url-key><?= escapeHtml($login_key_shown) ?></span></span></div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('two-step', 'Two-step sign-in', 'An agent giving their code can have the browser remembered, and isn\'t asked for one in it again until this runs out.') ?>
                <?= settingsInput('config_login_remember_me_expire', intval($config_login_remember_me_expire), ['icon' => 'fa-clock', 'type' => 'number', 'min' => 1, 'max' => 365, 'required' => true,
                    'suffix' => 'days', 'help' => 'Each agent can see and forget their remembered browsers in their own Security settings.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('failed', 'Failed sign-ins', 'Counted over ' . SIGN_IN_WINDOW_MINUTES . ' minutes, from one address or against one account. An address is turned away after ' . SIGN_IN_LOCKOUT . ', whatever is set here.') ?>
                <?= settingsSelect('config_login_alert_failures', intval($config_login_alert_failures), signInAlertOptions(), ['icon' => 'fa-bell',
                    'help' => 'Each admin gets a notification and an email.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('shares', 'Shared links', 'When an agent shares a login, note, document or file with someone outside ITFlow.') ?>
                <?= settingsSelect('config_share_max_hours', intval($config_share_max_hours), shareMaxOptions(), ['icon' => 'fa-hourglass-half',
                    'help' => 'The longest an agent can pick - a link stops working then, or sooner.']) ?>
                <?= settingsSwitch('config_share_require_protection', $config_share_require_protection, ['help' => 'A shared password or secure note opens only with a passphrase the agent gives, or a code emailed to the person it is for.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_security_settings') ?>
        </div>
    </div>
</form>

<script>
(function () {
    // The address agents sign in at, as the key is typed
    var input = document.querySelector('[name="config_login_key_secret"]');
    var out = document.querySelector('[data-login-url-key]');
    if (!input || !out) {
        return;
    }
    function draw() {
        out.textContent = input.value;
    }
    input.addEventListener('input', draw);
    input.form.addEventListener('settings:reset', draw);
})();
</script>

<?php
require_once "../includes/footer.php";
