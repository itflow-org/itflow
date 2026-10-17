<?php

/*
 * Tags on clients, locations, contacts, credentials, assets, vendors and domains.
 *
 * Every list and detail page draws a record's tags with tagPills(): coloured
 * pills that open the list filtered to that tag, then a "+" pill that opens
 * the tag picker (js/tag_picker.js). The picker ticks tags on and off in
 * place through agent/ajax.php and redraws the pills from the HTML this file
 * returns, so the page never reloads and nothing goes through a modal. The
 * record forms save their Tags field through tagSetForRecord().
 *
 * Admin > Tags (admin/tags.php) makes, changes, merges, archives and deletes
 * them. An archived tag stays on the records that have it, but isn't offered
 * for new tagging or in the list filters.
 */

/* What differs between the seven kinds of tagged record. */
function tagEntity($entity) {
    $entities = [
        'client' => [
            'title' => 'Client', 'plural' => 'clients', 'icon' => 'fa-users',
            'type' => 1, 'label' => 'client', 'module' => 'module_client',
            'link_table' => 'client_tags', 'link_record' => 'client_id', 'link_tag' => 'tag_id',
            'table' => 'clients', 'id' => 'client_id', 'client' => 'client_id', 'name' => 'client_name',
            'log' => 'Client', 'list' => 'clients.php'
        ],
        'location' => [
            'title' => 'Location', 'plural' => 'locations', 'icon' => 'fa-map-marker-alt',
            'type' => 2, 'label' => 'location', 'module' => 'module_client',
            'link_table' => 'location_tags', 'link_record' => 'location_id', 'link_tag' => 'tag_id',
            'table' => 'locations', 'id' => 'location_id', 'client' => 'location_client_id', 'name' => 'location_name',
            'log' => 'Location', 'list' => 'locations.php'
        ],
        'contact' => [
            'title' => 'Contact', 'plural' => 'contacts', 'icon' => 'fa-address-book',
            'type' => 3, 'label' => 'contact', 'module' => 'module_client',
            'link_table' => 'contact_tags', 'link_record' => 'contact_id', 'link_tag' => 'tag_id',
            'table' => 'contacts', 'id' => 'contact_id', 'client' => 'contact_client_id', 'name' => 'contact_name',
            'log' => 'Contact', 'list' => 'contacts.php'
        ],
        'credential' => [
            'title' => 'Credential', 'plural' => 'credentials', 'icon' => 'fa-key',
            'type' => 4, 'label' => 'credential', 'module' => 'module_credential',
            'link_table' => 'credential_tags', 'link_record' => 'credential_id', 'link_tag' => 'tag_id',
            'table' => 'credentials', 'id' => 'credential_id', 'client' => 'credential_client_id', 'name' => 'credential_name',
            'log' => 'Credential', 'list' => 'credentials.php'
        ],
        'asset' => [
            'title' => 'Asset', 'plural' => 'assets', 'icon' => 'fa-desktop',
            'type' => 5, 'label' => 'asset', 'module' => 'module_client',
            'link_table' => 'asset_tags', 'link_record' => 'asset_tag_asset_id', 'link_tag' => 'asset_tag_tag_id',
            'table' => 'assets', 'id' => 'asset_id', 'client' => 'asset_client_id', 'name' => 'asset_name',
            'log' => 'Asset', 'list' => 'assets.php'
        ],
        // A vendor of no client is one of ours - a supplier, which is the financial module's
        'vendor' => [
            'title' => 'Vendor', 'plural' => 'vendors', 'icon' => 'fa-building',
            'type' => 6, 'label' => 'vendor', 'module' => 'module_client', 'module_ours' => 'module_financial',
            'link_table' => 'vendor_tags', 'link_record' => 'vendor_id', 'link_tag' => 'tag_id',
            'table' => 'vendors', 'id' => 'vendor_id', 'client' => 'vendor_client_id', 'name' => 'vendor_name',
            'log' => 'Vendor', 'list' => 'vendors.php'
        ],
        'domain' => [
            'title' => 'Domain', 'plural' => 'domains', 'icon' => 'fa-globe',
            'type' => 7, 'label' => 'domain', 'module' => 'module_support',
            'link_table' => 'domain_tags', 'link_record' => 'domain_id', 'link_tag' => 'tag_id',
            'table' => 'domains', 'id' => 'domain_id', 'client' => 'domain_client_id', 'name' => 'domain_name',
            'log' => 'Domain', 'list' => 'domains.php'
        ],
    ];

    return $entities[$entity] ?? null;
}

