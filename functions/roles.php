<?php

/*
 * ITFlow - roles: what an agent's role lets them open.
 *
 * A role is an admin role - every part of ITFlow, every client, and
 * Administration (users, roles, settings) - or a level for each module:
 * none, read, modify (add, edit, archive and the everyday work) or full
 * (delete as well). Which clients an agent sees is set on the agent
 * (functions/user.php). The levels are kept in user_role_permissions, one
 * row per role and module, and read once a request by lookupUserPermission()
 * in functions/auth.php.
 *
 * Shared by Admin > Roles (admin/roles.php, admin/modals/role/*,
 * admin/post/roles.php).
 */

/** The levels a role can give a module. */
const ROLE_LEVELS = [0 => 'None', 1 => 'Read', 2 => 'Modify', 3 => 'Full'];

/** A level's colour - None grey, Read blue, Modify amber, Full red. */
const ROLE_LEVEL_TONES = [0 => 'secondary', 1 => 'primary', 2 => 'warning', 3 => 'danger'];

/*
 * The modules a role gives levels for, by module_name: label, icon, about
 * (what is in it) and levels (what each level adds - Modify and Full take in
 * the ones before). 'max' is the highest level that means anything.
 */
function roleModuleInfo() {
    return [
        'module_client' => [
            'label' => 'Clients', 'icon' => 'fa-users', 'about' => 'Clients, contacts, locations and vendors',
            'levels' => [1 => 'See them', 2 => 'Add, edit, import and archive them', 3 => 'Delete them, and anonymize a contact'],
        ],
        'module_support' => [
            'label' => 'Support', 'icon' => 'fa-life-ring', 'about' => 'Tickets, projects, assets and documentation',
            'levels' => [1 => 'See them', 2 => 'Work tickets and projects, and add, edit and archive assets and documentation', 3 => 'Delete them'],
        ],
        'module_credential' => [
            'label' => 'Credentials', 'icon' => 'fa-key', 'about' => 'Client usernames, passwords and 2FA codes',
            'levels' => [1 => 'See and copy them', 2 => 'Add, edit and share them', 3 => 'Delete them'],
        ],
        'module_sales' => [
            'label' => 'Sales', 'icon' => 'fa-file-invoice', 'about' => 'Quotes, invoices, recurring invoices and products',
            'levels' => [1 => 'See them', 2 => 'Create, edit and send them - and record payments, with Financial at Modify too', 3 => 'Delete them, and edit or refund a payment'],
        ],
        'module_financial' => [
            'label' => 'Financial', 'icon' => 'fa-piggy-bank', 'about' => 'Payments, income, expenses, accounts, budgets and trips',
            'levels' => [1 => 'See them', 2 => 'Add, edit and archive them, and reconcile accounts', 3 => 'Delete them, and undo a reconciliation'],
        ],
        'module_reporting' => [
            'label' => 'Reports', 'icon' => 'fa-chart-line', 'about' => 'The reports - each needs read access to what it reports on too',
            'levels' => [1 => 'Open the reports'], 'max' => 1,
        ],
    ];
}

/*
 * The modules, in their order - module_id => module_name, label, icon,
 * about, levels and max. One the list above doesn't know is named from its
 * module_name and its description.
 */
function roleModules() {
    global $mysqli;
    static $modules = null;
    if ($modules !== null) {
        return $modules;
    }
    $info = roleModuleInfo();
    $modules = [];
    $sql = mysqli_query($mysqli, "SELECT module_id, module_name, module_description FROM modules ORDER BY module_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $name = $row['module_name'];
        $modules[intval($row['module_id'])] = ($info[$name] ?? [
            'label' => ucfirst(str_replace('module_', '', $name)), 'icon' => 'fa-cube', 'about' => (string) $row['module_description'],
            'levels' => [1 => 'See it', 2 => 'Add, edit and archive', 3 => 'Delete'],
        ]) + ['module_name' => $name, 'max' => 3];
    }
    return $modules;
}

/** Each role's levels - role_id => [module_id => level]. All roles when $role_ids is empty. */
function roleLevels(array $role_ids = []) {
    global $mysqli;
    $where = $role_ids ? 'WHERE user_role_id IN (' . implode(',', array_map('intval', $role_ids)) . ')' : '';
    $levels = [];
    $sql = mysqli_query($mysqli, "SELECT user_role_id, module_id, user_role_permission_level FROM user_role_permissions $where");
    while ($row = mysqli_fetch_row($sql)) {
        if (intval($row[2]) > 0) {
            $levels[intval($row[0])][intval($row[1])] = min(3, intval($row[2]));
        }
    }
    return $levels;
}

