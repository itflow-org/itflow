<?php

/*
 * Locations - a client's sites, and what is at each one.
 *
 * A location is where a tech drives to, so it is built around getting there
 * (address, directions, phone, hours, who to ask for, how to get in) and what
 * is waiting on site: the people based there, the assets, networks and racks
 * there, the open tickets for it, and the documents and files about it (a
 * floor plan, a site runbook, photos of the comms cupboard).
 *
 * People, assets, networks and racks belong to one location each (a column on
 * their own table); documents and files link through location_documents and
 * location_files (functions/links.php), like the other link tables.
 */

/**
 * The things that sit at a location, each by a column on its own table - what
 * the location page lists and what can be moved to a location from it.
 * 'module' is the permission it takes to see and move them.
 */
const LOCATION_KINDS = [
    'contact' => ['table' => 'contacts', 'id' => 'contact_id', 'name' => 'contact_name', 'location' => 'contact_location_id', 'client' => 'contact_client_id', 'archived' => 'contact_archived_at', 'module' => 'module_client', 'label' => 'contact', 'plural' => 'Contacts', 'icon' => 'fa-user'],
    'asset' => ['table' => 'assets', 'id' => 'asset_id', 'name' => 'asset_name', 'location' => 'asset_location_id', 'client' => 'asset_client_id', 'archived' => 'asset_archived_at', 'module' => 'module_support', 'label' => 'asset', 'plural' => 'Assets', 'icon' => 'fa-desktop'],
    'network' => ['table' => 'networks', 'id' => 'network_id', 'name' => 'network_name', 'location' => 'network_location_id', 'client' => 'network_client_id', 'archived' => 'network_archived_at', 'module' => 'module_support', 'label' => 'network', 'plural' => 'Networks', 'icon' => 'fa-network-wired'],
    'rack' => ['table' => 'racks', 'id' => 'rack_id', 'name' => 'rack_name', 'location' => 'rack_location_id', 'client' => 'rack_client_id', 'archived' => 'rack_archived_at', 'module' => 'module_support', 'label' => 'rack', 'plural' => 'Racks', 'icon' => 'fa-server'],
];

/** The address on one line - "1140 Ridge Avenue, Pittsburgh, PA 15212". Raw in, raw out. */
function locationAddressText($row, $with_country = false) {
    return formatAddress($row['location_address'] ?? '', $row['location_city'] ?? '', $row['location_state'] ?? '', $row['location_zip'] ?? '', $with_country ? ($row['location_country'] ?? '') : '', ', ');
}

/** A link that opens the address in maps - Apple Maps on an Apple device, Google Maps otherwise. '' without an address. */
function locationMapUrl($row) {
    global $session_map_source;
    $address = locationAddressText($row, true);
    if ($address === '') {
        return '';
    }
    return ($session_map_source ?? 'google') === 'apple'
        ? 'https://maps.apple.com/?q=' . rawurlencode($address)
        : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
}

/** Turn-by-turn directions to the address from wherever the tech is. '' without an address. */
function locationDirectionsUrl($row) {
    global $session_map_source;
    $address = locationAddressText($row, true);
    if ($address === '') {
        return '';
    }
    return ($session_map_source ?? 'google') === 'apple'
        ? 'https://maps.apple.com/?daddr=' . rawurlencode($address)
        : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);
}

/** The location's photo, or a map pin tile. $size '' | 'sm' | 'lg'. */
function locationTile($row, $size = '') {
    $size_class = in_array($size, ['sm', 'lg'], true) ? " itflow-asset-icon-$size" : '';
    $photo = (string) ($row['location_photo'] ?? '');
    if ($photo !== '') {
        return '<img class="itflow-asset-icon' . $size_class . '" src="../uploads/clients/' . intval($row['location_client_id']) . '/' . escapeHtml($photo) . '" alt="">';
    }
    $icon = intval($row['location_primary'] ?? 0) ? 'fa-building' : 'fa-map-marker-alt';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-location" aria-hidden="true"><i class="fas fa-fw ' . $icon . '"></i></span>';
}

