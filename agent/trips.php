<?php

/*
 * Trips - the driving the business does, for clients and for itself.
 *
 * Along the top: the miles this month against the same days of last month,
 * the year so far, your own this month, and what is owed for driving own
 * vehicles (or, with no mileage rate, how much of the year's driving was
 * for clients). Pills cut the list to yours, for a client, for a ticket, our
 * own errands, to pay back and to bill; filters by driver, client and date.
 * To pay back lists who is owed what, with Pay Mileage. A row opens the
 * trip's quick look - where it went, the client's site and ticket, the
 * route on a map, what it was paid back and billed - with Return Trip to
 * log the way back.
 *
 * Agents with Financial access see every trip; anyone else sees the trips
 * they drove or logged, and logs their own.
 *
 * Takes ?driver=, ?client= (or the client pages' ?client_id=), ?location_id=,
 * ?ticket_id=, ?expense_id= (a mileage expense's trips), ?invoice_id= (an
 * invoice's), ?show= (mine, client, ticket, own, to_pay, to_bill), the date
 * filter and ?open= to open a trip.
 */

// Default Column Sortby/Order Filter
$sort = "trip_date";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_page = true;
} else {
    require_once "includes/inc_all.php";
    $client_page = false;
    $client_id = 0;
}

$can_manage = tripCanManage();
$sees_all = tripCanSeeAll();

$driver_filter = intval($_GET['driver'] ?? 0);
$client_filter = $client_page ? intval($client_id) : intval($_GET['client'] ?? 0);
$location_filter = intval($_GET['location_id'] ?? 0);
$ticket_filter = intval($_GET['ticket_id'] ?? 0);
$expense_filter = intval($_GET['expense_id'] ?? 0);
$invoice_filter = intval($_GET['invoice_id'] ?? 0);
$shows = tripShows();
$show = (string) ($_GET['show'] ?? '');
$show = isset($shows[$show]) ? $show : '';

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['trip_date' => 'trips.trip_date', 'user_name' => 'driver.user_name', 'trip_destination' => 'trips.trip_destination', 'client_name' => 'client_name', 'trip_miles' => 'trips.trip_miles'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'trip_date';
}

// Everything but the pill, then the pill - the pill counts are over what the rest leave
$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'show' => '', 'client' => $client_filter]);
$where = tripFiltersSql($filter_src);
$show_query = $shows[$show]['where'] ? "AND {$shows[$show]['where']}" : '';
$joins = tripJoinsSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS trips.*, client_name, driver.user_name AS driver_name, driver.user_avatar AS driver_avatar,
        location_name, ticket_prefix, ticket_number, ticket_subject, ticket_resolved_at, ticket_closed_at, invoice_prefix, invoice_number, invoice_status
    FROM trips $joins
    LEFT JOIN invoices ON trips.trip_invoice_id > 0 AND invoice_id = trips.trip_invoice_id
    WHERE $where $show_query
    ORDER BY {$sortable_columns[$sort]} $order, trips.trip_id DESC LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
$trips = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $trips[] = $row;
}

// The pills, and the miles of what is listed
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . ", COALESCE(SUM(CASE WHEN 1 $show_query THEN trips.trip_miles END), 0)
    FROM trips $joins WHERE $where"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}
$listed_miles = floatval($row[count($shows)]);

$stats = tripStats();

// The money - who is owed for their own vehicle (above the list on To pay back), and the rates
$rules = tripRules();
$pay_back = tripPayBackOn();
$owed_total = $pay_back ? tripToClaimCount() : null;
$owed = $show === 'to_pay' ? tripsToClaim($driver_filter) : [];
$stat_money = function ($amount) {
    return escapeHtml(billingMoney($amount));
};
$filter_url = function ($changes) use ($client_page, $client_id) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    if ($client_page) {
        $params['client_id'] = $client_id;
    }
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$date_url = function ($from, $to, $changes = []) use ($filter_url) {
    return $filter_url(array_merge(['canned_date' => 'custom', 'dtf' => $from, 'dtt' => $to], $changes));
};
$any_filter = $q || $driver_filter || ($client_filter && !$client_page) || $location_filter || $ticket_filter || $expense_filter || $invoice_filter || $show !== '' || $dtf !== '1970-01-01';
$add_url = 'modals/trip/trip_add.php' . ($client_page ? "?client_id=$client_id" : '');

