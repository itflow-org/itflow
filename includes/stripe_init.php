<?php
/**
 * Stripe bootstrap.
 *
 * Require this INSTEAD OF the Stripe library's own init.php everywhere in ITFlow.
 *
 * stripe-php 21.0.0+ persists a telemetry UUID to $HOME/.config/stripe/telemetry_id
 * on the first API call. On hosts with open_basedir (Hestia, cPanel, Plesk) that path
 * is outside the jail, and the library's file_exists()/is_dir() checks are not
 * @-suppressed - so PHP emits warnings that get prepended to our output, corrupting
 * JSON responses and breaking header() redirects. Point it at a writable temp dir.
 */

$stripe_config_dir = sys_get_temp_dir();
if (is_dir($stripe_config_dir) && is_writable($stripe_config_dir)) {
    putenv('XDG_CONFIG_HOME=' . $stripe_config_dir);
} else {
    // No writable temp dir - make the library's getConfigDir() return null so it
    // skips the filesystem entirely rather than reaching for a forbidden path.
    putenv('HOME');
    putenv('XDG_CONFIG_HOME');
}
unset($stripe_config_dir);

require_once __DIR__ . '/../libs/stripe-php/init.php';
