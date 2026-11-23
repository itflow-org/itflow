<?php

/*
 * ITFlow - what every address under api/phone/ starts with: the phone system whose key came with
 * the request (functions/phone.php) as $phone_system. The key is the key field of the address, or
 * the password of HTTP Basic sign-in (mod_xml_cdr's cred, user:key). Without a known key there is
 * nothing here (404); a system that is turned off answers 503, so its sender knows.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../includes/inc_set_timezone.php';
require_once __DIR__ . '/../../includes/load_global_settings.php';

header('Cache-Control: no-store');

$phone_reply = function (int $status, string $text) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
};

// logAudit() reads these
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;
$session_name = 'Phone system';

$phone_key = (string) ($_GET['key'] ?? '');
if ($phone_key === '' && isset($_SERVER['PHP_AUTH_PW'])) {
    $phone_key = (string) $_SERVER['PHP_AUTH_PW'];
}
$phone_system = phoneSystemByKey($phone_key);
if (!$phone_system || !phoneKind($phone_system)) {
    $phone_reply(404, 'Not found');
}
if (!intval($phone_system['phone_system_active'])) {
    $phone_reply(503, 'This phone system is turned off in ITFlow');
}
$session_name = escapeSql(phoneSystemName($phone_system));
