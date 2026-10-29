<?php

/*
 * Admin > Settings > Telemetry (admin/settings_telemetry.php):
 * edit_telemetry_settings switches the nightly report on (2) or off (0). An
 * install on Basic (1), which sent the same report, stays on it while the
 * switch is left on.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_telemetry_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_telemetry']);
    $on = !empty($_POST['config_telemetry']);
    $telemetry = $on ? (intval($before['config_telemetry']) > 0 ? intval($before['config_telemetry']) : 2) : 0;

    settingsSave('telemetry', $before, ['config_telemetry' => $telemetry]);

    redirect();

}
