<?php

/*
 * Admin > Ticket Templates - tickets ready to raise: a subject, details and
 * tasks, and how the ticket is set up (category, priority, billing, agent, due
 * time and tags). Each row shows what it sets up, its tasks and their time,
 * and how often it has been used. A template can be changed, copied as the
 * start of another, archived - kept for the recurring tickets and project
 * templates that use it, not offered for new tickets - or deleted. The work is
 * in functions/ticket_template.php; the handlers are admin/post/ticket_template.php.
 */

// Default Column Sortby Filter
$sort = "ticket_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// ?archived=1 is how older links asked for the archived ones
$show_archived = ($_GET['show'] ?? '') === 'archived' || !empty($_GET['archived']);

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'ticket_template_name' => "ticket_template_name $order",
    'task_count' => "task_count $order, ticket_template_name ASC",
    'used_count' => "used_count $order, ticket_template_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, ticket_template_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'ticket_template_name';
}

$search = "(ticket_template_name LIKE '%$q%' OR ticket_template_description LIKE '%$q%' OR ticket_template_subject LIKE '%$q%')";

// Its use is the tickets raised from it that are not archived - as ticketTemplateUsage() counts them
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS ticket_templates.*, category_name, category_color, category_icon, user_name AS assignee_name,
        (SELECT COUNT(*) FROM task_templates WHERE task_template_ticket_template_id = ticket_templates.ticket_template_id) AS task_count,
        (SELECT COALESCE(SUM(task_template_completion_estimate), 0) FROM task_templates WHERE task_template_ticket_template_id = ticket_templates.ticket_template_id) AS task_minutes,
        (SELECT COUNT(*) FROM recurring_tickets WHERE recurring_ticket_ticket_template_id = ticket_templates.ticket_template_id) AS recurring_count,
        (SELECT COUNT(*) FROM project_template_ticket_templates WHERE project_template_ticket_templates.ticket_template_id = ticket_templates.ticket_template_id) AS project_count,
        COALESCE(used.used_count, 0) AS used_count, COALESCE(used.open_count, 0) AS open_count, used.last_used
    FROM ticket_templates
    LEFT JOIN categories ON category_id = ticket_template_category_id AND ticket_template_category_id > 0
    LEFT JOIN users ON user_id = ticket_template_assigned_to AND ticket_template_assigned_to > 0
    LEFT JOIN (
        SELECT ticket_template_id AS used_template_id, COUNT(*) AS used_count, SUM(ticket_closed_at IS NULL AND ticket_resolved_at IS NULL) AS open_count, MAX(ticket_created_at) AS last_used
        FROM tickets WHERE ticket_template_id > 0 AND ticket_archived_at IS NULL GROUP BY ticket_template_id
    ) used ON used.used_template_id = ticket_templates.ticket_template_id
    WHERE $search AND ticket_template_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, ticket_templates.ticket_template_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$templates = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['ticket_template_id'])] = $row;
}

