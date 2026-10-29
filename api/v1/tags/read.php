<?php

/*
 * API - Tags - Read
 * GET /api/v1/tags/read.php
 *
 * The tags, or one record's tags.
 *
 * Parameters (GET):
 *   api_key    required - Your API key
 *   kind       optional - client, location, contact, credential, asset, vendor,
 *                         domain, license, service, network, document or ticket
 *                         (or its tag type, 1 to 12)
 *   record_id  optional - With kind: that record's tags
 *   tag_id     optional - One tag (archived or not), with tag_record_ids - the
 *                         records with it this key can see
 *   tag_name   optional - Tags with this name, in any case
 *   archived   optional - 1 to include archived tags in the list
 *   limit      optional - Max rows to return (default 50)
 *   offset     optional - Offset for pagination (default 0)
 *
 * Each tag: tag_id, tag_name, tag_description, tag_kind, tag_type, tag_color
 * (#rrggbb), tag_icon (a Font Awesome name) and tag_archived_at. Only the
 * kinds whose records the key's user can read.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

require_once 'tags_model.php';

$tag_columns = 'tag_id, tag_name, tag_description, tag_type, tag_color, tag_icon, tag_archived_at';

// One record's tags - a record this key can see
if (isset($_GET['record_id'])) {
    $entity = tagApiKind($_GET['kind'] ?? '', 1);
    $config = tagEntity($entity);
    $record_id = intval($_GET['record_id']);
    $record = mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$config['id']} FROM {$config['table']} WHERE {$config['id']} = $record_id " . apiClientScopeSql($config['client'])));
    if (!$record) {
        $sql = false;
        require_once "../read_output.php";
    }
    $rows = array_map('tagApiRow', tagsForRecord($entity, $record_id));
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

$types = tagApiReadableTypes();
if (isset($_GET['kind'])) {
    $types = [tagEntity(tagApiKind($_GET['kind'], 1))['type']];
}
$where = 'tag_type IN (' . implode(',', $types ?: [0]) . ')';

if (isset($_GET['tag_id'])) {
    $sql = mysqli_query($mysqli, "SELECT $tag_columns FROM tags WHERE $where AND tag_id = " . intval($_GET['tag_id']));
    $row = $sql ? mysqli_fetch_assoc($sql) : null;
    if (!$row) {
        $sql = false;
        require_once "../read_output.php";
    }
    // The records with it this key can see
    $config = tagEntityByType($row['tag_type']);
    $tag_row = tagApiRow($row);
    $tag_row['tag_record_ids'] = [];
    $records = mysqli_query($mysqli, "SELECT {$config['link_table']}.{$config['link_record']} FROM {$config['link_table']}
        JOIN {$config['table']} ON {$config['table']}.{$config['id']} = {$config['link_table']}.{$config['link_record']}
        WHERE {$config['link_table']}.{$config['link_tag']} = " . intval($row['tag_id']) . " " . apiClientScopeSql($config['client']) . "
        ORDER BY {$config['link_table']}.{$config['link_record']}");
    while ($record = mysqli_fetch_row($records)) {
        $tag_row['tag_record_ids'][] = intval($record[0]);
    }
    echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$tag_row]]);
    exit();
}

if (isset($_GET['tag_name'])) {
    $where .= " AND LOWER(tag_name) = LOWER('" . escapeSql($_GET['tag_name']) . "')";
}
if (intval($_GET['archived'] ?? 0) !== 1) {
    $where .= ' AND tag_archived_at IS NULL';
}

$sql = mysqli_query($mysqli, "SELECT $tag_columns FROM tags WHERE $where ORDER BY tag_type ASC, tag_name ASC LIMIT $limit OFFSET $offset");
if ($sql && mysqli_num_rows($sql) > 0) {
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = tagApiRow($row);
    }
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

// Output
require_once "../read_output.php";
