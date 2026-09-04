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
        $invoice_badge_color = "warning";
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
/*
 * Fixed SLA clock behaviour for the five built-in ticket statuses.
 *
 * New and Open always run the resolution clock; On Hold, Resolved and Closed
 * always stop it, and none of the five can be configured otherwise. A ticket
 * nobody can work on should not burn resolution budget, and a finished ticket
 * has a verdict rather than a clock.
 *
 * Resolved and Closed additionally stop BOTH clocks through ticket_resolved_at
 * and ticket_closed_at, which cron/ticket_sla.php and syncTicketSlaClock() read
 * directly - their behaviour never depended on this flag and still does not.
 * The flag is set on them so every SLA surface reads one consistent answer.
 *
 * Returns 1 or 0 for a built-in status, or null for a custom one (id > 5),
 * which the admin configures freely.
 */
function getTicketStatusSlaLock($ticket_status_id)
{
    $locked_statuses = [
        1 => 0, // New
        2 => 0, // Open
        3 => 1, // On Hold
        4 => 1, // Resolved
        5 => 1, // Closed
    ];

    return $locked_statuses[intval($ticket_status_id)] ?? null;
}

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

    $sql_task_templates = mysqli_query($mysqli, "SELECT task_template_completion_estimate, task_template_name, task_template_order FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id ORDER BY task_template_order ASC");

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

    $sql_folders = mysqli_query($mysqli, "SELECT folder_id, folder_name FROM folders WHERE parent_folder = $parent_folder_id AND folder_client_id = $client_id ORDER BY folder_name ASC");
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

/*
 * Reading git WITHOUT running git.
 *
 * Everything below answers from the files in .git, so it works on hosts where exec() and
 * shell_exec() are disabled and costs a couple of file reads instead of a process. Only
 * operations that need the network (git fetch) or the object database (a commit list) still
 * need the binary - see CONTRIBUTING rule 6.
 *
 * Returns '' rather than throwing on anything unexpected: a zip-drop install has no .git at
 * all, and every caller here is reporting, not deciding.
 */
function gitDir(): string
{
    $root = dirname(__DIR__);
    $git = $root . '/.git';

    if (is_dir($git)) {
        return $git;
    }

    // Submodules and linked worktrees put "gitdir: <path>" in a file instead of a directory
    if (is_file($git)) {
        $line = trim((string) @file_get_contents($git));

        if (str_starts_with($line, 'gitdir:')) {
            $path = trim(substr($line, 7));

            if ($path !== '' && $path[0] !== '/') {
                $path = $root . '/' . $path;
            }

            if ($path !== '' && is_dir($path)) {
                return $path;
            }
        }
    }

    return '';
}

/*
 * Where the refs live. A linked worktree keeps its own HEAD but shares the main repository's
 * refs, and points at them with a commondir file - resolve a ref against the worktree's own
 * directory and every lookup comes back empty.
 */
function gitCommonDir(): string
{
    $dir = gitDir();

    if ($dir === '') {
        return '';
    }

    $commondir = $dir . '/commondir';

    if (is_file($commondir)) {
        $path = trim((string) @file_get_contents($commondir));

        if ($path !== '' && $path[0] !== '/') {
            $path = $dir . '/' . $path;
        }

        if ($path !== '' && is_dir($path)) {
            return rtrim($path, '/');
        }
    }

    return $dir;
}

/*
 * The commit a ref points at, e.g. gitRefCommit('refs/remotes/origin/develop').
 *
 * A ref is either its own file or a line in packed-refs; git writes loose files and moves
 * them into packed-refs when it tidies up, so both have to be read. Lines starting with ^ in
 * packed-refs are the peeled target of an annotated tag, not a ref.
 */
function gitRefCommit(string $ref, int $depth = 0): string
{
    // The ref becomes part of a path, and $repo_branch reaches this from config.php
    if ($depth > 5 || str_contains($ref, '..') || !preg_match('#^[A-Za-z0-9._/-]+$#', $ref)) {
        return '';
    }

    $dir = gitCommonDir();

    if ($dir === '') {
        return '';
    }

    $loose = $dir . '/' . $ref;

    if (is_file($loose)) {
        $value = trim((string) @file_get_contents($loose));

        if (str_starts_with($value, 'ref: ')) {
            return gitRefCommit(trim(substr($value, 5)), $depth + 1);
        }

        return preg_match('/^[0-9a-f]{40}$/', $value) ? $value : '';
    }

    $packed = $dir . '/packed-refs';

    if (is_file($packed)) {
        foreach ((array) @file($packed, FILE_IGNORE_NEW_LINES) as $line) {

            if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                continue;
            }

            $parts = explode(' ', $line, 2);

            if (count($parts) === 2 && trim($parts[1]) === $ref) {
                return preg_match('/^[0-9a-f]{40}$/', $parts[0]) ? $parts[0] : '';
            }

        }
    }

    return '';
}

