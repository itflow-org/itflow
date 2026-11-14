<?php
/*
 * API - Trips - Read
 * GET /api/v1/trips/read.php
 *
 * Trips driven for clients and the business, newest first - every trip for a key
 * whose role can see Financial, otherwise the ones its agent drove or logged. Each
 * has its driver's and client's names. trip_miles is the whole trip; a round trip
 * is there and back.
 *
 *   trip_id        optional - Just this trip
 *   client_id      optional - Just this client's; 0 for trips for no client
 *   user_id        optional - Just this driver's
 *   from, to       optional - Driven on or after, and on or before, these days (Y-m-d)
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = 'trips.trip_archived_at IS NULL' . tripScopeSql();
if (isset($_GET['trip_id'])) {
    $where .= ' AND trips.trip_id = ' . intval($_GET['trip_id']);
}
if (isset($_GET['user_id'])) {
    $where .= ' AND trips.trip_user_id = ' . intval($_GET['user_id']);
}
foreach (['from' => '>=', 'to' => '<='] as $field => $op) {
    if (!empty($_GET[$field]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET[$field])) {
        $where .= " AND trips.trip_date $op '" . escapeSql($_GET[$field]) . "'";
    }
}

$sql = mysqli_query($mysqli, "SELECT trips.*, driver.user_name AS driver_name, client_name FROM trips " . tripJoinsSql() . "
    WHERE $where " . apiClientScopeSql('trips.trip_client_id') . " ORDER BY trips.trip_date DESC, trips.trip_id DESC LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
