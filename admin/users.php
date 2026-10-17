<?php

/*
 * Administration > Users - the agents who sign in to ITFlow. Each row is who
 * they are, their role, whether they can sign in and how (MFA, browsers that
 * skip the code), the clients they can see and when they last signed in. The
 * pills split them: everyone, admins, no MFA, disabled - and the archived.
 *
 * The admin's own row is shown but not changed here: that is their Account
 * pages, so nobody locks themselves out by mistake.
 */

// Default Column Sortby Filter
$sort = "user_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter here, so not carried into links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}

$roles = userRoles();
$role_filter = isset($roles[intval($_GET['role'] ?? 0)]) ? intval($_GET['role']) : 0;

$filters = "user_type = 1 AND (user_name LIKE '%$q%' OR user_email LIKE '%$q%' OR role_name LIKE '%$q%')" . ($role_filter ? " AND user_role_id = $role_filter" : '');
$joins = "LEFT JOIN user_roles ON user_role_id = role_id
    LEFT JOIN user_settings ON user_settings.user_id = users.user_id";

// The views
$shows = [
    '' => ['label' => 'Users', 'icon' => 'fa-users', 'where' => 'user_archived_at IS NULL', 'title' => 'Everyone not archived'],
    'admins' => ['label' => 'Admins', 'icon' => 'fa-user-shield', 'where' => 'user_archived_at IS NULL AND role_is_admin = 1', 'title' => 'Every client and every setting'],
    'nomfa' => ['label' => 'No MFA', 'icon' => 'fa-unlock', 'where' => "user_archived_at IS NULL AND user_status = 1 AND (user_token IS NULL OR user_token = '')", 'title' => 'Active, and signing in with a password alone'],
    'disabled' => ['label' => 'Disabled', 'icon' => 'fa-ban', 'where' => 'user_archived_at IS NULL AND user_status <> 1', 'title' => 'Cannot sign in'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'user_archived_at IS NOT NULL', 'title' => 'Gone - restore one with a new password'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';

// Only these sort - an unknown column would take the page down
$last_login_sql = "(SELECT MAX(log_id) FROM logs WHERE log_user_id = users.user_id AND log_type = 'Login' AND log_action = 'Success')";
$sortable_columns = [
    'user_name' => "user_name $order",
    'user_email' => "user_email $order",
    'role_name' => "role_is_admin DESC, role_name $order, user_name ASC",
    'user_status' => "user_status $order, user_name ASC",
    'last_login' => "$last_login_sql IS NULL, $last_login_sql $order, user_name ASC",
    'user_created_at' => "user_created_at $order, user_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'user_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS users.user_id, user_name, user_email, user_status, user_token, user_avatar, user_created_at, user_archived_at,
        user_role_id, role_name, role_is_admin, user_config_force_mfa
    FROM users $joins
    WHERE $filters AND {$shows[$show]['where']}
    ORDER BY {$sortable_columns[$sort]}, users.user_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$users = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $users[intval($row['user_id'])] = $row;
}
$logins = userLastLogins(array_keys($users));
$access = userClientAccess(array_keys($users));
$remembered = userRememberCounts(array_keys($users));

// How many in each view, over what the search and role leave
$view_counts = [];
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($s) {
    return "COALESCE(SUM({$s['where']}), 0)";
}, $shows)) . " FROM users $joins WHERE $filters"));
foreach (array_keys($shows) as $i => $key) {
    $view_counts[$key] = intval($row[$i]);
}