/** One location by id with its client and on-site contact - raw, or null. */
function locationRow($location_id) {
    global $mysqli;
    $location_id = intval($location_id);
    if (!$location_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT locations.*, client_id, client_name,
            contact_id, contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_extension,
            contact_mobile, contact_mobile_country_code, contact_photo, contact_archived_at
        FROM locations
        LEFT JOIN clients ON client_id = location_client_id
        LEFT JOIN contacts ON contact_id = location_contact_id AND contact_client_id = location_client_id
        WHERE location_id = $location_id
        LIMIT 1"));
    return $row ?: null;
}

/**
 * What is at each of these locations, in one query per kind rather than per
 * row: people, assets, networks and racks that are not archived, and tickets
 * still open. Returns [location_id => [kind => count]].
 */
function locationCounts($location_ids) {
    global $mysqli;
    $location_ids = array_values(array_filter(array_map('intval', (array) $location_ids)));
    $counts = [];
    foreach ($location_ids as $id) {
        $counts[$id] = ['contact' => 0, 'asset' => 0, 'network' => 0, 'rack' => 0, 'ticket' => 0];
    }
    if (!$location_ids) {
        return $counts;
    }
    $in = implode(',', $location_ids);
    foreach (LOCATION_KINDS as $kind => $k) {
        $sql = mysqli_query($mysqli, "SELECT {$k['location']}, COUNT(*) FROM {$k['table']} WHERE {$k['location']} IN ($in) AND {$k['archived']} IS NULL GROUP BY {$k['location']}");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])][$kind] = intval($row[1]);
        }
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_location_id, COUNT(*) FROM tickets WHERE ticket_location_id IN ($in) AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL GROUP BY ticket_location_id");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])]['ticket'] = intval($row[1]);
    }
    return $counts;
}

/**
 * The location fields a New / Edit form posts, as a SQL SET list - phone
 * numbers as digits, only the client's own contact. Null when there is no
 * name. Fields missing from the post are blank, never a PHP warning.
 */
function locationFormSet($post, $client_id) {
    $text = function ($field, $length = 200) use ($post) {
        $value = trim((string) ($post[$field] ?? ''));
        return "'" . escapeSql($length ? mb_substr($value, 0, $length) : $value) . "'";
    };
    $digits = function ($field, $length = 200) use ($post) {
        return "'" . substr(preg_replace('/[^0-9]/', '', (string) ($post[$field] ?? '')), 0, $length) . "'";
    };

    if (trim((string) ($post['name'] ?? '')) === '') {
        return null;
    }

    $fields = [
        'location_name' => $text('name'),
        'location_description' => $text('description', 0),
        'location_address' => $text('address'),
        'location_city' => $text('city'),
        'location_state' => $text('state'),
        'location_zip' => $text('zip'),
        'location_country' => $text('country'),
        'location_phone' => $digits('phone'),
        'location_phone_country_code' => $digits('phone_country_code', 10),
        'location_phone_extension' => $digits('extension', 10),
        'location_fax' => $digits('fax'),
        'location_fax_country_code' => $digits('fax_country_code', 10),
        'location_hours' => $text('hours'),
        'location_notes' => $text('notes', 0),
        'location_contact_id' => assetClientOwnedId('contact', $post['contact'] ?? 0, $client_id),
    ];
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return implode(', ', $set);
}

/** Replace a location's tags with these - location tags only, and an admin can type a new one in (tagSetForRecord()). */
function locationSetTags($location_id, $tag_ids) {
    tagSetForRecord('location', $location_id, (array) $tag_ids);
}

/** Make this the client's one primary location - what the client header and overview show. */
function locationMakePrimary($location_id, $client_id) {
    global $mysqli;
    $location_id = intval($location_id);
    $client_id = intval($client_id);
    mysqli_query($mysqli, "UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id AND location_id != $location_id");
    mysqli_query($mysqli, "UPDATE locations SET location_primary = 1 WHERE location_id = $location_id");
}

/**
 * Save an uploaded photo for a location, replacing (and removing) the old
 * one. Returns true if a photo was saved.
 */
