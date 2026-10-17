<?php

/*
 * ITFlow - trips: the driving an MSP does, for clients and for itself.
 *
 * A trip is a date, where from and where to, the miles (stored as the whole
 * trip - one way doubled for a round trip), what it was for and who drove.
 * For a client it can name the client's site it went to (trip_location_id)
 * and the ticket it was for (trip_ticket_id), so a ticket and a site show
 * their trips. Odometer readings are optional; when both are given they
 * make the miles.
 *
 * Who sees what: agents with Financial access see every trip in their
 * client scope; anyone else sees the trips they drove or logged. Anyone can
 * log a trip for themselves and change or delete their own; logging one for
 * someone else, or changing theirs, needs Financial write access, and
 * deleting theirs full access.
 *
 * A trip is money two ways (Mileage money, below): driven in the agent's own
 * vehicle it is paid back at a rate for the distance, and for a client it
 * can be billed to them. The distance is in miles or kilometres, as set in
 * Admin > Settings > Invoice - trip_miles holds whichever it is.
 */

/** The distance a trip is measured in - "miles" ("mi" short) or "kilometers" ("km"). */
function tripUnit($short = false) {
    if (tripRules()['unit'] === 'km') {
        return $short ? 'km' : 'kilometers';
    }
    return $short ? 'mi' : 'miles';
}

/** One of them, for a rate - "mile" or "km". */
function tripUnitOne() {
    return tripRules()['unit'] === 'km' ? 'km' : 'mile';
}

/** "12.4 mi" */
function tripMilesText($miles) {
    return number_format(floatval($miles), 1) . ' ' . tripUnit(true);
}

/** May this agent see every trip (in their client scope), not just their own? */
function tripCanSeeAll() {
    return lookupUserPermission('module_financial') >= 1;
}

/** May this agent log trips for others and change theirs? */
function tripCanManage() {
    return lookupUserPermission('module_financial') >= 2;
}

/** Is it this agent's own trip - they drove it or logged it? */
function tripIsMine(array $row) {
    global $session_user_id;
    return intval($row['trip_user_id']) === intval($session_user_id) || intval($row['trip_created_by'] ?? 0) === intval($session_user_id);
}

/** May this agent change this trip? */
function tripCanEdit(array $row) {
    return tripIsMine($row) || tripCanManage();
}

/** May this agent delete this trip? */
function tripCanDelete(array $row) {
    return tripIsMine($row) || lookupUserPermission('module_financial') >= 3;
}

/** The trips this agent may see, as SQL - their client scope, and their own unless they see all. */
function tripScopeSql() {
    global $session_user_id;
    $sql = clientScopeSql('trips.trip_client_id');
    if (!tripCanSeeAll()) {
        $me = intval($session_user_id);
        $sql .= " AND (trips.trip_user_id = $me OR trips.trip_created_by = $me)";
    }
    return $sql;
}

/** The joins the list, export and quick look read through. */
function tripJoinsSql() {
    return "LEFT JOIN clients ON client_id = trips.trip_client_id
        LEFT JOIN users AS driver ON driver.user_id = trips.trip_user_id
        LEFT JOIN locations ON location_id = trips.trip_location_id
        LEFT JOIN tickets ON ticket_id = trips.trip_ticket_id";
}

/** One trip with its client, driver, site, ticket and who logged it - raw, or null. Scoped to what this agent may see. */
function tripRow($trip_id, $scoped = true) {
    global $mysqli;
    $trip_id = intval($trip_id);
    if (!$trip_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT trips.*, client_name, client_abbreviation, driver.user_name AS driver_name, driver.user_avatar AS driver_avatar,
            creator.user_name AS created_by_name, location_name, location_address, location_city, location_state, location_zip, location_archived_at,
            ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at, ticket_onsite, ticket_billable,
            claim.expense_id AS claim_expense_id, claim.expense_date AS claim_date, claim.expense_amount AS claim_amount, claim.expense_currency_code AS claim_currency,
            reimbursement_date, invoice_prefix, invoice_number, invoice_status, item_subtotal AS billed_subtotal
        FROM trips " . tripJoinsSql() . "
        LEFT JOIN users AS creator ON creator.user_id = trips.trip_created_by
        LEFT JOIN expenses AS claim ON trips.trip_expense_id > 0 AND claim.expense_id = trips.trip_expense_id
        LEFT JOIN reimbursements ON reimbursement_id = claim.expense_reimbursement_id
        LEFT JOIN invoices ON trips.trip_invoice_id > 0 AND invoice_id = trips.trip_invoice_id
        LEFT JOIN invoice_items ON trips.trip_invoice_item_id > 0 AND item_id = trips.trip_invoice_item_id
        WHERE trips.trip_id = $trip_id AND trips.trip_archived_at IS NULL " . ($scoped ? tripScopeSql() : '') . "
        LIMIT 1"));
    return $row ?: null;
}

/** The miles one way - the whole trip halved for a round trip. */
function tripOneWay(array $row) {
    $miles = floatval($row['trip_miles']);
    return intval($row['round_trip']) ? round($miles / 2, 1) : $miles;
}

/** A trip in a sentence - "Office to Ravenwood Dental on 2026-10-14 (24.6 mi)". Raw. */
function tripLabel(array $row) {
    return $row['trip_source'] . ' to ' . $row['trip_destination'] . ' on ' . $row['trip_date'] . ' (' . tripMilesText($row['trip_miles']) . ')';
}

/** A directions link for the trip - to the address, from where it started. */
function tripMapUrl(array $row) {
    return 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode((string) $row['trip_source']) . '&destination=' . rawurlencode((string) $row['trip_destination']);
}

/** Where we start from - the company's address, as one line, or ''. */
function tripOfficeAddress() {
    global $mysqli;
    static $address = null;
    if ($address === null) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_address, company_city, company_state, company_zip FROM companies WHERE company_id = 1")) ?: [];
        $address = formatAddress($row['company_address'] ?? '', $row['company_city'] ?? '', $row['company_state'] ?? '', $row['company_zip'] ?? '', '', ' ');
    }
    return $address;
}

/** A client site's address as one line - its name when it has no address. */
function tripLocationAddress(array $row) {
    $address = formatAddress($row['location_address'] ?? '', $row['location_city'] ?? '', $row['location_state'] ?? '', $row['location_zip'] ?? '', '', ' ');
    return $address !== '' ? $address : (string) ($row['location_name'] ?? '');
}

