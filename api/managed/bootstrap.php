<?php

/*
 * ITFlow - managed mode's two endpoints, as a manager: enroll.php (an instance connecting
 * with a code) and checkin.php (a connected instance reporting in). The instances call these;
 * nothing else should. Each answers JSON to a JSON POST - see functions/managed.php.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../includes/inc_set_timezone.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$managed_api_reply = function (int $status, array $answer) {
    http_response_code($status);
    echo json_encode($answer);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $managed_api_reply(405, ['error' => 'POST only']);
}

// The manager's side arrives with a migration - before it, there is nothing to answer
if (!settingsColumnExists($mysqli, 'config_manager_keys')) {
    $managed_api_reply(503, ['error' => "The manager's database update hasn't been applied yet"]);
}

$managed_api_body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($managed_api_body)) {
    $managed_api_reply(400, ['error' => 'Expected JSON']);
}

// logAudit() reads these
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;
$session_name = 'Managed instance';
