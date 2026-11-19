<?php

/*
 * ITFlow - client-wide actions: archiving a client (Archive Client and Offboard), and what
 * offboarding one stops (agent/modals/client/client_offboard.php, offboard_client).
 */
/*
 * ---------- offboarding (agent/modals/client/client_offboard.php, offboard_client) ----------
 * Everything that keeps working for a client after you stop working for them, counted - so the
 * Offboard window can say what each step will do, and the handler do just those.
 */
function clientOffboardCounts($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $count = function ($sql) use ($mysqli) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, $sql));
        return intval($row[0] ?? 0);
    };
    return [
        'portal' => $count("SELECT COUNT(contact_id) FROM contacts JOIN users ON user_id = contact_user_id AND user_type = 2
            WHERE contact_client_id = $client_id AND user_auth_method != ''"),
        'invites' => portalInvitesReady() ? count(portalInvitesWaiting($client_id)) : 0,
        'shares' => $count("SELECT COUNT(item_id) FROM shared_items WHERE item_client_id = $client_id AND item_ended_at IS NULL AND item_active = 1 AND item_expire_at > NOW()"),
        'recurring_invoices' => $count("SELECT COUNT(recurring_invoice_id) FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL"),
        'recurring_tickets' => $count("SELECT COUNT(recurring_ticket_id) FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id AND recurring_ticket_paused_at IS NULL"),
        'open_tickets' => $count("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id AND " . TICKET_OPEN_SQL),
    ];
}

/**
 * Archive a client - as Archive Client and Offboard do: archived, its recurring invoices stopped,
 * and contract signing requests still out to it withdrawn. Returns how many were withdrawn.
 */
function clientArchiveNow($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");
    clientStopRecurringInvoices($client_id, 'Recurring Invoice inactive as client archived');
    // Nothing more is signed on their contracts - a request still out, or waiting on our countersignature, is withdrawn
    return contractSignatureWithdrawStale($client_id);
}

/** Stop a client's recurring invoices, each with a line in its history. Returns how many. */
function clientStopRecurringInvoices($client_id, $why) {
    global $mysqli;
    $client_id = intval($client_id);
    $why = escapeSql($why);
    $stopped = 0;
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1");
    while ($row = mysqli_fetch_assoc($sql)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);
        mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 0, history_description = '$why', history_recurring_invoice_id = $recurring_invoice_id");
        $stopped++;
    }
    return $stopped;
}
