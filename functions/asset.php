<?php

/*
 * Assets - how an asset is shown: the colour of its type, the badge for its
 * status, the icon for its operating system and how its warranty and age read.
 * Shared by the assets list (agent/assets.php), the asset page
 * (agent/asset.php) and the quick-look modal (agent/modals/asset/asset.php).
 *
 * Everything here takes raw values and returns plain strings or arrays of
 * them - escape anything that came from the database before echoing it.
 */

/** The colour family an asset type is drawn in - a CSS class suffix (itflow-asset-tone-*). */
function assetTypeTone($asset_type) {
    $tones = [
        'Laptop' => 'workstation',
        'Desktop' => 'workstation',
        'Server' => 'server',
        'Virtual Machine' => 'virtual',
        'Switch' => 'network',
        'Firewall/Router' => 'firewall',
        'Access Point' => 'wireless',
        'Phone' => 'phone',
        'Mobile Phone' => 'phone',
        'Tablet' => 'phone',
        'Printer' => 'peripheral',
        'Display' => 'peripheral',
        'Camera' => 'peripheral',
    ];
    return $tones[(string) $asset_type] ?? 'other';
}

/**
 * Bootstrap colour for an asset status. A colour set on the status in
 * Admin > Categories wins; otherwise the built-in statuses get a sensible
 * default and anything else is grey.
 *
 * Returns ['class' => bootstrap tone or '', 'style' => inline background or '']
 */
function assetStatusBadge($status, $category_color = '') {
    $category_color = trim((string) $category_color);
    if (preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', $category_color)) {
        return ['class' => '', 'style' => "background-color: $category_color"];
    }

    $defaults = [
        'Deployed' => 'success',
        'Ready to Deploy' => 'info',
        'Out for Repair' => 'warning',
        'Lost' => 'danger',
        'Stolen' => 'danger',
        'Retired' => 'secondary',
    ];
    return ['class' => $defaults[(string) $status] ?? 'secondary', 'style' => ''];
}

/** Status colours from Admin > Categories, keyed by status name (raw). One query per page. */
function assetStatusColors() {
    global $mysqli;
    static $colors = null;

    if ($colors === null) {
        $colors = [];
        $sql = mysqli_query($mysqli, "SELECT category_name, category_color FROM categories WHERE category_type = 'asset_status' AND category_archived_at IS NULL");
        while ($row = mysqli_fetch_assoc($sql)) {
            $colors[$row['category_name']] = (string) $row['category_color'];
        }
    }

    return $colors;
}

/** The colour a status is drawn in, as a CSS value - the same as its badge. */
function assetStatusColor($status, $category_color = '') {
    $badge = assetStatusBadge($status, $category_color);
    return $badge['style'] ? trim(substr($badge['style'], strlen('background-color:'))) : "var(--bs-{$badge['class']})";
}

/** The status badge, ready to echo. */
function assetStatusPill($status) {
    $status = (string) $status;
    if ($status === '') {
        return '';
    }
    $badge = assetStatusBadge($status, assetStatusColors()[$status] ?? '');
    $class = $badge['class'] ? "text-bg-{$badge['class']}" : 'text-white';
    $style = $badge['style'] ? " style=\"{$badge['style']}\"" : '';
    return "<span class=\"badge rounded-pill fw-normal $class\"$style>" . escapeHtml($status) . "</span>";
}

/** OS name fragments and their brand icons (Font Awesome 5), first match wins. Also drives the asset form's OS icon. */
function assetOsIconMap() {
    return [
        'windows' => 'fab fa-windows',
        'macos' => 'fab fa-apple',
        'mac os' => 'fab fa-apple',
        'ios' => 'fab fa-apple',
        'ipados' => 'fab fa-apple',
        'android' => 'fab fa-android',
        'ubuntu' => 'fab fa-ubuntu',
        'red hat' => 'fab fa-redhat',
        'rhel' => 'fab fa-redhat',
        'centos' => 'fab fa-centos',
        'fedora' => 'fab fa-fedora',
        'suse' => 'fab fa-suse',
        'freebsd' => 'fab fa-freebsd',
        'pfsense' => 'fab fa-freebsd',
        'opnsense' => 'fab fa-freebsd',
        'truenas' => 'fab fa-freebsd',
        'raspberry' => 'fab fa-raspberry-pi',
        'linux' => 'fab fa-linux',
        'debian' => 'fab fa-linux',
        'mint' => 'fab fa-linux',
        'proxmox' => 'fab fa-linux',
        'pop!_os' => 'fab fa-linux',
        'firmware' => 'fas fa-microchip',
        'routeros' => 'fas fa-microchip',
    ];
}

