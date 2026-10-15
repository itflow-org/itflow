<?php

/*
 * Tags on clients, locations, contacts, credentials, assets and vendors.
 *
 * Every list and detail page draws a record's tags with tagPills(): coloured
 * pills that open the list filtered to that tag, then a "+" pill that opens
 * the tag picker (js/tag_picker.js). The picker ticks tags on and off in
 * place through agent/ajax.php and redraws the pills from the HTML this file
 * returns, so the page never reloads and nothing goes through a modal.
 */

/* What differs between the six kinds of tagged record. */
function tagEntity($entity) {
    $entities = [
        'client' => [
            'type' => 1, 'label' => 'client', 'module' => 'module_client',
            'link_table' => 'client_tags', 'link_record' => 'client_id', 'link_tag' => 'tag_id',
            'table' => 'clients', 'id' => 'client_id', 'client' => 'client_id', 'name' => 'client_name',
            'log' => 'Client', 'list' => 'clients.php'
        ],
        'location' => [
            'type' => 2, 'label' => 'location', 'module' => 'module_client',
            'link_table' => 'location_tags', 'link_record' => 'location_id', 'link_tag' => 'tag_id',
            'table' => 'locations', 'id' => 'location_id', 'client' => 'location_client_id', 'name' => 'location_name',
            'log' => 'Location', 'list' => 'locations.php'
        ],
        'contact' => [
            'type' => 3, 'label' => 'contact', 'module' => 'module_client',
            'link_table' => 'contact_tags', 'link_record' => 'contact_id', 'link_tag' => 'tag_id',
            'table' => 'contacts', 'id' => 'contact_id', 'client' => 'contact_client_id', 'name' => 'contact_name',
            'log' => 'Contact', 'list' => 'contacts.php'
        ],
        'credential' => [
            'type' => 4, 'label' => 'credential', 'module' => 'module_credential',
            'link_table' => 'credential_tags', 'link_record' => 'credential_id', 'link_tag' => 'tag_id',
            'table' => 'credentials', 'id' => 'credential_id', 'client' => 'credential_client_id', 'name' => 'credential_name',
            'log' => 'Credential', 'list' => 'credentials.php'
        ],
        'asset' => [
            'type' => 5, 'label' => 'asset', 'module' => 'module_client',
            'link_table' => 'asset_tags', 'link_record' => 'asset_tag_asset_id', 'link_tag' => 'asset_tag_tag_id',
            'table' => 'assets', 'id' => 'asset_id', 'client' => 'asset_client_id', 'name' => 'asset_name',
            'log' => 'Asset', 'list' => 'assets.php'
        ],
        // A vendor of no client is one of ours - a supplier, which is the financial module's
        'vendor' => [
            'type' => 6, 'label' => 'vendor', 'module' => 'module_client', 'module_ours' => 'module_financial',
            'link_table' => 'vendor_tags', 'link_record' => 'vendor_id', 'link_tag' => 'tag_id',
            'table' => 'vendors', 'id' => 'vendor_id', 'client' => 'vendor_client_id', 'name' => 'vendor_name',
            'log' => 'Vendor', 'list' => 'vendors.php'
        ],
    ];

    return $entities[$entity] ?? null;
}

/* Tag rows (tag_id, tag_name, tag_color, tag_icon) for each record, in one query. */
function tagsForRecords($entity, array $record_ids) {
    global $mysqli;

    $config = tagEntity($entity);
    $record_ids = array_values(array_unique(array_filter(array_map('intval', $record_ids))));
    $tags = array_fill_keys($record_ids, []);
    if (!$config || !$record_ids) {
        return $tags;
    }

    $sql = mysqli_query($mysqli,
        "SELECT {$config['link_record']} AS record_id, tags.tag_id, tag_name, tag_color, tag_icon
        FROM {$config['link_table']}
        JOIN tags ON tags.tag_id = {$config['link_table']}.{$config['link_tag']}
        WHERE {$config['link_record']} IN (" . implode(',', $record_ids) . ")
        ORDER BY tag_name ASC"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $tags[intval($row['record_id'])][] = $row;
    }

    return $tags;
}

/* The tags of one record. */
function tagsForRecord($entity, $record_id) {
    return tagsForRecords($entity, [$record_id])[intval($record_id)] ?? [];
}

