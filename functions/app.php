<?php

// App/UI helpers - icons, badges, lookups, mail queue, iCal, taxes, update check
// Split from the former monolithic functions.php


function getAssetIcon($asset_type)
{
    if ($asset_type == 'Laptop') {
        $device_icon = "laptop";
    } elseif ($asset_type == 'Desktop') {
        $device_icon = "desktop";
    } elseif ($asset_type == 'Server') {
        $device_icon = "server";
    } elseif ($asset_type == 'Printer') {
        $device_icon = "print";
    } elseif ($asset_type == 'Camera') {
        $device_icon = "video";
    } elseif ($asset_type == 'Switch') {
        $device_icon = "network-wired";
    } elseif ($asset_type == 'Firewall/Router') {
        $device_icon = "fire-alt";
    } elseif ($asset_type == 'Access Point') {
        $device_icon = "wifi";
    } elseif ($asset_type == 'Phone') {
        $device_icon = "phone";
    } elseif ($asset_type == 'Mobile Phone') {
        $device_icon = "mobile-alt";
    } elseif ($asset_type == 'Tablet') {
        $device_icon = "tablet-alt";
    } elseif ($asset_type == 'Display') {
        $device_icon = "tv";
    } elseif ($asset_type == 'Virtual Machine') {
        $device_icon = "cloud";
    } else {
        $device_icon = "tag";
    }

    return $device_icon;
}

function getInvoiceBadgeColor($invoice_status)
{
    if ($invoice_status == "Sent") {
        $invoice_badge_color = "warning text-white";
    } elseif ($invoice_status == "Viewed") {
        $invoice_badge_color = "info";
    } elseif ($invoice_status == "Partial") {
        $invoice_badge_color = "primary";
    } elseif ($invoice_status == "Paid") {
        $invoice_badge_color = "success";
    } elseif ($invoice_status == "Cancelled") {
        $invoice_badge_color = "danger";
    } else {
        $invoice_badge_color = "secondary";
    }

    return $invoice_badge_color;
}

function getTicketStatusName($ticket_status) {

    global $mysqli;

    $status_id = intval($ticket_status);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id = $status_id LIMIT 1"));

    if ($row) {
        return escapeHtml($row['ticket_status_name']);
    }

    // Default return
    return "Unknown";

}

// Helper function for missing data fallback
function getFallback($data) {
    return !empty($data) ? $data : '-';
}

/**
 * Retrieves a specified field's value from a table based on the record's id.
 * It validates the table and field names, automatically determines the primary key (or uses the first column as fallback),
 * and returns the field value with an appropriate escaping method.
 *
 * @param string $table         The name of the table.
 * @param int    $id            The record's id.
 * @param string $field         The field (column) to retrieve.
 * @param string $escape_method The escape method: 'sql' (default, auto-detects int), 'html', 'json', or 'int'.
 *
 * @return mixed The escaped field value, or null if not found or invalid input.
 */
function getFieldById($table, $id, $field, $escape_method = 'sql') {
    global $mysqli;  // Use the global MySQLi connection

    // Validate table and field names to allow only letters, numbers, and underscores
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        return null; // Invalid table or field name
    }

    // Sanitize id as an integer
    $id = (int)$id;

    // Get the list of columns and their details from the table
    $columns_result = mysqli_query($mysqli, "SHOW COLUMNS FROM `$table`");
    if (!$columns_result || mysqli_num_rows($columns_result) == 0) {
        return null; // Table not found or has no columns
    }

    // Build an associative array with column details
    $columns = [];
    while ($row = mysqli_fetch_assoc($columns_result)) {
        $columns[$row['Field']] = [
            'type' => $row['Type'],
            'key'  => $row['Key']
        ];
    }

    // Find the primary key field if available
    $id_field = null;
    foreach ($columns as $col => $details) {
        if ($details['key'] === 'PRI') {
            $id_field = $col;
            break;
        }
    }
    // Fallback: if no primary key is found, use the first column
    if (!$id_field) {
        reset($columns);
        $id_field = key($columns);
    }

    // Ensure the requested field exists; if not, default to the id field
    if (!array_key_exists($field, $columns)) {
        $field = $id_field;
    }

    // Build and execute the query to fetch the specified field value
    $query = "SELECT `$field` FROM `$table` WHERE `$id_field` = $id";
    $sql = mysqli_query($mysqli, $query);

    if ($sql && mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        $value = $row[$field];

        // Apply the desired escaping method or auto-detect integer type if using SQL escaping
        switch ($escape_method) {
            case 'raw':
                return $value; // Return as-is from the database
            case 'html':
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); // Escape for HTML
            case 'json':
                return json_encode($value); // Escape for JSON
            case 'int':
                return (int)$value; // Explicitly cast value to integer
            case 'sql':
            default:
                // Auto-detect if the field type is integer
                if (stripos($columns[$field]['type'], 'int') !== false) {
                    return (int)$value;
                } else {
                    return escapeSql($value); // Escape for SQL using a custom function
                }
        }
    }

    return null; // Return null if no record was found
}

