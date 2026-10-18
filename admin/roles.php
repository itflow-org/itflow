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

require_once "includes/inc_all_admin.php";

$show_archived = isset($_GET['archived']);
$search = trim((string) ($_GET['q'] ?? ''));

$all_roles = roleRows();
$roles = [];
$archived_count = 0;
foreach ($all_roles as $role_id => $row) {
    $archived_count += $row['role_archived_at'] ? 1 : 0;
    if ((bool) $row['role_archived_at'] !== $show_archived) {
        continue;
    }
    if ($search !== '' && mb_stripos($row['role_name'], $search) === false && mb_stripos((string) $row['role_description'], $search) === false) {
        continue;
    }
    $roles[$role_id] = $row;
}
$agents = roleAgents(array_keys($roles), 5);

// This page with some of its query changed - null drops a key
$page_url = function (array $change = []) use ($show_archived, $search) {
    $query = array_merge(['q' => $search !== '' ? $search : null, 'archived' => $show_archived ? 1 : null], $change);
    return 'roles.php' . (($query = http_build_query(array_filter($query, function ($v) {
        return $v !== null;
    }))) ? "?$query" : '');
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

    <div class="p-2 border-bottom">
        <p class="small text-secondary mb-2">A role sets what its agents can open - an admin role everything, including Admin; any other a level of each part of ITFlow. Which clients an agent sees is set on the agent, in <a href="users.php">Users</a>.</p>
        <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
            <?php if ($show_archived) { ?><input type="hidden" name="archived" value="1"><?php } ?>
            <div class="input-group input-group-sm w-auto">
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search roles">
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
            <?php if ($roles) { ?>
                <thead class="text-dark">
                <tr>
                    <th class="ps-3">Role</th>
                    <th class="d-none d-md-table-cell">Access</th>
                    <th class="d-none d-sm-table-cell">Agents</th>
                    <th class="text-center" style="width: 1%">Action</th>
                </tr>
                </thead>
            <?php } ?>
            <tbody>
            <?php foreach ($roles as $role_id => $row) {
                $name = escapeHtml($row['role_name']);
                $is_admin = intval($row['role_is_admin']) === 1;
                $is_mine = $role_id === intval($session_user_role);
                $edit_url = "modals/role/role_edit.php?id=$role_id";
                $agent_count = intval($row['agents']);
                $archive_blocker = roleArchiveBlocker($row);
                $delete_blocker = roleDeleteBlocker($row);
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
                    <span class="small text-secondary">Nobody</span>
                <?php }
                if (intval($row['agents_archived'])) { ?>
                    <div class="small text-secondary"><?= intval($row['agents_archived']) ?> archived</div>
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
                                    <div class="small text-secondary"><?= escapeHtml($row['role_description']) ?></div>
                                <?php } ?>
                                <?php if ($row['role_archived_at']) { ?>
                                    <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['role_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On a phone, access and agents come along under the name -->
                                <div class="d-md-none mt-1"><?= roleAccessHtml($row) ?></div>
                                <div class="d-sm-none mt-1"><?= $agents_html ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= roleAccessHtml($row) ?></td>
                    <td class="d-none d-sm-table-cell"><?= $agents_html ?></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/role/role_add.php?copy=<?= $role_id ?>" title="A new role starting from this one"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                                <?php if ($agent_count) { ?>
                                    <a class="dropdown-item" href="users.php?role=<?= $role_id ?>"><i class="fas fa-fw fa-users me-2"></i>Its agents</a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['role_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                <?php } elseif ($archive_blocker !== '') { ?>
                                    <span class="dropdown-item disabled" title="<?= escapeHtml($archive_blocker) ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</span>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?archive_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>" title="Kept for agents archived with it, but not given to anyone new"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                <?php } ?>
                                <?php if ($delete_blocker !== '') { ?>
                                    <span class="dropdown-item disabled" title="<?= escapeHtml($delete_blocker) ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</span>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_role=<?= $role_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Nobody has it, so no agent changes." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if (!$roles) { ?>
        <?= emptyStateHtml([
            'thing' => 'roles',
            'title' => $show_archived ? 'No archived roles' : 'No roles yet',
            'text' => $show_archived ? 'An archived role stays with the agents archived with it, but isn\'t given to anyone new.' : 'A role sets what its agents can open.',
            'icon' => 'fa-user-shield',
            'filtered' => $search !== '',
            'clear_url' => $page_url(['q' => null]),
            'actions' => $show_archived ? [] : [['label' => 'New Role', 'modal' => 'modals/role/role_add.php', 'size' => 'lg']],
        ]) ?>
    <?php } ?>
</div>

<?php
require_once "../includes/footer.php";