/* The branch this working tree is on, or 'HEAD' when it is detached. */
function gitCurrentBranch(): string
{
    $dir = gitDir();

    if ($dir === '') {
        return '';
    }

    $head = trim((string) @file_get_contents($dir . '/HEAD'));

    if (str_starts_with($head, 'ref: refs/heads/')) {
        return substr($head, 16);
    }

    return $head === '' ? '' : 'HEAD';
}

/* The commit this working tree is on - the file-read equivalent of git rev-parse HEAD. */
function gitCurrentCommit(): string
{
    $dir = gitDir();

    if ($dir === '') {
        return '';
    }

    $head = trim((string) @file_get_contents($dir . '/HEAD'));

    if (str_starts_with($head, 'ref: ')) {
        return gitRefCommit(trim(substr($head, 5)));
    }

    return preg_match('/^[0-9a-f]{40}$/', $head) ? $head : '';
}

/*
 * Where a command lives, or '' if it is not on the path - what `which` was being run for.
 *
 * PHP-FPM pools often ship a nearly empty PATH, so the usual locations are checked as well;
 * a missing hit here would otherwise read as "git is not installed" on a box where it is.
 */
function commandPath(string $command): string
{
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $command)) {
        return '';
    }

    $dirs = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));

    foreach (['/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin'] as $fallback) {
        if (!in_array($fallback, $dirs, true)) {
            $dirs[] = $fallback;
        }
    }

    foreach ($dirs as $dir) {
        $candidate = rtrim($dir, '/') . '/' . $command;

        if (@is_file($candidate) && @is_executable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/*
 * Whether this PHP can run external commands at all. Nothing in the web tier does any more -
 * checkForUpdates() is called from cron/update_check.php alone, and the Update page reads what
 * that job stored. This is what the job asks before it starts, and what admin/debug.php
 * reports, so a host with exec()/shell_exec() disabled shows why its checks stopped rather
 * than failing silently.
 *
 * function_exists() already reports a disabled function as missing. disable_functions is
 * read as well because some hardening extensions leave the function defined and refuse the
 * call instead, and a fatal on the Update page is a poor way to find that out.
 */
function shellCommandsAvailable(): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    foreach (['exec', 'shell_exec'] as $shell_function) {
        if (!function_exists($shell_function) || in_array($shell_function, $disabled, true)) {
            return false;
        }
    }

    return true;
}

/*
 * Whether a settings column exists yet.
 *
 * Maintenance > Update is the page that APPLIES database updates, so it has to keep working
 * against a schema older than the code it is running - the window between the files being
 * updated and the database catching up is exactly when somebody opens it. mysqli throws on
 * an unknown column, so a page that reads a column newer than the oldest schema it might
 * meet dies before it can render the button that fixes it.
 *
 * Anything else that runs before the migrations have caught up has the same problem, which
 * is why this takes the column name rather than answering one question.
 */
function settingsColumnExists($mysqli, string $column): bool
{
    $column = escapeSql($column);

    $result = mysqli_query($mysqli, "SHOW COLUMNS FROM `settings` LIKE '$column'");

    return $result && mysqli_num_rows($result) > 0;
}

function checkForUpdates() {

    $updates = new stdClass();

    // Nothing here can run without a shell. Reported as a failed check rather than left to
    // fatal, because the nightly job calls this too and one host's php.ini must not take
    // the whole cron cycle down with it.
    if (!shellCommandsAvailable()) {
        $updates->output = ["PHP on this server cannot run external commands, so ITFlow cannot check for updates."];
        $updates->result = 127;
        $updates->current_version = '';
        $updates->latest_version = '';
        $updates->update_message = "Cannot check for updates";
        $updates->pending_commits = [];

        return $updates;
    }

    // Fetch the latest code changes but don't apply them. stderr is merged in because git
    // reports failures there, and it is the only thing the update page can show when this
    // breaks - it used to run a second git fetch of its own just to get the message.
    exec("git fetch 2>&1", $output, $result);

    // Both sides of the comparison are read out of .git rather than shelled for - the fetch
    // has already written the remote-tracking ref by the time we get here
    $latest_version = gitRefCommit("refs/remotes/origin/" . getRepoBranch());
    $current_version = gitCurrentCommit();

    /*
     * The commits between here and there. Fields are separated by \x1f rather than letting
     * git format the row itself, because a subject comes from outside this install and used
     * to reach the Update page as unescaped HTML.
     *
     * The date is %aI (absolute, ISO 8601) rather than %ar. This result is stored and read
     * back hours later, and a stored "2 hours ago" is wrong the moment it is written - the
     * relative form is worked out at render time instead.
     */
    $updates->pending_commits = [];

    $remote_ref = escapeshellarg("origin/" . getRepoBranch());

    foreach (explode("\n", trim((string) shell_exec("git log HEAD..$remote_ref --pretty=format:'%h%x1f%aI%x1f%s'"))) as $commit_line) {

        if ($commit_line === '') {
            continue;
        }

        $commit_fields = explode("\x1f", $commit_line, 3);

        if (count($commit_fields) === 3) {
            $updates->pending_commits[] = $commit_fields;
        }

    }

    if ($current_version == $latest_version) {
        $update_message = "No Updates available";
    } else {
        $update_message = "New Updates are Available [$latest_version]";
    }


    $updates->output = $output;
    $updates->result = $result;
    $updates->current_version = $current_version;
    $updates->latest_version = $latest_version;
    $updates->update_message = $update_message;


    return $updates;

}

function getMonthlyTax($tax_name, $month, $year, $mysqli) {
    // Cash basis - tax is booked to the month the money arrived, in proportion to
    // how much of the invoice that payment covered. Driving off payments (rather
    // than invoice_items) counts each payment exactly once, and pre-aggregating
    // the line items stops a multi-payment invoice multiplying its own tax.
    $sql = "SELECT SUM(invoice_tax.tax_total * (payments.payment_amount / invoices.invoice_amount)) AS monthly_tax
            FROM payments
            INNER JOIN invoices ON invoices.invoice_id = payments.payment_invoice_id
            INNER JOIN (SELECT item_invoice_id, SUM(item_tax) AS tax_total
                        FROM invoice_items
                        WHERE item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')
                        GROUP BY item_invoice_id) AS invoice_tax
                ON invoice_tax.item_invoice_id = invoices.invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) = $month
            AND invoices.invoice_amount > 0";
    $result = mysqli_query($mysqli, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['monthly_tax'] ?? 0;
}

function getQuarterlyTax($tax_name, $quarter, $year, $mysqli) {
    // Calculate start and end months for the quarter
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $start_month + 2;

    // SQL to calculate quarterly tax - see getMonthlyTax for why it is shaped this way
    $sql = "SELECT SUM(invoice_tax.tax_total * (payments.payment_amount / invoices.invoice_amount)) AS quarterly_tax
            FROM payments
            INNER JOIN invoices ON invoices.invoice_id = payments.payment_invoice_id
            INNER JOIN (SELECT item_invoice_id, SUM(item_tax) AS tax_total
                        FROM invoice_items
                        WHERE item_tax_id = (SELECT tax_id FROM taxes WHERE tax_name = '$tax_name')
                        GROUP BY item_invoice_id) AS invoice_tax
                ON invoice_tax.item_invoice_id = invoices.invoice_id
            WHERE YEAR(payments.payment_date) = $year AND MONTH(payments.payment_date) BETWEEN $start_month AND $end_month
            AND invoices.invoice_amount > 0";
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

function getTicketCalendarUid($ticket_id) {
    // An invite and its later cancellation MUST carry the same UID or the
    // recipient's calendar client cannot match them up. Derive it from the
    // ticket so it is stable across both, rather than from the current time.
    $ticket_id = intval($ticket_id);
    $host = $_SERVER['SERVER_NAME'] ?? 'itflow';
    return "ticket-$ticket_id@$host";
}

function createiCalStr($datetime, $title, $description, $location, $uid = null) {
    require_once "../libs/zapcal/zapcallib.php";

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
    if (empty($uid)) {
        $uid = date('Y-m-d-H-i-s') . "@" . ($_SERVER['SERVER_NAME'] ?? 'itflow');
    }
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("SEQUENCE:0"));
    $event->addNode(new ZCiCalDataNode("LOCATION:" . $location));
    $event->addNode(new ZCiCalDataNode("DESCRIPTION:" . $description));
    // Todo: add organizer details
    // $event->addNode(new ZCiCalDataNode("ORGANIZER;CN=Organizer Name:MAILTO:organizer@example.com"));

    // Return the iCal string
    return $cal_event->export();
}

function createiCalStrCancel($datetime, $title, $uid) {
    require_once "../libs/zapcal/zapcallib.php";

    // Build the cancellation fresh. There is no stored copy of the original
    // invite to reopen - the match is made by UID, not by the body.
    $cal_event = new ZCiCal();

    // METHOD belongs on the VCALENDAR, not on the VEVENT
    $cal_event->tree->data['METHOD'] = new ZCiCalDataNode("METHOD:CANCEL");

    $event = new ZCiCalNode("VEVENT", $cal_event->curnode);
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $title));
    if (!empty($datetime)) {
        $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($datetime)));
        $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($datetime)));
    }
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    // Must outrank the invite's SEQUENCE:0 or clients ignore the cancellation
    $event->addNode(new ZCiCalDataNode("SEQUENCE:1"));
    $event->addNode(new ZCiCalDataNode("STATUS:CANCELLED"));

    return $cal_event->export();
}

