<?php

/*
 * API - Tags - what read.php, update.php and create.php share. A tag's kind
 * is its record's: an asset tag needs the module assets are in, and a record's
 * tags need access to its client too.
 */

isset($mysqli) || die("Direct file access is not allowed");

/*
 * The kind asked for - its name ("asset", "license" or "software", "ticket"...)
 * or tag type number - when the key's user can read (1) or change (2) that
 * kind of record. Refuses the call otherwise.
 */
function tagApiKind($value, $level) {
    $value = strtolower(trim((string) $value));
    $value = $value === 'software' ? 'license' : $value;
    $entity = ctype_digit($value) ? (tagKinds()[intval($value)] ?? null) : (in_array($value, tagKinds(), true) ? $value : null);
    if (!$entity) {
        apiDeny("Unknown kind - one of " . implode(', ', tagKinds()) . ", or its tag type number.");
    }
    if (lookupUserPermission(tagEntity($entity)['module']) < $level) {
        apiDeny("The user linked to this API key does not have permission for " . tagEntity($entity)['plural'] . ".");
    }
    return $entity;
}

/* The tag types the key's user can read. */
function tagApiReadableTypes() {
    $types = [];
    foreach (tagKinds() as $type => $entity) {
        if (lookupUserPermission(tagEntity($entity)['module']) >= 1) {
            $types[] = $type;
        }
    }
    return $types;
}

/* The tags a call names - an array, or a comma-separated string - as trimmed values (ids or names). */
function tagApiValues($value) {
    $values = is_array($value) ? $value : explode(',', (string) $value);
    return array_values(array_filter(array_map(function ($v) {
        return trim((string) $v);
    }, $values), 'strlen'));
}
