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
    global $mysqli, $session_contact_id, $session_contact_primary, $session_contact_is_technical_contact, $session_client_id;

    // Setup
    if ($expected_ticket_state == "Closed") {
        // Closed tickets
        $ticket_state_snippet = "ticket_status = 5";
    } else {
        // Open (working/hold) tickets
        $ticket_state_snippet = "ticket_status != 5";
    }

    // Verify the contact has access to the provided ticket ID
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $requested_ticket_id AND $ticket_state_snippet AND ticket_client_id = $session_client_id LIMIT 1"));
    if ($row) {
        $ticket_id = $row['ticket_id'];

        if (intval($ticket_id) && ($session_contact_id == $row['ticket_contact_id'] || $session_contact_primary == 1 || $session_contact_is_technical_contact)) {
            // Client is ticket owner, primary contact, or a technical contact
            return true;
        }
    }

    // Client is NOT ticket owner or primary/tech contact
    return false;

}

/*
 * Returns appropriate FontAwesome icon for file extension
 */
function getFileIcon($file_extension) {
    $file_extension = strtolower($file_extension);
    
    // Document icons
    if (in_array($file_extension, ['pdf'])) {
        return 'file-pdf';
    } elseif (in_array($file_extension, ['doc', 'docx'])) {
        return 'file-word';
    } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
        return 'file-excel';
    } elseif (in_array($file_extension, ['ppt', 'pptx'])) {
        return 'file-powerpoint';
    } elseif (in_array($file_extension, ['txt', 'md', 'rtf'])) {
        return 'file-alt';
    } elseif (in_array($file_extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return 'file-archive';
    } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        return 'file-image';
    } elseif (in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv', 'flv'])) {
        return 'file-video';
    } elseif (in_array($file_extension, ['mp3', 'wav', 'ogg', 'flac'])) {
        return 'file-audio';
    } elseif (in_array($file_extension, ['html', 'htm', 'css', 'js', 'php', 'py', 'java'])) {
        return 'file-code';
    } else {
        return 'file';
    }
}

/*
 * Formats bytes into human readable file sizes
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