/** A brand icon for the operating system, where one is recognisable (Font Awesome 5 class). */
function assetOsIcon($os) {
    $os = strtolower((string) $os);
    foreach (assetOsIconMap() as $needle => $icon) {
        if (strpos($os, $needle) !== false) {
            return $icon;
        }
    }
    return 'fas fa-compact-disc';
}

/**
 * Where the warranty stands, or null when there's no date.
 *
 * Returns ['tone' => danger|warning|success, 'short' => "Expired" / "23 days left" / "Covered",
 *          'text' => "Expired 3 months ago" / "Expires in 23 days" / "Until 2028-11-08",
 *          'days' => days left (negative once expired)]
 */
function assetWarranty($warranty_expire, $soon_days = 90) {
    $warranty_expire = trim((string) $warranty_expire);
    if ($warranty_expire === '' || strpos($warranty_expire, '0000-00-00') === 0) {
        return null;
    }
    $expires = DateTime::createFromFormat('!Y-m-d', substr($warranty_expire, 0, 10));
    if (!$expires) {
        return null;
    }
    $today = new DateTime('today');
    $days = (int) $today->diff($expires)->format('%r%a');
    $date = $expires->format('Y-m-d');

    if ($days < 0) {
        return ['tone' => 'danger', 'short' => 'Expired', 'text' => 'Expired ' . timeAgo($date), 'days' => $days, 'date' => $date];
    }
    if ($days <= $soon_days) {
        $left = $days === 0 ? 'today' : ($days === 1 ? '1 day left' : "$days days left");
        return ['tone' => 'warning', 'short' => $left, 'text' => $days === 0 ? 'Expires today' : "Expires in $days day" . ($days === 1 ? '' : 's'), 'days' => $days, 'date' => $date];
    }
    return ['tone' => 'success', 'short' => 'Covered', 'text' => "Until $date", 'days' => $days, 'date' => $date];
}

/** "3 yrs 2 mos" since a date, or '' without one. For purchase / install dates. */
function assetAge($date) {
    $date = trim((string) $date);
    if ($date === '' || strpos($date, '0000-00-00') === 0) {
        return '';
    }
    $from = DateTime::createFromFormat('!Y-m-d', substr($date, 0, 10));
    if (!$from) {
        return '';
    }
    $diff = $from->diff(new DateTime('today'));
    if ($diff->invert) {
        return '';
    }
    $parts = [];
    if ($diff->y) {
        $parts[] = $diff->y . ' yr' . ($diff->y === 1 ? '' : 's');
    }
    if ($diff->m) {
        $parts[] = $diff->m . ' mo' . ($diff->m === 1 ? '' : 's');
    }
    if (!$parts) {
        return $diff->d <= 1 ? 'new' : $diff->d . ' days';
    }
    return implode(' ', $parts);
}

/**
 * The asset's links (URI, URI 2, client URI) as [label, url] pairs, skipping
 * empty ones. Raw - escapeUrl() each url before it goes in an href.
 */
function assetLinks($uri, $uri_2, $uri_client) {
    $links = [];
    foreach ([['Open', $uri], ['Open 2', $uri_2], ['Client link', $uri_client]] as [$label, $url]) {
        $url = trim((string) $url);
        if ($url !== '') {
            $links[] = [$label, $url];
        }
    }
    return $links;
}

/** A short name for a link, by what it opens: "Web", "SSH", "RDP"... The full URL goes in its title. */
function assetLinkLabel($url) {
    $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));
    $labels = ['http' => 'Web', 'https' => 'Web', 'ssh' => 'SSH', 'telnet' => 'Telnet', 'rdp' => 'RDP', 'vnc' => 'VNC',
        'ftp' => 'FTP', 'sftp' => 'SFTP', 'smb' => 'SMB'];
    return $labels[$scheme] ?? ($scheme !== '' ? strtoupper($scheme) : 'Link');
}

