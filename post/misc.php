<?php

/*
 * ITFlow - GET/POST request handler for misc (functionality that doesn't quite fit elsewhere)
 */

// Records to show per page

if(isset($_POST['change_records_per_page'])){

    $records_per_page = intval($_POST['change_records_per_page']);

    mysqli_query($mysqli,"UPDATE user_settings SET user_config_records_per_page = $records_per_page WHERE user_id = $session_user_id");

    redirect();

}

// Revoke sharing (sharing itself is done via ajax.php)
if (isset($_GET['deactivate_shared_item'])) {

    validateCSRFToken();

    $item_id = intval($_GET['deactivate_shared_item']);

    // Get details of the shared link
    $sql = mysqli_query($mysqli, "SELECT item_type, item_related_id, item_client_id FROM shared_items WHERE item_id = $item_id");
    $row = mysqli_fetch_assoc($sql);
    $item_type = escapeSql($row['item_type']);
    $item_related_id = intval($row['item_related_id']);
    $client_id = intval($row['item_client_id']);

    enforceClientAccess();

    // A credential's link is turned off by someone who may see credentials
    if ($item_type === 'Credential') {
        enforceUserPermission('module_credential');
    }

    // Deactivate item id
    mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_id = $item_id");

    // Logging
    logAudit("Sharing", "Delete", "$session_name deactivated shared $item_type link Item ID: $item_related_id. Share ID $item_id", $client_id, $item_id);
    if ($item_type === 'Credential') {
        // In the credential's own history too
        logAudit("Credential", "Unshare", "$session_name turned off a share link", $client_id, $item_related_id);
    }

    $_SESSION['alert_message'] = "Share Link deactivated";

    redirect();
}