// Recursive function to display folder options - Used in folders files and documents
function displayFolderOptions($parent_folder_id, $client_id, $indent = 0) {
    global $mysqli;

    $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE parent_folder = $parent_folder_id AND folder_client_id = $client_id ORDER BY folder_name ASC");
    while ($row = mysqli_fetch_assoc($sql_folders)) {
        $folder_id = intval($row['folder_id']);
        $folder_name = escapeHtml($row['folder_name']);

        // Indentation for subfolders
        $indentation = str_repeat('&nbsp;', $indent * 4);

        // Check if this folder is selected
        $selected = '';
        if ((isset($_GET['folder_id']) && intval($_GET['folder_id']) === $folder_id) ||
            (isset($_POST['folder']) && intval($_POST['folder']) === $folder_id)) {
            $selected = 'selected';
        }

        echo "<option value=\"$folder_id\" $selected>$indentation$folder_name</option>";

        // Recursively display subfolders
        displayFolderOptions($folder_id, $client_id, $indent + 1);
    }
}

function checkForUpdates() {

    global $repo_branch;

    // Fetch the latest code changes but don't apply them
    exec("git fetch", $output, $result);
    $latest_version = exec("git rev-parse origin/$repo_branch");
    $current_version = exec("git rev-parse HEAD");

    if ($current_version == $latest_version) {
        $update_message = "No Updates available";
    } else {
        $update_message = "New Updates are Available [$latest_version]";
    }


    $updates = new stdClass();
    $updates->output = $output;
    $updates->result = $result;
    $updates->current_version = $current_version;
    $updates->latest_version = $latest_version;
    $updates->update_message = $update_message;


    return $updates;

}

function getMonthlyTax($tax_name, $month, $year, $mysqli)
{
    // SQL to calculate monthly tax
    $sql = "SELECT SUM(item_tax) AS monthly_tax FROM invoice_items
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) = $month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['monthly_tax'] ?? 0;
}

function getQuarterlyTax($tax_name, $quarter, $year, $mysqli)
{
    // Calculate start and end months for the quarter
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $start_month + 2;

    // SQL to calculate quarterly tax
    $sql = "SELECT SUM(item_tax) AS quarterly_tax FROM invoice_items
            LEFT JOIN invoices ON invoice_items.item_invoice_id = invoices.invoice_id
            LEFT JOIN payments ON invoices.invoice_id = payments.payment_invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) BETWEEN $start_month AND $end_month
            AND invoice_items.item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['quarterly_tax'] ?? 0;
}

function addToMailQueue($data) {

    global $mysqli;

    foreach ($data as $email) {
        $from = strval($email['from']);
        $from_name = strval($email['from_name']);
        $recipient = strval($email['recipient']);
        $recipient_name = strval($email['recipient_name']);
        $subject = strval($email['subject']);
        $body = strval($email['body']);

        $cal_str = '';
        if (isset($email['cal_str'])) {
            $cal_str = mysqli_escape_string($mysqli, $email['cal_str']);
        }

        // Check if 'email_queued_at' is set and not empty
        if (isset($email['queued_at']) && !empty($email['queued_at'])) {
            $queued_at = "'" . escapeSql($email['queued_at']) . "'";
        } else {
            // Use the current date and time if 'email_queued_at' is not set or empty
            $queued_at = 'CURRENT_TIMESTAMP()';
        }

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$recipient', email_recipient_name = '$recipient_name', email_from = '$from', email_from_name = '$from_name', email_subject = '$subject', email_content = '$body', email_queued_at = $queued_at, email_cal_str = '$cal_str'");
    }

    return true;
}

function createiCalStr($datetime, $title, $description, $location)
{
    require_once "plugins/zapcal/zapcallib.php";

    // Create the iCal object
    $cal_event = new ZCiCal();
    $event = new ZCiCalNode("VEVENT", $cal_event->curnode);


    // Set the method to REQUEST to indicate an invite
    $event->addNode(new ZCiCalDataNode("METHOD:REQUEST"));
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $title));
    $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($datetime)));
    // Assuming the end time is the same as start time.
    // Todo: adjust this for actual duration
    $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($datetime)));
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    $uid = date('Y-m-d-H-i-s') . "@" . $_SERVER['SERVER_NAME'];
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("LOCATION:" . $location));
    $event->addNode(new ZCiCalDataNode("DESCRIPTION:" . $description));
    // Todo: add organizer details
    // $event->addNode(new ZCiCalDataNode("ORGANIZER;CN=Organizer Name:MAILTO:organizer@example.com"));

    // Return the iCal string
    return $cal_event->export();
}

function createiCalStrCancel($originaliCalStr) {
    require_once "plugins/zapcal/zapcallib.php";

    // Import the original iCal string
    $cal_event = new ZCiCal($originaliCalStr);

    // Iterate through the iCalendar object to find VEVENT nodes
    foreach($cal_event->tree->child as $node) {
        if($node->getName() == "VEVENT") {
            // Check if STATUS node exists, update it, or add a new one
            $statusFound = false;
            foreach($node->data as $key => $value) {
                if($key == "STATUS") {
                    $value->setValue("CANCELLED");
                    $statusFound = true;
                    break; // Exit the loop once the STATUS is updated
                }
            }
            // If STATUS node is not found, add a new STATUS node
            if (!$statusFound) {
                $node->addNode(new ZCiCalDataNode("STATUS:CANCELLED"));
            }
        }
    }

    // Return the modified iCal string
    return $cal_event->export();
}