/** The ways the list can be cut - ?show= */
function tripShows() {
    global $session_user_id;
    $me = intval($session_user_id);
    return [
        '' => ['label' => 'All', 'where' => ''],
        'mine' => ['label' => 'Mine', 'icon' => 'fa-user', 'where' => "trips.trip_user_id = $me", 'title' => 'Trips you drove'],
        'client' => ['label' => 'For a client', 'icon' => 'fa-building', 'where' => 'trips.trip_client_id > 0', 'title' => 'Driving for a client'],
        'ticket' => ['label' => 'For a ticket', 'icon' => 'fa-life-ring', 'where' => 'trips.trip_ticket_id > 0', 'title' => 'Onsite work on a ticket'],
        'own' => ['label' => 'Our own', 'icon' => 'fa-store', 'where' => 'trips.trip_client_id = 0', 'title' => "Errands for the business - the bank, the post office, a supplier"],
    ] + (tripPayBackOn() ? [
        'to_pay' => ['label' => 'To pay back', 'icon' => 'fa-hand-holding-usd', 'where' => tripClaimSql(), 'title' => 'In their own vehicle, not paid back for yet'],
    ] : []) + (tripBillingOn() ? [
        'to_bill' => ['label' => 'To bill', 'icon' => 'fa-file-invoice-dollar', 'where' => tripUnbilledSql(), 'title' => 'Billable to the client, on no invoice yet'],
    ] : []);
}

/**
 * The WHERE for a set of filters, as the list and the export take them: q,
 * driver, client, location_id, ticket_id, show, dtf and dtt. Starts with what
 * this agent may see. $summary gets what each is, by name, for the export.
 */
function tripFiltersSql(array $src, &$summary = []) {
    $summary = [];
    $sql = "trips.trip_archived_at IS NULL " . tripScopeSql();
    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql($q);
        $sql .= " AND (trips.trip_purpose LIKE '%$like%' OR trips.trip_source LIKE '%$like%' OR trips.trip_destination LIKE '%$like%' OR client_name LIKE '%$like%'
            OR driver.user_name LIKE '%$like%' OR location_name LIKE '%$like%' OR CONCAT(ticket_prefix, ticket_number) LIKE '%$like%')";
        $summary['Search'] = $q;
    }
    foreach (['driver' => ['trips.trip_user_id', 'users', 'user_name', 'Driver'], 'client' => ['trips.trip_client_id', 'clients', 'client_name', 'Client'],
        'location_id' => ['trips.trip_location_id', 'locations', 'location_name', 'Site'], 'ticket_id' => ['trips.trip_ticket_id', 'tickets', 'ticket_subject', 'Ticket'],
        'expense_id' => ['trips.trip_expense_id', 'expenses', 'expense_date', 'Mileage paid on'], 'invoice_id' => ['trips.trip_invoice_id', 'invoices', 'invoice_number', 'Billed on']] as $key => $f) {
        $id = intval($src[$key] ?? 0);
        if ($id) {
            $sql .= " AND {$f[0]} = $id";
            $summary[$f[3]] = (string) getFieldById($f[1], $id, $f[2]);
        }
    }
    $shows = tripShows();
    $show = (string) ($src['show'] ?? '');
    if (isset($shows[$show]) && $shows[$show]['where']) {
        $sql .= " AND {$shows[$show]['where']}";
        $summary['Showing'] = $shows[$show]['label'];
    }
    $dtf = (string) ($src['dtf'] ?? '');
    $dtt = (string) ($src['dtt'] ?? '');
    $dtf = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtf) ? $dtf : '1970-01-01';
    $dtt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND trips.trip_date BETWEEN '$dtf' AND '$dtt'";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/**
 * The driving along the top of the list, in what this agent may see: miles
 * this month against the same days of last month, the year so far, and the
 * agent's own this month.
 */
function tripStats() {
    global $mysqli, $session_user_id;
    $scope = "trips.trip_archived_at IS NULL " . tripScopeSql();
    $month_start = date('Y-m-01');
    $today = date('Y-m-d');
    $last_start = date('Y-m-01', strtotime('first day of last month'));
    $last_to_date = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($last_start))), strtotime($last_start . ' +' . (intval(date('j')) - 1) . ' days')));
    $year_start = date('Y-01-01');
    $me = intval($session_user_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(CASE WHEN trip_date BETWEEN '$month_start' AND '$today' THEN trip_miles END), 0) AS month_miles,
            COALESCE(SUM(trip_date BETWEEN '$month_start' AND '$today'), 0) AS month_trips,
            COALESCE(SUM(CASE WHEN trip_date BETWEEN '$last_start' AND '$last_to_date' THEN trip_miles END), 0) AS last_to_date,
            COALESCE(SUM(CASE WHEN trip_date BETWEEN '$year_start' AND '$today' THEN trip_miles END), 0) AS year_miles,
            COALESCE(SUM(trip_date BETWEEN '$year_start' AND '$today'), 0) AS year_trips,
            COALESCE(SUM(CASE WHEN trip_date BETWEEN '$year_start' AND '$today' AND trip_client_id > 0 THEN trip_miles END), 0) AS year_client_miles,
            COALESCE(SUM(CASE WHEN trip_date BETWEEN '$month_start' AND '$today' AND trip_user_id = $me THEN trip_miles END), 0) AS my_month_miles,
            COALESCE(SUM(trip_date BETWEEN '$month_start' AND '$today' AND trip_user_id = $me), 0) AS my_month_trips
        FROM trips LEFT JOIN clients ON client_id = trip_client_id WHERE $scope"));
    $row = array_map('floatval', $row ?: []);
    $row['month_start'] = $month_start;
    $row['last_start'] = $last_start;
    $row['year_start'] = $year_start;
    $row['change'] = $row['last_to_date'] > 0 ? round(($row['month_miles'] - $row['last_to_date']) / $row['last_to_date'] * 100) : null;
    return $row;
}

/** Who can drive - active agents, and $keep_id - id => [name, avatar]. */
function tripDrivers($keep_id = 0) {
    global $mysqli;
    $keep_id = intval($keep_id);
    $drivers = [];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE (user_type = 1 AND user_archived_at IS NULL) OR user_id = $keep_id ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $drivers[intval($row['user_id'])] = $row;
    }
    return $drivers;
}

/** The sites of the clients this agent may reach - client_id => [[id, name, address], ...], archived ones left out but $keep_id. */
function tripClientSites($client_id = 0, $keep_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $keep_id = intval($keep_id);
    $sites = [];
    $sql = mysqli_query($mysqli, "SELECT location_id, location_client_id, location_name, location_address, location_city, location_state, location_zip, location_primary
        FROM locations JOIN clients ON client_id = location_client_id
        WHERE (location_archived_at IS NULL OR location_id = $keep_id) AND client_archived_at IS NULL " . ($client_id ? "AND location_client_id = $client_id " : '') . clientScopeSql('location_client_id') . "
        ORDER BY location_primary DESC, location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $sites[intval($row['location_client_id'])][] = [intval($row['location_id']), $row['location_name'], tripLocationAddress($row)];
    }
    return $sites;
}

/**
 * The tickets a trip can be for - each client's open ones and those done in
 * the last 60 days, newest first, up to 25 a client, plus $keep_id - client_id
 * => [[id, "TCK-12 - Subject", location_id, open, billable], ...]. Only for
 * agents who can see tickets.
 */
