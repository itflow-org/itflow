<?php

require_once "../config.php";
require_once "../functions.php";

session_start();

require_once "../inc_set_timezone.php"; // Must be included after session_start to work

if (isset($_GET['accept_quote'], $_GET['url_key'])) {
    $quote_id = intval($_GET['accept_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Accepted', history_description = 'Client accepted Quote!', history_quote_id = $quote_id");

        // Notification
        appNotify("Quote Accepted", "Quote $quote_prefix$quote_number has been accepted by $client_name", "quote.php?quote_id=$quote_id", $client_id);
        customAction('quote_accept', $quote_id);

        // Internal email notification

        $sql_company = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);

        $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_settings);
        $config_smtp_host = $row['config_smtp_host'];
        $config_smtp_port = intval($row['config_smtp_port']);
        $config_smtp_encryption = $row['config_smtp_encryption'];
        $config_smtp_username = $row['config_smtp_username'];
        $config_smtp_password = $row['config_smtp_password'];
        $config_quote_from_name = sanitizeInput($row['config_quote_from_name']);
        $config_quote_from_email = sanitizeInput($row['config_quote_from_email']);
        $config_quote_notification_email = sanitizeInput($row['config_quote_notification_email']);
        $config_base_url = sanitizeInput($config_base_url);

        if (!empty($config_smtp_host) && !empty($config_quote_notification_email)) {
            $subject = "Quote Accepted - $client_name - Quote $quote_prefix$quote_number";
            $body = "Hello, <br><br>This is a notification that a quote has been accepted in ITFlow. <br><br>Client: $client_name<br>Quote: <a href=\'https://$config_base_url/quote.php?quote_id=$quote_id\'>$quote_prefix$quote_number</a><br><br>~<br>$company_name - Billing<br>$config_quote_from_email";

            $data[] = [
                'from' => $config_quote_from_email,
                'from_name' => $config_quote_from_name,
                'recipient' => $config_quote_notification_email,
                'subject' => $subject,
                'body' => $body,
            ];

            $mail = addToMailQueue($data);
        }

        $_SESSION['alert_message'] = "Quote Accepted";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['decline_quote'], $_GET['url_key'])) {
    $quote_id = intval($_GET['decline_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Declined', history_description = 'Client declined Quote!', history_quote_id = $quote_id");

        // Notification
        appNotify("Quote Declined", "Quote $quote_prefix$quote_number has been declined by $client_name", "quote.php?quote_id=$quote_id", $client_id);
        customAction('quote_decline', $quote_id);

        // Internal email notification

        $sql_company = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);

        $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_settings);
        $config_smtp_host = $row['config_smtp_host'];
        $config_smtp_port = intval($row['config_smtp_port']);
        $config_smtp_encryption = $row['config_smtp_encryption'];
        $config_smtp_username = $row['config_smtp_username'];
        $config_smtp_password = $row['config_smtp_password'];
        $config_quote_from_name = sanitizeInput($row['config_quote_from_name']);
        $config_quote_from_email = sanitizeInput($row['config_quote_from_email']);
        $config_quote_notification_email = sanitizeInput($row['config_quote_notification_email']);
        $config_base_url = sanitizeInput($config_base_url);

        if (!empty($config_smtp_host) && !empty($config_quote_notification_email)) {
            $subject = "Quote Declined - $client_name - Quote $quote_prefix$quote_number";
            $body = "Hello, <br><br>This is a notification that a quote has been declined in ITFlow. <br><br>Client: $client_name<br>Quote: <a href=\'https://$config_base_url/quote.php?quote_id=$quote_id\'>$quote_prefix$quote_number</a><br><br>~<br>$company_name - Billing<br>$config_quote_from_email";

            $data[] = [
                'from' => $config_quote_from_email,
                'from_name' => $config_quote_from_name,
                'recipient' => $config_quote_notification_email,
                'subject' => $subject,
                'body' => $body,
            ];

            $mail = addToMailQueue($data);
        }

        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Quote Declined";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['reopen_ticket'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
        customAction('ticket_update', $ticket_id);
        $_SESSION['alert_message'] = "Ticket reopened";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['close_ticket'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
        customAction('ticket_close', $ticket_id);
        $_SESSION['alert_message'] = "Ticket closed";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_GET['add_ticket_feedback'], $_GET['url_key'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);
    $feedback = sanitizeInput($_GET['feedback']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_closed_at IS NOT NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            $ticket_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            $ticket_prefix = sanitizeInput($ticket_details['ticket_prefix']);
            $ticket_number = intval($ticket_details['ticket_number']);

            appNotify("Feedback", "Guest rated ticket number $ticket_prefix$ticket_number (ID: $ticket_id) as bad", "ticket.php?ticket_id=$ticket_id");
        }

        $_SESSION['alert_message'] = "Feedback recorded - thank you";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        customAction('ticket_feedback', $ticket_id);
    } else {
        echo "Invalid!!";
    }
}

