<?php
/*
 * API - Ticket Replies - Read
 * GET /api/v1/ticket_replies/read.php
 *
 * Returns replies belonging to tickets within the key user's client access.
 *
 * Parameters (GET):
 *   api_key           required - Your API key
 *   ticket_reply_id   optional - Return a single reply by its own ID
 *   ticket_id         optional - Return all replies on a single ticket
 *   type              optional - Filter by reply type: Internal, Public or Client
 *   client_id         optional - Only return replies on tickets for this client
 *   include_archived  optional - Set to 1 to include archived replies (default: excluded)
 *   limit             optional - Max rows to return (default 50)
 *   offset            optional - Offset for pagination (default 0)
 *
 * Security:
 *   - ticket_replies are always INNER JOINed to tickets so that ticket_client_id is
 *     checked against the key user's client scope. A restricted key can never read
 *     replies on another client's ticket, even when ticket_reply_id is supplied
 *     directly.
 *
 * Notes:
 *   - ticket_reply_by is a user_id on Internal/Public replies and a contact_id on
 *     Client replies (those come from the email parser). ticket_reply_by_name is
 *     resolved here so callers don't have to know that.
 *   - Archived replies are hidden everywhere in the UI, so they're excluded by
 *     default here too.
 *   - Unlike invoice_items/read.php this does not require a filter - an unfiltered
 *     call lists all replies the key can see, matching tickets/read.php.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Archived replies are hidden throughout the UI - match that unless asked otherwise
$archived_sql = '';
if (empty($_GET['include_archived'])) {
    $archived_sql = " AND tr.ticket_reply_archived_at IS NULL";
}

// Optional reply type filter (whitelisted, so it's safe to interpolate)
$type_sql = '';
if (isset($_GET['type'])) {
    $type = ucfirst(strtolower($_GET['type']));
    if (in_array($type, ['Internal', 'Public', 'Client'], true)) {
        $type_sql = " AND tr.ticket_reply_type = '$type'";
    }
}

$select_sql =
    "SELECT tr.*,
            t.ticket_prefix, t.ticket_number, t.ticket_subject, t.ticket_client_id,
            COALESCE(u.user_name, c.contact_name) AS ticket_reply_by_name
     FROM ticket_replies tr
     INNER JOIN tickets t ON t.ticket_id = tr.ticket_reply_ticket_id
     LEFT JOIN users u ON tr.ticket_reply_type != 'Client' AND u.user_id = tr.ticket_reply_by
     LEFT JOIN contacts c ON tr.ticket_reply_type = 'Client' AND c.contact_id = tr.ticket_reply_by";

// Specific reply via ID (single)
if (isset($_GET['ticket_reply_id'])) {
    $id = intval($_GET['ticket_reply_id']);
    $sql = mysqli_query(
        $mysqli,
        "$select_sql
         WHERE tr.ticket_reply_id = '$id'
           AND 1=1 " . apiClientScopeSql('t.ticket_client_id') . "$archived_sql$type_sql
         LIMIT 1"
    );

} elseif (isset($_GET['ticket_id'])) {
    // All replies on a specific ticket
    $ticket_id = intval($_GET['ticket_id']);
    $sql = mysqli_query(
        $mysqli,
        "$select_sql
         WHERE tr.ticket_reply_ticket_id = '$ticket_id'
           AND 1=1 " . apiClientScopeSql('t.ticket_client_id') . "$archived_sql$type_sql
         ORDER BY tr.ticket_reply_id ASC
         LIMIT $limit OFFSET $offset"
    );

} else {
    // All replies the key can see
    $sql = mysqli_query(
        $mysqli,
        "$select_sql
         WHERE 1=1 " . apiClientScopeSql('t.ticket_client_id') . "$archived_sql$type_sql
         ORDER BY tr.ticket_reply_id ASC
         LIMIT $limit OFFSET $offset"
    );
}

// Output
require_once "../read_output.php";
