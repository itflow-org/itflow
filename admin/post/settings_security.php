<?php

/*
 * Admin > Settings > Security (admin/settings_security.php):
 * edit_security_settings saves the sign-in page's message and login key, how
 * long a browser is remembered, when admins hear of failed sign-ins, and
 * shared links. A login key required with none set - which would leave
 * agents no way in but the database - is refused, as is a key that couldn't
 * be typed into an address, or a time out of range. Nothing is saved then.
 * A choice not offered settles to the usual one.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_security_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_login_message', 'config_login_key_required', 'config_login_key_secret', 'config_login_remember_me_expire',
        'config_login_alert_failures', 'config_share_max_hours', 'config_share_require_protection']);

    // What was sent, and what is saved for anything that wasn't - a switch not sent is off
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);

    $after = [
        'config_login_message' => rtrim(str_replace("\r\n", "\n", (string) $in['config_login_message'])),
        'config_login_key_required' => empty($_POST['config_login_key_required']) ? 0 : 1,
        'config_login_key_secret' => trim((string) $in['config_login_key_secret']),
        'config_login_remember_me_expire' => intval($in['config_login_remember_me_expire']),
        'config_login_alert_failures' => intval($in['config_login_alert_failures']),
        'config_share_max_hours' => intval($in['config_share_max_hours']),
        'config_share_require_protection' => empty($_POST['config_share_require_protection']) ? 0 : 1,
    ];

    $errors = [];
    if ($after['config_login_key_secret'] !== '' && !preg_match('/^\w{3,99}$/', $after['config_login_key_secret'])) {
        $errors['config_login_key_secret'] = '3 to 99 letters, numbers and underscores - it goes in the address.';
    } elseif ($after['config_login_key_required'] && $after['config_login_key_secret'] === '') {
        $errors['config_login_key_secret'] = 'Required with no key, agents couldn\'t sign in at all - set one, or switch it off.';
    }
    $days = $after['config_login_remember_me_expire'];
    if ($days !== intval($before['config_login_remember_me_expire']) && ($days < 1 || $days > 365)) {
        $errors['config_login_remember_me_expire'] = 'From 1 day to 365.';
    }
    // Choices from a list settle to one of them, as they always have
    if (!isset(signInAlertOptions()[$after['config_login_alert_failures']])) {
        $after['config_login_alert_failures'] = 10;
    }
    if (!isset(shareMaxOptions()[$after['config_share_max_hours']])) {
        $after['config_share_max_hours'] = 720;
    }
    if ($errors) {
        settingsRefuse('security', $errors);
    }

    settingsSave('security', $before, $after);

    redirect();

}
