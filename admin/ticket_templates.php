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

require_once "includes/inc_all_admin.php";

$show_archived = isset($_GET['archived']);
$search = trim((string) ($_GET['q'] ?? ''));
$search_sql = escapeSql($search);

$templates = [];
$sql = mysqli_query($mysqli, "SELECT ticket_templates.*, category_name, category_color, category_icon, user_name AS assignee_name,
        (SELECT COUNT(*) FROM task_templates WHERE task_template_ticket_template_id = ticket_templates.ticket_template_id) AS task_count,
        (SELECT COALESCE(SUM(task_template_completion_estimate), 0) FROM task_templates WHERE task_template_ticket_template_id = ticket_templates.ticket_template_id) AS task_minutes,
        (SELECT COUNT(*) FROM recurring_tickets WHERE recurring_ticket_ticket_template_id = ticket_templates.ticket_template_id) AS recurring_count,
        (SELECT COUNT(*) FROM project_template_ticket_templates WHERE project_template_ticket_templates.ticket_template_id = ticket_templates.ticket_template_id) AS project_count
    FROM ticket_templates
    LEFT JOIN categories ON category_id = ticket_template_category_id AND ticket_template_category_id > 0
    LEFT JOIN users ON user_id = ticket_template_assigned_to AND ticket_template_assigned_to > 0
    WHERE ticket_template_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    " . ($search !== '' ? "AND (ticket_template_name LIKE '%$search_sql%' OR ticket_template_description LIKE '%$search_sql%' OR ticket_template_subject LIKE '%$search_sql%')" : '') . "
    ORDER BY ticket_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['ticket_template_id'])] = $row;
}
$usage = ticketTemplateUsage(array_keys($templates));
// What each asks for - its fill-ins, from its rows and the bracketed words in its text
$template_tasks = ticketTemplateTasksFor(array_keys($templates));
$template_fill_in_rows = ticketTemplateFillInRowsFor(array_keys($templates));
$archived_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ticket_templates WHERE ticket_template_archived_at IS NOT NULL"))[0]);
$tag_rows = [];
$sql_tags = mysqli_query($mysqli, "SELECT ticket_template_id, tags.* FROM ticket_template_tags JOIN tags ON tags.tag_id = ticket_template_tags.tag_id ORDER BY tag_name");
while ($row = mysqli_fetch_assoc($sql_tags)) {
    $tag_rows[intval($row['ticket_template_id'])][] = $row;
}

