<?php

/*
 * Admin > Settings > Quotes (admin/settings_quote.php):
 * edit_quote_settings saves the numbering, the footer, who is emailed a
 * client's answer, the pricing a line starts with and whether lines show
 * their pictures. A next number already
 * in use, an email address that isn't one, or a % that can't be used, comes
 * back to the page saying so - nothing is saved.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_quote_settings'])) {

    validateCSRFToken();

    $pricing_columns = quotePricingReady() ? ['config_quote_price_method', 'config_quote_price_percent', 'config_quote_price_presets'] : [];
    $image_columns = quoteImagesReady() ? ['config_quote_show_images'] : [];
    $before = settingsCurrent(array_merge(['config_quote_prefix', 'config_quote_next_number', 'config_quote_footer', 'config_quote_notification_email'], $pricing_columns, $image_columns));

    // What was sent, and what is saved for anything that wasn't - a switch sends nothing when off
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);
    if ($image_columns) {
        $in['config_quote_show_images'] = empty($_POST['config_quote_show_images']) ? '0' : '1';
    }

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
    if ($image_columns) {
        $after['config_quote_show_images'] = $in['config_quote_show_images'];
    }
    // How a line's price comes from its cost (functions/quote_pricing.php)
    if ($pricing_columns) {
        $method = quotePriceMethod($in['config_quote_price_method']);
        $percent = quotePricingNumber($in['config_quote_price_percent']);
        $presets = quotePricePresets($in['config_quote_price_presets']);
        $after['config_quote_price_method'] = in_array($method, ['markup', 'margin'], true) ? $method : 'markup';
        $after['config_quote_price_percent'] = $percent === null ? '' : number_format($percent, 2, '.', '');
        $after['config_quote_price_presets'] = implode(',', array_map('quotePricePercentText', $presets));
        if ($percent === null || $percent < 0 || $percent >= 1000) {
            $errors['config_quote_price_percent'] = 'A number from 0 to 999.';
        } elseif ($after['config_quote_price_method'] === 'margin' && $percent >= 100) {
            $errors['config_quote_price_percent'] = 'A margin has to be under 100%.';
        }
        if (!$presets) {
            $errors['config_quote_price_presets'] = 'At least one %, such as 15, 20, 25.';
        }
    }
    if ($errors) {
        settingsRefuse('quote', $errors);
    }

    settingsSave('quote', $before, $after);

    redirect();

}