// What each asks for - its fill-ins, from its rows and the bracketed words in its text
$template_tasks = ticketTemplateTasksFor(array_keys($templates));
$template_fill_in_rows = ticketTemplateFillInRowsFor(array_keys($templates));
$tag_rows = [];
if ($templates) {
    $sql_tags = mysqli_query($mysqli, "SELECT ticket_template_id, tags.* FROM ticket_template_tags JOIN tags ON tags.tag_id = ticket_template_tags.tag_id
        WHERE ticket_template_id IN (" . implode(',', array_keys($templates)) . ") ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql_tags)) {
        $tag_rows[intval($row['ticket_template_id'])][] = $row;
    }
}

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(ticket_template_archived_at IS NULL), 0), COALESCE(SUM(ticket_template_archived_at IS NOT NULL), 0) FROM ticket_templates WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring me-2"></i>Ticket Templates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket_template/ticket_template_add.php">
                    <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Ticket Template</span>
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_template/ticket_template_import.php">
                        <i class="fas fa-fw fa-file-upload text-secondary me-2"></i>Import
                    </a>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_template/ticket_template_export.php">
                        <i class="fas fa-fw fa-file-download text-secondary me-2"></i>Export
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/agent/reports/ticket_templates.php">
                        <i class="fas fa-fw fa-chart-bar text-secondary me-2"></i>How they are used
                    </a>
                    <a class="dropdown-item" href="starter_content.php">
                        <i class="fas fa-fw fa-seedling text-secondary me-2"></i>Starter templates
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search ticket templates..." title="Name, description or subject">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-life-ring me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'archived']))) ?>" title="Not offered for new tickets - recurring tickets and project templates that use them keep them"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('ticket_template_name', 'Template') ?></th>
                <th class="d-none d-lg-table-cell">Sets up</th>
                <th class="d-none d-sm-table-cell text-center"><?= $sort_link('task_count', 'Tasks') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('used_count', 'Used') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $template_id => $row) {
                $name = escapeHtml($row['ticket_template_name']);
                $task_count = intval($row['task_count']);
                $minutes = intval($row['task_minutes']);
                $used_count = intval($row['used_count']);
                $open_count = intval($row['open_count']);
                $tags = $tag_rows[$template_id] ?? [];
                $defaults = ticketTemplateDefaultsHtml($row);
                $fill_in_count = count(ticketTemplateFillIns($row, $template_tasks[$template_id] ?? [], $template_fill_in_rows[$template_id] ?? []));
                if ($fill_in_count) {
                    $defaults .= ($defaults !== '' ? ' ' : '') . '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-pen-square text-secondary me-1"></i>Asks for ' . $fill_in_count . ' thing' . ($fill_in_count === 1 ? '' : 's') . '</span>';
                }
                if (intval($row['ticket_template_portal'])) {
                    $defaults .= ($defaults !== '' ? ' ' : '') . '<span class="badge rounded-pill text-bg-light border fw-normal" title="Offered in the client portal as ' . escapeHtml(ticketTemplatePortalTitle($row)) . '"><i class="fas fa-fw fa-door-open text-secondary me-1"></i>In the portal</span>';
                }
                $tags_html = $tags ? '<span class="d-inline-flex flex-wrap gap-1">' . implode('', array_map('tagPill', $tags)) . '</span>' : '';
                $set_up = trim($defaults . ($defaults !== '' && $tags_html !== '' ? ' ' : '') . $tags_html);
                $linked = [];
                if (intval($row['recurring_count'])) {
                    $linked[] = intval($row['recurring_count']) . ' recurring ticket' . (intval($row['recurring_count']) === 1 ? '' : 's');
                }
                if (intval($row['project_count'])) {
                    $linked[] = intval($row['project_count']) . ' project template' . (intval($row['project_count']) === 1 ? '' : 's');
                }
                $used_text = $used_count . ' ticket' . ($used_count === 1 ? '' : 's');
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-life-ring"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="ticket_template.php?ticket_template_id=<?= $template_id ?>"><?= $name ?></a>
                                <?php if (trim((string) $row['ticket_template_description']) !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($row['ticket_template_description']) ?></div>
                                <?php } elseif (trim((string) $row['ticket_template_subject']) !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($row['ticket_template_subject']) ?></div>
                                <?php } ?>
                                <?php if ($row['ticket_template_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['ticket_template_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens what it sets up and its use come along under the name -->
                                <?php if ($set_up !== '') { ?><div class="d-lg-none mt-1"><?= $set_up ?></div><?php } ?>
                                <div class="d-md-none small text-body-secondary mt-1"><?= $used_count ? $used_text . ' · last ' . timeAgo($row['last_used']) : 'Not used yet' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell"><?= $set_up !== '' ? $set_up : '<span class="small text-body-secondary">Just the subject and details</span>' ?></td>
                    <td class="d-none d-sm-table-cell text-center text-nowrap">
                        <?php if ($task_count) { ?>
                            <div><?= $task_count ?></div>
                            <?php if ($minutes) { ?><div class="small text-body-secondary"><i class="far fa-fw fa-clock me-1"></i><?= escapeHtml(ticketTemplateMinutesText($minutes)) ?></div><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($used_count) { ?>
                            <div class="small text-nowrap"><?= $used_text ?><?= $open_count ? ' <span class="text-body-secondary">· ' . $open_count . ' open</span>' : '' ?></div>
                        <?php } else { ?>
                            <span class="small text-body-secondary">Not yet</span>
                        <?php } ?>
                        <?php if ($linked) { ?><div class="small text-body-secondary"><i class="fas fa-fw fa-link me-1"></i><?= escapeHtml(implode(', ', $linked)) ?></div><?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_used'] ? ' title="' . escapeHtml($row['last_used']) . '"' : '' ?>><?= $row['last_used'] ? timeAgo($row['last_used']) : '-' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket_template/ticket_template_edit.php?id=<?= $template_id ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if (!$row['ticket_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="/agent/tickets.php?new_from_template=<?= $template_id ?>">
                                        <i class="fas fa-fw fa-plus text-secondary me-2"></i>New ticket from it
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item" href="post.php?copy_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['ticket_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="Not offered for new tickets - recurring tickets and project templates that use it keep it">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its tasks go with it<?= $linked ? ', and ' . escapeHtml(implode(' and ', $linked)) . ' stop using it' : '' ?>. Tickets raised from it stay as they are." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'ticket templates',
        'icon' => 'fa-life-ring',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'A ticket ready to raise - its subject, details and tasks, and how it is set up. <span class="font-monospace">[Client]</span>, <span class="font-monospace">[Contact]</span>, <span class="font-monospace">[Month]</span> and the like are filled in when a ticket is raised.',
        'actions' => [['label' => 'New Ticket Template', 'modal' => 'modals/ticket_template/ticket_template_add.php', 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