/* Tag rows (tag_id, tag_name, tag_description, tag_color, tag_icon, tag_archived_at) for each record, in one query. */
function tagsForRecords($entity, array $record_ids) {
    global $mysqli;

    $config = tagEntity($entity);
    $record_ids = array_values(array_unique(array_filter(array_map('intval', $record_ids))));
    $tags = array_fill_keys($record_ids, []);
    if (!$config || !$record_ids) {
        return $tags;
    }

    $sql = mysqli_query($mysqli,
        "SELECT {$config['link_record']} AS record_id, tags.tag_id, tag_name, tag_description, tag_color, tag_icon, tag_archived_at
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
        $color = tagColor($tag['tag_color']);
        $icon = tagIcon($tag['tag_icon'] ?? '');
        $about = trim((string) ($tag['tag_description'] ?? ''));
        $html .= '<a href="' . escapeHtml($filter) . 'tags[]=' . $tag_id . '" class="itflow-tag" style="--itflow-tag-color: ' . $color . '; --itflow-tag-text: ' . tagTextColor($color) . '" title="'
            . ($about !== '' ? escapeHtml($about) . ' - ' : '') . 'Show ' . $config['plural'] . ' tagged ' . $name . '">'
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

/* A colour for a new tag - the next one round the palette. */
function tagNextColor($type) {
    global $mysqli;

    $palette = ['#0d6efd', '#198754', '#6f42c1', '#fd7e14', '#d63384', '#20c997', '#dc3545', '#0dcaf0', '#6610f2', '#ffc107'];
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(tag_id) FROM tags WHERE tag_type = " . intval($type)));

    return $palette[intval($row[0]) % count($palette)];
}

/* The colours offered in the tag window - any other can be picked too. */
const TAG_COLORS = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0', '#795548', '#6c757d', '#343a40', '#001f3f'];

/* Icons offered in the tag window, by what they suit - any Font Awesome solid icon's name works. */
const TAG_ICONS = [
    'tag', 'star', 'crown', 'heart', 'flag', 'bookmark', 'bolt', 'fire', 'gem', 'thumbs-up',
    'exclamation-triangle', 'exclamation-circle', 'ban', 'lock', 'shield-alt', 'user-shield', 'user-secret', 'eye-slash', 'skull-crossbones', 'biohazard',
    'user', 'user-tie', 'users', 'user-graduate', 'user-md', 'handshake', 'building', 'industry', 'store', 'home',
    'hospital', 'school', 'church', 'balance-scale', 'gavel', 'landmark', 'university', 'piggy-bank', 'dollar-sign', 'file-invoice-dollar',
    'desktop', 'laptop', 'server', 'hdd', 'database', 'print', 'mobile-alt', 'tablet-alt', 'phone', 'fax',
    'network-wired', 'wifi', 'ethernet', 'globe', 'cloud', 'envelope', 'microchip', 'memory', 'plug', 'battery-full',
    'key', 'fingerprint', 'id-card', 'certificate', 'clock', 'calendar-alt', 'hourglass-half', 'sync-alt', 'archive', 'tools',
    'wrench', 'cog', 'cogs', 'code', 'terminal', 'bug', 'robot', 'rocket', 'leaf', 'recycle',
    'map-marker-alt', 'truck', 'car', 'plane', 'box', 'boxes', 'warehouse', 'flask', 'graduation-cap', 'trophy',
];

/* A tag's colour as #rrggbb - grey for anything else. */
function tagColor($color) {
    $color = strtolower(trim((string) $color));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) {
        return "#$m[1]$m[1]$m[2]$m[2]$m[3]$m[3]";
    }
    return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#6c757d';
}

/* Dark or white text, whichever reads on a colour. */
function tagTextColor($color) {
    $color = tagColor($color);
    $channel = function ($hex) {
        $c = hexdec($hex) / 255;
        return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    };
    $luminance = 0.2126 * $channel(substr($color, 1, 2)) + 0.7152 * $channel(substr($color, 3, 2)) + 0.0722 * $channel(substr($color, 5, 2));
    return $luminance > 0.4 ? '#212529' : '#fff';
}

/* A tag's icon - a Font Awesome name ("star"), or the plain tag. */
function tagIcon($icon) {
    $icon = preg_replace('/^(fa[srb]? +)?fa-/', '', strtolower(trim((string) $icon)));
    $icon = preg_replace('/[^a-z0-9-]/', '', $icon);
    return $icon !== '' ? mb_substr($icon, 0, 50) : 'tag';
}

/* One tag as a pill, with nowhere to go - for Admin > Tags and the windows. */
function tagPill(array $tag) {
    $color = tagColor($tag['tag_color'] ?? '');
    $about = trim((string) ($tag['tag_description'] ?? ''));
    return '<span class="itflow-tag" style="--itflow-tag-color: ' . $color . '; --itflow-tag-text: ' . tagTextColor($color) . '"' . ($about !== '' ? ' title="' . escapeHtml($about) . '"' : '') . '>'
        . '<i class="fas fa-fw fa-' . tagIcon($tag['tag_icon'] ?? '') . '" aria-hidden="true"></i>' . escapeHtml($tag['tag_name']) . '</span>';
}

/* The seven kinds by tag type - type => the entity's key in tagEntity(). */
function tagKinds() {
    return [1 => 'client', 2 => 'location', 3 => 'contact', 4 => 'credential', 5 => 'asset', 6 => 'vendor', 7 => 'domain'];
}

/* "an asset tag", "a client tag" - for messages. */
function tagKindPhrase($type) {
    $word = strtolower(tagEntityByType($type)['title'] ?? 'unknown');
    return (in_array($word[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ') . $word . ' tag';
}

/* The tagEntity() of a tag type - or null. */
function tagEntityByType($type) {
    $entity = tagKinds()[intval($type)] ?? null;
    return $entity ? tagEntity($entity) + ['entity' => $entity] : null;
}

/* One tag, or null. */
function tagRow($tag_id) {
    global $mysqli;
    $tag_id = intval($tag_id);
    return $tag_id ? (mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_description, tag_type, tag_color, tag_icon, tag_created_at, tag_archived_at
        FROM tags WHERE tag_id = $tag_id LIMIT 1")) ?: null) : null;
}

/* How many records have each tag - tag_id => count, every kind (archived records too). */
function tagUsageCounts() {
    return array_map(function ($u) {
        return $u['all'];
    }, tagUsageBreakdown());
}

/* How many records have each tag, and how many of those are archived - tag_id => ['all' => n, 'archived' => n]. */
function tagUsageBreakdown() {
    global $mysqli;
    $counts = [];
    foreach (tagKinds() as $entity) {
        $config = tagEntity($entity);
        $archived = str_replace('_id', '_archived_at', $config['id']);
        $sql = mysqli_query($mysqli, "SELECT {$config['link_tag']}, COUNT(*), SUM({$config['table']}.$archived IS NOT NULL) FROM {$config['link_table']}
            JOIN {$config['table']} ON {$config['table']}.{$config['id']} = {$config['link_table']}.{$config['link_record']} GROUP BY {$config['link_tag']}");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])] = ['all' => intval($row[1]), 'archived' => intval($row[2])];
        }
    }
    return $counts;
}

/* How many records have one tag. */
function tagUsage($tag_id) {
    return tagUsageCounts()[intval($tag_id)] ?? 0;
}

/* The recurring invoice lines that count users or devices with any of these tags ("Users tagged VIP") - item id => its seat filter. */
function tagSeatLineItems(array $tag_ids) {
    global $mysqli;
    $tag_ids = array_map('intval', $tag_ids);
    $items = [];
    // The filter is JSON written by recurringSeatFilter(); read in PHP rather than with SQL's JSON functions
    $sql = mysqli_query($mysqli, "SELECT item_id, item_seat_source, item_seat_filter FROM recurring_invoice_items
        WHERE item_seat_source IN ('contacts', 'assets') AND item_seat_filter LIKE '%\"tag\"%'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $filter = recurringSeatFilter($row['item_seat_source'], $row['item_seat_filter']);
        if (in_array(intval($filter['tag'] ?? 0), $tag_ids, true)) {
            $items[intval($row['item_id'])] = $filter;
        }
    }
    return $items;
}

/* How many recurring invoice lines count by each tag - tag_id => count, for the tags any line counts by. */
function tagSeatLineCounts() {
    global $mysqli;
    $counts = [];
    $sql = mysqli_query($mysqli, "SELECT item_seat_source, item_seat_filter FROM recurring_invoice_items
        WHERE item_seat_source IN ('contacts', 'assets') AND item_seat_filter LIKE '%\"tag\"%'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tag_id = intval(recurringSeatFilter($row['item_seat_source'], $row['item_seat_filter'])['tag'] ?? 0);
        if ($tag_id) {
            $counts[$tag_id] = ($counts[$tag_id] ?? 0) + 1;
        }
    }
    return $counts;
}

/* How many recurring invoice lines count by a tag. */
function tagSeatLines($tag_id) {
    return count(tagSeatLineItems([$tag_id]));
}

/*
 * A tag from the tag window - [$fields, $error]. $in: name, description,
 * color, icon, and type (a new tag, or one nothing has yet). Raw values;
 * tagCreate() and tagUpdate() escape them.
 */
function tagFromInput(array $in, $existing = null) {
    global $mysqli;
    // Stored without markup (escapeSql() strips it), so checked without it
    $name = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($in['name'] ?? ''))), 0, 200));
    $description = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($in['description'] ?? ''))), 0, 255));
    $type = $existing ? intval($existing['tag_type']) : intval($in['type'] ?? 0);
    if ($existing && isset($in['type']) && intval($in['type']) !== $type) {
        if (tagUsage($existing['tag_id']) || tagSeatLines($existing['tag_id'])) {
            return [null, 'A tag can only move to another kind while nothing has it'];
        }
        $type = intval($in['type']);
    }
    if (!tagEntityByType($type)) {
        return [null, 'Pick what the tag is for'];
    }
    if ($name === '') {
        return [null, 'A tag needs a name'];
    }
    // Unique within its kind - a tag already sharing its name with another can still be saved under it
    $kept_name = $existing && intval($existing['tag_type']) === $type && mb_strtolower($existing['tag_name']) === mb_strtolower($name);
    $exclude = $existing ? 'AND tag_id != ' . intval($existing['tag_id']) : '';
    $same = $kept_name ? null : mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_type = $type AND LOWER(tag_name) = LOWER('" . escapeSql($name) . "') $exclude LIMIT 1"));
    if ($same) {
        return [null, 'There is already ' . tagKindPhrase($type) . ' called ' . $same['tag_name']];
    }
    $color = trim((string) ($in['color'] ?? ''));
    $color = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? tagColor($color) : ($existing ? tagColor($existing['tag_color']) : tagNextColor($type));
    return [['name' => $name, 'description' => $description, 'type' => $type, 'color' => $color, 'icon' => tagIcon($in['icon'] ?? '')], ''];
}

