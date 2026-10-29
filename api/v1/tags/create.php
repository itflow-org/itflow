<?php

/*
 * API - Tags - Create
 * POST /api/v1/tags/create.php
 *
 * Make a tag - an admin's job, as in Admin > Tags.
 *
 * Parameters (POST, JSON):
 *   api_key      required - Your API key, of an admin user
 *   kind         required - client, location, contact, credential, asset, vendor,
 *                           domain, license, service, network, document or ticket
 *                           (or its tag type, 1 to 12)
 *   name         required - Unique within its kind, in any case
 *   description  optional
 *   color        optional - #rrggbb, else the next colour along
 *   icon         optional - A Font Awesome icon's name, else "tag"
 *
 * Returns the new tag's id as insert_id.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

require_once 'tags_model.php';

if (empty($session_is_admin)) {
    apiDeny("Only an admin can make tags.");
}

$entity = tagApiKind($_POST['kind'] ?? '', 2);

[$fields, $error] = tagFromInput([
    'name' => $_POST['name'] ?? '',
    'description' => $_POST['description'] ?? '',
    'type' => tagEntity($entity)['type'],
    'color' => $_POST['color'] ?? '',
    'icon' => $_POST['icon'] ?? '',
]);

if ($error !== '') {
    echo json_encode(['success' => 'False', 'message' => $error]);
    exit();
}

$insert_id = tagCreate($fields);
logAudit("Tag", "Create", "$session_name created $entity tag " . escapeSql($fields['name']) . " (API)", 0, $insert_id);

// Output
require_once '../create_output.php';
