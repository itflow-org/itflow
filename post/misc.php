<?php

/*
 * ITFlow - GET/POST request handler for misc (functionality that doesn't quite fit elsewhere)
 */

// Records to show per page

if(isset($_POST['change_records_per_page'])){

    $records_per_page = intval($_POST['change_records_per_page']);

    mysqli_query($mysqli,"UPDATE user_settings SET user_config_records_per_page = $records_per_page WHERE user_id = $session_user_id");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// In app notifications

if (isset($_GET['dismiss_notification'])) {

    $notification_id = intval($_GET['dismiss_notification']);

    mysqli_query($mysqli,"UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $session_user_id WHERE notification_id = $notification_id");

    // Logging
    logAction("Notification", "Dismiss", "$session_name dismissed notification");

    $_SESSION['alert_message'] = "Notification Dismissed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['dismiss_all_notifications'])) {

    $sql = mysqli_query($mysqli,"SELECT * FROM notifications WHERE notification_dismissed_at IS NULL");

    $num_notifications = mysqli_num_rows($sql);

    while($row = mysqli_fetch_array($sql)) {
        $notification_id = intval($row['notification_id']);
        $notification_dismissed_at = sanitizeInput($row['notification_dismissed_at']);

        mysqli_query($mysqli,"UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $session_user_id WHERE notification_id = $notification_id");

    }

    // Logging
    logAction("Notification", "Dismiss", "$session_name dismissed $num_notifications notifications");

    $_SESSION['alert_message'] = "$num_notifications Notifications Dismissed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Revoke sharing (sharing itself is done via ajax.php)
if (isset($_GET['deactivate_shared_item'])) {

    $item_id = intval($_GET['deactivate_shared_item']);

    // Get details of the shared link
    $sql = mysqli_query($mysqli, "SELECT item_type, item_related_id, item_client_id FROM shared_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $item_type = sanitizeInput($row['item_type']);
    $item_related_id = intval($row['item_related_id']);
    $client_id = intval($row['item_client_id']);

    // Deactivate item id
    mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_id = $item_id");

    // Logging
    logAction("Sharing", "Delete", "$session_name deactivated shared $item_type link Item ID: $item_related_id. Share ID $item_id", $client_id, $item_id);

    $_SESSION['alert_message'] = "Share Link deactivated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
