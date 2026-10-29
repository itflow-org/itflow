<?php

/*
 * API - Tags - Update
 * POST /api/v1/tags/update.php
 *
 * Set a record's tags, or add or take some off.
 *
 * Parameters (POST, JSON):
 *   api_key    required - Your API key
 *   kind       required - client, location, contact, credential, asset, vendor,
 *                         domain, license, service, network, document or ticket
 *                         (or its tag type, 1 to 12)
 *   record_id  required - The record
 *   tags       required - Tag ids or names, as an array or comma-separated.
 *                         A name that is no tag of the kind yet is made when the
 *                         key's user is an admin, and skipped otherwise
 *   mode       optional - set (the default: the record has these and no others),
 *                         add or remove
 *   client_id  optional - The record's client - needed, as for every write, when
 *                         the key's user is limited to certain clients
 *
 * The user needs edit rights on that kind of record and access to its client.
 * Only the kind's own tags count, and an archived tag only stays on a record
 * that has it - as in the record forms. Returns the record's tags afterwards,
 * and in "message" what changed.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

require_once 'tags_model.php';

$entity = tagApiKind($_POST['kind'] ?? '', 2);
$config = tagEntity($entity);
$record_id = intval($_POST['record_id'] ?? 0);
$records = tagRecordsEditable($entity, [$record_id]);
if ($records === false) {
    apiDeny("That " . $config['label'] . " doesn't exist, or the user linked to this API key cannot change it.");
}
$record = $records[$record_id];

$mode = strtolower((string) ($_POST['mode'] ?? 'set'));
if (!in_array($mode, ['set', 'add', 'remove'], true)) {
    apiDeny("mode is set, add or remove.");
}
$values = tagApiValues($_POST['tags'] ?? []);
$before = tagIdsForRecord($entity, $record_id);

if ($mode === 'add') {
    $values = array_merge(array_map('strval', $before), $values);
} elseif ($mode === 'remove') {
    // What to take off - by id, or by name within the kind
    $remove = [];
    foreach ($values as $value) {
        if (ctype_digit($value)) {
            $remove[] = intval($value);
        } else {
            $match = mysqli_fetch_row(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = {$config['type']} AND LOWER(tag_name) = LOWER('" . escapeSql($value) . "') LIMIT 1"));
            if ($match) {
                $remove[] = intval($match[0]);
            }
        }
    }
    $values = array_map('strval', array_values(array_diff($before, $remove)));
}

$after = tagSetForRecord($entity, $record_id, $values);
$change = tagChangeText($before, $after);

if ($change !== '') {
    logAudit($config['log'], "Edit", escapeSql(stripslashes((string) $session_name) . " $change on " . $record['name'] . " (API)"), $record['client_id'], $record_id);
    if ($entity === 'ticket') {
        logTicketHistory($record_id, escapeSql(stripslashes((string) $session_name) . " $change (API)"));
    }
}

$rows = array_map('tagApiRow', tagsForRecord($entity, $record_id));
echo json_encode(['success' => 'True', 'count' => count($rows), 'message' => $change !== '' ? ucfirst($change) : 'No change', 'data' => $rows]);
exit();
