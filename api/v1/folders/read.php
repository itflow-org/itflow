<?php
/*
 * API - Folders - Read
 * GET /api/v1/folders/read.php
 *
 * A client's folders, by name - each with the folder it is in (parent_folder,
 * 0: the top level).
 *
 *   folder_id  optional - Just this folder
 *   client_id  optional - Just this client's
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = isset($_GET['folder_id']) ? 'folder_id = ' . intval($_GET['folder_id']) : '1 = 1';

$sql = mysqli_query($mysqli, "SELECT folder_id, folder_name, parent_folder, folder_client_id FROM folders WHERE $where " . apiClientScopeSql('folder_client_id') . " ORDER BY folder_client_id, folder_name LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
