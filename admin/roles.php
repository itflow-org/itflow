<?php

/*
 * Admin > Roles - what each role lets its agents open: an admin role opens
 * everything, any other a level of each module. Each shows its access at a
 * glance and its agents (opening Users filtered to them). A role can be
 * changed, copied as the start of another, archived - kept for the agents
 * archived with it, not offered for new ones - or, while nobody has it,
 * deleted. The work is in functions/roles.php; the handlers are
 * admin/post/roles.php.
 */

// Default Column Sortby Filter
$sort = "role_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// ?archived=1 is how older links asked for the archived ones
$show_archived = ($_GET['show'] ?? '') === 'archived' || !empty($_GET['archived']);

// Only these sort - an unknown column would take the page down. Admin roles stay first, as on Users
$sortable_columns = [
    'role_name' => "role_is_admin DESC, role_name $order",
    'agents' => "agents $order, role_name ASC",
    'role_updated_at' => "COALESCE(role_updated_at, role_created_at) $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'role_name';
}

$search = "role_type = 1 AND (role_name LIKE '%$q%' OR role_description LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS role_id, role_name, role_description, role_is_admin, role_created_at, role_updated_at, role_archived_at,
        (SELECT COUNT(*) FROM users WHERE user_role_id = role_id AND user_type = 1 AND user_archived_at IS NULL) AS agents,
        (SELECT COUNT(*) FROM users WHERE user_role_id = role_id AND user_type = 1 AND user_archived_at IS NOT NULL) AS agents_archived
    FROM user_roles
    WHERE $search AND role_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, role_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Each with its levels, as roleRows() gives them
$roles = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $roles[intval($row['role_id'])] = $row;
}
$levels = roleLevels(array_keys($roles));
foreach ($roles as $role_id => $row) {
    $roles[$role_id]['levels'] = $levels[$role_id] ?? [];
}
$agents = roleAgents(array_keys($roles), 5);

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(role_archived_at IS NULL), 0), COALESCE(SUM(role_archived_at IS NOT NULL), 0) FROM user_roles WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-user-shield me-2"></i>Roles</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/role/role_add.php"><i class="fas fa-plus me-2"></i>New Role</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search roles..." title="Name or description">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-user-shield me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'archived']))) ?>" title="Kept for the agents archived with them, not given to anyone new"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('role_name', 'Role') ?></th>
                <th class="d-none d-md-table-cell">Access</th>
                <th class="d-none d-sm-table-cell"><?= $sort_link('agents', 'Agents') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('role_updated_at', 'Updated') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $role_id => $row) {
                $name = escapeHtml($row['role_name']);
                $is_admin = intval($row['role_is_admin']) === 1;
                $is_mine = $role_id === intval($session_user_role);
                $edit_url = "modals/role/role_edit.php?id=$role_id";
                $agent_count = intval($row['agents']);
                $archive_blocker = roleArchiveBlocker($row);
                $delete_blocker = roleDeleteBlocker($row);
                $updated = $row['role_updated_at'] ?: $row['role_created_at'];
                // The agents with it - a few faces and how many, opening Users filtered to them
                ob_start();
                if ($agent_count) { ?>
                    <a class="d-inline-flex align-items-center gap-2 text-reset text-decoration-none" href="users.php?role=<?= $role_id ?>" title="Their agents in Users">
                        <span class="itflow-role-agents">
                            <?php foreach ($agents[$role_id] ?? [] as $agent) { ?><?= userAvatar($agent['user_id'], $agent['user_name'], $agent['user_avatar'], 'sm') ?><?php } ?>
                        </span>
                        <span class="small text-nowrap"><?= $agent_count ?> agent<?= $agent_count === 1 ? '' : 's' ?></span>
                    </a>
                <?php } else { ?>
                    <span class="small text-body-secondary">Nobody</span>
                <?php }
                if (intval($row['agents_archived'])) { ?>
                    <div class="small text-body-secondary"><?= intval($row['agents_archived']) ?> archived</div>
                <?php }
                $agents_html = ob_get_clean();
                ?>
                <tr data-role-id="<?= $role_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw <?= $is_admin ? 'fa-user-shield' : 'fa-user-tag' ?>"></i></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                <?php if ($is_mine) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1">Yours</span><?php } ?>
                                <?php if (trim((string) $row['role_description']) !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($row['role_description']) ?></div>
                                <?php } ?>
                                <?php if ($row['role_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['role_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On a phone, access and agents come along under the name -->
                                <div class="d-md-none mt-1"><?= roleAccessHtml($row) ?></div>
                                <div class="d-sm-none mt-1"><?= $agents_html ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= roleAccessHtml($row) ?></td>
                    <td class="d-none d-sm-table-cell"><?= $agents_html ?></td>
                    <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($updated) ?>"><?= timeAgo($updated) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/role/role_add.php?copy=<?= $role_id ?>" title="A new role starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <?php if ($agent_count) { ?>
                                    <a class="dropdown-item" href="users.php?role=<?= $role_id ?>">
                                        <i class="fas fa-fw fa-users text-secondary me-2"></i>Its agents
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['role_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } elseif ($archive_blocker !== '') { ?>
                                    <span class="dropdown-item disabled" title="<?= escapeHtml($archive_blocker) ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</span>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>" title="Kept for agents archived with it, but not given to anyone new">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <?php if ($delete_blocker !== '') { ?>
                                    <span class="dropdown-item disabled" title="<?= escapeHtml($delete_blocker) ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</span>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Nobody has it, so no agent changes." data-confirm-button="Delete">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
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
        'thing' => 'roles',
        'icon' => 'fa-user-shield',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'A role sets what its agents can open - an admin role everything, including Admin; any other a level of each part of ITFlow.',
        'actions' => [['label' => 'New Role', 'modal' => 'modals/role/role_add.php', 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
