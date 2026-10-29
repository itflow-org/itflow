<?php

/*
 * Roles (Admin > Roles) - making, changing, copying, archiving, restoring
 * and deleting them. The work is in functions/roles.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_role'])) {

    validateCSRFToken();

    [$fields, $error] = roleFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $role_id = roleSave($fields);

    $access = $fields['admin'] ? 'an admin role' : (implode(', ', array_filter(array_map(function ($module_id) use ($fields) {
        $level = $fields['levels'][$module_id];
        return $level ? roleModules()[$module_id]['label'] . ' ' . ROLE_LEVELS[$level] : '';
    }, array_keys($fields['levels'])))) ?: 'no access');
    logAudit("User Role", "Create", "$session_name created role " . escapeSql($fields['name']) . " - " . escapeSql($access), 0, $role_id);

    flashAlert("Role <strong>" . escapeHtml($fields['name']) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_POST['role_id'] ?? 0));
    if (!$row) {
        flashAlert("That role is gone - it may have been deleted", 'error');
        redirect();
    }
    $role_id = intval($row['role_id']);

    [$fields, $error] = roleFromInput($_POST, $role_id);
    if ($error === '' && intval($row['role_is_admin']) && !$fields['admin'] && ($blocker = roleAdminBlocker($row)) !== '') {
        $error = "$row[role_name] stays an admin role: $blocker";
    }
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $changes = roleChangeText($row, $fields);
    roleSave($fields, $role_id);

    if ($changes !== '') {
        logAudit("User Role", "Edit", "$session_name edited role " . escapeSql($fields['name']) . " - " . escapeSql($changes), 0, $role_id);
    }

    flashAlert("Role <strong>" . escapeHtml($fields['name']) . "</strong> " . ($changes !== '' ? 'saved' : 'unchanged'));

    redirect();

}

if (isset($_GET['archive_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['archive_role']));
    if (!$row) {
        flashAlert("That role is gone - it may have been deleted", 'error');
        redirect();
    }
    if (($blocker = roleArchiveBlocker($row)) !== '') {
        flashAlert("<strong>" . escapeHtml($row['role_name']) . "</strong> wasn't archived: " . escapeHtml($blocker), 'error');
        redirect();
    }

    roleSetArchived($row['role_id'], true);

    logAudit("User Role", "Archive", "$session_name archived role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert("Role <strong>" . escapeHtml($row['role_name']) . "</strong> archived");

    redirect();

}

if (isset($_GET['restore_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['restore_role']));
    if (!$row) {
        flashAlert("That role is gone - it may have been deleted", 'error');
        redirect();
    }

    roleSetArchived($row['role_id'], false);

    logAudit("User Role", "Restore", "$session_name restored role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert("Role <strong>" . escapeHtml($row['role_name']) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_role'])) {

    validateCSRFToken();

    $row = roleRow(intval($_GET['delete_role']));
    if (!$row) {
        flashAlert("That role is gone - it may have been deleted", 'error');
        redirect();
    }
    if (($blocker = roleDeleteBlocker($row)) !== '') {
        flashAlert("<strong>" . escapeHtml($row['role_name']) . "</strong> wasn't deleted: " . escapeHtml($blocker), 'error');
        redirect();
    }

    roleDelete($row['role_id']);

    logAudit("User Role", "Delete", "$session_name deleted role " . escapeSql($row['role_name']), 0, $row['role_id']);

    flashAlert("Role <strong>" . escapeHtml($row['role_name']) . "</strong> deleted", 'error');

    redirect();

}
