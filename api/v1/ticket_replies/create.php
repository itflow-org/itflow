<?php
/*
 * API - Ticket Replies - Create
 * POST /api/v1/ticket_replies/create.php
 *
 * Adds a reply to an existing ticket. This is the endpoint an RMM, monitoring
 * system or chat bridge uses to append to a ticket it didn't open.
 *
 * Parameters (POST, JSON body):
 *   api_key                  required - Your API key
 *   client_id                required - Must match the ticket's client (restricted
 *                                       keys only; unrestricted/admin keys may omit)
 *   ticket_id                required - Ticket to reply to
 *   ticket_reply             required - Reply body (HTML allowed, same as the UI)
 *   ticket_reply_type        optional - 'Internal' (default) or 'Public'.
 *                                       Public emails the contact and any watchers
 *                                       and counts as the ticket's first response.
 *   ticket_reply_time_worked optional - HH:MM:SS, default 00:00:00
 *   ticket_status            optional - Also set the ticket status. Status 4 resolves
 *                                       the ticket (sets resolved_at + SLA met).
 *
 * Security:
 *   - The parent ticket is loaded through apiClientScopeSql(), so a restricted key
 *     can't reply to another client's ticket even with a valid ticket_id.
 *   - The client_id supplied for the write must match the ticket's own client.
 *   - The reply is attributed to the user the API key runs as ($session_user_id,
 *     set by enforce_api_rbac.php), so ticket history stays honest.
 *
 * Note: 'ticket_replies' must be present in $resource_module in enforce_api_rbac.php
 * (mapped to module_support) or the enforcer will fail closed on this endpoint.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Ticket/mail settings for public reply notifications
require_once "../../../includes/load_global_settings.php";

// Parse Info
$ticket_id = intval($_POST['ticket_id'] ?? 0);
$ticket_reply_row = false; // Creation, not an update
require_once 'ticket_reply_model.php';

// Default
$insert_id = false;

if (!empty($ticket_id) && !empty($reply)) {

    // Load the parent ticket, scoped to the key user's client access
    $ticket_sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
         WHERE ticket_id = $ticket_id
           AND 1=1 " . apiClientScopeSql('ticket_client_id') . "
         LIMIT 1"
    );
    $ticket_row = $ticket_sql ? mysqli_fetch_assoc($ticket_sql) : null;

    // The client named on the write must be the ticket's own client
    if ($ticket_row && $client_id != 0 && intval($ticket_row['ticket_client_id']) !== $client_id) {
        $ticket_row = null;
    }

    if ($ticket_row) {

        $ticket_prefix = escapeSql($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_subject = escapeSql($ticket_row['ticket_subject']);
        $ticket_url_key = escapeSql($ticket_row['ticket_url_key']);
        $ticket_first_response_at = escapeSql($ticket_row['ticket_first_response_at']);
        $client_id = intval($ticket_row['ticket_client_id']);
        $original_ticket_status = intval($ticket_row['ticket_status']);

        // Mark first response time if required - internal notes don't count as a response
        if (empty($ticket_first_response_at) && $reply_type == 'Public') {
            setTicketFirstResponse($ticket_id);
        }

        // Add reply
        $insert_sql = mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$reply', ticket_reply_type = '$reply_type', ticket_reply_time_worked = '$reply_time_worked', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

        // Check insert & get insert ID
        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);

            // Optional status change alongside the reply
            if (!empty($reply_ticket_status)) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $reply_ticket_status WHERE ticket_id = $ticket_id LIMIT 1");

                // Only record a status change when the status actually changed -
                // Resolved is left out because the resolve block below logs it
                if ($reply_ticket_status !== $original_ticket_status && $reply_ticket_status != 4) {
                    $new_status_name = escapeSql(getTicketStatusName($reply_ticket_status));
                    logTicketHistory($ticket_id, "Status set to $new_status_name via the API ($api_key_name)");
                }

                // Resolve the ticket, if it is actually moving into Resolved
                if ($reply_ticket_status == 4 && $original_ticket_status != 4) {
                    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_resolved_at IS NULL LIMIT 1");
                    setTicketResolutionSlaMet($ticket_id);

                    logTicketHistory($ticket_id, "Resolved via the API ($api_key_name)");

                    logAudit("Ticket", "Resolve", "Resolved ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id, $ticket_id);

                    triggerCustomAction('ticket_resolve', $ticket_id);
                }
            }

            // Logging
            logAudit("Ticket", "Reply", "Added a $reply_type reply to ticket $ticket_prefix$ticket_number - $ticket_subject via API ($api_key_name)", $client_id, $ticket_id);
            logAudit("API", "Success", "Added a $reply_type reply to ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);

            // Custom action/notif handler
            if ($reply_type == 'Internal') {
                triggerCustomAction('ticket_reply_agent_internal', $ticket_id);
            } else {
                triggerCustomAction('reply_reply_agent_public', $ticket_id);
            }

            // Email the contact and watchers on a public reply, as the agent reply handler does
            if ($reply_type == 'Public') {
                ticketMailReply($ticket_id, (string) getFieldById('ticket_replies', $insert_id, 'ticket_reply'), stripslashes($session_name));
            }

        }

    }

}

// Output
require_once '../create_output.php';
