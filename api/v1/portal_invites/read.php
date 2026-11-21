<?php
/*
 * API - Client Portal Invites - Read
 * GET /api/v1/portal_invites/read.php
 *
 * A client's invites waiting to be accepted - still in date, or run out -
 * newest first, as the client's Contacts page lists them. Each has its
 * state: pending or expired. Sending one again is portal_invites/create.php
 * with its email.
 *
 * Parameters (GET):
 *   api_key    required - Your API key
 *   client_id  required - The client
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$client_ok = $client_id_supplied && $client_id && apiUserCanAccessClient($client_id) && portalInvitesReady();

$invites = $client_ok ? portalInvitesWaiting($client_id) : [];

if (!$client_ok) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "client_id is required - a client the key's user can reach.";
    echo json_encode($return_arr);
    exit();
}

$return_arr['success'] = "True";
$return_arr['count'] = count($invites);
$return_arr['data'] = array_map(function ($row) {
    return [
        'invite_id' => intval($row['invite_id']),
        'invite_email' => $row['invite_email'],
        'invite_name' => $row['invite_name'],
        'invite_billing' => intval($row['invite_billing']),
        'invite_technical' => intval($row['invite_technical']),
        'invite_state' => portalInviteState($row),
        'invite_by' => portalInviteBy($row),
        'invite_created_at' => $row['invite_created_at'],
        'invite_sent_at' => $row['invite_sent_at'],
        'invite_sent_count' => intval($row['invite_sent_count']),
        'invite_expires_at' => $row['invite_expires_at'],
    ];
}, $invites);

echo json_encode($return_arr);
exit();
