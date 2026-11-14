<?php
/*
 * API - Calendars - Read
 * GET /api/v1/calendars/read.php
 *
 * The calendars events go on (Admin > Calendars) - their ids, names and colours.
 * Not their subscription links, which are an admin's.
 *
 *   calendar_id  optional - Just this calendar
 *   archived     optional - 1 to include archived calendars
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = isset($_GET['calendar_id']) ? 'calendar_id = ' . intval($_GET['calendar_id']) : (!empty($_GET['archived']) ? '1 = 1' : 'calendar_archived_at IS NULL');

$sql = mysqli_query($mysqli, "SELECT calendar_id, calendar_name, calendar_color, calendar_created_at, calendar_updated_at, calendar_archived_at FROM calendars WHERE $where ORDER BY calendar_name");

// Output
require_once "../read_output.php";
