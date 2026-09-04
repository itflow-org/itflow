<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse ID
$credential_id = intval($_POST['credential_id']);

// Default
$delete_count = false;

if (!empty($credential_id)) {
	$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT credential_name FROM credentials WHERE credential_id = $credential_id AND credential_client_id = $client_id AND credential_archived_at IS NOT NULL LIMIT 1"));
	$credential_name = escapeSql($row['credential_name'] ?? '');

	$delete_sql = mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_id = $credential_id AND credential_client_id = $client_id AND credential_archived_at IS NOT NULL LIMIT 1");

	// Check delete & get affected rows
	if ($delete_sql && !empty($credential_name)) {
		$delete_count = mysqli_affected_rows($mysqli);

		// Logging
		logAudit("Credential", "Delete", "$credential_name via API ($api_key_name)", $client_id, $credential_id);
	}
}

// Output
require_once '../delete_output.php';
