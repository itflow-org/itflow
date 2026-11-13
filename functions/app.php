<?php

// App/UI helpers - icons, badges, lookups, mail queue, iCal, taxes, update check
// Split from the former monolithic functions.php

/*
 * The address of one of ITFlow's own scripts or stylesheets, with when the file last changed
 * on the end - /js/app.js?v=1762700000. Browsers keep these files between visits, so without
 * it they went on using the copies from before an update, against pages that expect the new
 * ones, until someone cleared their cache. An update changes the files it brings, so their
 * addresses change with them and browsers fetch them again; the files it didn't touch keep
 * theirs, and stay cached.
 *
 * Every <script src> and <link rel="stylesheet" href> to a file in ITFlow - libs/ included -
 * goes through this: src="<?= staticFileUrl('/js/app.js') ?>".
 */
function staticFileUrl(string $path): string
{
    $changed = @filemtime(dirname(__DIR__) . '/' . ltrim($path, '/'));

    return $changed ? "$path?v=$changed" : $path;
}

/** The icon of an asset type, without its fa- (laptop) - from ASSET_TYPES (functions/asset_type.php); a tag for one it doesn't know. */
function getAssetIcon($asset_type) {
    return substr(assetTypeInfo($asset_type)['icon'], 3);
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

/*
 * getFieldById() for a record that belongs to a client: null when the agent
 * signed in can't open that client, as it is for a record that isn't there -
 * so a filter's label, made from an id in the address, never names another
 * client's record. $client_field is the record's own client column, where 0
 * is no client.
 */
function getFieldByIdInScope($table, $id, $field, $client_field) {
    $client_id = intval(getFieldById($table, $id, $client_field));

    return $client_id && !hasClientAccess($client_id) ? null : getFieldById($table, $id, $field);
}


// Recursive function to display folder options - Used in folders files and documents
/*
 * A page of a list in newest-first date order, read from the newest dates
 * back. $page(string $and) runs the list's query with $and added to its
 * WHERE and returns the page's rows; $date_column is the list's date, which
 * must be what the list sorts by first, descending; $needed is how many rows
 * the page has - min(the page size, the list's count less the rows before
 * the page).
 *
 * Tried over the last month, then the last year, then four, and only then the
 * lot. Every row older than a cutoff sorts after every row newer than it, so
 * when the rows from the cutoff on fill the page, the page is the same as the
 * whole list's would be. A list over a union (Transactions, Income) has the
 * database build and sort every line of it to give back 25; a date on the
 * union is handed down to each table, where its date index finds the month's
 * lines alone.
 */
function listNewestFirst(callable $page, $date_column, $needed) {
    $needed = intval($needed);
    if ($needed <= 0) {
        return [];
    }
    foreach (['-1 month', '-1 year', '-4 years'] as $back) {
        $rows = $page("AND $date_column >= '" . date('Y-m-d', strtotime($back)) . "'");
        if (count($rows) >= $needed) {
            return $rows;
        }
    }
    return $page('');
}

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

/*
 * What the Update page says about an update besides its commits, asked by
 * cron/update_check.php once checkForUpdates() has fetched - the page never runs git.
 *
 *   version        APP_VERSION on the remote branch - the release an update brings
 *   changelog      [[version, markdown], ...], newest first: the CHANGELOG.md sections
 *                  newer than this install's release, and on a branch that keeps one
 *                  version while work lands (develop), that version's section too
 *   local_changes  ITFlow's own files this install has changed, which updating puts back
 *                  (update_cli.php resets to the remote) - the first 50, and how many in all
 *
 * Anything that can't be read comes back empty rather than failing the check.
 */
function updateCheckDetails(int $pending_commits): array
{
    if (!defined('APP_VERSION')) {
        require_once __DIR__ . '/../includes/app_version.php';
    }

    $details = ['version' => '', 'changelog' => [], 'local_changes' => [], 'local_changes_count' => 0];

    if (!shellCommandsAvailable()) {
        return $details;
    }

    $remote = "origin/" . getRepoBranch();

    $version_file = (string) shell_exec("git show " . escapeshellarg("$remote:includes/app_version.php") . " 2>/dev/null");
    if (preg_match('/DEFINE\(\s*["\']APP_VERSION["\']\s*,\s*["\']([^"\']+)["\']\s*\)/i', $version_file, $version_match)) {
        $details['version'] = $version_match[1];
    }

    $changelog = (string) shell_exec("git show " . escapeshellarg("$remote:CHANGELOG.md") . " 2>/dev/null");
    $include_current = $pending_commits > 0 && $details['version'] === APP_VERSION;
    $details['changelog'] = updateChangelogSections($changelog, APP_VERSION, $include_current);

    $changed = [];
    exec("git status --porcelain --untracked-files=no 2>/dev/null", $changed);
    foreach ($changed as $line) {
        $path = trim(substr($line, 3));
        if ($path !== '') {
            $details['local_changes_count']++;
            if (count($details['local_changes']) < 50) {
                $details['local_changes'][] = $path;
            }
        }
    }

    return $details;
}

/*
 * The sections of a CHANGELOG.md newer than $installed - [[version, markdown], ...] in the
 * file's order, newest first - plus $installed's own when $include_current. At most twelve
 * releases and about 200 KB, so an install far behind still stores a sensible amount.
 */
function updateChangelogSections(string $markdown, string $installed, bool $include_current = false): array
{
    preg_match_all('/^## \[([^\]\n]+)\][^\n]*\n(.*?)(?=^## \[|\z)/ms', $markdown, $matches, PREG_SET_ORDER);

    $sections = [];
    $size = 0;

    foreach ($matches as [, $version, $body]) {
        $version = trim($version);
        $newer = $installed === '' || version_compare($version, $installed, '>');

        if (!$newer && !($include_current && $version === $installed)) {
            continue;
        }

        $body = trim($body);
        $size += strlen($body);

        if (count($sections) >= 12 || ($sections && $size > 200000)) {
            break;
        }

        $sections[] = [$version, $body];
    }

    return $sections;
}

/*
 * One changelog section as HTML for the Update page. CHANGELOG.md uses a small part of
 * Markdown - ### headings, - lists, paragraphs, `code`, **bold** and links - and only that is
 * understood. Everything is escaped first, so nothing in the file reaches the page as markup.
 * Developer Updates is folded away: the people reading this page are the MSP's admins.
 */
function updateChangelogHtml(string $markdown): string
{
    $inline = function (string $text): string {
        $text = escapeHtml($text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        return preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function ($link) {
            return '<a href="' . $link[2] . '" target="_blank" rel="noopener noreferrer">' . $link[1] . '</a>';
        }, $text);
    };

    // Into its ### parts - the text before the first heading has none
    $parts = [];
    $title = '';
    $lines = [];
    foreach (preg_split('/\r?\n/', $markdown) as $line) {
        if (preg_match('/^###\s+(.+)$/', $line, $heading)) {
            if ($title !== '' || trim(implode('', $lines)) !== '') {
                $parts[] = [$title, $lines];
            }
            $title = trim($heading[1]);
            $lines = [];
            continue;
        }
        $lines[] = $line;
    }
    if ($title !== '' || trim(implode('', $lines)) !== '') {
        $parts[] = [$title, $lines];
    }

    $html = '';
    foreach ($parts as [$title, $lines]) {
        // Paragraphs and lists - a line indented under a list item carries on that item
        $body = '';
        $paragraph = [];
        $items = [];
        $flush = function () use (&$body, &$paragraph, &$items, $inline) {
            if ($paragraph) {
                $body .= '<p>' . $inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
            if ($items) {
                $body .= '<ul>' . implode('', array_map(function ($item) use ($inline) {
                    return '<li>' . $inline($item) . '</li>';
                }, $items)) . '</ul>';
                $items = [];
            }
        };
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $flush();
            } elseif (preg_match('/^\s{0,3}[-*]\s+(.*)$/', $line, $item)) {
                if ($paragraph) {
                    $flush();
                }
                $items[] = $item[1];
            } elseif ($items && preg_match('/^\s+\S/', $line)) {
                $items[count($items) - 1] .= ' ' . trim($line);
            } else {
                if ($items) {
                    $flush();
                }
                $paragraph[] = trim($line);
            }
        }
        $flush();

        if ($title === '') {
            $html .= $body;
        } elseif (stripos($title, 'developer') !== false) {
            $html .= '<details class="itflow-changelog-dev mt-3"><summary class="text-secondary">' . escapeHtml($title) . '</summary><div class="mt-2 small">' . $body . '</div></details>';
        } else {
            $html .= '<h6 class="text-uppercase text-secondary small fw-semibold mt-3 mb-2">' . escapeHtml($title) . '</h6>' . $body;
        }
    }

    return $html;
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

        // The ticket reply it carries (emailQueue()'s ticket_reply_id), once the 3.4.8 update has made the column
        $ticket_reply_sql = !empty($email['ticket_reply_id']) ? ', email_ticket_reply_id = ' . intval($email['ticket_reply_id']) : '';

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$recipient', email_recipient_name = '$recipient_name', email_from = '$from', email_from_name = '$from_name', email_subject = '$subject', email_content = '$body', email_queued_at = $queued_at, email_cal_str = '$cal_str', email_attachments = '$attachments'$ticket_reply_sql");
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

/*
 * The calendar file sent with a ticket's "scheduled" email - the visit, an hour
 * long, in the instance timezone - for the recipient to add to their calendar.
 * Published rather than sent as a meeting request: the support mailbox is not
 * an organiser people should be accepting invitations from, and an "Accepted:"
 * reply would arrive there as a new ticket. Rescheduling sends it again with the
 * same UID and a higher SEQUENCE, which moves the one already added.
 */
function createiCalStr($datetime, $title, $description, $location, $uid = null) {
    global $config_timezone;
    if (empty($uid)) {
        $uid = date('Y-m-d-H-i-s') . "@" . ($_SERVER['SERVER_NAME'] ?? 'itflow');
    }
    return icsCalendar(['method' => 'PUBLISH', 'invite' => true], [[
        'uid' => $uid,
        'start' => $datetime,
        'end' => date('Y-m-d H:i:s', strtotime($datetime) + CALENDAR_TICKET_MINUTES * 60),
        'summary' => $title,
        'description' => $description,
        'location' => $location,
        'stamp' => date('Y-m-d H:i:s'),
        'sequence' => icsSequenceNow(),
        'status' => 'CONFIRMED',
    ]], $config_timezone);
}

/*
 * Its cancellation. There is no stored copy of the original to reopen - the
 * match is made by UID - and the SEQUENCE must outrank the one it was sent with.
 */
function createiCalStrCancel($datetime, $title, $uid) {
    global $config_timezone;
    return icsCalendar(['method' => 'CANCEL', 'invite' => true], [[
        'uid' => $uid,
        'start' => $datetime ?: date('Y-m-d H:i:s'),
        'end' => date('Y-m-d H:i:s', strtotime($datetime ?: 'now') + CALENDAR_TICKET_MINUTES * 60),
        'summary' => $title,
        'stamp' => date('Y-m-d H:i:s'),
        'sequence' => icsSequenceNow(),
        'status' => 'CANCELLED',
    ]], $config_timezone);
}

/*
 * A SEQUENCE that only goes up: minutes since 2020. For what has nowhere to
 * keep a counter - a ticket's schedule - each resend outranks the last.
 */
function icsSequenceNow() {
    return intdiv(time() - 1577836800, 60);
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
 * All three pages share js/billing_items.js, so they must all be handed the
 * same shape. They used to carry a SELECT each and they drifted: quote and
 * recurring invoice only selected label/description/price/tax, so the shared
 * onSelect wrote item.product_name - undefined - into the item name field.
 *
 * Returns a JSON string ready to emit into the page.
 */
function getProductsForAutocomplete($mysqli, $client_id = 0): string
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
            product_frequency AS frequency,
            product_seat_source AS seat_source,
            product_track_stock AS track_stock,
            " . productStockSql() . " AS available_stock
        FROM products
        LEFT JOIN taxes ON product_tax_id = tax_id
        WHERE product_archived_at IS NULL
        ORDER BY product_name ASC
    ");

    while ($row = mysqli_fetch_assoc($sql)) {
        // For a client, the rate a line of it gets them - none when they are
        // tax exempt, their own rate in place of the product's (functions/tax.php)
        if ($client_id) {
            $row['tax'] = taxDefaultFor($client_id, 'product', intval($row['tax']));
            $tax_row = taxRow($row['tax']);
            $row['tax_percent'] = $tax_row ? $tax_row['tax_percent'] : null;
        }
        $products[] = $row;
    }

    // Safe inside a <script> element, whatever a product's name or description holds
    return json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP) ?: '[]';
}

