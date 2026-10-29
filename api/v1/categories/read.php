<?php

/*
 * API - Categories - Read
 * GET /api/v1/categories/read.php
 *
 * The categories set up in Admin > Categories, or one of them. Read only -
 * categories are changed in Admin > Categories.
 *
 * Parameters (GET):
 *   api_key        required - Your API key
 *   kind           optional - Expense, Income, Referral, Ticket, network_interface,
 *                             asset_status, software_type, rack_type,
 *                             contact_note_type or asset_note_type (or what
 *                             Admin > Categories calls it - "Asset Status"),
 *                             in any case
 *   category_id    optional - One category, archived or not
 *   category_name  optional - Categories with this name, in any case
 *   archived       optional - 1 to include archived categories in the list
 *   limit          optional - Max rows to return (default 50)
 *   offset         optional - Offset for pagination (default 0)
 *
 * Each category: category_id, category_name, category_description,
 * category_type (its kind, as above), category_color (#rrggbb or null),
 * category_icon (a Font Awesome name, or null), category_order (the order
 * pickers list it in, for the kinds that are put in order), category_created_at,
 * category_updated_at, category_archived_at, and category_used - how many
 * records have it, archived ones too - with category_used_by, that by table
 * ({"expenses": 42, "budget": 2}). The counts take in every client's records,
 * so they are null for a key whose user can't see every client.
 *
 * Only the kinds whose records the key's user can read - expense categories
 * need Financial, income categories Sales or Financial, referrals and contact
 * note types Clients, the rest Support.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

require_once 'categories_model.php';

$category_columns = 'category_id, category_name, category_description, category_type, category_color, category_icon, category_order,
    category_created_at, category_updated_at, category_archived_at';

$kinds = isset($_GET['kind']) ? [categoryApiKind($_GET['kind'])] : categoryApiReadableKinds();
$where = "category_type IN ('" . implode("', '", array_map('escapeSql', $kinds ?: ['-'])) . "')";

if (isset($_GET['category_id'])) {
    $where .= ' AND category_id = ' . intval($_GET['category_id']);
} else {
    if (isset($_GET['category_name'])) {
        $where .= " AND LOWER(category_name) = LOWER('" . escapeSql(trim($_GET['category_name'])) . "')";
    }
    if (intval($_GET['archived'] ?? 0) !== 1) {
        $where .= ' AND category_archived_at IS NULL';
    }
}

// Kinds in Admin > Categories' order; within one, the order pickers list them in
$all_kinds = array_keys(categoryKinds());
$ordered_kinds = array_keys(array_filter(categoryKinds(), function ($config) {
    return $config['ordered'];
}));
$sql = mysqli_query($mysqli, "SELECT $category_columns FROM categories WHERE $where
    ORDER BY FIELD(category_type, '" . implode("', '", array_map('escapeSql', $all_kinds)) . "'),
        IF(category_type IN ('" . implode("', '", array_map('escapeSql', $ordered_kinds)) . "'), category_order, 0), category_name ASC, category_id ASC
    LIMIT $limit OFFSET $offset");

if ($sql && mysqli_num_rows($sql) > 0) {
    // The counts take in every client's records - only for a user who sees them all
    $see_all = clientScopeSql('category_id') === '';
    $usage = [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if ($see_all && !isset($usage[$row['category_type']])) {
            $usage[$row['category_type']] = categoryUsage($row['category_type']);
        }
        $rows[] = categoryApiRow($row, $see_all ? $usage[$row['category_type']] : null);
    }
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

// Output
require_once "../read_output.php";