function locationSavePhoto($location_id, $client_id, $file) {
    global $mysqli;
    if (empty($file['tmp_name']) || !($new_file_name = checkFileUpload($file, ['jpg', 'jpeg', 'gif', 'png', 'webp']))) {
        return false;
    }
    $client_id = intval($client_id);
    $location_id = intval($location_id);
    $dir = "../uploads/clients/$client_id";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], "$dir/$new_file_name")) {
        return false;
    }
    $old = (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT location_photo FROM locations WHERE location_id = $location_id"))[0];
    if ($old !== '' && basename($old) === $old && is_file("$dir/$old")) {
        unlink("$dir/$old");
    }
    mysqli_query($mysqli, "UPDATE locations SET location_photo = '" . escapeSql($new_file_name) . "' WHERE location_id = $location_id");
    return true;
}

/**
 * Before a location is deleted for good: everything that pointed at it
 * points at no location instead of an id that no longer exists.
 */
function locationClearReferences($location_id) {
    global $mysqli;
    $location_id = intval($location_id);
    foreach (LOCATION_KINDS as $k) {
        $none = $k['table'] === 'racks' ? 'NULL' : '0';
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['location']} = $none WHERE {$k['location']} = $location_id");
    }
    mysqli_query($mysqli, "UPDATE tickets SET ticket_location_id = 0 WHERE ticket_location_id = $location_id");
    mysqli_query($mysqli, "UPDATE calendar_events SET event_location_id = 0 WHERE event_location_id = $location_id");
    mysqli_query($mysqli, "UPDATE trips SET trip_location_id = 0 WHERE trip_location_id = $location_id");
}

/**
 * Move records of one kind to a location. Only the location's own client's,
 * unarchived records are moved; assets and contacts go through their quick
 * pickers' setters, so they are logged (and assets get a history line) the
 * same as a move from their own pages. Returns the names moved.
 */
function locationAssign($location, $kind, $ids) {
    global $mysqli, $session_name;
    $k = LOCATION_KINDS[$kind] ?? null;
    if (!$k || !$location) {
        return [];
    }
    $location_id = intval($location['location_id']);
    $client_id = intval($location['location_client_id']);
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    if (!$ids) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT {$k['id']}, {$k['name']} FROM {$k['table']}
        WHERE {$k['id']} IN (" . implode(',', $ids) . ") AND {$k['client']} = $client_id AND {$k['archived']} IS NULL
        AND ({$k['location']} IS NULL OR {$k['location']} != $location_id)");
    $moved = [];
    while ($row = mysqli_fetch_row($sql)) {
        [$id, $name] = [intval($row[0]), $row[1]];
        if ($kind === 'asset') {
            [$ok] = assetQuickSet($id, 'asset_location', $location_id);
        } elseif ($kind === 'contact') {
            [$ok] = contactQuickSetLocation($id, $location_id);
        } else {
            mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['location']} = $location_id WHERE {$k['id']} = $id");
            logAudit(ucfirst($kind), "Edit", "$session_name moved {$k['label']} " . escapeSql($name) . " to location " . escapeSql($location['location_name']), $client_id, $id);
            $ok = true;
        }
        if ($ok) {
            $moved[] = $name;
        }
    }
    return $moved;
}

/** Take one record off a location - it is then at no location. Returns its name, or '' if it was not there. */
function locationUnassign($location, $kind, $id) {
    global $mysqli, $session_name;
    $k = LOCATION_KINDS[$kind] ?? null;
    if (!$k || !$location) {
        return '';
    }
    $location_id = intval($location['location_id']);
    $id = intval($id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$k['name']} FROM {$k['table']} WHERE {$k['id']} = $id AND {$k['location']} = $location_id"));
    if (!$row) {
        return '';
    }
    if ($kind === 'asset') {
        assetQuickSet($id, 'asset_location', 0);
    } elseif ($kind === 'contact') {
        contactQuickSetLocation($id, 0);
    } else {
        $none = $k['table'] === 'racks' ? 'NULL' : '0';
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['location']} = $none WHERE {$k['id']} = $id");
        logAudit(ucfirst($kind), "Edit", "$session_name removed {$k['label']} " . escapeSql($row[0]) . " from location " . escapeSql($location['location_name']), intval($location['location_client_id']), $id);
    }
    return $row[0];
}
