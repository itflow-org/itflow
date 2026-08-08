<?php
// Not including the guest header as we don't want any HTML output
require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";

require_once "../functions.php";


$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);

if (isset($_GET['id']) && isset($_GET['key'])) {
    $item_id = intval($_GET['id']);
    $item_key = escapeSql($_GET['key']);

    $sql = mysqli_query($mysqli, "SELECT item_active, item_client_id, item_related_id, item_type, item_view_limit, item_views FROM shared_items WHERE item_id = $item_id AND item_key = '$item_key' AND item_expire_at > NOW() LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    $item_active = intval($row['item_active']);
    $item_type = escapeSql($row['item_type']);
    $item_views = intval($row['item_views']);
    $item_view_limit = intval($row['item_view_limit']);
    $item_related_id = intval($row['item_related_id']);
    $client_id = intval($row['item_client_id']);

    // Check result
    if (mysqli_num_rows($sql) !== 1 || !$row) {
        exit("Item cannot be viewed at this time (disabled or invalid).");
    }

    // Check it is a file
    if ($item_type !== "File") {
        exit("Item cannot be viewed at this time (Bad item type: expected File but got $item_type).");
    }

    // Check item sharing link is active
    if ($item_active != "1") {
        exit("Item cannot be viewed at this time (disabled).");
    }

    // Check view limit (if not unlimited)
    if ($item_view_limit !== 0) {
        // Not unlimited
        if ($item_views >= $item_view_limit) {
            // Views exceed
            exit("Item cannot be viewed at this time (view limit exceeded).");
        }
    }

    $file_sql = mysqli_query($mysqli, "SELECT file_client_id, file_name, file_reference_name FROM files WHERE file_id = $item_related_id AND file_client_id = $client_id LIMIT 1");
    $file_row = mysqli_fetch_assoc($file_sql);

    if (mysqli_num_rows($file_sql) !== 1 || !$file_row) {
        exit("Item cannot be viewed at this time (No file, may have been deleted).");
    }

    $file_name = escapeSql($file_row['file_name']);
    $file_reference_name = escapeSql($file_row['file_reference_name']);
    $client_id = intval($file_row['file_client_id']);
    $file_path = "../uploads/clients/$client_id/$file_reference_name";

    // Don't burn a view on a file that is missing from disk
    if (!is_readable($file_path)) {
        exit("Item cannot be viewed at this time (No file, may have been deleted).");
    }

    // Claim the view before the file is served. The checks above stay as a
    // fast path for messaging - this UPDATE is what enforces the limit.
    if (!claimSharedItemView($item_id)) {
        exit("Item cannot be viewed at this time (view limit exceeded).");
    }

    // Display file as download
    $mime_type = mime_content_type($file_path);
    header('Content-type: '.$mime_type);
    header('Content-Disposition: attachment; filename=' . $file_name);
    readfile($file_path);

    //Logging
    logAudit("Share", "View", "Downloaded shared file $file_name via link", $client_id, $item_id);

}