function tripClientTickets($client_id = 0, $keep_id = 0) {
    global $mysqli;
    if (lookupUserPermission('module_support') < 1) {
        return [];
    }
    $client_id = intval($client_id);
    $keep_id = intval($keep_id);
    $tickets = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_location_id, ticket_resolved_at, ticket_closed_at, ticket_billable
        FROM tickets
        WHERE ticket_client_id > 0 AND ticket_archived_at IS NULL " . ($client_id ? "AND ticket_client_id = $client_id " : '') . "
            AND (" . TICKET_OPEN_SQL . " OR COALESCE(ticket_resolved_at, ticket_closed_at) >= CURDATE() - INTERVAL 60 DAY OR ticket_id = $keep_id) " . clientScopeSql('ticket_client_id') . "
        ORDER BY ticket_client_id, (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, ticket_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $cid = intval($row['ticket_client_id']);
        if (count($tickets[$cid] ?? []) >= 25 && intval($row['ticket_id']) !== $keep_id) {
            continue;
        }
        $tickets[$cid][] = [intval($row['ticket_id']), ticketReference($row) . ' - ' . mb_substr((string) $row['ticket_subject'], 0, 80), intval($row['ticket_location_id']), ticketIsOpen($row) ? 1 : 0, intval($row['ticket_billable'])];
    }
    return $tickets;
}

/**
 * Routes driven before - "from|to" (lowercase) => miles one way, the last
 * time each was driven, from the trips this agent may see. Both directions,
 * so the way back is known too. For the form to fill in.
 */
function tripKnownRoutes($limit = 400) {
    global $mysqli;
    $routes = [];
    $sql = mysqli_query($mysqli, "SELECT trip_source, trip_destination, trip_miles, round_trip FROM trips LEFT JOIN clients ON client_id = trip_client_id
        WHERE trips.trip_archived_at IS NULL AND trip_miles > 0 " . tripScopeSql() . "
        ORDER BY trip_date DESC, trip_id DESC LIMIT " . intval($limit * 3));
    while ($row = mysqli_fetch_assoc($sql)) {
        $from = mb_strtolower(trim((string) $row['trip_source']));
        $to = mb_strtolower(trim((string) $row['trip_destination']));
        if ($from === '' || $to === '') {
            continue;
        }
        $one_way = tripOneWay($row);
        $routes += ["$from|$to" => $one_way, "$to|$from" => $one_way];
        if (count($routes) >= $limit * 2) {
            break;
        }
    }
    return $routes;
}

/** Where this agent has started from lately, most used first - for the From box to offer, with the office first. */
function tripStartPlaces($limit = 6) {
    global $mysqli, $session_user_id;
    $places = [];
    $office = tripOfficeAddress();
    if ($office !== '') {
        $places[] = $office;
    }
    $sql = mysqli_query($mysqli, "SELECT trip_source, COUNT(*) AS n FROM trips WHERE trip_user_id = " . intval($session_user_id) . " AND trip_archived_at IS NULL
        GROUP BY trip_source ORDER BY n DESC LIMIT " . intval($limit));
    while ($row = mysqli_fetch_row($sql)) {
        if (!in_array($row[0], $places, true) && trim((string) $row[0]) !== '') {
            $places[] = $row[0];
        }
    }
    return $places;
}

/** Where this agent's last trip started - for a new one to start from. */
function tripLastStart() {
    global $mysqli, $session_user_id;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT trip_source FROM trips WHERE trip_user_id = " . intval($session_user_id) . " AND trip_archived_at IS NULL ORDER BY trip_date DESC, trip_id DESC LIMIT 1"));
    return $row ? (string) $row[0] : '';
}

/**
 * The one path that writes a trip from a form - New, Edit, Copy and Return.
 * $in: date, source, destination, miles (one way), round_trip, odometer_start,
 * odometer_end, purpose, driver, client_id, location_id, ticket_id,
 * own_vehicle and billable. A ticket brings its client when none is picked;
 * a site and ticket must be the client's. Once paid back for (on a mileage
 * expense) the distance, driver and vehicle stay as they were paid; once
 * billed, the distance, client and billable stay as they were billed - the
 * form shows them locked. Returns the trip id, or 0 with $error.
 */
function tripSave(array $in, $trip_id = 0, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    $trip_id = intval($trip_id);
    $existing = $trip_id ? tripRow($trip_id) : null;
    if ($trip_id && !$existing) {
        $error = 'That trip no longer exists, or is not one you can see';
        return 0;
    }
    if ($existing && !tripCanEdit($existing)) {
        $error = "That is someone else's trip - changing it needs write access to Financial";
        return 0;
    }

    $date = trim((string) ($in['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date of the trip';
        return 0;
    }
    $source = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in['source'] ?? ''))), 0, 200);
    $destination = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in['destination'] ?? ''))), 0, 200);
    if ($source === '' || $destination === '') {
        $error = 'Enter where the trip started and where it went';
        return 0;
    }
    $purpose = mb_substr(trim((string) ($in['purpose'] ?? '')), 0, 200);
    if ($purpose === '') {
        $error = 'Enter what the trip was for';
        return 0;
    }

    // Paid back for, or billed - those keep what they were paid or billed on
    $claimed = $existing && intval($existing['trip_expense_id']) > 0;
    $billed = $existing && intval($existing['trip_invoice_id']) > 0;

    // The miles: from the odometer when both readings are there, else one way, doubled for a round trip
    $round = !empty($in['round_trip']) ? 1 : 0;
    if ($claimed || $billed) {
        $round = intval($existing['round_trip']);
        $in['odometer_start'] = $existing['trip_start_odometer'] ?? '';
        $in['odometer_end'] = $existing['trip_end_odometer'] ?? '';
    }
    $odo_start_text = trim(str_replace(',', '', (string) ($in['odometer_start'] ?? '')));
    $odo_end_text = trim(str_replace(',', '', (string) ($in['odometer_end'] ?? '')));
    if (($odo_start_text !== '' && !ctype_digit($odo_start_text)) || ($odo_end_text !== '' && !ctype_digit($odo_end_text))) {
        $error = 'The odometer readings are whole numbers';
        return 0;
    }
    $odo_start = $odo_start_text === '' ? null : intval($odo_start_text);
    $odo_end = $odo_end_text === '' ? null : intval($odo_end_text);
    if ($claimed || $billed) {
        $miles = round(floatval($existing['trip_miles']), 1);
    } elseif ($odo_start !== null && $odo_end !== null) {
        if ($odo_end < $odo_start) {
            $error = 'The odometer at the end is lower than at the start';
            return 0;
        }
        $miles = round($odo_end - $odo_start, 1);
    } else {
        $miles_text = trim(str_replace([',', ' '], '', (string) ($in['miles'] ?? '')));
        if (!is_numeric($miles_text) || floatval($miles_text) < 0 || floatval($miles_text) >= 100000) {
            $error = 'Enter the ' . tripUnit() . ' one way';
            return 0;
        }
        $miles = round(round(floatval($miles_text), 1) * ($round ? 2 : 1), 1);
    }

    // The client, the ticket it was for and the site it went to
    $client_id = $billed ? intval($existing['trip_client_id']) : intval($in['client_id'] ?? 0);
    $ticket_id = intval($in['ticket_id'] ?? 0);
    if ($ticket_id) {
        $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id > 0 " . clientScopeSql('ticket_client_id')));
        if (!$ticket || ($client_id && intval($ticket['ticket_client_id']) !== $client_id) || (lookupUserPermission('module_support') < 1 && (!$existing || intval($existing['trip_ticket_id']) !== $ticket_id))) {
            $error = "That ticket isn't one of the client's";
            return 0;
        }
        $client_id = intval($ticket['ticket_client_id']);
    }
    if ($client_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id " . clientScopeSql('client_id')))) {
        $error = "That client isn't one you can log trips for";
        return 0;
    }
    $location_id = $client_id ? intval($in['location_id'] ?? 0) : 0;
    if ($location_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT location_id FROM locations WHERE location_id = $location_id AND location_client_id = $client_id"))) {
        $error = "That site isn't one of the client's";
        return 0;
    }

    // Who drove - only themselves, unless they may log trips for others
    $driver = $claimed ? intval($existing['trip_user_id']) : intval($in['driver'] ?? 0);
    $drivers = tripDrivers($existing ? $existing['trip_user_id'] : 0);
    if (!isset($drivers[$driver])) {
        $error = 'Pick who drove';
        return 0;
    }
    if (!tripCanManage() && $driver !== intval($session_user_id) && !($existing && $driver === intval($existing['trip_user_id']))) {
        $error = 'You can log your own trips - logging one for someone else needs write access to Financial';
        return 0;
    }

    // Their own vehicle - paid back for the distance; billable to the client
    $own_vehicle = $claimed ? 1 : (!empty($in['own_vehicle']) ? 1 : 0);
    $billable = $billed ? 1 : ($client_id && !empty($in['billable']) ? 1 : 0);

    $set = "trip_date = '$date', trip_source = '" . escapeSql($source) . "', trip_destination = '" . escapeSql($destination) . "', trip_miles = $miles, round_trip = $round,
        trip_start_odometer = " . ($odo_start === null ? 'NULL' : $odo_start) . ", trip_end_odometer = " . ($odo_end === null ? 'NULL' : $odo_end) . ",
        trip_purpose = '" . escapeSql($purpose) . "', trip_user_id = $driver, trip_client_id = $client_id, trip_location_id = $location_id, trip_ticket_id = $ticket_id,
        trip_own_vehicle = $own_vehicle, trip_billable = $billable";
    if ($existing) {
        mysqli_query($mysqli, "UPDATE trips SET $set WHERE trip_id = $trip_id");
        return $trip_id;
    }
    mysqli_query($mysqli, "INSERT INTO trips SET $set, trip_created_by = " . intval($session_user_id));
    return mysqli_insert_id($mysqli);
}