/*
 * The roles, admins first then by name - role_id => the row with levels
 * ([module_id => level]), agents (active ones) and agents_archived. All of
 * them, or the current or archived ones.
 */
function roleRows($archived = null) {
    global $mysqli;
    $where = $archived === null ? '1' : ($archived ? 'role_archived_at IS NOT NULL' : 'role_archived_at IS NULL');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT role_id, role_name, role_description, role_is_admin, role_created_at, role_updated_at, role_archived_at,
            (SELECT COUNT(*) FROM users WHERE user_role_id = role_id AND user_type = 1 AND user_archived_at IS NULL) AS agents,
            (SELECT COUNT(*) FROM users WHERE user_role_id = role_id AND user_type = 1 AND user_archived_at IS NOT NULL) AS agents_archived
        FROM user_roles WHERE role_type = 1 AND $where
        ORDER BY role_is_admin DESC, role_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['role_id'])] = $row;
    }
    $levels = roleLevels(array_keys($rows));
    foreach ($rows as $role_id => $row) {
        $rows[$role_id]['levels'] = $levels[$role_id] ?? [];
    }
    return $rows;
}

/** One role as roleRows() gives it, or null. */
function roleRow($role_id) {
    $role_id = intval($role_id);
    foreach (roleRows() as $id => $row) {
        if ($id === $role_id) {
            return $row;
        }
    }
    return null;
}

/** A few of each role's active agents for its row - role_id => [user rows], up to $limit each. */
function roleAgents(array $role_ids, $limit = 5) {
    global $mysqli;
    $agents = [];
    if (!$role_ids) {
        return $agents;
    }
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_role_id FROM users
        WHERE user_type = 1 AND user_archived_at IS NULL AND user_role_id IN (" . implode(',', array_map('intval', $role_ids)) . ")
        ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $role_id = intval($row['user_role_id']);
        if (count($agents[$role_id] ?? []) < $limit) {
            $agents[$role_id][] = $row;
        }
    }
    return $agents;
}

/*
 * What a role opens, as pills - one per module it has, its level in that
 * level's colour; an admin role as one pill. HTML.
 */
function roleAccessHtml(array $row) {
    if (intval($row['role_is_admin'])) {
        return '<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal" title="Every part of ITFlow, every client, and Administration"><i class="fas fa-user-shield me-1"></i>Admin - everything</span>';
    }
    $pills = [];
    foreach (roleModules() as $module_id => $module) {
        $level = min(intval($row['levels'][$module_id] ?? 0), $module['max']);
        if (!$level) {
            continue;
        }
        $tone = ROLE_LEVEL_TONES[$level];
        $title = $module['label'] . ' - ' . implode('; ', array_slice($module['levels'], 0, $level));
        $pills[] = '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal" title="' . escapeHtml($title) . '"><i class="fas fa-fw ' . $module['icon'] . ' me-1"></i>'
            . escapeHtml($module['label']) . ($module['max'] > 1 ? ' · ' . ROLE_LEVELS[$level] : '') . '</span>';
    }
    return $pills ? '<span class="d-inline-flex flex-wrap gap-1">' . implode('', $pills) . '</span>' : '<span class="small text-secondary">Nothing yet - it opens no part of ITFlow</span>';
}

/*
 * The New, Edit and Copy role window's fields, checked - [fields, error].
 * fields: name, description, admin (0 or 1) and levels ([module_id =>
 * level], every module, held to what it can have). $role_id is the role
 * being edited, 0 for a new one.
 */
function roleFromInput(array $in, $role_id = 0) {
    global $mysqli;
    $name = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['name'] ?? ''))), 0, 200));
    $description = trim(mb_substr(strip_tags((string) ($in['description'] ?? '')), 0, 200));
    if ($name === '') {
        return [null, 'A role needs a name.'];
    }
    $taken = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT role_name, role_archived_at FROM user_roles
        WHERE LOWER(role_name) = LOWER('" . escapeSql($name) . "') AND role_id <> " . intval($role_id) . " LIMIT 1"));
    if ($taken) {
        return [null, 'There is already a role called ' . $taken['role_name'] . ($taken['role_archived_at'] ? ' - it is archived, so restore it instead' : '') . '.'];
    }
    $levels = [];
    foreach (roleModules() as $module_id => $module) {
        $level = intval($in['levels'][$module_id] ?? 0);
        $levels[$module_id] = max(0, min($level, $module['max']));
    }
    return [['name' => $name, 'description' => $description, 'admin' => !empty($in['admin']) ? 1 : 0, 'levels' => $levels], ''];
}

