<?php

/*
 * Admin > Settings > Quotes (admin/settings_quote.php):
 * edit_quote_settings saves the numbering, the footer and who is emailed a
 * client's answer. A next number already in use, or an email address that
 * isn't one, comes back to the page saying so - nothing is saved.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_quote_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_quote_prefix', 'config_quote_next_number', 'config_quote_footer', 'config_quote_notification_email']);

    // What was sent, and what is saved for anything that wasn't
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);

    [$prefix, $number, $errors] = settingsNumberingCheck('quote', $in, $before);
    $after = [
        'config_quote_prefix' => $prefix,
        'config_quote_next_number' => $number,
        'config_quote_footer' => rtrim(str_replace("\r\n", "\n", (string) $in['config_quote_footer'])),
        'config_quote_notification_email' => trim((string) $in['config_quote_notification_email']),
    ];
    if ($problem = settingsEmailProblem($after['config_quote_notification_email'])) {
        $errors['config_quote_notification_email'] = $problem;
    }
    if ($errors) {
        settingsRefuse('quote', $errors);
    }

    settingsSave('quote', $before, $after);

    redirect();

}