/* The SET list for a tag's fields. */
function tagSetSql(array $f) {
    return "tag_name = '" . escapeSql($f['name']) . "', tag_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL') . ",
        tag_type = " . intval($f['type']) . ", tag_color = '" . escapeSql($f['color']) . "', tag_icon = '" . escapeSql($f['icon']) . "'";
}

/* Make a tag. Its id. */
function tagCreate(array $fields) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO tags SET " . tagSetSql($fields));
    return intval(mysqli_insert_id($mysqli));
}

/* Save a tag's name, description, colour, icon (and kind, while unused). */
function tagUpdate($tag_id, array $fields) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE tags SET " . tagSetSql($fields) . " WHERE tag_id = " . intval($tag_id));
}

/* Archive a tag - kept on its records, no longer offered. */
function tagArchive($tag_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE tags SET tag_archived_at = NOW() WHERE tag_archived_at IS NULL AND tag_id = " . intval($tag_id));
}

/* Offer an archived tag again. */
function tagRestore($tag_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE tags SET tag_archived_at = NULL WHERE tag_id = " . intval($tag_id));
}

/*
 * Delete a tag - it comes off every record that has it (the link tables
 * cascade). Refused while a recurring invoice line counts by it, as the line
 * would count nothing: archive it, or merge it into another. Whether it went.
 */
