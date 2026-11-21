<?php
/*
 * API - Ticket Replies - Update
 * POST /api/v1/ticket_replies/update.php
 *
 * Edit a reply, as Edit does on the ticket - its text, Public or Internal,
 * and the time worked. Only what you send changes. A client's own reply
 * can't be edited.
 *
 * Parameters (POST, JSON body):
 *   api_key                  required - Your API key
 *   client_id                required - The ticket's client (restricted keys only)
 *   ticket_reply_id          required - The reply
 *   ticket_reply             optional - The reply's text (HTML allowed, same as the UI)
 *   ticket_reply_type        optional - Internal or Public. Making a reply Public
 *                                       doesn't email it - it was written already.
 *   ticket_reply_time_worked optional - HH:MM:SS
 *
 * An agent newly @mentioned in an internal note is told, as in the app.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

// Parse ID
$ticket_reply_id = intval($_POST['ticket_reply_id'] ?? 0);

// Default
$update_count = false;

if (!empty($ticket_reply_id)) {

    $ticket_reply_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT ticket_replies.*, ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id
        FROM ticket_replies
        JOIN tickets ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_reply_id = $ticket_reply_id AND ticket_client_id = $client_id AND ticket_reply_archived_at IS NULL
        LIMIT 1
    "));

    if ($ticket_reply_row && $ticket_reply_row['ticket_reply_type'] === 'Client') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "A client's reply can't be edited.";
        echo json_encode($return_arr);
        exit();
    }

    if ($ticket_reply_row) {

        // What changes - the rest from the reply as it is
        require_once 'ticket_reply_model.php';

        $ticket_id = intval($ticket_reply_row['ticket_id']);

        // Its @mentions put right - made public, it mentions nobody (functions/ticket_mention.php)
        $reply_html = isset($_POST['ticket_reply']) ? (string) $_POST['ticket_reply'] : (string) $ticket_reply_row['ticket_reply'];
        [$reply_html, $mentioned] = ticketMentionsFromHtml($reply_html, $ticket_reply_row['ticket_client_id'], $reply_type === 'Internal');
        $reply = mysqli_real_escape_string($mysqli, $reply_html);

        $update_sql = mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$reply', ticket_reply_type = '$reply_type', ticket_reply_time_worked = '$reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id LIMIT 1");

        if ($update_sql) {
            // A reply saved as it was still counts as edited
            $update_count = max(1, mysqli_affected_rows($mysqli));

            // Only the agents it mentions now that it didn't before are told
            $told = ticketMentionsTell($ticket_reply_row, $ticket_reply_id, $reply_html, ticketMentionsSync($ticket_reply_id, $mentioned));
            if ($told) {
                logTicketHistory($ticket_id, "$session_name mentioned " . escapeSql(ticketMentionNames($told)) . " in an internal note");
            }

            $reference = escapeSql(ticketReference($ticket_reply_row));
            logAudit("Ticket", "Reply", "Edited a reply on ticket $reference via API ($api_key_name)", $client_id, $ticket_id);
            logAudit("API", "Success", "Edited a reply on ticket $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