/** Delete a trip this agent may delete - the row it deleted, or null with $error. */
function tripDelete($trip_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = tripRow($trip_id);
    if (!$row) {
        $error = 'That trip no longer exists, or is not one you can see';
        return null;
    }
    if (!tripCanDelete($row)) {
        $error = "That is someone else's trip - deleting it needs full access to Financial";
        return null;
    }
    if ($row['claim_expense_id'] !== null) {
        $error = 'That trip is paid back for on a mileage expense - delete that expense first';
        return null;
    }
    if ($row['invoice_number'] !== null) {
        $error = 'That trip is billed on ' . $row['invoice_prefix'] . $row['invoice_number'] . ' - take it off the invoice first';
        return null;
    }
    mysqli_query($mysqli, "DELETE FROM trips WHERE trip_id = " . intval($row['trip_id']));
    return $row;
}

/** The trips of a ticket, a site or a client - newest first, scoped. For the cards that list them. */
function tripsFor($column, $id, $limit = 10) {
    global $mysqli;
    if (!in_array($column, ['trip_ticket_id', 'trip_location_id', 'trip_client_id', 'trip_expense_id', 'trip_invoice_id'], true)) {
        return [];
    }
    $id = intval($id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT trips.*, driver.user_name AS driver_name, driver.user_avatar AS driver_avatar, client_name, location_name, ticket_prefix, ticket_number, ticket_subject
        FROM trips " . tripJoinsSql() . "
        WHERE trips.$column = $id AND trips.trip_archived_at IS NULL " . tripScopeSql() . "
        ORDER BY trip_date DESC, trip_id DESC LIMIT " . intval($limit));
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** How many trips and miles a ticket, site or client has - [count, miles], scoped. */
function tripTotalsFor($column, $id) {
    global $mysqli;
    if (!in_array($column, ['trip_ticket_id', 'trip_location_id', 'trip_client_id', 'trip_expense_id', 'trip_invoice_id'], true)) {
        return [0, 0.0];
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(trip_miles), 0) FROM trips LEFT JOIN clients ON client_id = trip_client_id
        WHERE trips.$column = " . intval($id) . " AND trips.trip_archived_at IS NULL " . tripScopeSql()));
    return [intval($row[0]), floatval($row[1])];
}

/** A small table of trips - from tripsFor() - each opening its quick look. For a site's Trips tab. */
function tripTableHtml(array $rows) {
    $html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 itflow-trip-table">'
        . '<thead class="text-nowrap table-light"><tr><th>Date</th><th>Trip</th><th>Driver</th><th class="text-end">' . ucfirst(tripUnit()) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $open = 'href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=' . intval($row['trip_id']) . '"';
        $ticket = $row['ticket_number'] !== null && intval($row['trip_ticket_id']) ? '<span class="font-monospace text-body-secondary me-1">' . escapeHtml(ticketReference($row)) . '</span>' : '';
        $html .= '<tr>'
            . '<td class="text-nowrap"><a ' . $open . '>' . escapeHtml(date('M j, Y', strtotime($row['trip_date']))) . '</a></td>'
            . '<td class="itflow-trip-route"><div class="text-truncate">' . escapeHtml($row['trip_source']) . ' &rarr; ' . escapeHtml($row['trip_destination']) . '</div>'
            . '<div class="small text-body-secondary text-truncate">' . $ticket . escapeHtml($row['trip_purpose']) . '</div></td>'
            . '<td class="text-nowrap">' . formatAssignee($row['trip_user_id'], $row['driver_name'] ?? '', $row['driver_avatar'] ?? '', false, 'No driver') . '</td>'
            . '<td class="text-end text-nowrap">' . escapeHtml(tripMilesText($row['trip_miles']))
            . (intval($row['round_trip']) ? '<i class="fas fa-sync-alt text-body-secondary ms-1" title="Round trip"></i>' : '') . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/* --------------------------------------------------------- Mileage money */

/*
 * Two ways a trip is money, both set in Admin > Settings > Invoice.
 *
 * Paying back: driven in the agent's own vehicle (trip_own_vehicle), the
 * distance is paid back at the mileage rate. Pay Mileage turns a driver's
 * trips into mileage expenses paid personally by them - one for each client
 * and one for our own errands, so each client's costs include the driving -
 * under the mileage category and vendor. The trips point at it
 * (trip_expense_id). The expenses are then paid back with anything else the
 * agent paid for (functions/expenses.php, Paid personally), there and then
 * or later from Reimburse.
 *
 * Billing: a trip for a client can be billable (trip_billable). It waits in
 * Unbilled Time beside the client's tickets and expenses, priced at the
 * charge for a trip plus the rate for the distance beyond what the charge
 * covers, and goes on an invoice as a line of its own (trip_invoice_id,
 * trip_invoice_item_id) - taken off again the way a billed expense is.
 */

/**
 * The mileage settings - unit (mi or km), rate (paid back a unit in their
 * own vehicle), category_id and vendor_id (of the mileage expenses), and
 * what a trip bills: charge (a trip), bill_rate (a unit), included (the
 * distance the charge covers) and item_name (the invoice line).
 */
function tripRules() {
    global $mysqli;
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_mileage_unit, config_mileage_rate, config_mileage_category_id, config_mileage_vendor_id,
            config_trip_charge, config_trip_bill_rate, config_trip_included, config_trip_item_name FROM settings WHERE company_id = 1")) ?: [];
    } catch (mysqli_sql_exception $e) {
        $row = [];
    }
    $rules = [
        'unit' => ($row['config_mileage_unit'] ?? 'mi') === 'km' ? 'km' : 'mi',
        'rate' => max(0, floatval($row['config_mileage_rate'] ?? 0)),
        'category_id' => intval($row['config_mileage_category_id'] ?? 0),
        'vendor_id' => intval($row['config_mileage_vendor_id'] ?? 0),
        'charge' => max(0, floatval($row['config_trip_charge'] ?? 0)),
        'bill_rate' => max(0, floatval($row['config_trip_bill_rate'] ?? 0)),
        'included' => max(0, floatval($row['config_trip_included'] ?? 0)),
        'item_name' => trim((string) ($row['config_trip_item_name'] ?? '')) ?: 'Travel',
    ];
    return $rules;
}

/** Are agents paid back for their own vehicle - is there a mileage rate? */
function tripPayBackOn() {
    return tripRules()['rate'] > 0;
}

/** Do trips bill clients - is there a charge or a rate? */
function tripBillingOn() {
    $rules = tripRules();
    return $rules['charge'] > 0 || $rules['bill_rate'] > 0;
}

/** A rate typed in - up to four decimals, as 0.655 - or null when it isn't a number of zero or more. */
function tripRateInput($value) {
    $value = trim(str_replace([',', ' ', '$', '€', '£'], '', (string) $value));
    if ($value === '') {
        return 0.0;
    }
    return is_numeric($value) && floatval($value) >= 0 && floatval($value) < 1000 ? round(floatval($value), 4) : null;
}

/** A rate as money - $0.70, or $0.655 when it has more than cents. */
function tripRateMoney($rate) {
    $rate = round(floatval($rate), 4);
    if (abs($rate - round($rate, 2)) < 0.00001) {
        return billingMoney($rate);
    }
    $format = numfmt_create((string) (billingCompany()['company_locale'] ?? '') ?: 'en_US', NumberFormatter::CURRENCY);
    numfmt_set_attribute($format, NumberFormatter::MIN_FRACTION_DIGITS, 2);
    numfmt_set_attribute($format, NumberFormatter::MAX_FRACTION_DIGITS, 4);
    global $session_company_currency;
    return numfmt_format_currency($format, $rate, (string) (($session_company_currency ?? '') ?: (billingCompany()['company_currency'] ?? '') ?: 'USD'));
}

/** "$0.70 a mile" */
function tripRateText($rate) {
    return tripRateMoney($rate) . ' a ' . tripUnitOne();
}

/** A distance without a trailing .0 - "20 mi", "12.5 km". */
function tripDistanceText($distance) {
    return rtrim(rtrim(number_format(floatval($distance), 1, '.', ''), '0'), '.') . ' ' . tripUnit(true);
}

/** What a trip bills its client - the charge, and the rate for the distance beyond what the charge covers. */
function tripBillPrice(array $row) {
    $rules = tripRules();
    return round($rules['charge'] + $rules['bill_rate'] * max(0, floatval($row['trip_miles']) - $rules['included']), 2);
}

/** How a trip is billed, in words - "$45.00 a trip, covering the first 20 mi, then $0.85 a mile". '' when trips don't bill. */
function tripBillRuleText() {
    $rules = tripRules();
    $charge = $rules['charge'] > 0 ? billingMoney($rules['charge']) . ' a trip' : '';
    $rate = $rules['bill_rate'] > 0 ? tripRateText($rules['bill_rate']) : '';
    if ($charge !== '' && $rate !== '') {
        return $rules['included'] > 0 ? "$charge, covering the first " . tripDistanceText($rules['included']) . ", then $rate" : "$charge plus $rate";
    }
    if ($rate !== '') {
        return $rules['included'] > 0 ? "$rate beyond the first " . tripDistanceText($rules['included']) : $rate;
    }
    return $charge;
}

/** The expense category mileage would go under - the one set, else Mileage or Travel - or 0 when there is none yet. */
function tripMileageCategoryShown() {
    global $mysqli;
    $id = tripRules()['category_id'];
    if ($id && mysqli_num_rows(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_id = $id AND category_type = 'Expense' AND category_archived_at IS NULL"))) {
        return $id;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL AND category_name IN ('Mileage', 'Travel')
        ORDER BY category_name = 'Mileage' DESC, category_id ASC LIMIT 1"));
    return $row ? intval($row[0]) : 0;
}

/** The expense category mileage goes under - as shown, else a new Mileage. Kept in the settings. */
function tripMileageCategory() {
    global $mysqli;
    $id = tripMileageCategoryShown();
    if ($id === tripRules()['category_id'] && $id) {
        return $id;
    }
    if (!$id) {
        mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Mileage', category_type = 'Expense', category_color = '#6f42c1'");
        $id = mysqli_insert_id($mysqli);
    }
    mysqli_query($mysqli, "UPDATE settings SET config_mileage_category_id = $id WHERE company_id = 1");
    return $id;
}

/** The vendor mileage is paid to - the one set, else one of ours named Mileage, else a new one. Kept in the settings. */
function tripMileageVendor() {
    global $mysqli;
    $id = tripRules()['vendor_id'];
    if ($id && mysqli_num_rows(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_id = $id AND vendor_client_id = 0"))) {
        return $id;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_client_id = 0 AND vendor_name = 'Mileage' ORDER BY vendor_archived_at IS NULL DESC, vendor_id ASC LIMIT 1"));
    if ($row) {
        $id = intval($row[0]);
    } else {
        mysqli_query($mysqli, "INSERT INTO vendors SET " . vendorFormSet(['name' => 'Mileage', 'description' => 'Agents paid back for driving their own vehicles']) . ", vendor_client_id = 0");
        $id = mysqli_insert_id($mysqli);
    }
    mysqli_query($mysqli, "UPDATE settings SET config_mileage_vendor_id = $id WHERE company_id = 1");
    return $id;
}

/* Paying back */

/** The SQL that makes a trip to pay back for: their own vehicle, a driver and a distance, on no mileage expense yet. */
function tripClaimSql() {
    return 'trips.trip_own_vehicle = 1 AND trips.trip_expense_id = 0 AND trips.trip_miles > 0 AND trips.trip_user_id > 0 AND trips.trip_archived_at IS NULL';
}

/**
 * Trips to pay back for - one driver's ($user_id) or everyone's the agent may
 * see, optionally only $ids - by driver: user_id => [user_id, user_name,
 * trips (id => row), count, miles, value (at the mileage rate), oldest (days)].
 */
function tripsToClaim($user_id = 0, array $ids = []) {
    global $mysqli;
    $user_id = intval($user_id);
    $ids = array_filter(array_map('intval', $ids));
    $rate = tripRules()['rate'];
    $sql = mysqli_query($mysqli, "SELECT trips.*, client_name, location_name, ticket_prefix, ticket_number, ticket_subject, driver.user_name AS driver_name,
            DATEDIFF(CURDATE(), trips.trip_date) AS waiting
        FROM trips " . tripJoinsSql() . "
        WHERE " . tripClaimSql() . ($user_id ? " AND trips.trip_user_id = $user_id" : '') . ($ids ? ' AND trips.trip_id IN (' . implode(',', $ids) . ')' : '') . ' ' . tripScopeSql() . "
        ORDER BY driver.user_name ASC, trips.trip_date ASC, trips.trip_id ASC");
    $drivers = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $uid = intval($row['trip_user_id']);
        if (!isset($drivers[$uid])) {
            $drivers[$uid] = ['user_id' => $uid, 'user_name' => (string) $row['driver_name'], 'trips' => [], 'count' => 0, 'miles' => 0.0, 'value' => 0.0, 'oldest' => 0];
        }
        $drivers[$uid]['trips'][intval($row['trip_id'])] = $row;
        $drivers[$uid]['count']++;
        $drivers[$uid]['miles'] = round($drivers[$uid]['miles'] + floatval($row['trip_miles']), 1);
        $drivers[$uid]['oldest'] = max($drivers[$uid]['oldest'], intval($row['waiting']));
    }
    foreach ($drivers as &$driver) {
        $driver['value'] = round($driver['miles'] * $rate, 2);
    }
    unset($driver);
    return $drivers;
}

/** How many trips are waiting to be paid back for, their distance and drivers, and what that comes to at the rate - for the list and the dashboard. */
function tripToClaimCount() {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(trip_miles), 0), COUNT(DISTINCT trip_user_id)
        FROM trips LEFT JOIN clients ON client_id = trips.trip_client_id WHERE " . tripClaimSql() . ' ' . tripScopeSql()));
    $miles = round(floatval($row[1]), 1);
    return ['count' => intval($row[0]), 'miles' => $miles, 'agents' => intval($row[2]), 'value' => round($miles * tripRules()['rate'], 2)];
}

/**
 * Pay a driver for their ticked trips in their own vehicle: a mileage
 * expense paid personally by them for each client (and one for our own
 * errands), at $opts['rate'] a unit, dated $opts['date']. The trips are
 * claimed as each expense is made, so two agents can't pay one twice.
 * $opts['by'] is who, for the log. Returns [expense_ids, count, miles,
 * amount, rate, user_name, currency], or null with $error.
 */
function tripClaim($user_id, array $trip_ids, array $opts, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $user_id = intval($user_id);
    $driver = $trip_ids ? (tripsToClaim($user_id, $trip_ids)[$user_id] ?? null) : null;
    if (!$user_id || !$driver) {
        $error = 'Nothing to pay - those trips are paid back for already, or not in their own vehicle.';
        return null;
    }
    $rate = tripRateInput($opts['rate'] ?? '');
    if ($rate === null || $rate <= 0) {
        $error = 'Enter the rate a ' . tripUnitOne() . ', more than nothing';
        return null;
    }
    $date = (string) ($opts['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date of the mileage expense';
        return null;
    }
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $currency = (string) (($session_company_currency ?? '') ?: (billingCompany()['company_currency'] ?? '') ?: 'USD');
    $category_id = tripMileageCategory();
    $vendor_id = tripMileageVendor();

    // A mileage expense for each client - so their costs include the driving
    $groups = [];
    foreach ($driver['trips'] as $trip_id => $trip) {
        $groups[intval($trip['trip_client_id'])][] = $trip_id;
    }
    $expense_ids = [];
    $count = 0;
    $miles = 0.0;
    $amount = 0.0;
    foreach ($groups as $client_id => $ids) {
        mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = 0, expense_currency_code = '" . escapeSql($currency) . "',
            expense_vendor_id = $vendor_id, expense_category_id = $category_id, expense_account_id = 0, expense_paid_by = $user_id, expense_client_id = $client_id,
            expense_description = 'Mileage', expense_created_by = " . intval($session_user_id));
        $expense_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli, "UPDATE trips SET trip_expense_id = $expense_id WHERE trip_id IN (" . implode(',', $ids) . ") AND trip_user_id = $user_id AND " . tripClaimSql());
        $got = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(trip_miles), 0), MIN(trip_date), MAX(trip_date) FROM trips WHERE trip_expense_id = $expense_id"));
        if (!intval($got[0])) {
            mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");
            continue;
        }
        $group_count = intval($got[0]);
        $group_miles = round(floatval($got[1]), 1);
        $group_amount = round($group_miles * $rate, 2);
        $dates = $got[2] === $got[3] ? date('M j, Y', strtotime($got[2])) : date('M j', strtotime($got[2])) . ' to ' . date('M j, Y', strtotime($got[3]));
        $description = 'Mileage - ' . ($group_count === 1 ? '1 trip' : "$group_count trips") . ', ' . tripDistanceText($group_miles) . "\n" . 'At ' . tripRateText($rate) . ", $dates";
        mysqli_query($mysqli, "UPDATE expenses SET expense_amount = $group_amount, expense_description = '" . escapeSql($description) . "' WHERE expense_id = $expense_id");
        logAudit('Expense', 'Create', escapeSql("$by paid {$driver['user_name']} mileage for " . ($group_count === 1 ? '1 trip' : "$group_count trips") . ', ' . tripDistanceText($group_miles) . ' at ' . tripRateText($rate)
            . ' - ' . billingMoney($group_amount, $currency) . ' to pay back'), $client_id, $expense_id);
        $expense_ids[] = $expense_id;
        $count += $group_count;
        $miles += $group_miles;
        $amount += $group_amount;
    }
    if (!$expense_ids) {
        $error = 'Nothing to pay - someone else paid for those trips just now.';
        return null;
    }
    return ['expense_ids' => $expense_ids, 'count' => $count, 'miles' => round($miles, 1), 'amount' => round($amount, 2), 'rate' => $rate,
        'user_name' => $driver['user_name'], 'currency' => $currency];
}

/* Billing */

/** The SQL that makes a trip to bill: billable, for a client, on no invoice yet. */
function tripUnbilledSql() {
    return 'trips.trip_billable = 1 AND trips.trip_invoice_id = 0 AND trips.trip_client_id > 0 AND trips.trip_archived_at IS NULL';
}

/**
 * Trips to bill - one client's ($client_id) or every client the agent may
 * see, optionally only $ids - oldest first, each with its client, site,
 * ticket and driver, the price it bills at and the days since it was driven.
 */
function tripUnbilled($client_id = 0, array $ids = []) {
    global $mysqli;
    $client_id = intval($client_id);
    $ids = array_filter(array_map('intval', $ids));
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT trips.*, client_name, client_abbreviation, location_name, ticket_prefix, ticket_number, ticket_subject,
            driver.user_name AS driver_name, DATEDIFF(CURDATE(), trips.trip_date) AS waiting
        FROM trips " . tripJoinsSql() . "
        WHERE " . tripUnbilledSql() . ($client_id ? " AND trips.trip_client_id = $client_id" : '') . ($ids ? ' AND trips.trip_id IN (' . implode(',', $ids) . ')' : '') . ' ' . tripScopeSql() . "
        ORDER BY client_name ASC, trips.trip_date ASC, trips.trip_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['price'] = tripBillPrice($row);
        $row['waiting'] = max(0, intval($row['waiting']));
        $rows[intval($row['trip_id'])] = $row;
    }
    return $rows;
}

/** How many trips are waiting to be billed, and what they come to - every client the agent can see, or one - for the menu, the dashboard and the client's overview. */
function tripUnbilledCount($client_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $rules = tripRules();
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(ROUND({$rules['charge']} + {$rules['bill_rate']} * GREATEST(0, trip_miles - {$rules['included']}), 2)), 0)
        FROM trips LEFT JOIN clients ON client_id = trips.trip_client_id
        WHERE " . tripUnbilledSql() . ($client_id ? " AND trips.trip_client_id = $client_id" : '') . ' ' . tripScopeSql()));
    return ['count' => intval($row[0]), 'value' => round(floatval($row[1]), 2)];
}

/**
 * Unbilled Time's clients (after expenseUnbilledMerge()) with their trips to
 * bill as well: each gets trips, trip_value and trip_miles, a client with
 * only trips waiting is added, and the oldest counts the trips too. Sorted
 * by what it all comes to.
 */
function tripUnbilledMerge(array $clients, $client_id = 0) {
    global $mysqli;
    foreach ($clients as $id => $c) {
        $clients[$id] += ['expenses' => [], 'expense_cost' => 0.0, 'expense_value' => 0.0, 'trips' => [], 'trip_value' => 0.0, 'trip_miles' => 0.0];
    }
    $added = [];
    foreach (tripUnbilled($client_id) as $trip_id => $trip) {
        $id = intval($trip['trip_client_id']);
        if (!isset($clients[$id])) {
            $client_rate = getFieldById('clients', $id, 'client_rate');
            $clients[$id] = ['client_id' => $id, 'client_name' => $trip['client_name'], 'client_abbreviation' => $trip['client_abbreviation'],
                'rate' => billingTimeRate($client_rate), 'default_rate' => floatval($client_rate) <= 0, 'tickets' => [], 'seconds' => 0, 'hours' => 0.0,
                'value' => 0.0, 'oldest' => 0, 'no_time' => 0, 'drafts' => [], 'expenses' => [], 'expense_cost' => 0.0, 'expense_value' => 0.0,
                'trips' => [], 'trip_value' => 0.0, 'trip_miles' => 0.0];
            $added[] = $id;
        }
        $clients[$id]['trips'][$trip_id] = $trip;
        $clients[$id]['trip_value'] = round($clients[$id]['trip_value'] + $trip['price'], 2);
        $clients[$id]['trip_miles'] = round($clients[$id]['trip_miles'] + floatval($trip['trip_miles']), 1);
        $clients[$id]['oldest'] = max($clients[$id]['oldest'], $trip['waiting']);
    }
    if ($added) {
        $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_amount, invoice_client_id FROM invoices
            WHERE invoice_status = 'Draft' AND invoice_archived_at IS NULL AND invoice_client_id IN (" . implode(',', array_unique($added)) . ")
            ORDER BY invoice_date DESC, invoice_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $clients[intval($row['invoice_client_id'])]['drafts'][intval($row['invoice_id'])] = $row;
        }
    }
    uasort($clients, function ($a, $b) {
        return ($b['value'] + $b['expense_value'] + $b['trip_value']) <=> ($a['value'] + $a['expense_value'] + $a['trip_value'])
            ?: (count($b['tickets']) + count($b['expenses']) + count($b['trips'])) <=> (count($a['tickets']) + count($a['expenses']) + count($a['trips']));
    });
    return $clients;
}

/** The invoice line a trip goes on as - [name, description]. Raw. */
function tripBillLine(array $trip) {
    $description = trim((string) $trip['trip_purpose']);
    $detail = implode(' - ', array_filter([
        $trip['trip_source'] . ' to ' . $trip['trip_destination'],
        date('m/d/Y', strtotime((string) $trip['trip_date'])),
        tripDistanceText($trip['trip_miles']) . (intval($trip['round_trip']) ? ' round trip' : ''),
        intval($trip['trip_ticket_id']) && ($trip['ticket_number'] ?? null) !== null ? ticketReference($trip) : '',
    ]));
    return [mb_substr(tripRules()['item_name'], 0, 200), $description !== '' ? "$description\n$detail" : $detail];
}

/**
 * Bill trips on an invoice, a line each. $prices is trip id => price (null:
 * what tripBillPrice() makes it). Only the client's trips still to bill are
 * taken, each claimed first. $opts as expenseBillInvoice() takes them:
 * invoice_id (or 0 for a new draft with date, category_id and scope), tax_id
 * and by. Returns [invoice_id, number, new, trips (count), amount, miles],
 * or null with $error.
 */
function tripBillInvoice($client_id, array $prices, array $opts, &$error = '') {
    global $mysqli;
    $error = '';
    $client_id = intval($client_id);
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $trips = $prices ? tripUnbilled($client_id, array_keys($prices)) : [];
    if (!$client_id || !$trips) {
        $error = 'Nothing to bill - those trips are already on an invoice, or no longer billable.';
        return null;
    }

    $bill = [];
    foreach ($trips as $id => $trip) {
        $value = $prices[$id] ?? null;
        $price = ($value === null || trim((string) $value) === '') ? $trip['price'] : billingNumberInput($value);
        if ($price === null || $price < 0 || $price >= 1e9) {
            $error = 'The price for the trip to ' . $trip['trip_destination'] . ' on ' . $trip['trip_date'] . ' has to be a number, zero or more.';
            return null;
        }
        $bill[$id] = round($price, 2);
    }
    $tax_id = taxChoice($opts['tax_id'] ?? 0, $client_id, 'trip');
    if ($tax_id && !taxRow($tax_id)) {
        $error = 'That tax no longer exists.';
        return null;
    }

    $invoice_id = intval($opts['invoice_id'] ?? 0);
    $new = false;
    if ($invoice_id) {
        $invoice = billingRow('invoice', $invoice_id);
        if (!$invoice || intval($invoice['client_id']) !== $client_id || !billingCanEditItems('invoice', $invoice)) {
            $error = "That invoice isn't one of the client's you can still add to.";
            return null;
        }
    } else {
        $invoice_id = billingInvoiceCreate($client_id, $opts['date'] ?? '', $opts['category_id'] ?? 0, $opts['scope'] ?? '', $by);
        if (!$invoice_id) {
            $error = "The invoice couldn't be made.";
            return null;
        }
        $new = true;
    }
    $number = billingNumber('invoice', billingRow('invoice', $invoice_id, false));

    // Claim them - only those still to bill go on
    $in = implode(',', array_keys($bill));
    mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = $invoice_id WHERE trip_id IN ($in) AND trip_client_id = $client_id AND " . tripUnbilledSql());
    $claimed = [];
    $sql = mysqli_query($mysqli, "SELECT trip_id FROM trips WHERE trip_invoice_id = $invoice_id AND trip_id IN ($in) AND trip_invoice_item_id = 0");
    while ($row = mysqli_fetch_row($sql)) {
        $claimed[intval($row[0])] = true;
    }
    if (!$claimed) {
        if ($new) {
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_id = $invoice_id");
        }
        $error = 'Nothing to bill - someone else billed those trips just now.';
        return null;
    }

    $lines = 0;
    $amount = 0.0;
    $miles = 0.0;
    $invoice = billingRow('invoice', $invoice_id, false);
    $currency = (string) $invoice['invoice_currency_code'];
    foreach ($bill as $id => $price) {
        if (!isset($claimed[$id])) {
            continue;
        }
        $trip = $trips[$id];
        [$name, $description] = tripBillLine($trip);
        $item_id = billingItemSave('invoice', $invoice_id, ['name' => $name, 'description' => $description, 'qty' => 1, 'price' => $price, 'tax_id' => $tax_id], 0, $line_error);
        if (!$item_id) {
            mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0 WHERE trip_id = $id AND trip_invoice_id = $invoice_id");
            continue;
        }
        mysqli_query($mysqli, "UPDATE trips SET trip_invoice_item_id = " . intval($item_id) . " WHERE trip_id = $id");
        $lines++;
        $amount += $price;
        $miles += floatval($trip['trip_miles']);
        logAudit('Trip', 'Bill', escapeSql("$by billed the trip " . tripLabel($trip) . " on invoice $number at " . billingMoney($price, $currency)), $client_id, $id);
    }
    billingRecalc('invoice', $invoice_id);

    $invoice = billingRow('invoice', $invoice_id, false);
    $what = $lines === 1 ? '1 trip' : "$lines trips";
    billingLog('invoice', $invoice_id, $invoice['invoice_status'], ucfirst($what) . " billed by $by - " . billingMoney($amount, $currency) . ' for ' . tripDistanceText($miles));
    logAudit('Invoice', $new ? 'Create' : 'Edit', escapeSql("$by billed $what on invoice $number - " . billingMoney($amount, $currency)), $client_id, $invoice_id);
    if ($new) {
        triggerCustomAction('invoice_create', $invoice_id);
    }

    return ['invoice_id' => $invoice_id, 'number' => $number, 'new' => $new, 'trips' => $lines, 'amount' => round($amount, 2), 'miles' => round($miles, 1)];
}

/** May this agent take a trip off an invoice in this status - with write access to Financial and Sales, while its lines can change or once it is cancelled. */
function tripCanUnbill($invoice_status) {
    return tripCanManage() && expenseCanUnbill($invoice_status);
}

/**
 * Take a trip off the invoice it was billed on - its line comes off, and it
 * is to bill again. On a cancelled invoice the line stays and only the link
 * goes. The invoice number, or '' with $error.
 */
function tripUnbill($trip_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = tripRow($trip_id);
    if (!$row || !intval($row['trip_invoice_id'])) {
        $error = "That trip isn't on an invoice";
        return '';
    }
    $id = intval($row['trip_id']);
    $invoice = billingRow('invoice', intval($row['trip_invoice_id']), false);
    if (!$invoice) {
        mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_id = $id");
        return 'an invoice since deleted';
    }
    $number = billingNumber('invoice', $invoice);
    if (!tripCanUnbill($invoice['invoice_status'])) {
        $error = "$number is " . strtolower($invoice['invoice_status']) . " - its lines can't change any more";
        return '';
    }
    if (intval($row['trip_invoice_item_id']) && $invoice['invoice_status'] !== 'Cancelled') {
        billingItemDelete('invoice', intval($invoice['invoice_id']), intval($row['trip_invoice_item_id']));
    }
    mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_id = $id");
    return $number;
}

/** The trips billed on an invoice - each with what its line bills before tax. */
function tripsOnInvoice($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT trips.*, driver.user_name AS driver_name, ticket_prefix, ticket_number, ticket_subject, item_subtotal
        FROM trips
        LEFT JOIN users AS driver ON driver.user_id = trips.trip_user_id
        LEFT JOIN tickets ON ticket_id = trips.trip_ticket_id
        LEFT JOIN invoice_items ON trips.trip_invoice_item_id > 0 AND item_id = trips.trip_invoice_item_id
        WHERE trips.trip_invoice_id = $invoice_id
        ORDER BY trips.trip_date ASC, trips.trip_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['billed'] = $row['item_subtotal'] !== null ? floatval($row['item_subtotal']) : null;
        $rows[] = $row;
    }
    return $rows;
}