/** Link schemes and their icons. Also drives the asset form's link icons. */
function assetLinkIconMap() {
    return [
        'ssh' => 'fas fa-terminal',
        'telnet' => 'fas fa-terminal',
        'rdp' => 'fas fa-desktop',
        'vnc' => 'fas fa-desktop',
        'ftp' => 'fas fa-folder-open',
        'sftp' => 'fas fa-folder-open',
        'smb' => 'fas fa-folder-open',
        'http' => 'fas fa-globe',
        'https' => 'fas fa-globe',
    ];
}

/** Icon for a link by its scheme. */
function assetLinkIcon($url) {
    $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));
    return assetLinkIconMap()[$scheme] ?? 'fas fa-external-link-alt';
}

/**
 * $id if that location / contact / vendor / network belongs to the client,
 * else 0. The asset handlers run their picks through this so a hand-edited
 * POST can't hang another client's contact or location off an asset.
 */
function assetClientOwnedId($kind, $id, $client_id) {
    global $mysqli;

    $tables = [
        'location' => ['locations', 'location_id', 'location_client_id'],
        'contact' => ['contacts', 'contact_id', 'contact_client_id'],
        'vendor' => ['vendors', 'vendor_id', 'vendor_client_id'],
        'network' => ['networks', 'network_id', 'network_client_id'],
    ];
    $id = intval($id);
    $client_id = intval($client_id);
    if (!$id || !isset($tables[$kind])) {
        return 0;
    }
    [$table, $id_column, $client_column] = $tables[$kind];
    $sql = mysqli_query($mysqli, "SELECT 1 FROM $table WHERE $id_column = $id AND $client_column = $client_id LIMIT 1");
    return mysqli_num_rows($sql) ? $id : 0;
}

/*
 * Quick pickers (js/quick_pick.js) for an asset's status, location and who it
 * is assigned to - changed where they show on the assets list and the asset
 * page instead of through Edit. Wrapped in quickPick() (functions/ticket_fields.php)
 * with the asset's client as the scope, since locations and contacts are per client.
 */

/** The asset fields a quick picker can change. */
const ASSET_QUICK_FIELDS = ['asset_status', 'asset_location', 'asset_contact'];

/** The asset and what its pickers show, by id - the row assetQuickHtml() and assetContactDetails() draw from. */
function assetQuickRow($asset_id) {
    global $mysqli;
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_status, asset_client_id,
            asset_location_id, location_name, location_archived_at,
            asset_contact_id, contact_name, contact_photo, contact_archived_at, contact_email,
            contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code
        FROM assets
        LEFT JOIN locations ON location_id = asset_location_id
        LEFT JOIN contacts ON contact_id = asset_contact_id
        WHERE asset_id = " . intval($asset_id))) ?: null;
}

/**
 * What a picker shows for the value it holds now. $style is 'list' (a pill,
 * on the assets list and in the asset page header) or 'detail' (a line in the
 * asset page's Where & who card). $row is raw, as assetQuickRow() returns it.
 */
function assetQuickHtml($field, $row, $style = 'list') {
    $client_id = intval($row['asset_client_id'] ?? 0);
    $pill = 'badge rounded-pill fw-normal d-inline-flex align-items-center gap-1 itflow-asset-pill';
    $empty = "$pill itflow-asset-pill-empty";

    if ($field === 'asset_status') {
        return assetStatusPill($row['asset_status'] ?? '') ?: "<span class=\"$empty\">No status</span>";
    }

    if ($field === 'asset_location') {
        if (!intval($row['asset_location_id'] ?? 0) || (string) ($row['location_name'] ?? '') === '') {
            return $style === 'detail'
                ? '<span class="text-body-secondary">No location</span>'
                : "<span class=\"$empty\"><i class=\"fas fa-map-marker-alt\"></i>No location</span>";
        }
        $archived = !empty($row['location_archived_at']);
        $name = '<span class="text-truncate' . ($archived ? ' text-decoration-line-through' : '') . '"' . ($archived ? ' title="Archived location"' : '') . '>' . escapeHtml($row['location_name']) . '</span>';
        return $style === 'detail'
            ? '<span class="d-inline-flex align-items-center gap-2 min-w-0' . ($archived ? ' text-danger' : '') . "\">$name</span>"
            : "<span class=\"$pill " . ($archived ? 'text-danger-emphasis bg-danger-subtle' : 'text-body-emphasis bg-body-secondary') . "\"><i class=\"fas fa-map-marker-alt text-body-secondary\"></i>$name</span>";
    }

    if ($field === 'asset_contact') {
        if (!intval($row['asset_contact_id'] ?? 0) || (string) ($row['contact_name'] ?? '') === '') {
            return $style === 'detail'
                ? '<span class="text-body-secondary">Nobody</span>'
                : "<span class=\"$empty\"><i class=\"fas fa-user-slash\"></i>Nobody</span>";
        }
        $archived = !empty($row['contact_archived_at']);
        $avatar = contactAvatar($client_id, $row['asset_contact_id'], $row['contact_name'], $row['contact_photo'] ?? '', 'xs');
        $name = '<span class="text-truncate' . ($archived ? ' text-decoration-line-through' : '') . '"' . ($archived ? ' title="Archived contact"' : '') . '>' . escapeHtml($row['contact_name']) . '</span>';
        return $style === 'detail'
            ? "<span class=\"d-inline-flex align-items-center gap-2 min-w-0\">$avatar$name</span>"
            : "<span class=\"$pill itflow-asset-pill-person " . ($archived ? 'text-body-secondary' : 'text-body-emphasis') . " bg-body-secondary\">$avatar$name</span>";
    }

    return '';
}

