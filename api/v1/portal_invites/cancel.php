<?php
/*
 * API - Client Portal Invites - Cancel
 * POST /api/v1/portal_invites/cancel.php
 *
 * Cancel an invite that is waiting, as Cancel does on the client's Contacts -
 * its link stops working.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   invite_id  required - The invite
 *   client_id  required - Its client
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$invite_id = intval($_POST['invite_id'] ?? 0);

// Default
$update_count = false;

$invite = ($invite_id && portalInvitesReady()) ? portalInviteRow($invite_id) : null;

if ($invite && intval($invite['invite_client_id']) === $client_id) {

    if ($invite['invite_kind'] !== 'invite' || !in_array(portalInviteState($invite), ['pending', 'expired'], true)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "That invite isn't waiting any more - it may have been accepted or cancelled.";
        echo json_encode($return_arr);
        exit();
    }

    if (portalInviteCancel($invite_id, $client_id)) {
        $update_count = 1;

        $email = escapeSql($invite['invite_email']);
        logAudit("Contact", "Cancel", "Cancelled the client portal invite to $email via API ($api_key_name)", $client_id);
        logAudit("API", "Success", "Cancelled the client portal invite to $email via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