if (isset($_POST['guest_quote_upload_file'])) {
    $quote_id = intval($_POST['quote_id']);
    $url_key = sanitizeInput($_POST['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_id = intval($row['client_id']);

        // Define & create directories, as required
        $upload_file_dir = "../uploads/clients/$client_id/";
        mkdirMissing($upload_file_dir);

        // Store attached any file
        if (!empty($_FILES)) {

            for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
                // Extract file details for this iteration
                $single_file = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];

                if ($file_reference_name = checkFileUpload($single_file, array('pdf'))) {

                    $file_tmp_path = $_FILES['file']['tmp_name'][$i];

                    $file_name = sanitizeInput($_FILES['file']['name'][$i]);
                    $extarr = explode('.', $_FILES['file']['name'][$i]);
                    $file_extension = sanitizeInput(strtolower(end($extarr)));

                    // Extract the file mime type and size
                    $file_mime_type = sanitizeInput($single_file['type']);
                    $file_size = intval($single_file['size']);

                    // Define destination file path
                    $dest_path = $upload_file_dir . $file_reference_name;

                    // Get/Create a top-level folder called Client Uploads
                    $folder_sql = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_name = 'Client Uploads' AND parent_folder = 0 AND folder_client_id = $client_id LIMIT 1");
                    if (mysqli_num_rows($folder_sql) == 1) {
                        // Get
                        $row = mysqli_fetch_array($folder_sql);
                        $folder_id = $row['folder_id'];
                    } else {
                        // Create
                        mysqli_query($mysqli,"INSERT INTO folders SET folder_name = 'Client Uploads', parent_folder = 0, folder_location = 1, folder_client_id = $client_id");
                        $folder_id = mysqli_insert_id($mysqli);
                        logAction("Folder", "Create", "Automatically created folder Client Uploads", $client_id, $folder_id);
                    }

                    // Do move/upload
                    move_uploaded_file($file_tmp_path, $dest_path);

                    // Create reference in files
                    mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_description = 'Uploaded via $quote_prefix$quote_number', file_ext = '$file_extension', file_mime_type = '$file_mime_type', file_size = $file_size, file_folder_id = $folder_id, file_client_id = $client_id");
                    $file_id = mysqli_insert_id($mysqli);

                    // Associate file with quote
                    mysqli_query($mysqli, "INSERT INTO quote_files SET quote_id = $quote_id, file_id = $file_id");

                    // Logging & feedback
                    $_SESSION['alert_message'] = 'File uploaded!';
                    appNotify("Quote File", "$file_name was uploaded to quote $quote_prefix$quote_number", "quote.php?quote_id=$quote_id", $client_id);
                    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Upload', history_description = 'Client uploaded file $file_name', history_quote_id = $quote_id");
                    logAction("File", "Upload", "Guest uploaded file $file_name to quote $quote_prefix$quote_number", $client_id);

                } else {
                    $_SESSION['alert_type'] = 'error';
                    $_SESSION['alert_message'] = 'Something went wrong uploading the file - please let the support team know.';
                    logApp("Guest", "error", "Error uploading file to invoice");
                }

            }
        }

        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        echo "Invalid!!";
    }
}

?>
