<?php
/*
 * Client Portal
 * Functions
 */

/*
 * Verifies a contact has access to a particular ticket ID, and that the ticket is in the correct state (open/closed) to perform an action
 */
function verifyContactTicketAccess($requested_ticket_id, $expected_ticket_state)
{

    // Access the global variables
    global $mysqli, $session_contact_id, $session_contact_primary, $session_contact_is_technical_contact, $session_client_id, $config_ticket_status_id_closed;

    // Setup
    if ($expected_ticket_state == "Closed") {
        // Closed tickets
        $ticket_state_snippet = "ticket_status = 'Closed' OR ticket_status = $config_ticket_status_id_closed";
    } else {
        // Open (working/hold) tickets
        $ticket_state_snippet = "ticket_status != 'Closed' or ticket_status != $config_ticket_status_id_closed";
    }

    // Verify the contact has access to the provided ticket ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $requested_ticket_id AND $ticket_state_snippet AND ticket_client_id = $session_client_id LIMIT 1");
    $row = mysqli_fetch_array($sql);
    $ticket_id = $row['ticket_id'];

    if (intval($ticket_id) && ($session_contact_id == $row['ticket_contact_id'] || $session_contact_primary == 1 || $session_contact_is_technical_contact)) {
        // Client is ticket owner, primary contact, or a technical contact
        return true;
    }

    // Client is NOT ticket owner or primary/tech contact
    return false;

}
