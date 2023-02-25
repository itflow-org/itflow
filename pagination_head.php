<?php
/*
 * Pagination - Head
 * Sets the paging/sort for use in limit/order by
 * Sets the default search query from GET to $q
 *
 * Should not be accessed directly, but called from other pages
 */

// Paging
if (isset($_GET['p'])) {
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$_SESSION['records_per_page'];
    $record_to = $_SESSION['records_per_page'];
} else {
    $record_from = 0;
    $record_to = $_SESSION['records_per_page'];
    $p = 1;
}

// Order
if (isset($_GET['o'])) {
    if ($_GET['o'] == 'ASC') {
        $o = "ASC";
        $disp = "DESC";
    } else {
        $o = "DESC";
        $disp = "ASC";
    }
} else {
    if ($o == "ASC") {
        $disp = "DESC";
    } else {
        $disp = "ASC";
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
    //Phone Numbers
    $n = preg_replace("/[^0-9]/", '', $q);
    if (empty($n)) {
        $n = $q;
    }
} else {
    $q = "";
    $phone_query = "";
}

// Sortby
if (!empty($_GET['sb'])) {
    $sb = sanitizeInput($_GET['sb']);
}

// Date Handling
if (empty($_GET['canned_date'])) {
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
    $_GET['canned_date'] = 'custom';
}

// Date Filter
if ($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])) {
    $dtf = sanitizeInput($_GET['dtf']);
    $dtt = sanitizeInput($_GET['dtt']);
} elseif ($_GET['canned_date'] == "today") {
    $dtf = date('Y-m-d');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "yesterday") {
    $dtf = date('Y-m-d', strtotime("yesterday"));
    $dtt = date('Y-m-d', strtotime("yesterday"));
} elseif ($_GET['canned_date'] == "thisweek") {
    $dtf = date('Y-m-d', strtotime("monday this week"));
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastweek") {
    $dtf = date('Y-m-d', strtotime("monday last week"));
    $dtt = date('Y-m-d', strtotime("sunday last week"));
} elseif ($_GET['canned_date'] == "thismonth") {
    $dtf = date('Y-m-01');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastmonth") {
    $dtf = date('Y-m-d', strtotime("first day of last month"));
    $dtt = date('Y-m-d', strtotime("last day of last month"));
} elseif ($_GET['canned_date'] == "thisyear") {
    $dtf = date('Y-01-01');
    $dtt = date('Y-m-d');
} elseif ($_GET['canned_date'] == "lastyear") {
    $dtf = date('Y-m-d', strtotime("first day of january last year"));
    $dtt = date('Y-m-d', strtotime("last day of december last year"));
} else {
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
}