// Agent user limit (set in config.php, 0 / unset = unlimited)
$agent_user_limit = getAgentUserLimit();
$agent_user_count = getActiveAgentUserCount();
$agent_user_limit_reached = $agent_user_limit > 0 && $agent_user_count >= $agent_user_limit;

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-users me-2"></i>Users
            <?php if ($agent_user_limit > 0) { ?>
                <span class="badge rounded-pill text-bg-<?= $agent_user_limit_reached ? 'danger' : 'secondary' ?> fw-normal ms-2" title="Active agents, of the agent user limit set for this installation">
                    <?= $agent_user_count ?> of <?= $agent_user_limit ?> seats
                </span>
            <?php } ?>
        </h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($agent_user_limit_reached) { ?>
                    <button type="button" class="btn btn-primary" disabled title="Every seat is taken - disable or archive a user first">
                        <i class="fas fa-user-plus"></i><span class="d-none d-lg-inline ms-2">New User</span>
                    </button>
                <?php } else { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/user/user_add.php?role=<?= $role_filter ?>">
                        <i class="fas fa-user-plus"></i><span class="d-none d-lg-inline ms-2">New User</span>
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/user/user_export.php', ['q', 'role'], ['archived' => $show === 'archived' ? 1 : null]) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <a class="dropdown-item" href="roles.php">
                        <i class="fa fa-fw fa-user-tag text-secondary me-2"></i>Roles
                    </a>
                    <a class="dropdown-item" href="settings_security.php">
                        <i class="fa fa-fw fa-shield-alt text-secondary me-2"></i>Security settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/user/user_all_reset_password.php">
                        <i class="fas fa-fw fa-skull-crossbones me-2"></i>Incident response
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml($_GET['q'] ?? '') ?>" placeholder="Search users..." title="Name, email or role">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="role" onchange="this.form.submit()">
                        <option value="">- Any role -</option>
                        <?php foreach ($roles as $role_id => $role) { ?>
                            <option value="<?= $role_id ?>" <?= $role_filter === $role_id ? 'selected' : '' ?><?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-5 col-md-12">
                    <div class="d-flex justify-content-end gap-2">
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/user/user_bulk_role.php" data-bulk="true">
                                    <i class="fas fa-fw fa-user-tag text-secondary me-2"></i>Role
                                </a>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_require_mfa_users">
                                    <i class="fas fa-fw fa-lock text-secondary me-2"></i>Require MFA
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unrequire_mfa_users">
                                    <i class="fas fa-fw fa-lock-open text-secondary me-2"></i>Stop requiring MFA
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_revoke_remember_users">
                                    <i class="fas fa-fw fa-laptop text-secondary me-2"></i>Forget remembered browsers
                                </button>
                                <?php if ($show !== 'archived') { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_activate_users">
                                        <i class="fas fa-fw fa-user-check text-secondary me-2"></i>Activate
                                    </button>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_disable_users"
                                        data-confirm-text="They are signed out and cannot sign in, and their open tickets and recurring tickets are unassigned. Activating them again does not give those back."
                                        data-confirm-button="Disable">
                                        <i class="fas fa-fw fa-user-slash me-2"></i>Disable
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $view) {
                if ($key === 'archived') { ?><span class="vr mx-1 my-1"></span><?php } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key === '' || $show === $key ? null : $key])) ?>" title="<?= escapeHtml($view['title']) ?>">
                    <i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= $view['label'] ?> <span class="badge rounded-pill ms-1"><?= $view_counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list itflow-user-list">
                <thead class="<?php if (!$users) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                    <th><?= $sort_link('user_name', 'User') ?> <span class="fw-normal text-body-secondary small ms-2"><?= $sort_link('user_email', 'by email') ?></span></th>
                    <th class="d-none d-md-table-cell"><?= $sort_link('role_name', 'Role') ?></th>
                    <th class="d-none d-md-table-cell"><?= $sort_link('user_status', 'Sign in') ?></th>
                    <th class="d-none d-xl-table-cell">Clients</th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('last_login', 'Last signed in') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user_id => $row) {
                    $is_self = $user_id === intval($session_user_id);
                    $is_archived = !empty($row['user_archived_at']);
                    $is_active = intval($row['user_status']) === 1 && !$is_archived;
                    $is_admin = intval($row['role_is_admin']) === 1;
                    $has_mfa = trim((string) $row['user_token']) !== '';
                    $display_name = $is_archived ? trim(str_replace(' (archived)', '', $row['user_name'])) : $row['user_name'];
                    $name = escapeHtml($display_name);
                    $quick_url = "modals/user/user.php?id=$user_id";
                    $user_access = $access[$user_id];
                    $access_title = $is_admin ? 'Admins see every client' : trim(
                        ($user_access['allow'] ? 'Only: ' . implode(', ', $user_access['allow']) . '. ' : '')
                        . ($user_access['deny'] ? 'Kept out of: ' . implode(', ', $user_access['deny']) . '.' : '')
                    );
                    $remember_count = $remembered[$user_id] ?? 0;
                    ?>
                    <tr class="itflow-ticket-row <?= !$has_mfa && $is_active ? 'is-warning' : '' ?>">
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <?php if ($is_self) { ?>
                                    <input class="form-check-input" type="checkbox" disabled title="That's you - change your own account from Account">
                                <?php } else { ?>
                                    <input class="form-check-input bulk-select" type="checkbox" name="user_ids[]" value="<?= $user_id ?>" aria-label="Select <?= $name ?>">
                                <?php } ?>
                            </div>
                        </td>

                        <!-- Who -->
                        <td class="itflow-ticket-main itflow-user-main">
                            <div class="d-flex align-items-center gap-3">
                                <a class="ajax-modal text-decoration-none <?= $is_active ? '' : 'opacity-50' ?>" href="#" data-modal-url="<?= $quick_url ?>" tabindex="-1" aria-hidden="true"><?= userAvatar($user_id, $display_name, $row['user_avatar']) ?></a>
                                <div class="min-w-0">
                                    <a class="ajax-modal <?= $is_active ? 'fw-semibold text-body' : 'text-body-secondary' ?>" href="#" data-modal-url="<?= $quick_url ?>"><?= $name ?></a>
                                    <?php if ($is_self) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1">You</span><?php } ?>
                                    <div class="small text-body-secondary text-truncate">
                                        <?= escapeHtml($row['user_email']) ?><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= escapeHtml($row['user_email']) ?>"><i class="far fa-copy"></i></button>
                                    </div>
                                    <!-- On a phone the role and sign in columns are off to the side - they come along -->
                                    <div class="d-md-none small text-body-secondary"><i class="fas fa-fw <?= $is_admin ? 'fa-user-shield' : 'fa-user-tag' ?> me-1"></i><?= escapeHtml($row['role_name'] ?? 'No role') ?></div>
                                    <div class="d-md-none d-flex flex-wrap gap-1 mt-1">
                                        <?= userStatusPill($row['user_status'], $is_archived) ?>
                                        <?php if (!$is_archived) { ?><?= userMfaPill($row['user_token'], $row['user_config_force_mfa']) ?><?php } ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="d-none d-md-table-cell text-nowrap">
                            <?php if ($row['role_name'] === null) { ?>
                                <span class="text-danger-emphasis small"><i class="fas fa-exclamation-triangle me-1"></i>No role</span>
                            <?php } else { ?>
                                <i class="fas fa-fw <?= $is_admin ? 'fa-user-shield text-primary' : 'fa-user-tag text-body-secondary' ?> me-1"></i><?= escapeHtml($row['role_name']) ?>
                            <?php } ?>
                        </td>

                        <!-- Can they sign in, and how -->
                        <td class="d-none d-md-table-cell">
                            <div class="d-flex flex-wrap gap-1">
                                <?= userStatusPill($row['user_status'], $is_archived) ?>
                                <?php if (!$is_archived) { ?><?= userMfaPill($row['user_token'], $row['user_config_force_mfa']) ?><?php } ?>
                            </div>
                            <?php if ($remember_count && !$is_archived) { ?>
                                <div class="small text-body-secondary mt-1 text-nowrap" title="Browsers that skip the MFA code - remember me, at sign in"><i class="fas fa-fw fa-laptop me-1"></i><?= $remember_count ?> remembered browser<?= $remember_count === 1 ? '' : 's' ?></div>
                            <?php } ?>
                            <?php if ($is_archived) { ?>
                                <div class="small text-body-secondary mt-1" title="<?= escapeHtml($row['user_archived_at']) ?>"><?= escapeHtml(timeAgo($row['user_archived_at'])) ?></div>
                            <?php } ?>
                        </td>

                        <td class="d-none d-xl-table-cell small <?= $is_admin ? 'text-body-secondary' : '' ?>" title="<?= escapeHtml($access_title) ?>">
                            <?php if (!$is_admin && ($user_access['allow'] || $user_access['deny'])) { ?><i class="fas fa-fw fa-filter text-warning me-1"></i><?php } ?><?= escapeHtml(userClientAccessText($user_access, $is_admin)) ?>
                        </td>

                        <td class="d-none d-lg-table-cell small"><?= userLoginHtml($logins[$user_id] ?? null) ?></td>

                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="audit_logs.php?user=<?= $user_id ?>">
                                        <i class="fas fa-fw fa-history text-secondary me-2"></i>Audit log
                                    </a>
                                    <?php if ($is_self) { ?>
                                        <a class="dropdown-item" href="/agent/user/user_details.php">
                                            <i class="fas fa-fw fa-user-cog text-secondary me-2"></i>Your account
                                        </a>
                                    <?php } elseif ($is_archived) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/user/user_restore.php?id=<?= $user_id ?>">
                                            <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Restore
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/user/user_edit.php?id=<?= $user_id ?>">
                                            <i class="fas fa-fw fa-user-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if ($has_mfa) { ?>
                                            <a class="dropdown-item confirm-link" href="post.php?reset_user_mfa=<?= $user_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-text="<?= $name ?> signs in with their password alone<?= intval($row['user_config_force_mfa']) ? ' until they set MFA up again, which they are asked to at their next sign in' : ' until they turn MFA on again' ?>. For a lost or replaced phone."
                                                data-confirm-button="Reset MFA">
                                                <i class="fas fa-fw fa-mobile-alt text-secondary me-2"></i>Reset MFA
                                            </a>
                                        <?php } ?>
                                        <?php if ($remember_count) { ?>
                                            <a class="dropdown-item" href="post.php?revoke_remember_me=<?= $user_id ?>&csrf_token=<?= $csrf ?>">
                                                <i class="fas fa-fw fa-laptop text-secondary me-2"></i>Forget <?= $remember_count ?> remembered browser<?= $remember_count === 1 ? '' : 's' ?>
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($is_active) { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?disable_user=<?= $user_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-text="<?= $name ?> is signed out and cannot sign in, and their open tickets and recurring tickets are unassigned. Activating them again does not give those back."
                                                data-confirm-button="Disable">
                                                <i class="fas fa-fw fa-user-slash me-2"></i>Disable
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item" href="post.php?activate_user=<?= $user_id ?>&csrf_token=<?= $csrf ?>">
                                                <i class="fas fa-fw fa-user-check text-secondary me-2"></i>Activate
                                            </a>
                                        <?php } ?>
                                        <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/user/user_archive.php?id=<?= $user_id ?>">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
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
    </form>
    <?php
    $empty_state = [
        'thing' => 'users',
        'icon' => 'fa-users',
        'filtered' => $q !== '' || $role_filter,
        'archived' => $show === 'archived',
        'views' => ['show'],
        'view' => $show === 'nomfa' ? ['title' => 'Everyone active signs in with MFA', 'icon' => 'fa-check'] : emptyStatePill($shows, $show),
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
