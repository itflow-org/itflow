<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Archived ones only when asked for (?archived=1)
$archived_query = intval($_GET['archived'] ?? 0) === 1 ? '' : 'AND service_archived_at IS NULL';

if (isset($_GET['service_id'])) {
    // Specific service via ID (single)
    $id = intval($_GET['service_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM services WHERE service_id = $id " . apiClientScopeSql('service_client_id'));

} elseif (isset($_GET['service_name'])) {
    // Service by name
    $name = mysqli_real_escape_string($mysqli, $_GET['service_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM services WHERE service_name = '$name' $archived_query " . apiClientScopeSql('service_client_id') . " ORDER BY service_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['review_due'])) {
    // Due for review in the next 30 days, or overdue
    $sql = mysqli_query($mysqli, "SELECT * FROM services WHERE " . serviceReviewDueSql() . " AND service_archived_at IS NULL " . apiClientScopeSql('service_client_id') . " ORDER BY service_review_due LIMIT $limit OFFSET $offset");

} else {
    // All services (by client ID or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM services WHERE 1=1 $archived_query " . apiClientScopeSql('service_client_id') . " ORDER BY service_id LIMIT $limit OFFSET $offset");
}

// Output
$api_tag_entity = 'service'; // each one's tags
require_once "../read_output.php";
