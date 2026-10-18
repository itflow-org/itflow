<?php

/*
 * API - Categories - what read.php uses. A kind of category belongs to the
 * modules whose records hold it (categoryKinds() in functions/categories.php):
 * expense categories to Financial, ticket categories and asset statuses to
 * Support, referrals to Clients, and so on. A key's user with any one of a
 * kind's modules can read that kind.
 */

isset($mysqli) || die("Direct file access is not allowed");

/* Whether the key's user can read a kind's categories. */
function categoryApiCanRead($kind) {
    $config = categoryKind($kind);
    foreach ($config['modules'] ?? [] as $module) {
        if (lookupUserPermission($module) >= 1) {
            return true;
        }
    }
    return false;
}

/* The kinds the key's user can read - category_type values, in Admin > Categories' order. */
function categoryApiReadableKinds() {
    return array_values(array_filter(array_keys(categoryKinds()), 'categoryApiCanRead'));
}

/*
 * The kind asked for - its category_type ("Expense", "asset_status") or what
 * Admin > Categories calls it ("Asset Status"), in any case - when the key's
 * user can read it. Refuses the call otherwise.
 */
function categoryApiKind($value) {
    $key = str_replace([' ', '-'], '_', strtolower(trim((string) $value)));
    foreach (categoryKinds() as $kind => $config) {
        if ($key === strtolower($kind) || $key === str_replace(' ', '_', strtolower($config['label']))) {
            if (!categoryApiCanRead($kind)) {
                apiDeny("The user linked to this API key does not have permission for " . $config['many'] . ".");
            }
            return $kind;
        }
    }
    apiDeny("Unknown kind - one of " . implode(', ', array_keys(categoryKinds())) . ".");
}

/*
 * One category as the API gives it. $usage: categoryUsage() for its kind -
 * or null when the key's user can't see every client, as the counts take in
 * every client's records - for category_used (how many records have it,
 * archived ones too) and category_used_by (that, by table).
 */
function categoryApiRow(array $row, $usage) {
    $config = categoryKind($row['category_type']);
    $used = null;
    $used_by = null;
    if ($usage !== null) {
        $mine = $usage[intval($row['category_id'])] ?? ['all' => 0, 'uses' => []];
        $used = intval($mine['all']);
        $used_by = [];
        foreach ($config['uses'] ?? [] as $index => $use) {
            if (!empty($mine['uses'][$index]['all'])) {
                $used_by[$use['table']] = intval($mine['uses'][$index]['all']);
            }
        }
        // {} rather than [] when nothing has it
        $used_by = (object) $used_by;
    }
    return [
        'category_id' => intval($row['category_id']),
        'category_name' => $row['category_name'],
        'category_description' => $row['category_description'] ?? null,
        'category_type' => $row['category_type'],
        'category_color' => categoryColor($row['category_color'] ?? '') ?: null,
        'category_icon' => categoryIcon($row['category_icon'] ?? '') ?: null,
        'category_order' => intval($row['category_order']),
        'category_created_at' => $row['category_created_at'],
        'category_updated_at' => $row['category_updated_at'] ?? null,
        'category_archived_at' => $row['category_archived_at'] ?? null,
        'category_used' => $used,
        'category_used_by' => $used_by,
    ];
}
