<?php

/*
 * ITFlow - managed mode: an instance this ITFlow manages checking in, every minute, from its
 * Manager Check-in job. See managerCheckin() in functions/managed.php.
 */

require_once __DIR__ . '/bootstrap.php';

$managed_authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
if ($managed_authorization === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $managed_header => $managed_value) {
        if (strtolower($managed_header) === 'authorization') {
            $managed_authorization = (string) $managed_value;
        }
    }
}

[$managed_status, $managed_answer] = managerCheckin($managed_authorization, $managed_api_body, getIP());

$managed_api_reply($managed_status, $managed_answer);