/** Under the asset page's Assigned picker: how to reach that person, and a link to them. Redrawn when the contact changes. */
function assetContactDetails($row) {
    $contact_id = intval($row['asset_contact_id'] ?? 0);
    if (!$contact_id || (string) ($row['contact_name'] ?? '') === '') {
        return '';
    }
    $client_id = intval($row['asset_client_id']);
    $email = escapeHtml($row['contact_email'] ?? '');
    $phone = escapeHtml(formatPhoneNumber($row['contact_phone'] ?? '', $row['contact_phone_country_code'] ?? ''));
    $extension = escapeHtml($row['contact_extension'] ?? '');
    $mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'] ?? '', $row['contact_mobile_country_code'] ?? ''));

    $html = '';
    if ($email) {
        $html .= "<div class=\"small text-truncate\"><a href=\"mailto:$email\">$email</a><button type=\"button\" class=\"itflow-copy clipboardjs\" title=\"Copy email\" data-clipboard-text=\"$email\"><i class=\"far fa-copy\"></i></button></div>";
    }
    if ($phone) {
        $html .= "<div class=\"small\"><i class=\"fas fa-fw fa-phone text-secondary me-1\"></i>$phone" . ($extension ? " x$extension" : '') . '</div>';
    }
    if ($mobile) {
        $html .= "<div class=\"small\"><i class=\"fas fa-fw fa-mobile-alt text-secondary me-1\"></i>$mobile</div>";
    }
    $html .= "<div class=\"small\"><a href=\"contact.php?client_id=$client_id&contact_id=$contact_id\">Open contact<i class=\"fas fa-arrow-right ms-1\"></i></a></div>";

    return $html;
}

/**
 * The choices a picker offers, in js/quick_pick.js's option shape. Locations
 * and contacts are the client's own (null if the agent can't reach that client).
 */
function assetQuickOptions($field, $client_id) {
    global $mysqli;
    $client_id = intval($client_id);

    if ($field === 'asset_status') {
        $options = [['value' => '', 'label' => 'No status', 'icon' => 'fas fa-minus', 'none' => true]];
        $sql = mysqli_query($mysqli, "SELECT category_name, category_color FROM categories
            WHERE category_type = 'asset_status' AND category_archived_at IS NULL
            ORDER BY category_order ASC, category_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $options[] = ['value' => $row['category_name'], 'label' => $row['category_name'], 'color' => assetStatusColor($row['category_name'], $row['category_color'])];
        }
        return $options;
    }

    if (!$client_id || !hasClientAccess($client_id)) {
        return null;
    }

    if ($field === 'asset_location') {
        $options = [['value' => '0', 'label' => 'No location', 'icon' => 'fas fa-ban', 'none' => true]];
        $sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_primary, location_city, location_state FROM locations
            WHERE location_client_id = $client_id AND location_archived_at IS NULL
            ORDER BY location_primary DESC, location_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $place = implode(', ', array_filter([trim((string) $row['location_city']), trim((string) $row['location_state'])]));
            $description = implode(' · ', array_filter([intval($row['location_primary']) ? 'Primary' : '', $place]));
            $options[] = ['value' => (string) intval($row['location_id']), 'label' => $row['location_name'], 'icon' => 'fas fa-map-marker-alt', 'description' => $description];
        }
        return $options;
    }

    if ($field === 'asset_contact') {
        $options = [['value' => '0', 'label' => 'Nobody', 'icon' => 'fas fa-user-slash', 'none' => true]];
        $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo FROM contacts
            WHERE contact_client_id = $client_id AND contact_archived_at IS NULL
            ORDER BY contact_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $options[] = ['value' => (string) intval($row['contact_id']), 'label' => $row['contact_name'], 'description' => (string) $row['contact_title']]
                + contactAvatarData($client_id, $row['contact_id'], $row['contact_name'], $row['contact_photo']);
        }
        return $options;
    }

    return null;
}

