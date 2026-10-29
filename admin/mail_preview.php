<?php

/*
 * ITFlow - GET /admin/mail_preview.php?email=KEY
 * One email from Admin > Mail > Previews, as JSON - its subject, who it is
 * from and to, and its HTML - made on sample records that are thrown away
 * again (emailPreview(), functions/email_preview.php).
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

enforceAdminPermission();
validateCSRFToken();

header('Content-Type: application/json');
header('Cache-Control: no-store');

$preview = emailPreview((string) ($_GET['email'] ?? ''));
if ($preview === null) {
    http_response_code(404);
    exit(json_encode(['error' => 'There is no such email']));
}

$email = $preview['email'];
$person = function ($name, $address) {
    return trim($name) !== '' && strcasecmp(trim($name), trim($address)) !== 0 ? "$name <$address>" : $address;
};
$others = [];
foreach ($preview['emails'] as $other) {
    if ($email && $other['subject'] === $email['subject'] && strcasecmp($other['to'], $email['to']) !== 0) {
        $others[] = $person($other['to_name'], $other['to']);
    }
}

echo json_encode([
    'label' => $preview['label'],
    'to_who' => $preview['to'],
    'when' => $preview['when'],
    'subject' => $email['subject'] ?? '',
    'from' => $email ? ($email['from'] !== '' ? $person($email['from_name'], $email['from']) : 'No address yet - set it under From Addresses') : '',
    'to' => $email ? $person($email['to_name'], $email['to']) : '',
    'also' => array_values(array_unique($others)),
    'calendar' => !empty($email['cal_str']),
    'html' => $email['html'] ?? '',
    'error' => $email ? '' : ($preview['error'] !== '' ? 'It could not be made: ' . $preview['error'] : 'Nothing would be sent with the sample records.'),
]);
