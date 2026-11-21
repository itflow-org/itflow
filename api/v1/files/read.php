<?php
/*
 * API - Files - Read
 * GET /api/v1/files/read.php
 *
 * A client's files, newest first - archived ones too - each with the folder it is
 * in (0: the top level). files/download.php gets one's content.
 *
 *   file_id        optional - Just this file
 *   client_id      optional - Just this client's
 *   folder_id      optional - Just those in this folder
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = '1 = 1';
if (isset($_GET['file_id'])) {
    $where .= ' AND file_id = ' . intval($_GET['file_id']);
}
if (isset($_GET['folder_id'])) {
    $where .= ' AND file_folder_id = ' . intval($_GET['folder_id']);
}

// Not where it is kept on the server
$sql = mysqli_query($mysqli, "SELECT file_id, file_name, file_description, file_ext, file_size, file_mime_type, file_favorite, file_created_at, file_updated_at,
        file_archived_at, file_accessed_at, file_created_by, file_folder_id, folder_name, file_client_id
    FROM files LEFT JOIN folders ON folder_id = file_folder_id AND file_folder_id > 0
    WHERE $where " . apiClientScopeSql('file_client_id') . " ORDER BY file_created_at DESC, file_id DESC LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
