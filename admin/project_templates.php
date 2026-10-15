<?php

/*
 * Project templates - the plans a new project can start from: onboarding a
 * client, a server refresh, an office move. Each is an ordered set of ticket
 * templates, and each of those brings its tasks. Picking one on New Project
 * raises those tickets on the project.
 */

// Default Column Sortby Filter
$sort = "project_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$show_archived = ($_GET['show'] ?? '') === 'archived';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'project_template_name' => "project_template_name $order",
    'ticket_count' => "ticket_count $order, project_template_name ASC",
    'task_count' => "task_count $order, project_template_name ASC",
    'project_template_updated_at' => "COALESCE(project_template_updated_at, project_template_created_at) $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'project_template_name';
}

$search = "(project_template_name LIKE '%$q%' OR project_template_description LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS project_templates.project_template_id, project_template_name, project_template_description,
        project_template_created_at, project_template_updated_at, project_template_archived_at,
        COUNT(DISTINCT ptt.ticket_template_id) AS ticket_count, COUNT(task_template_id) AS task_count
    FROM project_templates
    LEFT JOIN project_template_ticket_templates ptt ON ptt.project_template_id = project_templates.project_template_id
    LEFT JOIN task_templates ON task_template_ticket_template_id = ptt.ticket_template_id
    WHERE $search AND project_template_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    GROUP BY project_templates.project_template_id
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(project_template_archived_at IS NULL), 0), COALESCE(SUM(project_template_archived_at IS NOT NULL), 0) FROM project_templates WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-cubes me-2"></i>Project Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/project_template/project_template_add.php"><i class="fas fa-plus me-2"></i>New Project Template</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search project templates...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-cubes me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'archived']))) ?>"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('project_template_name', 'Template') ?></th>
                <th class="text-center"><?= $sort_link('ticket_count', 'Tickets') ?></th>
                <th class="text-center"><?= $sort_link('task_count', 'Tasks') ?></th>
                <th><?= $sort_link('project_template_updated_at', 'Updated') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $project_template_id = intval($row['project_template_id']);
                $updated = $row['project_template_updated_at'] ?: $row['project_template_created_at'];
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-project-icon" aria-hidden="true"><i class="fas fa-fw fa-cubes"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="project_template.php?project_template_id=<?= $project_template_id ?>"><?= escapeHtml($row['project_template_name']) ?></a>
                                <?php if ($row['project_template_description']) { ?>
                                    <div class="small text-body-secondary text-truncate" style="max-width: 36rem"><?= escapeHtml($row['project_template_description']) ?></div>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-center"><?= intval($row['ticket_count']) ?: '<span class="text-body-secondary">-</span>' ?></td>
                    <td class="text-center"><?= intval($row['task_count']) ?: '<span class="text-body-secondary">-</span>' ?></td>
                    <td class="small text-body-secondary text-nowrap" title="<?= escapeHtml($updated) ?>"><?= timeAgo($updated) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project_template/project_template_edit.php?project_template_id=<?= $project_template_id ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="post.php?duplicate_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Duplicate
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['project_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_project_template=<?= $project_template_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                    data-confirm-text="The template goes for good. Its ticket templates stay, and so do projects started from it.">
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
    <?php if ($num_rows[0] == 0) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-cubes fa-2x mb-2 opacity-50"></i>
            <div><?= $q !== '' ? 'No project templates match.' : ($show_archived ? 'No archived project templates.' : 'No project templates yet - a client onboarding plan is a good first one: one ticket template per step, each with its checklist of tasks.') ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
