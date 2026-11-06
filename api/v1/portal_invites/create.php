<?php

/*
 * API - Client Portal Invites - Create
 * POST /api/v1/portal_invites/create.php
 *
 * Invites someone by email to a client's portal, as an agent does from the
 * client's Contacts: they choose their own password from the link, and are
 * made a contact with portal access - or the client's contact with that
 * address is given access. Asked again, a waiting invite goes again with a
 * new link. It comes from your company rather than the key's agent. Needs
 * mail set up, to send it.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   client_id    required - The client
 *   email        required - Who to invite
 *   name         optional - Their name, filled in for them
 *   billing      optional - 1: they join as a billing contact
 *   technical    optional - 1: they join as a technical contact
 *
 * Answer: invite - "invited", "resent" or "skipped", and why when skipped
 * (they sign in already, here or to another account, or the email could
 * not be sent).
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

require_once "../../../includes/load_global_settings.php";

$insert_id = false;
$email = trim((string) ($_POST['email'] ?? ''));
$client = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id = " . intval($client_id) . " AND client_archived_at IS NULL")) : null;

if (!portalInvitesReady()) {
    $error = 'Client portal invites need the 3.4.6 database update.';
} elseif (!$client) {
    $error = 'client_id is not a client here, or it is archived.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'email is not an email address.';
} else {
    $error = '';
}
if ($error !== '') {
    $return_arr['success'] = "False";
    $return_arr['message'] = $error;
    header("HTTP/1.1 400 Bad Request");
    echo json_encode($return_arr);
    exit();
}

$name = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['name'] ?? '')))), 0, 200);
$done = portalInviteSend(intval($client_id), [['email' => $email, 'name' => $name]],
    // From your company, not the key's agent - "You are invited to the <company> client portal"
    ['billing' => intval($_POST['billing'] ?? 0) === 1, 'technical' => intval($_POST['technical'] ?? 0) === 1], ['user_id' => 0]);

if (isset($done['skipped'][$email])) {
    $return_arr['success'] = "True";
    $return_arr['count'] = '0';
    $return_arr['invite'] = 'skipped';
    $return_arr['message'] = "Not invited - " . $done['skipped'][$email];
    echo json_encode($return_arr);
    exit();
}

$state = in_array($email, $done['resent'], true) ? 'resent' : 'invited';
$insert_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT invite_id FROM portal_invites WHERE invite_client_id = " . intval($client_id) . "
    AND invite_kind = 'invite' AND invite_email = '" . escapeSql($email) . "' ORDER BY invite_id DESC LIMIT 1"))[0] ?? 0);
$return_arr['invite'] = $state;
logAudit("Contact", "Invite", escapeSql(($state === 'resent' ? 'Sent the client portal invite again to ' : 'Invited ') . "$email" . ($state === 'resent' ? '' : ' to the client portal') . " for {$client['client_name']} via API ($api_key_name)"), intval($client_id));
logAudit("API", "Success", escapeSql("Client portal invite for $email via API ($api_key_name)"), intval($client_id));

// Output
require_once '../create_output.php';