/*
 * Which contacts a document's Send Email picker offers, as a SQL fragment.
 *
 * A client with fifty contacts should not hand the agent a fifty-row list to
 * scroll, so the picker is narrowed to the people a given document type is
 * actually addressed to:
 *
 *   invoice - primary and billing. A bill goes to whoever pays it.
 *   quote   - primary, billing, technical and important. A quote gets read by
 *             the person who scoped the work as often as the one who signs it.
 *
 * Lives here rather than inline in the modals because the Send Email button on
 * agent/invoice.php and agent/quote.php gates on a COUNT using the same rule -
 * if the two drift, the button appears and then the modal it opens reports
 * there is nobody to send to.
 *
 * Note the post handlers deliberately do NOT re-apply this filter. It is a
 * shortlist, not a permission boundary: any agent who can send a document can
 * already set these flags on a contact.
 */
function documentContactFilterSql($document_type) {
    if ($document_type === 'quote') {
        return "AND (contact_primary = 1 OR contact_billing = 1 OR contact_technical = 1 OR contact_important = 1)";
    }

    return "AND (contact_primary = 1 OR contact_billing = 1)";
}

/*
 * Which of the offered contacts are selected by default, as a SQL fragment.
 *
 * Two things read this and they must agree: the Send Email picker uses it to
 * decide which boxes open ticked, and Quick Send uses it as the whole
 * recipient list - Quick Send is exactly "send to the ticked ones without
 * opening the modal".
 *
 *   invoice - primary and billing, which is everyone the picker offers. A bill
 *             has no recipient you would routinely leave out.
 *   quote   - primary only. Technical and important contacts are offered
 *             because they often want the quote, but sending unasked to
 *             someone who did not request pricing is not a safe default.
 *
 * Always a subset of documentContactFilterSql() for the same document type.
 */
