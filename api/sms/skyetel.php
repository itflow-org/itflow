<?php

/*
 * ITFlow - texts from Skyetel. In Skyetel's portal, a number's SMS tab sends texts to a
 * Callback URL - this address with its key, as Admin > Integrations > Text Messaging shows it.
 * Skyetel sends JSON - {"to", "from", "text", "media": [urls]} - by POST, or by GET; both are
 * taken. Without the key there is nothing here (404). What becomes of a text is smsInbound()'s
 * (functions/sms.php): a reply on a ticket, a new ticket, or the Texts inbox.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../includes/inc_set_timezone.php';
require_once __DIR__ . '/../../includes/load_global_settings.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$sms_api_reply = function (int $status, array $answer) {
    http_response_code($status);
    echo json_encode($answer);
    exit;
};

// logAudit() reads these
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;
$session_name = 'Skyetel';

// The key says which provider it is - one that is set up in ITFlow, with this key
$key = (string) ($_GET['key'] ?? '');
$provider = null;
if (strlen($key) >= 32) {
    try {
        $sql = mysqli_query($mysqli, "SELECT * FROM sms_providers WHERE sms_provider_name = 'Skyetel'");
        while ($row = mysqli_fetch_assoc($sql)) {
            if ((string) $row['sms_provider_inbound_key'] !== '' && hash_equals((string) $row['sms_provider_inbound_key'], $key)) {
                $provider = $row;
                break;
            }
        }
    } catch (Throwable $e) {
        // Before the 3.6.0 database update there is nothing here
    }
}
if (!$provider) {
    $sms_api_reply(404, ['error' => 'Not found']);
}
if (!intval($provider['sms_provider_active'])) {
    $sms_api_reply(503, ['error' => 'Texting is turned off in ITFlow']);
}

// The text - a JSON body, or the same as form or query fields
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = array_merge($_GET, $_POST);
}
$media = $body['media'] ?? [];
if (is_string($media)) {
    $media = $media !== '' ? (json_decode($media, true) ?: [$media]) : [];
}
$text = (string) ($body['text'] ?? $body['message'] ?? $body['body'] ?? '');

$result = smsInbound($provider, (string) ($body['from'] ?? ''), (string) ($body['to'] ?? ''), $text, is_array($media) ? $media : []);

$sms_api_reply(200, ['ok' => true, 'status' => $result['status']]);
