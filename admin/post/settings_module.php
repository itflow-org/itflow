<?php

/*
 * Admin > Settings > Modules (admin/settings_module.php):
 * edit_module_settings saves which modules are in use, and the white-label
 * key. A key that isn't one is refused rather than saved; the key already in
 * use, run out, can stay while the modules are saved - it is just not in
 * force. Empty, white-labelling is off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_module_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_module_enable_itdoc', 'config_module_enable_ticketing', 'config_module_enable_accounting', 'config_client_portal_enable',
        'config_whitelabel_enabled', 'config_whitelabel_key']);

    $after = [];
    foreach (['config_module_enable_itdoc', 'config_module_enable_ticketing', 'config_module_enable_accounting', 'config_client_portal_enable'] as $module) {
        $after[$module] = empty($_POST[$module]) ? 0 : 1;
    }

    $key = trim((string) ($_POST['config_whitelabel_key'] ?? ''));
    $errors = [];
    if ($key === '') {
        $after['config_whitelabel_key'] = '';
        $after['config_whitelabel_enabled'] = 0;
    } elseif (validateWhitelabelKey($key)) {
        $after['config_whitelabel_key'] = $key;
        $after['config_whitelabel_enabled'] = 1;
    } elseif ($key === (string) $before['config_whitelabel_key']) {
        // The one already in use, run out - kept, not in force
        $after['config_whitelabel_enabled'] = 0;
    } else {
        $errors['config_whitelabel_key'] = 'That isn\'t a white-label key, or it has run out.';
    }
    if ($errors) {
        settingsRefuse('module', $errors);
    }

    settingsSave('module', $before, $after);

    redirect();

}