/*
 * Why a role can't stop being an admin role - '' when it can. Not your own
 * (you'd be shut out of this page), and not while its agents are the only
 * active admins.
 */
function roleAdminBlocker(array $row) {
    global $mysqli, $session_user_role;
    if (!intval($row['role_is_admin'])) {
        return '';
    }
    if (intval($row['role_id']) === intval($session_user_role)) {
        return 'It is your own role - another admin can change it.';
    }
    $others = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM users JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL AND role_is_admin = 1 AND role_id <> " . intval($row['role_id'])))[0]);
    if ($others === 0 && intval($row['agents'])) {
        return 'Its agents are the only admins - nobody would be left to run Admin.';
    }
    return '';
}

/* Why a role can't be archived - '' when it can. Not while active agents have it, nor your own. */
function roleArchiveBlocker(array $row) {
    global $session_user_role;
    if (intval($row['role_id']) === intval($session_user_role)) {
        return 'It is your own role.';
    }
    if (intval($row['agents'])) {
        $n = intval($row['agents']);
        return $n . ' agent' . ($n === 1 ? ' has' : 's have') . ' it - give them another role first.';
    }
    return '';
}

/*
 * Why a role can't be deleted - '' when it can. As archiving, and not while
 * an archived agent has it either, as they'd come back with no role.
 */
function roleDeleteBlocker(array $row) {
    $blocker = roleArchiveBlocker($row);
    if ($blocker !== '') {
        return $blocker;
    }
    if (intval($row['agents_archived'])) {
        $n = intval($row['agents_archived']);
        return $n . ' archived agent' . ($n === 1 ? ' has' : 's have') . ' it - archive the role instead.';
    }
    return '';
}

/*
 * What changed on a role, in words for the audit log - "admin taken off,
 * Sales Read to Modify, Reports given Read". Raw; '' when nothing did.
 */
function roleChangeText(array $before, array $fields) {
    $changes = [];
    if (($before['role_name'] ?? '') !== $fields['name']) {
        $changes[] = 'renamed from ' . $before['role_name'];
    }
    if ((string) ($before['role_description'] ?? '') !== $fields['description']) {
        $changes[] = 'description changed';
    }
    if (intval($before['role_is_admin'] ?? 0) !== $fields['admin']) {
        $changes[] = $fields['admin'] ? 'made an admin role' : 'admin taken off';
    }
    foreach (roleModules() as $module_id => $module) {
        $was = intval($before['levels'][$module_id] ?? 0);
        $now = intval($fields['levels'][$module_id] ?? 0);
        if ($was !== $now) {
            $changes[] = $module['label'] . ' ' . ROLE_LEVELS[$was] . ' to ' . ROLE_LEVELS[$now];
        }
    }
    return implode(', ', $changes);
}

/* Save a role's fields (roleFromInput()) - a new role when $role_id is 0. Returns its id. */
function roleSave(array $fields, $role_id = 0) {
    global $mysqli;
    $role_id = intval($role_id);
    $set = "role_name = '" . escapeSql($fields['name']) . "', role_description = '" . escapeSql($fields['description']) . "', role_is_admin = " . intval($fields['admin']);
    if ($role_id) {
        mysqli_query($mysqli, "UPDATE user_roles SET $set WHERE role_id = $role_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO user_roles SET $set, role_type = 1");
        $role_id = intval(mysqli_insert_id($mysqli));
    }
    // An admin role keeps its levels too, for if it stops being one
    mysqli_query($mysqli, "DELETE FROM user_role_permissions WHERE user_role_id = $role_id");
    foreach ($fields['levels'] as $module_id => $level) {
        if ($level > 0) {
            mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = $role_id, module_id = " . intval($module_id) . ", user_role_permission_level = " . intval($level));
        }
    }
    return $role_id;
}

/* Archive or restore a role. */
function roleSetArchived($role_id, $archived) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE user_roles SET role_archived_at = " . ($archived ? 'NOW()' : 'NULL') . " WHERE role_id = " . intval($role_id));
}

/* Delete a role and its levels - check roleDeleteBlocker() first. */
function roleDelete($role_id) {
    global $mysqli;
    $role_id = intval($role_id);
    mysqli_query($mysqli, "DELETE FROM user_role_permissions WHERE user_role_id = $role_id");
    mysqli_query($mysqli, "DELETE FROM user_roles WHERE role_id = $role_id");
}