/** The client's ticked trips to bill aren't billable after all - their labels. */
function tripWriteOff($client_id, array $trip_ids, $by) {
    global $mysqli;
    $trips = $trip_ids ? tripUnbilled($client_id, $trip_ids) : [];
    $done = [];
    foreach ($trips as $trip_id => $trip) {
        mysqli_query($mysqli, "UPDATE trips SET trip_billable = 0 WHERE trip_id = $trip_id AND trip_invoice_id = 0 AND trip_billable = 1");
        if (mysqli_affected_rows($mysqli) === 1) {
            logAudit('Trip', 'Edit', escapeSql("$by marked the trip " . tripLabel($trip) . ' not billable'), intval($client_id), $trip_id);
            $done[] = tripLabel($trip);
        }
    }
    return $done;
}

/** Each driver's vehicle on their last trip - user_id => 1 (their own) or 0 - for New Trip to start with. */
function tripDriverVehicles() {
    global $mysqli;
    $vehicles = [];
    $sql = mysqli_query($mysqli, "SELECT t.trip_user_id, t.trip_own_vehicle FROM trips AS t
        WHERE t.trip_archived_at IS NULL AND t.trip_user_id > 0 AND t.trip_id = (SELECT last.trip_id FROM trips AS last
            WHERE last.trip_user_id = t.trip_user_id AND last.trip_archived_at IS NULL ORDER BY last.trip_date DESC, last.trip_id DESC LIMIT 1)");
    while ($row = mysqli_fetch_row($sql)) {
        $vehicles[intval($row[0])] = intval($row[1]);
    }
    return $vehicles;
}