// This page with some of its query changed - null drops a key
$page_url = function (array $change = []) use ($show_archived, $search) {
    $query = array_merge(['q' => $search !== '' ? $search : null, 'archived' => $show_archived ? 1 : null], $change);
    return 'ticket_templates.php' . (($query = http_build_query(array_filter($query, function ($v) {
        return $v !== null;
    }))) ? "?$query" : '');
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring me-2"></i>Ticket Templates</h3>
        <div class="card-tools d-flex gap-2">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_template/ticket_template_add.php" data-modal-size="lg"><i class="fas fa-plus me-2"></i>New Ticket Template</button>
            <div class="dropdown">
                <button class="btn btn-outline-light" type="button" data-bs-toggle="dropdown" title="More"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_template/ticket_template_import.php"><i class="fas fa-fw fa-file-upload me-2"></i>Import</a>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_template/ticket_template_export.php"><i class="fas fa-fw fa-file-download me-2"></i>Export</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/agent/reports/ticket_templates.php"><i class="fas fa-fw fa-chart-bar me-2"></i>How they are used</a>
                    <a class="dropdown-item" href="starter_content.php"><i class="fas fa-fw fa-seedling me-2"></i>Starter templates</a>
                </div>
            </div>
        </div>
    </div>

    <div class="p-2 border-bottom">
        <p class="small text-secondary mb-2">
            A ticket ready to raise - its subject, details and tasks, and how it is set up. Pick one on New Ticket, when raising tickets for several assets or clients, and on recurring tickets;
            project templates raise theirs. <span class="font-monospace">[Client]</span>, <span class="font-monospace">[Contact]</span>, <span class="font-monospace">[Month]</span> and the like are filled in when a ticket is raised.
        </p>
        <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
            <?php if ($show_archived) { ?><input type="hidden" name="archived" value="1"><?php } ?>
            <div class="input-group input-group-sm w-auto">
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search templates">
                <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Current or archived">
                <a class="btn btn-outline-secondary<?= $show_archived ? '' : ' active' ?>" href="<?= escapeHtml($page_url(['archived' => null])) ?>">Current</a>
                <a class="btn btn-outline-secondary<?= $show_archived ? ' active' : '' ?>" href="<?= escapeHtml($page_url(['archived' => 1])) ?>">Archived <span class="badge text-bg-secondary ms-1"><?= $archived_count ?></span></a>
            </div>
        </form>
    </div>

    <div class="table-responsive-sm">
        <table class="table table-borderless table-hover align-middle mb-0">
            <?php if ($templates) { ?>
                <thead class="text-dark">
                <tr>
                    <th class="ps-3">Template</th>
                    <th class="d-none d-lg-table-cell">Sets up</th>
                    <th class="d-none d-sm-table-cell">Tasks</th>
                    <th class="d-none d-md-table-cell">Used</th>
                    <th class="text-center" style="width: 1%">Action</th>
                </tr>
                </thead>
            <?php } ?>
            <tbody>
            <?php foreach ($templates as $template_id => $row) {
                $name = escapeHtml($row['ticket_template_name']);
                $task_count = intval($row['task_count']);
                $minutes = intval($row['task_minutes']);
                $used = $usage[$template_id] ?? ['count' => 0, 'open' => 0, 'last' => null];
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
                ob_start(); ?>
                    <?php if ($used['count']) { ?>
                        <div class="small text-nowrap"><?= $used['count'] ?> ticket<?= $used['count'] === 1 ? '' : 's' ?><?= $used['open'] ? ' <span class="text-secondary">· ' . $used['open'] . ' open</span>' : '' ?></div>
                        <div class="small text-secondary text-nowrap" title="<?= escapeHtml($used['last']) ?>">Last <?= timeAgo($used['last']) ?></div>
                    <?php } else { ?>
                        <span class="small text-secondary">Not yet</span>
                    <?php } ?>
                    <?php if ($linked) { ?><div class="small text-secondary"><i class="fas fa-fw fa-link me-1"></i><?= escapeHtml(implode(', ', $linked)) ?></div><?php } ?>
                <?php $used_html = ob_get_clean();
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-life-ring"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="ticket_template.php?ticket_template_id=<?= $template_id ?>"><?= $name ?></a>
                                <?php if (trim((string) $row['ticket_template_description']) !== '') { ?>
                                    <div class="small text-secondary"><?= escapeHtml($row['ticket_template_description']) ?></div>
                                <?php } elseif (trim((string) $row['ticket_template_subject']) !== '') { ?>
                                    <div class="small text-secondary text-truncate"><?= escapeHtml($row['ticket_template_subject']) ?></div>
                                <?php } ?>
                                <?php if ($row['ticket_template_archived_at']) { ?>
                                    <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['ticket_template_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens what it sets up and its use come along under the name -->
                                <?php if ($set_up !== '') { ?><div class="d-lg-none mt-1"><?= $set_up ?></div><?php } ?>
                                <div class="d-md-none mt-1"><?= $used_html ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell"><?= $set_up !== '' ? $set_up : '<span class="small text-secondary">Just the subject and details</span>' ?></td>
                    <td class="d-none d-sm-table-cell text-nowrap">
                        <?php if ($task_count) { ?>
                            <div class="small"><?= $task_count ?> task<?= $task_count === 1 ? '' : 's' ?></div>
                            <?php if ($minutes) { ?><div class="small text-secondary"><i class="far fa-fw fa-clock me-1"></i><?= escapeHtml(ticketTemplateMinutesText($minutes)) ?></div><?php } ?>
                        <?php } else { ?>
                            <span class="small text-secondary">None</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= $used_html ?></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket_template/ticket_template_edit.php?id=<?= $template_id ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                <?php if (!$row['ticket_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="/agent/tickets.php?new_from_template=<?= $template_id ?>"><i class="fas fa-fw fa-plus me-2"></i>New ticket from it</a>
                                <?php } ?>
                                <a class="dropdown-item" href="post.php?copy_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['ticket_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?archive_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="Not offered for new tickets - recurring tickets and project templates that use it keep it"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its tasks go with it<?= $linked ? ', and ' . escapeHtml(implode(' and ', $linked)) . ' stop using it' : '' ?>. Tickets raised from it stay as they are." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if (!$templates) { ?>
        <?= emptyStateHtml([
            'thing' => 'ticket templates',
            'title' => $show_archived ? 'No archived ticket templates' : 'No ticket templates yet',
            'text' => $show_archived ? 'An archived template isn\'t offered for new tickets, but the recurring tickets and project templates that use it keep it.' : 'A ticket ready to raise - its subject, details and tasks, and how it is set up.',
            'icon' => 'fa-life-ring',
            'filtered' => $search !== '',
            'clear_url' => $page_url(['q' => null]),
            'actions' => $show_archived ? [] : [['label' => 'New Ticket Template', 'modal' => 'modals/ticket_template/ticket_template_add.php', 'size' => 'lg']],
        ]) ?>
    <?php } ?>
</div>

<?php
require_once "../includes/footer.php";
