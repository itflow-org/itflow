<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$contact_id = intval($_POST['contact_id']);

// Default
$update_count = false;

if (!empty($contact_id)) {

    // Fetch contact info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT contact_name, contact_client_id, contact_user_id
        FROM contacts
        WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_user_id = intval($row['contact_user_id']);

        // Archive associated user if applicable
        if ($contact_user_id > 0) {
            mysqli_query($mysqli, "UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
        }

        // Archive contact
        $update_sql = mysqli_query($mysqli, "
            UPDATE contacts SET
            contact_important = 0,
            contact_billing = 0,
            contact_technical = 0,
            contact_archived_at = NOW()
            WHERE contact_id = $contact_id AND contact_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAction("Contact", "Archive", "$contact_name archived via API ($api_key_name)", $client_id, $contact_id);
        }
    }
}

// Output
require_once '../update_output.php';