/*
 * Agent user limit
 *
 * Optional cap on the number of active agent users, set in config.php:
 *     $config_agent_user_limit = 5;
 * Unset or 0 means unlimited, which is the default for every self-hosted install.
 * It lives in config.php rather than the settings table on purpose: on a hosted
 * instance the provider controls config.php and the customer's admins do not, so
 * nobody inside the app can raise their own limit.
 *
 * A managed instance's manager can set one instead (functions/managed.php), and when it does,
 * it is the one that counts - that is where the instance's plan is kept.
 *
 * An active agent is user_type 1, enabled and not archived. Disabled and archived
 * users do not use a seat. An invite waiting and in date does
 * (userInvitesHoldingSeats(), functions/user_invite.php) - it is a seat promised.
 * The manager's support user doesn't: it is the manager's, not one of theirs.
 */
function getAgentUserLimit(): int
{
    global $config_agent_user_limit;

    $managed = managedSeatLimit();
    if ($managed > 0) {
        return $managed;
    }

    return max(0, intval($config_agent_user_limit ?? 0));
}

function getActiveAgentUserCount(): int
{
    global $mysqli;

    $support_user_id = managedSupportUserId();

    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(user_id) FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL"
        . ($support_user_id ? " AND user_id <> $support_user_id" : '')));

    return intval($row[0]);
}

/** Seats taken: active agents, and invites waiting in date (leaving out $except_invite_id). */
function getAgentSeatsTaken(int $except_invite_id = 0): int
{
    return getActiveAgentUserCount() + userInvitesHoldingSeats($except_invite_id);
}

function agentUserLimitReached(int $except_invite_id = 0): bool
{
    $limit = getAgentUserLimit();

    return $limit > 0 && getAgentSeatsTaken($except_invite_id) >= $limit;
}

function agentUserLimitMessage(): string
{
    $limit = getAgentUserLimit();
    $invites = userInvitesHoldingSeats();
    $provider = managedSeatLimit() > 0 ? escapeHtml(managedManagerName()) : 'your provider';

    return "Agent user limit reached - this installation allows <strong>$limit</strong> active agent " . ($limit == 1 ? "user" : "users")
        . ($invites ? ", and $invites invite" . ($invites === 1 ? ' is' : 's are') . " waiting for one" : '')
        . ". Archive or disable a user" . ($invites ? ' or cancel an invite' : '') . " first, or contact $provider to add more.";
}
