<?php
/*
 * API - Tags - Delete
 * POST /api/v1/tags/delete.php
 *
 * Delete a tag - an admin's job, as in Admin > Tags. It comes off every record
 * that has it. A tag that recurring invoice lines count users or devices by
 * stays: archive it, or merge it into another tag, in Admin > Tags.
 *
 * Parameters (POST, JSON):
 *   api_key  required - Your API key, of an admin user
 *   tag_id   required - The tag
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

if (empty($session_is_admin)) {
    apiDeny("Only an admin can delete tags.");
}

$tag_id = intval($_POST['tag_id'] ?? 0);

// Default
$delete_count = false;

$row = $tag_id ? tagRow($tag_id) : null;

if ($row) {

    $error = '';
    if (!tagDelete($tag_id, $error)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error !== '' ? $row['tag_name'] . " wasn't deleted - " . lcfirst($error) . "." : "That tag is gone.";
        echo json_encode($return_arr);
        exit();
    }

    $delete_count = 1;
    $tag_name = escapeSql($row['tag_name']);

    logAudit("Tag", "Delete", "$tag_name via API ($api_key_name)", 0, $tag_id);
    logAudit("API", "Success", "Deleted tag $tag_name via API ($api_key_name)", 0);
}

// Output
require_once '../delete_output.php';
