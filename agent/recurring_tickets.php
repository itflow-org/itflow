<?php

/*
 * Recurring tickets - the schedules that raise tickets by themselves: the
 * monthly maintenance visit, the quarterly backup test. Each row says when
 * it runs next (and whether that was missed), how often, what it raised
 * last and how that one stands, and who gets it. The pills along the top
 * pick out the ones to look at: due this week, missed, paused, and nobody's.
 *
 * Old links still work: ?assigned=<agent> from the "assigned to you"
 * notifications, as well as ?assigned_agent=.
 */

// Default Column Sortby Filter
$sort = "recurring_ticket_next_run";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND recurring_ticket_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$can_edit = ticketCan(2);
$can_delete = ticketCan(3);

if (!isset($_GET['assigned_agent']) && isset($_GET['assigned'])) {
    $_GET['assigned_agent'] = $_GET['assigned'];
}
unset($_GET['assigned']);

// Client (all clients)
$client = 0;
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND recurring_ticket_client_id = $client";
}

// Category
$category_filter = intval($_GET['category'] ?? 0);
$category_query = $category_filter ? "AND recurring_ticket_category = $category_filter" : '';

// Agent
$agent_filter = intval($_GET['assigned_agent'] ?? 0);
$agent_query = $agent_filter ? "AND recurring_ticket_assigned_to = $agent_filter" : '';

// Billable - 1 or 0, or any
$billable_filter = isset($_GET['billable']) && in_array((string) $_GET['billable'], ['0', '1'], true) ? intval($_GET['billable']) : null;
$billable_query = $billable_filter === null ? '' : "AND recurring_ticket_billable = $billable_filter";

$filters = "(recurring_ticket_subject LIKE '%$q%' OR category_name LIKE '%$q%' OR client_name LIKE '%$q%' OR contact_name LIKE '%$q%' OR asset_name LIKE '%$q%')
    " . clientScopeSql('recurring_ticket_client_id') . " $client_query $category_query $agent_query $billable_query";