function tagDelete($tag_id, &$error = '') {
    global $mysqli;
    $tag_id = intval($tag_id);
    if ($lines = tagSeatLines($tag_id)) {
        $error = $lines === 1 ? 'A recurring invoice line counts users or devices with it - archive it, or merge it into another tag' : "$lines recurring invoice lines count users or devices with it - archive it, or merge it into another tag";
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM tags WHERE tag_id = $tag_id");
    return mysqli_affected_rows($mysqli) > 0;
}

/*
 * Merge tags into one of the same kind: every record with one of them gets
 * $into_id instead, recurring invoice lines counting by them count by it, and
 * they are deleted. How many records changed hands.
 */
function tagMerge(array $from_ids, $into_id) {
    global $mysqli;
    $into = tagRow($into_id);
    if (!$into) {
        return 0;
    }
    $config = tagEntityByType($into['tag_type']);
    $into_id = intval($into['tag_id']);
    $from_ids = array_values(array_diff(array_unique(array_filter(array_map('intval', $from_ids))), [$into_id]));
    if (!$from_ids) {
        return 0;
    }
    // Only tags of the same kind
    $from_list = implode(',', $from_ids);
    $from_ids = [];
    $sql = mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_id IN ($from_list) AND tag_type = " . intval($into['tag_type']));
    while ($row = mysqli_fetch_row($sql)) {
        $from_ids[] = intval($row[0]);
    }
    if (!$from_ids) {
        return 0;
    }
    $from_list = implode(',', $from_ids);
    $moved = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT {$config['link_record']}) FROM {$config['link_table']} WHERE {$config['link_tag']} IN ($from_list)"))[0]);
    mysqli_query($mysqli, "INSERT IGNORE INTO {$config['link_table']} ({$config['link_record']}, {$config['link_tag']})
        SELECT DISTINCT {$config['link_record']}, $into_id FROM {$config['link_table']} WHERE {$config['link_tag']} IN ($from_list)");
    foreach (tagSeatLineItems($from_ids) as $item_id => $filter) {
        $filter['tag'] = $into_id;
        mysqli_query($mysqli, "UPDATE recurring_invoice_items SET item_seat_filter = '" . escapeSql(json_encode($filter)) . "' WHERE item_id = " . intval($item_id));
    }
    mysqli_query($mysqli, "DELETE FROM tags WHERE tag_id IN ($from_list)");
    return $moved;
}

/*
 * The tags a record form offers - this kind's, not archived, plus any the
 * record already has. Rows of tag_id, tag_name, tag_description, tag_color,
 * tag_icon.
 */
function tagOptionsFor($entity, array $keep_ids = []) {
    global $mysqli;
    $config = tagEntity($entity);
    if (!$config) {
        return [];
    }
    $keep = array_values(array_filter(array_map('intval', $keep_ids)));
    $keep_sql = $keep ? ' OR tag_id IN (' . implode(',', $keep) . ')' : '';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_description, tag_color, tag_icon, tag_archived_at FROM tags
        WHERE tag_type = {$config['type']} AND (tag_archived_at IS NULL$keep_sql) ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* A record form's Tags options - the tagOptionsFor() rows as <option>s, with $selected chosen. */
function tagOptionsHtml($entity, array $selected = []) {
    $selected = array_map('intval', $selected);
    $html = '';
    foreach (tagOptionsFor($entity, $selected) as $row) {
        $id = intval($row['tag_id']);
        $about = trim((string) $row['tag_description']);
        $html .= '<option value="' . $id . '"' . (in_array($id, $selected, true) ? ' selected' : '')
            . selectOptionColor(tagColor($row['tag_color']), tagIcon($row['tag_icon']))
            . ($about !== '' ? ' data-description="' . escapeHtml($about) . '"' : '') . '>'
            . escapeHtml($row['tag_name']) . ($row['tag_archived_at'] ? ' (archived)' : '') . '</option>';
    }
    return $html;
}

/*
 * Set a record's tags from a form's Tags field. $values are tag ids, or -
 * typed into the field by an admin - the names of new tags, made with the
 * next colour. Only this kind's tags count, each once; an archived tag only
 * stays when the record already had it. Returns the tag ids it now has.
 */
function tagSetForRecord($entity, $record_id, array $values) {
    global $mysqli, $session_is_admin, $session_name;
    $config = tagEntity($entity);
    $record_id = intval($record_id);
    if (!$config || !$record_id) {
        return [];
    }
    $link = $config['link_table'];
    $link_record = $config['link_record'];
    $link_tag = $config['link_tag'];
    $type = $config['type'];

    $had = [];
    $sql = mysqli_query($mysqli, "SELECT $link_tag FROM $link WHERE $link_record = $record_id");
    while ($row = mysqli_fetch_row($sql)) {
        $had[] = intval($row[0]);
    }

    $ids = [];
    foreach ($values as $value) {
        $value = trim((string) $value);
        if (preg_match('/^[1-9][0-9]*$/', $value)) {
            $ids[] = intval($value);
        } elseif ($value !== '' && !empty($session_is_admin)) {
            // A new tag typed in - or the one of that name already there
            $name = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($value)), 0, 200));
            if ($name === '') {
                continue;
            }
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id, tag_archived_at FROM tags WHERE tag_type = $type AND LOWER(tag_name) = LOWER('" . escapeSql($name) . "') LIMIT 1"));
            if ($row) {
                // Typing an archived tag's name brings it back, as in the tag picker
                if ($row['tag_archived_at']) {
                    tagRestore($row['tag_id']);
                    logAudit('Tag', 'Restore', escapeSql(stripslashes((string) $session_name) . " restored {$config['label']} tag $name"), 0, intval($row['tag_id']));
                }
                $ids[] = intval($row['tag_id']);
            } else {
                $tag_id = tagCreate(['name' => $name, 'description' => '', 'type' => $type, 'color' => tagNextColor($type), 'icon' => 'tag']);
                logAudit('Tag', 'Create', escapeSql(stripslashes((string) $session_name) . " created {$config['label']} tag $name"), 0, $tag_id);
                $ids[] = $tag_id;
            }
        }
    }
    $ids = array_values(array_unique($ids));

    // This kind's tags only; archived ones only when already there
    $keep = [];
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT tag_id, tag_archived_at FROM tags WHERE tag_type = $type AND tag_id IN (" . implode(',', $ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            if (!$row['tag_archived_at'] || in_array(intval($row['tag_id']), $had, true)) {
                $keep[] = intval($row['tag_id']);
            }
        }
    }

    $remove = array_diff($had, $keep);
    if ($remove) {
        mysqli_query($mysqli, "DELETE FROM $link WHERE $link_record = $record_id AND $link_tag IN (" . implode(',', $remove) . ")");
    }
    foreach (array_diff($keep, $had) as $tag_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO $link SET $link_record = $record_id, $link_tag = $tag_id");
    }
    sort($keep);
    return $keep;
}
