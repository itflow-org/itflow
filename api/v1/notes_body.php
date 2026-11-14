<?php

/*
 * API - asset and contact notes - one body for both, included by asset_notes/ and
 * contact_notes/ with $note_kind ('asset' or 'contact') and $note_op (read, create,
 * archive, unarchive, delete). The notes on an asset's or a contact's page: a type
 * (Admin > Categories) and what was noted, by the agent who wrote it. Archived ones
 * stay until deleted, and only an archived one can be deleted.
 */

if (!function_exists('apiDeny') || !isset($note_kind, $note_op)) {
    http_response_code(404);
    exit();
}

$k = [
    'asset' => ['table' => 'asset_notes', 'p' => 'asset_note_', 'owner' => 'asset_note_asset_id', 'parent' => 'assets', 'id' => 'asset_id',
        'name' => 'asset_name', 'client' => 'asset_client_id', 'category' => 'asset_note_type', 'audit' => 'Asset'],
    'contact' => ['table' => 'contact_notes', 'p' => 'contact_note_', 'owner' => 'contact_note_contact_id', 'parent' => 'contacts', 'id' => 'contact_id',
        'name' => 'contact_name', 'client' => 'contact_client_id', 'category' => 'contact_note_type', 'audit' => 'Contact'],
][$note_kind];
$p = $k['p'];

$note_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    echo json_encode($return_arr);
    exit();
};

if ($note_op === 'read') {
    $parent_id = intval($_GET[$k['id']] ?? 0);
    $note_id = intval($_GET[$p . 'id'] ?? 0);
    if (!$parent_id && !$note_id) {
        $note_refuse("{$k['table']}/read.php needs {$k['id']} or {$p}id.");
    }
    $sql = mysqli_query($mysqli, "SELECT {$k['table']}.*, user_name AS {$p}created_by_name, {$k['client']}
        FROM {$k['table']}
        JOIN {$k['parent']} ON {$k['id']} = {$k['owner']}
        LEFT JOIN users ON user_id = {$p}created_by
        WHERE " . ($note_id ? "{$p}id = $note_id" : "{$k['owner']} = $parent_id") . " " . apiClientScopeSql($k['client']) . "
        ORDER BY {$p}created_at DESC, {$p}id DESC LIMIT $limit OFFSET $offset");
    require_once __DIR__ . '/read_output.php';
}

if ($note_op === 'create') {
    $insert_id = false;
    $parent_id = intval($_POST[$k['id']] ?? 0);
    $parent = $parent_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$k['id']}, {$k['name']}, {$k['client']} FROM {$k['parent']} WHERE {$k['id']} = $parent_id "
        . clientScopeSql($k['client']) . ($client_id ? " AND {$k['client']} = $client_id" : '') . " LIMIT 1")) : null;
    if ($parent && apiUserCanAccessClient($parent[$k['client']])) {
        $client_id = intval($parent[$k['client']]);
        $note_field = $note_kind . '_note';
        $note = trim((string) (is_scalar($_POST[$note_field] ?? '') ? ($_POST[$note_field] ?? '') : ''));
        $type = categoryPickedName($k['category'], $_POST[$p . 'type'] ?? '');
        if ($note === '') {
            $note_refuse("A note needs something in it - {$note_kind}_note.");
        }
        if ($type === null || $type === '') {
            $note_refuse("{$p}type is one of: " . implode(', ', array_keys(categoryPicker($k['category']))) . '.');
        }
        $type_sql = escapeSql($type);
        mysqli_query($mysqli, "INSERT INTO {$k['table']} SET {$p}type = '$type_sql', {$note_kind}_note = '" . escapeSql($note) . "', {$p}created_by = " . intval($session_user_id) . ", {$k['owner']} = $parent_id");
        $insert_id = intval(mysqli_insert_id($mysqli)) ?: false;
        if ($insert_id) {
            $label = "a $type_sql note for $note_kind " . escapeSql($parent[$k['name']]);
            logAudit($k['audit'], "Edit", "Created $label via API ($api_key_name)", $client_id, $parent_id);
            logAudit("API", "Success", "Created $label via API ($api_key_name)", $client_id);
        }
    }
    require_once __DIR__ . '/create_output.php';
}

// The rest act on one note, of a client the key's user can reach
$note_id = intval($_POST[$p . 'id'] ?? 0);
$row = $note_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$p}id, {$p}type, {$p}archived_at, {$k['id']}, {$k['name']}, {$k['client']}
    FROM {$k['table']} JOIN {$k['parent']} ON {$k['id']} = {$k['owner']}
    WHERE {$p}id = $note_id " . clientScopeSql($k['client']) . ($client_id ? " AND {$k['client']} = $client_id" : '') . " LIMIT 1")) : null;
if ($row && !apiUserCanAccessClient($row[$k['client']])) {
    $row = null;
}
$client_id = intval($row[$k['client']] ?? 0);
$label = $row ? escapeSql($row[$p . 'type'] . " note for $note_kind " . $row[$k['name']]) : '';

if ($note_op === 'archive' || $note_op === 'unarchive') {
    $update_count = false;
    $archive = $note_op === 'archive';
    if ($row && empty($row[$p . 'archived_at']) === $archive) {
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$p}archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE {$p}id = $note_id");
        $update_count = 1;
        logAudit($k['audit'], "Edit", ($archive ? 'Archived' : 'Restored') . " the $label via API ($api_key_name)", $client_id, intval($row[$k['id']]));
        logAudit("API", "Success", ($archive ? 'Archived' : 'Restored') . " the $label via API ($api_key_name)", $client_id);
    }
    require_once __DIR__ . '/update_output.php';
}

if ($note_op === 'delete') {
    $delete_count = false;
    // Archived first, as for the other records the API deletes
    if ($row && !empty($row[$p . 'archived_at'])) {
        mysqli_query($mysqli, "DELETE FROM {$k['table']} WHERE {$p}id = $note_id");
        $delete_count = mysqli_affected_rows($mysqli);
        if ($delete_count > 0) {
            logAudit($k['audit'], "Edit", "Deleted the $label via API ($api_key_name)", $client_id, intval($row[$k['id']]));
            logAudit("API", "Success", "Deleted the $label via API ($api_key_name)", $client_id);
        }
    }
    require_once __DIR__ . '/delete_output.php';
}
