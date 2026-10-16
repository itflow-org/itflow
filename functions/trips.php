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
 */

/** The distance a trip is measured in - "miles", or "mi" short. */
function tripUnit($short = false) {
    return $short ? 'mi' : 'miles';
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
            ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at, ticket_onsite
        FROM trips " . tripJoinsSql() . "
        LEFT JOIN users AS creator ON creator.user_id = trips.trip_created_by
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
    ];
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
        'location_id' => ['trips.trip_location_id', 'locations', 'location_name', 'Site'], 'ticket_id' => ['trips.trip_ticket_id', 'tickets', 'ticket_subject', 'Ticket']] as $key => $f) {
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
 * => [[id, "TCK-12 - Subject", location_id, open], ...]. Only for agents who
 * can see tickets.
 */
function tripClientTickets($client_id = 0, $keep_id = 0) {
    global $mysqli;
    if (lookupUserPermission('module_support') < 1) {
        return [];
    }
    $client_id = intval($client_id);
    $keep_id = intval($keep_id);
    $tickets = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_location_id, ticket_resolved_at, ticket_closed_at
        FROM tickets
        WHERE ticket_client_id > 0 AND ticket_archived_at IS NULL " . ($client_id ? "AND ticket_client_id = $client_id " : '') . "
            AND (" . TICKET_OPEN_SQL . " OR COALESCE(ticket_resolved_at, ticket_closed_at) >= CURDATE() - INTERVAL 60 DAY OR ticket_id = $keep_id) " . clientScopeSql('ticket_client_id') . "
        ORDER BY ticket_client_id, (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, ticket_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $cid = intval($row['ticket_client_id']);
        if (count($tickets[$cid] ?? []) >= 25 && intval($row['ticket_id']) !== $keep_id) {
            continue;
        }
        $tickets[$cid][] = [intval($row['ticket_id']), ticketReference($row) . ' - ' . mb_substr((string) $row['ticket_subject'], 0, 80), intval($row['ticket_location_id']), ticketIsOpen($row) ? 1 : 0];
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
 * odometer_end, purpose, driver, client_id, location_id, ticket_id. A ticket
 * brings its client when none is picked; a site and ticket must be the
 * client's. Returns the trip id, or 0 with $error.
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

    // The miles: from the odometer when both readings are there, else one way, doubled for a round trip
    $round = !empty($in['round_trip']) ? 1 : 0;
    $odo_start_text = trim(str_replace(',', '', (string) ($in['odometer_start'] ?? '')));
    $odo_end_text = trim(str_replace(',', '', (string) ($in['odometer_end'] ?? '')));
    if (($odo_start_text !== '' && !ctype_digit($odo_start_text)) || ($odo_end_text !== '' && !ctype_digit($odo_end_text))) {
        $error = 'The odometer readings are whole numbers';
        return 0;
    }
    $odo_start = $odo_start_text === '' ? null : intval($odo_start_text);
    $odo_end = $odo_end_text === '' ? null : intval($odo_end_text);
    if ($odo_start !== null && $odo_end !== null) {
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
    $client_id = intval($in['client_id'] ?? 0);
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
    $driver = intval($in['driver'] ?? 0);
    $drivers = tripDrivers($existing ? $existing['trip_user_id'] : 0);
    if (!isset($drivers[$driver])) {
        $error = 'Pick who drove';
        return 0;
    }
    if (!tripCanManage() && $driver !== intval($session_user_id) && !($existing && $driver === intval($existing['trip_user_id']))) {
        $error = 'You can log your own trips - logging one for someone else needs write access to Financial';
        return 0;
    }

    $set = "trip_date = '$date', trip_source = '" . escapeSql($source) . "', trip_destination = '" . escapeSql($destination) . "', trip_miles = $miles, round_trip = $round,
        trip_start_odometer = " . ($odo_start === null ? 'NULL' : $odo_start) . ", trip_end_odometer = " . ($odo_end === null ? 'NULL' : $odo_end) . ",
        trip_purpose = '" . escapeSql($purpose) . "', trip_user_id = $driver, trip_client_id = $client_id, trip_location_id = $location_id, trip_ticket_id = $ticket_id";
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
    mysqli_query($mysqli, "DELETE FROM trips WHERE trip_id = " . intval($row['trip_id']));
    return $row;
}

/** The trips of a ticket, a site or a client - newest first, scoped. For the cards that list them. */
function tripsFor($column, $id, $limit = 10) {
    global $mysqli;
    if (!in_array($column, ['trip_ticket_id', 'trip_location_id', 'trip_client_id'], true)) {
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
    if (!in_array($column, ['trip_ticket_id', 'trip_location_id', 'trip_client_id'], true)) {
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