// Which ones - the pills along the top
$running_sql = 'recurring_ticket_paused_at IS NULL';
$shows = [
    '' => ['label' => 'All', 'where' => ''],
    'week' => ['label' => 'Due this week', 'icon' => 'fa-calendar-week', 'where' => "$running_sql AND recurring_ticket_next_run BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY",
        'title' => 'Raising a ticket in the next seven days'],
    'missed' => ['label' => 'Missed', 'icon' => 'fa-exclamation-triangle', 'where' => "$running_sql AND recurring_ticket_next_run < CURDATE()",
        'title' => 'Due before today and not raised - the nightly cron raises them on its next run', 'alarm' => 'danger'],
    'paused' => ['label' => 'Paused', 'icon' => 'fa-pause', 'where' => 'recurring_ticket_paused_at IS NOT NULL', 'title' => 'Kept, raising nothing until resumed'],
    'unassigned' => ['label' => 'Unassigned', 'icon' => 'fa-user-slash', 'where' => 'recurring_ticket_assigned_to = 0', 'title' => 'Raises tickets nobody has', 'alarm' => 'warning'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

$joins = "LEFT JOIN clients ON recurring_ticket_client_id = client_id
    LEFT JOIN contacts ON recurring_ticket_contact_id = contact_id
    LEFT JOIN assets ON recurring_ticket_asset_id = asset_id
    LEFT JOIN categories ON category_id = recurring_ticket_category
    LEFT JOIN users ON user_id = recurring_ticket_assigned_to
    LEFT JOIN ticket_templates ON ticket_template_id = recurring_ticket_ticket_template_id";

// Only these sort - an unknown column would take the page down
$frequency_days_sql = "CASE recurring_ticket_frequency WHEN 'Three Days' THEN 3 WHEN 'Weekly' THEN 7 WHEN 'Biweekly' THEN 14 WHEN 'Monthly' THEN 30 WHEN 'Quarterly' THEN 91 WHEN 'Biannually' THEN 182 WHEN 'Annually' THEN 365 ELSE 999 END";
$sortable_columns = [
    'recurring_ticket_next_run' => "recurring_ticket_paused_at IS NOT NULL, recurring_ticket_next_run $order",
    'recurring_ticket_subject' => "recurring_ticket_subject $order",
    'recurring_ticket_frequency' => "$frequency_days_sql $order",
    'recurring_ticket_priority' => "CASE recurring_ticket_priority WHEN 'Urgent' THEN 0 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END $order",
    'category_name' => "category_name $order",
    'user_name' => "recurring_ticket_assigned_to = 0 $order, user_name $order",
    'client_name' => "client_name $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'recurring_ticket_next_run';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS recurring_tickets.*, client_name, contact_name, contact_photo, asset_name,
        category_name, category_color, user_name, user_avatar, ticket_template_name,
        (SELECT COUNT(*) FROM recurring_ticket_tasks WHERE recurring_ticket_task_recurring_ticket_id = recurring_ticket_id) AS recurring_ticket_task_count
    FROM recurring_tickets $joins
    WHERE $filters $show_query
    ORDER BY {$sortable_columns[$sort]}, recurring_ticket_subject ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$recurring_tickets = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $recurring_tickets[intval($row['recurring_ticket_id'])] = $row;
}
$last_tickets = recurringTicketLastTickets(array_keys($recurring_tickets));

// How many of each, for the pills - over what the filters leave
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM recurring_tickets $joins WHERE $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-redo-alt me-2"></i>Recurring Tickets</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>" data-modal-size="lg">
                        <i class="fas fa-plus me-2"></i>New Recurring Ticket
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="tickets.php<?= $client_url ? '?' . rtrim($client_url, '&') : '' ?>">
                        <i class="fa fa-fw fa-life-ring me-2"></i>Tickets
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search recurring tickets...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="assigned_agent" onchange="this.form.submit()">
                        <option value="">- Any agent -</option>
                        <?php
                        $sql_agents_filter = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1
                            AND (EXISTS (SELECT 1 FROM recurring_tickets WHERE recurring_ticket_assigned_to = user_id) OR user_id = $agent_filter) ORDER BY user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_agents_filter)) {
                            $user_id = intval($row['user_id']); ?>
                            <option <?= $agent_filter === $user_id ? 'selected' : '' ?> value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()">
                        <option value="">- Any category -</option>
                        <?php
                        $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
                            WHERE category_type = 'Ticket' AND (EXISTS (SELECT 1 FROM recurring_tickets WHERE recurring_ticket_category = category_id) OR category_id = $category_filter) ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                            $category_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $category_id ? 'selected' : '' ?> value="<?= $category_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                JOIN recurring_tickets ON recurring_ticket_client_id = client_id
                                WHERE 1 = 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']); ?>
                                <option <?= $client === $client_id_filter ? 'selected' : '' ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-2' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <button class="dropdown-item confirm-link" type="submit" form="bulkActions" name="bulk_force_recurring_tickets"
                                        data-confirm-text="A ticket is raised now from each one, and its next run moves on.">
                                        <i class="fas fa-fw fa-paper-plane me-2"></i>Raise now
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_pause_recurring_tickets">
                                        <i class="fas fa-fw fa-pause me-2"></i>Pause
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_resume_recurring_tickets">
                                        <i class="fas fa-fw fa-play me-2"></i>Resume
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_bulk_agent_edit.php" data-bulk="true">
                                        <i class="fas fa-fw fa-user-check me-2"></i>Assign
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_bulk_priority_edit.php" data-bulk="true">
                                        <i class="fas fa-fw fa-thermometer-half me-2"></i>Set priority
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_bulk_category_edit.php" data-bulk="true">
                                        <i class="fas fa-fw fa-tag me-2"></i>Set category
                                    </a>
                                    <?php if ($config_module_enable_accounting && lookupUserPermission('module_sales') >= 2) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_bulk_billable_edit.php" data-bulk="true">
                                            <i class="fas fa-fw fa-dollar-sign me-2"></i>Set billing
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_bulk_next_run_edit.php" data-bulk="true">
                                        <i class="fas fa-fw fa-calendar-day me-2"></i>Set next run
                                    </a>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_recurring_tickets"
                                            data-confirm-text="The schedules go for good. Tickets they already raised stay.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
        <!-- Which ones - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $counts[$key] && !empty($s['alarm']) && $show !== $key ? $s['alarm'] : ''; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? "text-bg-$alarm" : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list">
                <thead class="<?php if (!$recurring_tickets) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th><?= $sort_link('recurring_ticket_subject', 'Recurring ticket') ?></th>
                    <th><?= $client_url ? 'For' : $sort_link('client_name', 'Client') ?></th>
                    <th><?= $sort_link('recurring_ticket_frequency', 'How often') ?></th>
                    <th><?= $sort_link('recurring_ticket_next_run', 'Next run') ?></th>
                    <th>Last ticket</th>
                    <th><?= $sort_link('user_name', 'Agent') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recurring_tickets as $recurring_ticket_id => $row) {
                    $paused = recurringTicketPaused($row);
                    $last = $last_tickets[$recurring_ticket_id] ?? null;
                    $edit_url = "modals/recurring_ticket/recurring_ticket_edit.php?id=$recurring_ticket_id";
                    $category_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $row['category_color']) ? $row['category_color'] : '';
                    $task_count = intval($row['recurring_ticket_task_count']);
                    $next = recurringTicketNextRunText($row);
                    $assignee = formatAssignee($row['recurring_ticket_assigned_to'], $row['user_name'], $row['user_avatar'], !$paused);
                    ?>
                    <tr class="itflow-ticket-row <?= $next['tone'] === 'danger' ? 'is-danger' : '' ?> <?= $paused ? 'text-body-secondary' : '' ?>">
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="recurring_ticket_ids[]" value="<?= $recurring_ticket_id ?>" aria-label="Select <?= escapeHtml($row['recurring_ticket_subject']) ?>">
                                </div>
                            </td>
                        <?php } ?>
                        <td class="itflow-ticket-main">
                            <?php if ($can_edit) { ?>
                                <a class="fw-semibold <?= $paused ? 'text-body-secondary' : 'text-body' ?> ajax-modal itflow-ticket-subject" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><?= escapeHtml($row['recurring_ticket_subject']) ?></a>
                            <?php } else { ?>
                                <span class="fw-semibold itflow-ticket-subject"><?= escapeHtml($row['recurring_ticket_subject']) ?></span>
                            <?php } ?>
                            <div class="itflow-ticket-meta small text-body-secondary">
                                <?= ticketPriorityHtml($row['recurring_ticket_priority'], 'detail') ?>
                                <?php if ($row['category_name'] !== null && $row['category_name'] !== '') { ?>
                                    <span class="text-nowrap"><i class="fas fa-circle itflow-ticket-dot" <?= $category_color ? 'style="color: ' . $category_color . '"' : '' ?>></i><?= escapeHtml($row['category_name']) ?></span>
                                <?php } ?>
                                <?php if (!empty($row['asset_name'])) { ?>
                                    <span class="text-nowrap text-truncate itflow-ticket-asset"><i class="fas fa-desktop me-1"></i><?= escapeHtml($row['asset_name']) ?></span>
                                <?php } ?>
                                <?php if ($task_count) { ?>
                                    <span class="text-nowrap" title="Adds <?= $task_count ?> task<?= $task_count === 1 ? '' : 's' ?>"><i class="fas fa-tasks me-1"></i><?= $task_count ?></span>
                                <?php } ?>
                                <?php if ($row['ticket_template_name']) { ?>
                                    <span class="text-nowrap" title="Template"><i class="fas fa-cube me-1"></i><?= escapeHtml($row['ticket_template_name']) ?></span>
                                <?php } ?>
                                <?php if (intval($row['recurring_ticket_billable'])) { ?>
                                    <span class="text-nowrap" title="Raises billable tickets"><i class="fas fa-dollar-sign"></i></span>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="itflow-ticket-who-cell">
                            <?php if (!$client_url) { ?>
                                <a class="d-block text-truncate text-body fw-semibold small" href="recurring_tickets.php?client_id=<?= intval($row['recurring_ticket_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php } ?>
                            <?php if (intval($row['recurring_ticket_contact_id']) && $row['contact_name'] !== null) { ?>
                                <div class="small text-truncate"><?= contactLink($row['recurring_ticket_client_id'], $row['recurring_ticket_contact_id'], $row['contact_name'], $row['contact_photo'] ?? '') ?></div>
                            <?php } elseif ($client_url) { ?>
                                <span class="small text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <div><i class="fas fa-redo-alt text-body-secondary small me-1"></i><?= escapeHtml($row['recurring_ticket_frequency']) ?></div>
                            <div class="small text-body-secondary"><?= escapeHtml(recurringTicketCadenceText($row)) ?></div>
                        </td>
                        <td class="text-nowrap">
                            <?= recurringTicketNextRunPill($row) ?>
                            <?php if (!$paused) { ?>
                                <div class="small text-body-secondary mt-1"><?= escapeHtml(date('D M j, Y', strtotime($row['recurring_ticket_next_run']))) ?></div>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?php if ($last) { ?>
                                <div class="text-nowrap"><a class="font-monospace" href="<?= escapeHtml(ticketUrl($last['ticket_id'], $last['ticket_client_id'])) ?>"><?= escapeHtml(ticketReference($last)) ?></a>
                                    <?= ticketStatusHtml($last['ticket_status_name'], $last['ticket_status_color'], 'detail') ?></div>
                                <div class="text-body-secondary" title="<?= escapeHtml($last['ticket_created_at']) ?>"><?= timeAgo($last['ticket_created_at']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">None yet</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <?= $can_edit ? quickPick('recurring_assignee', $recurring_ticket_id, (string) intval($row['recurring_ticket_assigned_to']), $assignee) : $assignee ?>
                        </td>
                        <td class="text-end">
                            <?php if ($can_edit) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item confirm-link" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="A ticket is raised now, and the next run moves on a step - this takes its place.">
                                            <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Raise now
                                        </a>
                                        <?php if ($paused) { ?>
                                            <a class="dropdown-item" href="post.php?resume_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-play text-secondary me-2"></i>Resume
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item" href="post.php?pause_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-pause text-secondary me-2"></i>Pause
                                            </a>
                                        <?php } ?>
                                        <?php if ($can_delete) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-text="The schedule goes for good. Tickets it already raised stay.">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => 'recurring tickets',
        'icon' => 'fa-redo-alt',
        'filtered' => $q !== '' || $category_filter || $agent_filter || $client || $billable_filter !== null,
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['missed', 'unassigned']),
        'text' => 'A monthly maintenance visit or a quarterly backup test is a good first one.',
        'actions' => $can_edit ? [['label' => 'New Recurring Ticket', 'modal' => "modals/recurring_ticket/recurring_ticket_add.php?$client_url", 'size' => 'lg']] : [],
    ];
    require_once '../includes/filter_footer.php';
    ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
