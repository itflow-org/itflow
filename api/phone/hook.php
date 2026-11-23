<?php

/*
 * ITFlow - desk phones telling ITFlow about themselves. A Yealink phone calls this at startup, as
 * its lines register, and as calls ring, start and end (its action URLs, with e= the event and the
 * phone's own variables - the system's setup window in Admin > Phone Systems has them); a Polycom
 * phone posts its telephony notifications here as XML. Each phone is kept by its MAC address, and
 * becomes a Phone asset of its domain's client (phoneDeviceSave(), functions/phone.php). Anything
 * that isn't from a phone is turned down (400).
 */

require_once __DIR__ . '/phone_system.php';

$phone_body = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) file_get_contents('php://input', false, null, 0, PHONE_HOOK_MAX_BYTES + 1) : '';
$phone_event = phoneHookEvent($_GET, $phone_body, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (!$phone_event) {
    $phone_reply(400, 'Not a phone event');
}
if ($phone_event['mac'] === '') {
    $phone_reply(400, 'No MAC address');
}

phoneDeviceSave($phone_system, $phone_event);

$phone_reply(200, 'OK');