/*
 * The pills for one record, plus the "+" that opens the picker when the
 * agent may edit this kind of record.
 *
 * $filter is where a pill goes, up to the tag: "contacts.php?client_id=4&"
 * becomes contacts.php?client_id=4&tags[]=9. Empty means the list page for
 * this kind of record across all clients. Nothing at all comes back for a
 * record with no tags when the agent cannot add any.
 */
function tagPills($entity, $record_id, array $tags, $filter = '', $can_edit = null) {
    $config = tagEntity($entity);
    if (!$config) {
        return '';
    }
    $record_id = intval($record_id);
    $filter = tagFilterBase($entity, $filter);
    // The caller can say who may edit when the module alone does not (one of our own vendors)
    $can_edit = $can_edit === null ? lookupUserPermission($config['module']) >= 2 : (bool) $can_edit;
    if (!$tags && !$can_edit) {
        return '';
    }

    $html = '<span class="itflow-tags" data-tag-entity="' . $entity . '" data-tag-record="' . $record_id . '" data-tag-filter="' . escapeHtml($filter) . '">';

    foreach ($tags as $tag) {
        $tag_id = intval($tag['tag_id']);
        $name = escapeHtml($tag['tag_name']);
        $color = preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', trim((string) $tag['tag_color'])) ? trim($tag['tag_color']) : 'var(--bs-secondary)';
        $icon = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $tag['tag_icon'])) ?: 'tag';
        $html .= '<a href="' . escapeHtml($filter) . 'tags[]=' . $tag_id . '" class="itflow-tag" style="--itflow-tag-color: ' . $color . '" title="Show ' . $config['label'] . 's tagged ' . $name . '">'
            . '<i class="fas fa-fw fa-' . $icon . '" aria-hidden="true"></i>' . $name . '</a>';
    }

    if ($can_edit) {
        $html .= '<button type="button" class="itflow-tag itflow-tag-add" data-tag-picker data-tag-entity="' . $entity . '" data-tag-ids="' . $record_id . '" data-csrf="' . escapeHtml($_SESSION['csrf_token'] ?? '') . '" title="' . ($tags ? 'Change tags' : 'Add tags') . '">'
            . '<i class="fas fa-fw fa-plus" aria-hidden="true"></i>' . ($tags ? '<span class="visually-hidden">Change tags</span>' : 'Tag') . '</button>';
    }

    return $html . '</span>';
}

/* A pill link base - a page of ours with a query string, or the default list. */
function tagFilterBase($entity, $filter) {
    $config = tagEntity($entity);
    $filter = (string) $filter;
    if ($filter === '' || !preg_match('/^[a-z_]+\.php\?([a-z_]+=[0-9a-z_]*&)*$/i', $filter)) {
        return $config['list'] . '?';
    }
    return $filter;
}

/*
 * Check the agent may tag these records: edit rights on the module, and
 * access to the client each record belongs to. Returns [record_id => name]
 * for the ones that exist, or false if any is out of bounds.
 */
function tagRecordsEditable($entity, array $record_ids) {
    global $mysqli;

    $config = tagEntity($entity);
    $record_ids = array_values(array_unique(array_filter(array_map('intval', $record_ids))));
    if (!$config || !$record_ids) {
        return false;
    }
    $can_module = lookupUserPermission($config['module']) >= 2;
    $can_ours = isset($config['module_ours']) && lookupUserPermission($config['module_ours']) >= 2;
    if (!$can_module && !$can_ours) {
        return false;
    }

    $records = [];
    $sql = mysqli_query($mysqli,
        "SELECT {$config['id']} AS record_id, {$config['client']} AS client_id, {$config['name']} AS record_name
        FROM {$config['table']} WHERE {$config['id']} IN (" . implode(',', $record_ids) . ")"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['client_id']) === 0 && isset($config['module_ours'])) {
            if (!$can_ours) {
                return false;
            }
        } elseif (!$can_module || !hasClientAccess(intval($row['client_id']))) {
            return false;
        }
        $records[intval($row['record_id'])] = ['name' => $row['record_name'], 'client_id' => intval($row['client_id'])];
    }

    return count($records) === count($record_ids) ? $records : false;
}

/* A colour for a tag made from the picker - the next one round the palette. */
function tagNextColor($type) {
    global $mysqli;

    $palette = ['#0d6efd', '#198754', '#6f42c1', '#fd7e14', '#d63384', '#20c997', '#dc3545', '#0dcaf0', '#6610f2', '#ffc107'];
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(tag_id) FROM tags WHERE tag_type = " . intval($type)));

    return $palette[intval($row[0]) % count($palette)];
}
