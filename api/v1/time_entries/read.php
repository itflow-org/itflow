<?php

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$filter_ticket_id  = isset($_GET['ticket_id'])  ? (int) $_GET['ticket_id']  : 0;
$filter_user_id    = isset($_GET['user_id'])    ? (int) $_GET['user_id']    : 0;
$filter_client_id  = isset($_GET['client_id'])  ? (int) $_GET['client_id']  : 0;
$filter_date_from  = isset($_GET['date_from'])  ? $_GET['date_from']        : '';
$filter_date_to    = isset($_GET['date_to'])    ? $_GET['date_to']          : '';

if ($filter_date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $filter_date_from = '';
}
if ($filter_date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $filter_date_to = '';
}

$where = "WHERE tr.ticket_reply_time_worked IS NOT NULL
          AND tr.ticket_reply_time_worked > '00:00:00'
          AND t.ticket_client_id LIKE '$client_id'";

if ($filter_client_id > 0) {
    $where .= " AND t.ticket_client_id = " . (int) $filter_client_id . " ";
}

if ($filter_ticket_id > 0) {
    $where .= " AND t.ticket_id = " . (int) $filter_ticket_id . " ";
}

if ($filter_user_id > 0) {
    $where .= " AND tr.ticket_reply_by = " . (int) $filter_user_id . " ";
}

if ($filter_date_from) {
    $date_from_escaped = mysqli_real_escape_string($mysqli, $filter_date_from . ' 00:00:00');
    $where .= " AND tr.ticket_reply_created_at >= '$date_from_escaped' ";
}

if ($filter_date_to) {
    $date_to_escaped = mysqli_real_escape_string($mysqli, $filter_date_to . ' 23:59:59');
    $where .= " AND tr.ticket_reply_created_at <= '$date_to_escaped' ";
}

$sql_query = "
    SELECT
        tr.ticket_reply_id            AS time_entry_id,
        tr.ticket_reply               AS time_entry_note,
        tr.ticket_reply_type          AS time_entry_type,
        tr.ticket_reply_time_worked   AS time_worked_hhmmss,
        TIME_TO_SEC(tr.ticket_reply_time_worked) AS time_worked_seconds,
        tr.ticket_reply_created_at    AS time_entry_created_at,
        tr.ticket_reply_updated_at    AS time_entry_updated_at,
        tr.ticket_reply_by            AS user_id,
        tr.ticket_reply_ticket_id     AS ticket_id,
        t.ticket_prefix,
        t.ticket_number,
        t.ticket_subject,
        t.ticket_category             AS ticket_category,
        cat.category_name             AS ticket_category_name,
        t.ticket_priority,
        t.ticket_status,
        t.ticket_billable             AS billable,
        t.ticket_client_id            AS client_id,
        c.client_name,
        c.client_type,
        c.client_rate,
        c.client_currency_code,
        u.user_name,
        u.user_email,
        tr.ticket_reply_archived_at   AS ticket_reply_archived_at
    FROM ticket_replies tr
    JOIN tickets t   ON tr.ticket_reply_ticket_id = t.ticket_id
    LEFT JOIN categories cat ON cat.category_id = t.ticket_category
    JOIN clients c   ON t.ticket_client_id = c.client_id
    JOIN users u     ON tr.ticket_reply_by = u.user_id
    $where
    ORDER BY tr.ticket_reply_created_at ASC
    LIMIT $limit OFFSET $offset
";

$sql = mysqli_query($mysqli, $sql_query);

require_once "../read_output.php";