function documentDefaultContactFilterSql($document_type) {
    if ($document_type === 'quote') {
        return "AND contact_primary = 1";
    }

    return "AND (contact_primary = 1 OR contact_billing = 1)";
}

/*
 * The delivery methods offered by the Mark Sent modal on invoices and quotes.
 *
 * Marking a document sent records that it left the building by some route
 * other than ITFlow's own mailer, so the list is about how it got there. It is
 * deliberately a fixed list rather than a categories row: the post handler
 * validates the submitted value against it, and "Other" plus the free-text
 * note covers anything not listed.
 */
function getSentMethods() {
    return [
        'Sent by Snail Mail',
        'Sent by Email Client',
        'Hand Delivered',
        'Sent by Fax',
        'Sent by Courier',
        'Shared Guest Link',
        'Other'
    ];
}


/*
 * Products for the line-item autocomplete on invoices, quotes and recurring
 * invoices.
 *
 * All three pages share js/product_autocomplete.js, so they must all be handed
 * the same shape. They used to carry a SELECT each and they drifted: quote and
 * recurring invoice only selected label/description/price/tax, so the shared
 * onSelect wrote item.product_name - undefined - into the item name field.
 *
 * Returns a JSON string ready to emit into the page.
 */
function getProductsForAutocomplete($mysqli): string
{
    $products = [];

    $sql = mysqli_query($mysqli, "
        SELECT
            IF(product_code IS NULL OR product_code = '', product_name, CONCAT(product_code, ' - ', product_name)) AS label,
            product_name,
            product_code,
            product_type AS type,
            product_description AS description,
            product_price AS price,
            product_tax_id AS tax,
            tax_percent,
            product_id AS prod_id,
            COALESCE(SUM(product_stock.stock_qty), 0) AS available_stock
        FROM products
        LEFT JOIN product_stock ON product_id = stock_product_id
        LEFT JOIN taxes ON product_tax_id = tax_id
        WHERE product_archived_at IS NULL
        GROUP BY product_id
        ORDER BY product_name ASC
    ");

    while ($row = mysqli_fetch_assoc($sql)) {
        $products[] = $row;
    }

    return json_encode($products) ?: '[]';
}