?>

<!-- The driving -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['month_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['month_start'], date('Y-m-t'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-calendar-alt me-1"></i>This month</div>
            <div class="itflow-billing-stat-value"><?= escapeHtml(tripMilesText($stats['month_miles'])) ?></div>
            <div class="itflow-billing-stat-note">
                <?= intval($stats['month_trips']) ?> trip<?= intval($stats['month_trips']) === 1 ? '' : 's' ?>
                <?php if ($stats['change'] !== null) { ?>
                    &middot; <span class="<?= $stats['change'] > 0 ? 'text-warning-emphasis' : 'text-success-emphasis' ?>"><i class="fas fa-arrow-<?= $stats['change'] > 0 ? 'up' : 'down' ?> me-1"></i><?= abs($stats['change']) ?>%</span> on the same days of <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['year_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['year_start'], date('Y-12-31'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-chart-line me-1"></i><?= date('Y') ?> so far</div>
            <div class="itflow-billing-stat-value"><?= escapeHtml(tripMilesText($stats['year_miles'])) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($stats['year_trips']) ?> trip<?= intval($stats['year_trips']) === 1 ? '' : 's' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $show === 'mine' && $dtf === $stats['month_start'] ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['month_start'], date('Y-m-t'), ['show' => 'mine'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-user me-1"></i>Yours this month</div>
            <div class="itflow-billing-stat-value"><?= escapeHtml(tripMilesText($stats['my_month_miles'])) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($stats['my_month_trips']) ?> trip<?= intval($stats['my_month_trips']) === 1 ? '' : 's' ?> you drove</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php if ($pay_back) { ?>
            <a class="itflow-billing-stat <?= $show === 'to_pay' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'to_pay', 'canned_date' => '', 'dtf' => '', 'dtt' => ''])) ?>">
                <div class="itflow-billing-stat-label"><i class="fas fa-hand-holding-usd me-1"></i><?= $sees_all ? 'To pay back' : 'Owed to you' ?></div>
                <div class="itflow-billing-stat-value <?= $owed_total['count'] ? 'text-warning-emphasis' : '' ?>"><?= $stat_money($owed_total['value']) ?></div>
                <div class="itflow-billing-stat-note">
                    <?php if ($owed_total['count']) { ?>
                        <?= escapeHtml(tripMilesText($owed_total['miles'])) ?> in their own vehicle<?= $sees_all ? ' &middot; ' . $owed_total['agents'] . ' agent' . ($owed_total['agents'] === 1 ? '' : 's') : '' ?>
                    <?php } else { ?>
                        Nothing waiting &middot; <?= escapeHtml(tripRateText($rules['rate'])) ?>
                    <?php } ?>
                </div>
            </a>
        <?php } else { ?>
            <a class="itflow-billing-stat <?= $show === 'client' && $dtf === $stats['year_start'] ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['year_start'], date('Y-12-31'), ['show' => 'client'])) ?>">
                <div class="itflow-billing-stat-label"><i class="fas fa-building me-1"></i>For clients this year</div>
                <div class="itflow-billing-stat-value"><?= escapeHtml(tripMilesText($stats['year_client_miles'])) ?></div>
                <div class="itflow-billing-stat-note"><?= $stats['year_miles'] > 0 ? round($stats['year_client_miles'] / $stats['year_miles'] * 100) . '% of the driving' : 'No driving yet this year' ?></div>
            </a>
        <?php } ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-route me-2"></i>Trips<?= $sees_all ? '' : ' <span class="fw-normal text-white-50 small ms-1">yours</span>' ?></h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus me-2"></i>New Trip</button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/trip/trip_export.php', ['client_id', 'client', 'driver', 'location_id', 'ticket_id', 'show', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <?php if ($pay_back && $can_manage) { ?>
                        <a class="dropdown-item" href="<?= escapeHtml($filter_url(['show' => 'to_pay'])) ?>"><i class="fa fa-fw fa-hand-holding-usd text-secondary me-2"></i>Pay Mileage</a>
                    <?php } ?>
                    <?php if (lookupUserPermission('module_reporting') >= 1 && lookupUserPermission('module_financial') >= 1) { ?>
                        <a class="dropdown-item" href="reports/mileage.php"><i class="fa fa-fw fa-chart-bar text-secondary me-2"></i>Mileage Report</a>
                    <?php } ?>
                    <?php if ($session_is_admin) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/admin/settings_invoice.php#mileage"><i class="fa fa-fw fa-cog text-secondary me-2"></i>Mileage Rates</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <?php if ($client_page) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <?php foreach (['location_id' => $location_filter, 'ticket_id' => $ticket_filter, 'expense_id' => $expense_filter, 'invoice_id' => $invoice_filter] as $key => $value) {
                if ($value) { ?>
                    <input type="hidden" name="<?= $key ?>" value="<?= $value ?>">
                <?php }
            } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search trips" title="Purpose, from, to, client, site, driver, ticket">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Date of the trip" aria-label="Date of the trip">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-<?= $client_page ? 6 : 3 ?> col-md-6">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <select class="form-select select2" name="driver" onchange="this.form.submit()" aria-label="Driver">
                                <option value="">- Any driver -</option>
                                <?php
                                $sql_filter = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE EXISTS (SELECT 1 FROM trips WHERE trip_user_id = user_id) ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_filter)) {
                                    $option_id = intval($row['user_id']); ?>
                                    <option <?= $driver_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if ($client_page && $can_manage) { ?>
                            <?php require __DIR__ . '/includes/trip_bulk_menu.php'; ?>
                        <?php } ?>
                    </div>
                </div>
                <?php if (!$client_page) { ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="d-flex gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                                    <option value="">- Any client -</option>
                                    <?php
                                    $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                        WHERE EXISTS (SELECT 1 FROM trips WHERE trip_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_filter)) {
                                        $option_id = intval($row['client_id']); ?>
                                        <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php if ($can_manage) { ?>
                                <?php require __DIR__ . '/includes/trip_bulk_menu.php'; ?>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </form>
        <!-- Cut the list - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $key === 'to_pay' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if ($location_filter || $ticket_filter || $expense_filter || $invoice_filter) {
            if ($location_filter) {
                $filter_text = 'To the site ' . escapeHtml((string) getFieldById('locations', $location_filter, 'location_name'));
            } elseif ($ticket_filter) {
                $filter_text = 'For the ticket ' . escapeHtml((string) getFieldById('tickets', $ticket_filter, 'ticket_subject'));
            } elseif ($expense_filter) {
                $filter_text = 'Paid back for on the <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=' . $expense_filter . '">mileage expense</a>';
            } else {
                $invoice_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number FROM invoices WHERE invoice_id = $invoice_filter"));
                $filter_text = 'Billed on <a href="invoice.php?invoice_id=' . $invoice_filter . '">' . escapeHtml($invoice_row ? $invoice_row['invoice_prefix'] . $invoice_row['invoice_number'] : 'an invoice since deleted') . '</a>';
            } ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i><?= $filter_text ?>
                <a class="ms-2" href="<?= escapeHtml($filter_url(['location_id' => '', 'ticket_id' => '', 'expense_id' => '', 'invoice_id' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
    </div>

    <?php if ($owed) { ?>
        <!-- Who is owed for driving their own vehicle, to pay -->
        <div class="list-group list-group-flush border-bottom">
            <?php foreach ($owed as $owed_id => $owed_driver) { ?>
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2 bg-warning-subtle">
                    <i class="fas fa-car-side text-warning-emphasis"></i>
                    <span class="flex-grow-1"><strong><?= escapeHtml($owed_driver['user_name']) ?></strong> is owed <strong class="itflow-money"><?= $stat_money($owed_driver['value']) ?></strong>
                        <span class="text-body-secondary">for <?= $owed_driver['count'] ?> trip<?= $owed_driver['count'] === 1 ? '' : 's' ?>, <?= escapeHtml(tripMilesText($owed_driver['miles'])) ?> at <?= escapeHtml(tripRateText($rules['rate'])) ?><?= $owed_driver['oldest'] > 1 ? ', the oldest ' . $owed_driver['oldest'] . ' days ago' : '' ?></span></span>
                    <?php if ($can_manage) { ?>
                        <button type="button" class="btn btn-sm btn-success ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_claim.php?user_id=<?= $owed_id ?>"><i class="fas fa-hand-holding-usd me-1"></i>Pay Mileage</button>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <?php if ($can_manage) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=trip_date&order=<?= $disp ?>">Date <?php if ($sort == 'trip_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=trip_destination&order=<?= $disp ?>">Trip <?php if ($sort == 'trip_destination') { echo $order_icon; } ?></a></th>
                <?php if (!$client_page) { ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <?php } else { ?>
                    <th>Site</th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=user_name&order=<?= $disp ?>">Driver <?php if ($sort == 'user_name') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=trip_miles&order=<?= $disp ?>"><?= ucfirst(tripUnit()) ?> <?php if ($sort == 'trip_miles') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($trips as $row) {
                $trip_id = intval($row['trip_id']);
                $row_open = 'data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=' . $trip_id . '"';
                ?>
                <tr>
                    <?php if ($can_manage) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="trip_ids[]" value="<?= $trip_id ?>" aria-label="Select this trip">
                            </div>
                        </td>
                    <?php } ?>
                    <td class="text-nowrap"><a class="fw-semibold ajax-modal" href="#" <?= $row_open ?>><?= escapeHtml($row['trip_date']) ?></a></td>
                    <td class="itflow-trip-route">
                        <div class="text-truncate" title="<?= escapeHtml($row['trip_source'] . ' → ' . $row['trip_destination']) ?>">
                            <span class="text-body-secondary"><?= escapeHtml($row['trip_source']) ?></span>
                            <i class="fas fa-long-arrow-alt-right text-body-tertiary mx-1"></i><?= escapeHtml($row['trip_destination']) ?>
                        </div>
                        <div class="small text-body-secondary text-truncate" title="<?= escapeHtml($row['trip_purpose']) ?>">
                            <?php if (intval($row['trip_ticket_id']) && $row['ticket_number'] !== null) { ?>
                                <a class="font-monospace" href="<?= escapeHtml(ticketUrl($row['trip_ticket_id'], $row['trip_client_id'])) ?>" title="<?= escapeHtml($row['ticket_subject']) ?>"><?= escapeHtml(ticketReference($row)) ?></a> &middot;
                            <?php } ?>
                            <?= escapeHtml($row['trip_purpose']) ?>
                        </div>
                    </td>
                    <?php if (!$client_page) { ?>
                        <td>
                            <?php if (intval($row['trip_client_id'])) { ?>
                                <div class="text-truncate" style="max-width: 12rem;"><a class="text-reset" href="<?= escapeHtml($filter_url(['client' => intval($row['trip_client_id'])])) ?>" title="<?= escapeHtml((string) $row['client_name']) ?> - their trips"><?= escapeHtml($row['client_name']) ?></a></div>
                                <?php if ($row['location_name'] !== null) { ?><div class="small text-body-secondary text-truncate" style="max-width: 12rem;"><i class="fas fa-map-marker-alt me-1"></i><?= escapeHtml($row['location_name']) ?></div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary small">Our own</span>
                            <?php } ?>
                        </td>
                    <?php } else { ?>
                        <td class="small"><?= $row['location_name'] !== null ? '<a class="text-reset" href="' . escapeHtml($filter_url(['location_id' => intval($row['trip_location_id'])])) . '"><i class="fas fa-map-marker-alt text-body-secondary me-1"></i>' . escapeHtml($row['location_name']) . '</a>' : '<span class="text-body-secondary">-</span>' ?></td>
                    <?php } ?>
                    <td class="text-nowrap"><?= formatAssignee($row['trip_user_id'], $row['driver_name'] ?? '', $row['driver_avatar'] ?? '', false, 'No driver') ?></td>
                    <td class="text-end text-nowrap">
                        <span class="fw-semibold"><?= number_format(floatval($row['trip_miles']), 1) ?></span>
                        <?php if (intval($row['round_trip'])) { ?><i class="fas fa-sync-alt text-body-tertiary small ms-1" title="Round trip - <?= escapeHtml(tripMilesText(tripOneWay($row))) ?> each way"></i><?php } ?>
                        <?php
                        // The money, as marks - their own vehicle and paid back, billable and billed
                        $marks = [];
                        if (intval($row['trip_own_vehicle'])) {
                            $marks[] = intval($row['trip_expense_id'])
                                ? '<i class="fas fa-car-side text-success-emphasis" title="Their own vehicle - paid back for"></i>'
                                : '<i class="fas fa-car-side text-warning-emphasis" title="Their own vehicle - to pay back' . ($pay_back ? ' ' . escapeHtml(billingMoney(round(floatval($row['trip_miles']) * $rules['rate'], 2))) : '') . '"></i>';
                        }
                        if ($row['invoice_number'] !== null) {
                            $marks[] = '<a href="invoice.php?invoice_id=' . intval($row['trip_invoice_id']) . '" class="text-success-emphasis" title="Billed on ' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '"><i class="fas fa-file-invoice-dollar"></i></a>';
                        } elseif (intval($row['trip_billable']) && intval($row['trip_client_id'])) {
                            $marks[] = '<i class="fas fa-file-invoice-dollar text-info-emphasis" title="To bill ' . escapeHtml(billingMoney(tripBillPrice($row))) . '"></i>';
                        }
                        if ($marks) { ?>
                            <div class="small d-flex justify-content-end gap-2 mt-1"><?= implode('', $marks) ?></div>
                        <?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" <?= $row_open ?>><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <a class="dropdown-item" href="<?= escapeHtml(tripMapUrl($row)) ?>" target="_blank" rel="noopener"><i class="fas fa-fw fa-map-marked-alt text-secondary me-2"></i>Directions<i class="fas fa-external-link-alt small text-secondary ms-2"></i></a>
                                <div class="dropdown-divider"></div>
                                <?php if (tripCanEdit($row)) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_edit.php?id=<?= $trip_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_copy.php?id=<?= $trip_id ?>"><i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy</a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_copy.php?id=<?= $trip_id ?>&amp;return=1"><i class="fas fa-fw fa-undo-alt text-secondary me-2"></i>Return Trip</a>
                                <?php if ($pay_back && $can_manage && intval($row['trip_own_vehicle']) && !intval($row['trip_expense_id'])) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_claim.php?trip_ids[]=<?= $trip_id ?>"><i class="fas fa-fw fa-hand-holding-usd text-secondary me-2"></i>Pay Mileage</a>
                                <?php } ?>
                                <?php if (tripCanDelete($row) && !intval($row['trip_expense_id']) && $row['invoice_number'] === null) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_trip=<?= $trip_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= escapeHtml(tripLabel($row)) ?> goes for good.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
            <?php if ($num_rows[0]) { ?>
                <tfoot>
                <tr class="table-light">
                    <td colspan="<?= $can_manage ? 5 : 4 ?>" class="text-body-secondary small">
                        <?= intval($num_rows[0]) ?> trip<?= intval($num_rows[0]) === 1 ? '' : 's' ?><?= $any_filter ? ' match' . (intval($num_rows[0]) === 1 ? 'es' : '') : '' ?><?= $num_rows[0] > count($trips) ? ' - the total is for all of them, not just this page' : '' ?>
                    </td>
                    <td class="text-end fw-semibold text-nowrap"><?= escapeHtml(tripMilesText($listed_miles)) ?></td>
                    <td></td>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'trips',
        'icon' => 'fa-route',
        'filtered' => $q || $driver_filter || ($client_filter && !$client_page) || $location_filter || $ticket_filter || $expense_filter || $invoice_filter || $dtf !== '1970-01-01',
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['to_pay', 'to_bill']),
        'text' => 'Log the drive out to a client from their ticket - Log a trip, in its menu - or with New Trip here.',
        'actions' => [['label' => 'New Trip', 'modal' => $add_url, 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from elsewhere - straight to that trip's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/trip/trip.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