/**
 * Change one field on one asset, as Edit Asset would - audit log and asset
 * history included. The caller checks the agent may edit assets of its
 * client. Returns [ok, plain-text message].
 */
function assetQuickSet($asset_id, $field, $value) {
    global $mysqli, $session_name;

    $asset_id = intval($asset_id);
    $row = assetQuickRow($asset_id);
    if (!$row) {
        return [false, 'That asset no longer exists.'];
    }
    $client_id = intval($row['asset_client_id']);
    $asset_name = escapeSql($row['asset_name']);
    $value = trim((string) $value);

    if ($field === 'asset_status') {
        if ($value !== '' && !isset(assetStatusColors()[$value])) {
            return [false, 'Pick one of the asset statuses.'];
        }
        if ($value === (string) $row['asset_status']) {
            return [true, ''];
        }
        $status = escapeSql($value);
        mysqli_query($mysqli, "UPDATE assets SET asset_status = '$status' WHERE asset_id = $asset_id");
        $what = $value === '' ? "cleared the status on $asset_name" : "set status to $status on $asset_name";
        $message = $value === '' ? 'Status cleared' : "Status set to $value";

    } elseif ($field === 'asset_location') {
        $location_id = assetClientOwnedId('location', $value, $client_id);
        if (intval($value) && !$location_id) {
            return [false, 'That location is not one of this client\'s.'];
        }
        if ($location_id === intval($row['asset_location_id'])) {
            return [true, ''];
        }
        $location_name = $location_id ? (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT location_name FROM locations WHERE location_id = $location_id"))[0] : '';
        $status = escapeSql($row['asset_status']);
        mysqli_query($mysqli, "UPDATE assets SET asset_location_id = $location_id WHERE asset_id = $asset_id");
        $what = $location_id ? "assigned asset $asset_name to location " . escapeSql($location_name) : "removed asset $asset_name from its location";
        $message = $location_id ? "Moved to $location_name" : 'Location removed';

    } elseif ($field === 'asset_contact') {
        $contact_id = assetClientOwnedId('contact', $value, $client_id);
        if (intval($value) && !$contact_id) {
            return [false, 'That contact is not one of this client\'s.'];
        }
        if ($contact_id === intval($row['asset_contact_id'])) {
            return [true, ''];
        }
        $contact_name = $contact_id ? (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_id = $contact_id"))[0] : '';
        $status = escapeSql($row['asset_status']);
        mysqli_query($mysqli, "UPDATE assets SET asset_contact_id = $contact_id WHERE asset_id = $asset_id");
        $what = $contact_id ? "assigned asset $asset_name to contact " . escapeSql($contact_name) : "unassigned asset $asset_name";
        $message = $contact_id ? "Assigned to $contact_name" : 'Unassigned';

    } else {
        return [false, 'Unknown field.'];
    }

    logAudit("Asset", "Edit", "$session_name $what", $client_id, $asset_id);
    mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = '$session_name $what', asset_history_asset_id = $asset_id");

    return [true, $message];
}

/** What quick_pick.js redraws after a change: the value in both looks, plus the contact details on the asset page. */
function assetQuickRecord($asset_id, $field) {
    $row = assetQuickRow($asset_id);
    $current = [
        'asset_status' => (string) $row['asset_status'],
        'asset_location' => (string) intval($row['asset_location_id']),
        'asset_contact' => (string) intval($row['asset_contact_id']),
    ][$field];
    $record = ['current' => $current, 'html' => ['list' => assetQuickHtml($field, $row, 'list'), 'detail' => assetQuickHtml($field, $row, 'detail')]];
    if ($field === 'asset_contact') {
        $record['related'] = ['asset-contact-' . intval($asset_id) => assetContactDetails($row)];
    }
    return $record;
}
