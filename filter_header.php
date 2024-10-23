<?php
/*
 * Filter - Head
 * Sets the paging/sort for use in limit/order by
 * Sets the default search query from GET to $q
 *
 * Should not be accessed directly, but called from other pages
 */

// Unset Array Var to prevent Duplicate Get VARs
$get_copy = $_GET; // create a copy of the $_GET array
//unset($get_copy['page']);
unset($get_copy['sort']);
unset($get_copy['order']);

// Paging
if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $record_from = (($page)-1)*$user_config_records_per_page;
    $record_to = $user_config_records_per_page;
} else {
    $record_from = 0;
    $record_to = $user_config_records_per_page;
    $page = 1;
}

// Order
if (isset($_GET['order'])) {
    if ($_GET['order'] == 'ASC') {
        $order = "ASC";
        $disp = "DESC";
    } else {
        $order = "DESC";
        $disp = "ASC";
    }
} elseif(isset($order)) {
    if ($order == "ASC") {
        $disp = "DESC";
    } else {
        $disp = "ASC";
    }
}

// Set the order Icon
if (isset($sort)) {
    if ($order == "ASC") {
        $order_icon = "<i class='fas fa-sort-up'></i>";
    } else {
        $order_icon = "<i class='fas fa-sort-down'></i>";
    }
}

// Search
if (isset($_GET['q'])) {
    $q = sanitizeInput($_GET['q']);
    //Phone Numbers
    $phone_query = preg_replace("/[^0-9]/", '', $q);
    if (empty($phone_query)) {
        $phone_query = $q;
    }
} else {
    $q = "";
    $phone_query = "";
}

// Sortby
if (!empty($_GET['sort'])) {
    $sort = sanitizeInput(preg_replace('/[^a-z_]/', '', $_GET['sort'])); // JQ 2023-05-09 - See issue #673 on GitHub to see the reasoning why we used preg_replace technically sanitizeInput() should have been enough to escape SQL Commands
}

// Date Handling
if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / end of the month
    $_GET['canned_date'] = 'custom';
}

// Date Filter
$row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT user_config_calendar_first_day FROM user_settings WHERE user_id = $session_user_id"));
if (intval($row['user_config_calendar_first_day']) == 1){
	$user_config_calendar_first_day = "monday";
} else {
	$user_config_calendar_first_day = "sunday";
}

if ($_GET['canned_date'] == "custom" && !empty($_GET['dtf']) || !empty($_GET['dtt'])) {
    $dtf = sanitizeInput($_GET['dtf']);
    $dtt = sanitizeInput($_GET['dtt']);
} elseif ($_GET['canned_date'] == "today") {
    $dtf = date('Y-m-d');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "yesterday") {
    $dtf = date('Y-m-d', strtotime("yesterday"));
    $dtt = date('Y-m-d', strtotime("yesterday"));
} elseif ($_GET['canned_date'] == "thisweek") {
    $dtf = date('Y-m-d', strtotime("last $user_config_calendar_first_day"));
    $dtt = date('Y-m-d', strtotime("last $user_config_calendar_first_day + 6 days"));
} elseif ($_GET['canned_date'] == "lastweek") {
    $dtf = date('Y-m-d', strtotime("last $user_config_calendar_first_day -7 days"));
    $dtt = date('Y-m-d', strtotime("last $user_config_calendar_first_day - 1 days"));
} elseif ($_GET['canned_date'] == "thismonth") {
    $dtf = date('Y-m-01');
    $dtt = date('Y-m-d', strtotime("last day of this month"));
} elseif ($_GET['canned_date'] == "lastmonth") {
    $dtf = date('Y-m-d', strtotime("first day of last month"));
    $dtt = date('Y-m-d', strtotime("last day of last month"));
} elseif ($_GET['canned_date'] == "thisyear") {
    $dtf = date('Y-01-01');
    $dtt = date('Y-m-d', strtotime("last day of december this year"));
} elseif ($_GET['canned_date'] == "lastyear") {
    $dtf = date('Y-m-d', strtotime("first day of january last year"));
    $dtt = date('Y-m-d', strtotime("last day of december last year"));
} else {
    $dtf = "NULL";
    $dtt = date('Y-m-d', strtotime("last day of this month"));
}

// Archived

$archived = 0;

if (isset($_GET['archived'])) {
    $archived = intval($_GET['archived']);
}

if ($archived == 1){
    $archive_query = "archived_at IS NOT NULL";
} else {
    $archive_query = "archived_at IS NULL";
}
