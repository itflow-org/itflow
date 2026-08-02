<?php

// App/UI helpers - icons, badges, lookups, mail queue, iCal, taxes, update check
// Split from the former monolithic functions.php

function getAssetIcon($asset_type) {
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

function getInvoiceBadgeColor($invoice_status) {
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

/*
 * The display name for a ticket status id, RAW. Escaping is the caller's job -
 * same convention as getFieldById() above.
 */
function getTicketStatusName($ticket_status) {

    global $mysqli;

    $status_id = intval($ticket_status);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_id = $status_id LIMIT 1"));

    if (!$row) {
        // Default return
        return "Unknown";
    }

    return $row['ticket_status_name'];

}


/**
 * Copies a ticket template's tasks onto a ticket.
 *
 * Called anywhere a ticket is raised from a template - the ticket add modals,
 * the bulk asset add, and every recurring ticket run (cron, force, bulk force).
 * Tasks are a snapshot: editing the template later does not touch tickets that
 * have already been raised from it.
 *
 * @param int $ticket_id          The ticket to attach the tasks to.
 * @param int $ticket_template_id The template to copy tasks from. 0 = no-op.
 *
 * @return int The number of tasks created.
 */
function addTasksFromTicketTemplate($ticket_id, $ticket_template_id) {

    global $mysqli;

    $ticket_id = intval($ticket_id);
    $ticket_template_id = intval($ticket_template_id);

    if (!$ticket_id || !$ticket_template_id) {
        return 0;
    }

    $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id ORDER BY task_template_order ASC");

    $tasks_added = 0;

    while ($row = mysqli_fetch_assoc($sql_task_templates)) {
        $task_order = intval($row['task_template_order']);
        $task_name = escapeSql($row['task_template_name']);
        $task_completion_estimate = intval($row['task_template_completion_estimate']);

        mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate, task_ticket_id = $ticket_id");

        $tasks_added++;
    }

    return $tasks_added;

}

/**
 * Copies a recurring ticket's task list onto a ticket it has just raised.
 *
 * Recurring tickets own their tasks (see recurring_ticket_tasks) rather than
 * reading the linked ticket template at run time, so that a schedule's task
 * list can be edited without touching the template or any other schedule.
 *
 * @param int $ticket_id           The ticket to attach the tasks to.
 * @param int $recurring_ticket_id The schedule to copy tasks from. 0 = no-op.
 *
 * @return void
 */
function addTasksFromRecurringTicket($ticket_id, $recurring_ticket_id) {

    global $mysqli;

    $ticket_id = intval($ticket_id);
    $recurring_ticket_id = intval($recurring_ticket_id);

    if (!$ticket_id || !$recurring_ticket_id) {
        return;
    }

    mysqli_query($mysqli, "INSERT INTO tasks (task_name, task_order, task_completion_estimate, task_ticket_id)
        SELECT recurring_ticket_task_name, recurring_ticket_task_order, recurring_ticket_task_completion_estimate, $ticket_id
        FROM recurring_ticket_tasks
        WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id
        ORDER BY recurring_ticket_task_order ASC");

}

/**
 * Reads the editable task rows posted by the ticket and recurring ticket modals.
 *
 * The rows submit as parallel tasks[] and task_estimates[] arrays, aligned by
 * their order in the form. Rows left blank are dropped, and the order is taken
 * from the surviving rows rather than the raw array index.
 *
 * Names come back already escaped for SQL, as every caller inserts them.
 *
 * @return array List of ['name' => string, 'order' => int, 'estimate' => int]
 */
function parseSubmittedTasks() {

    $tasks = [];

    if (empty($_POST['tasks']) || !is_array($_POST['tasks'])) {
        return $tasks;
    }

    $estimates = $_POST['task_estimates'] ?? [];
    $task_order = 0;

    foreach ($_POST['tasks'] as $index => $task_name) {
        $task_name = trim($task_name);

        if ($task_name === '') {
            continue;
        }

        $tasks[] = [
            'name' => escapeSql($task_name),
            'order' => $task_order,
            'estimate' => intval($estimates[$index] ?? 0)
        ];

        $task_order++;
    }

    return $tasks;
}

/*
 * Fetches one field from one row by id, and returns it RAW.
 *
 * Escaping is the caller's job, the same as any other value read out of the
 * database - wrap the call in escapeSql() for a query or escapeHtml() for
 * output. This function used to escape for you via an $escape_method argument,
 * which meant half its callers wrapped it in escapeSql() anyway and got a
 * double-escaped value: a client named O'Brien came back as O\'Brien and the
 * backslash ended up in export filenames, flash messages and, on the user
 * restore path, written back into the database.
 *
 * Table, field and id are still validated here - that is about building a safe
 * query, not about escaping what comes out of it.
 */
function getFieldById($table, $id, $field) {
    global $mysqli;  // Use the global MySQLi connection

    // Validate table and field names to allow only letters, numbers, and underscores
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        return null; // Invalid table or field name
    }

    // Sanitize id as an integer
    $id = (int)$id;

    /*
     * Get the list of columns from the table, to find the primary key and to
     * confirm the requested field exists.
     *
     * The catch is what makes the "table not found" case actually return null:
     * mysqli throws on an unknown table by default on PHP 8.1+, so this
     * function's own not-found branch was unreachable and a bad table name
     * took the whole page down instead.
     */
    try {
        $columns_result = mysqli_query($mysqli, "SHOW COLUMNS FROM `$table`");
    } catch (mysqli_sql_exception $e) {
        return null; // Table not found
    }

    if (!$columns_result || mysqli_num_rows($columns_result) == 0) {
        return null; // Table not found or has no columns
    }

    $columns = [];
    $id_field = null;
    while ($row = mysqli_fetch_assoc($columns_result)) {
        $columns[$row['Field']] = true;
        if (!$id_field && $row['Key'] === 'PRI') {
            $id_field = $row['Field'];
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
    $sql = mysqli_query($mysqli, "SELECT `$field` FROM `$table` WHERE `$id_field` = $id");

    if ($sql && mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        return $row[$field];
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

/*
 * The branch this install tracks. Both setup paths write $repo_branch into config.php, but
 * an install older than that has no value at all, and every caller puts it into a shell
 * command - so it is defaulted and escaped here rather than trusted at each call site.
 */
function getRepoBranch(): string
{
    global $repo_branch;

    $branch = trim((string) ($repo_branch ?? ''));

    return $branch === '' ? 'master' : $branch;
}

function checkForUpdates() {

    $remote_ref = escapeshellarg("origin/" . getRepoBranch());

    // Fetch the latest code changes but don't apply them. stderr is merged in because git
    // reports failures there, and it is the only thing the update page can show when this
    // breaks - it used to run a second git fetch of its own just to get the message.
    exec("git fetch 2>&1", $output, $result);
    $latest_version = exec("git rev-parse $remote_ref");
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

function getMonthlyTax($tax_name, $month, $year, $mysqli) {
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

function getQuarterlyTax($tax_name, $quarter, $year, $mysqli) {
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

        // Attachments travel as a manifest of app-root-relative paths rather than
        // file contents, so the queue table stays small. cron/mail_queue.php
        // re-checks each path is inside uploads/ before attaching it.
        $attachments = '';
        if (!empty($email['attachments']) && is_array($email['attachments'])) {
            $attachment_manifest = [];
            foreach ($email['attachments'] as $attachment) {
                if (empty($attachment['path']) || empty($attachment['name'])) {
                    continue;
                }
                $attachment_manifest[] = [
                    'path' => strval($attachment['path']),
                    'name' => strval($attachment['name'])
                ];
            }
            if ($attachment_manifest) {
                $attachments = mysqli_escape_string($mysqli, json_encode($attachment_manifest));
            }
        }

        // Check if 'email_queued_at' is set and not empty
        if (isset($email['queued_at']) && !empty($email['queued_at'])) {
            $queued_at = "'" . escapeSql($email['queued_at']) . "'";
        } else {
            // Use the current date and time if 'email_queued_at' is not set or empty
            $queued_at = 'CURRENT_TIMESTAMP()';
        }

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$recipient', email_recipient_name = '$recipient_name', email_from = '$from', email_from_name = '$from_name', email_subject = '$subject', email_content = '$body', email_queued_at = $queued_at, email_cal_str = '$cal_str', email_attachments = '$attachments'");
    }

    return true;
}

function createiCalStr($datetime, $title, $description, $location) {
    require_once "libs/zapcal/zapcallib.php";

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
    require_once "libs/zapcal/zapcallib.php";

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
