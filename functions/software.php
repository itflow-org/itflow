<?php

/*
 * Licenses - the software table and everything hung off it: the devices and
 * users holding a seat (software_assets, software_contacts), the individual
 * keys and who each one is handed to (software_keys and its two assignment
 * tables), and the documents, files and credentials linked to it
 * (functions/links.php).
 *
 * What the license list, the license page and quick look, and the asset and
 * contact pages share: how a renewal date and a seat count read, the icon a
 * kind of software gets, the seat and key queries, and keeping seats in step
 * when a form or a key assignment changes them.
 *
 * A seat is a device or user on the license. Archived devices and users keep
 * their seat row but do not use a seat - the same as they drop out of every
 * other list - and get it back if they are restored. A license with no seat
 * count (blank or 0) has no limit.
 */

/** License types offered on the form, with their icons - the common MSP ones first. */
const SOFTWARE_LICENSE_TYPES = [
    'Subscription' => 'fa-sync-alt',
    'Device' => 'fa-desktop',
    'User' => 'fa-user',
    'Site' => 'fa-building',
    'Concurrent' => 'fa-users',
    'Perpetual' => 'fa-infinity',
    'Trial' => 'fa-hourglass-half',
    'Usage-based' => 'fa-tachometer-alt',
    'Open Source' => 'fa-code-branch',
];

/** The icon for a license type, typed or from the list. */
function softwareLicenseTypeIcon($license_type) {
    $license_type = html_entity_decode((string) $license_type, ENT_QUOTES, 'UTF-8');
    return SOFTWARE_LICENSE_TYPES[$license_type] ?? 'fa-certificate';
}

/**
 * The icon and colour for a kind of software (Admin > Categories, software
 * types). An icon set on the category wins; otherwise it is read from the
 * name, so "Security Software" is a red shield and "Software as a Service" a
 * cloud. The colours are the asset tones (css: .itflow-asset-tone-*).
 */
function softwareTypeStyle($software_type) {
    global $mysqli;
    static $category_icons = null;

    if ($category_icons === null) {
        $category_icons = [];
        $sql = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories
            WHERE category_type = 'software_type' AND category_icon IS NOT NULL AND category_icon != ''");
        while ($row = mysqli_fetch_assoc($sql)) {
            if (preg_match('/^fa-[a-z0-9-]+$/', $row['category_icon'])) {
                $category_icons[strtolower($row['category_name'])] = $row['category_icon'];
            }
        }
    }

    $type = strtolower(trim(html_entity_decode((string) $software_type, ENT_QUOTES, 'UTF-8')));

    // First match wins - "Operating System" before "System Software"
    $styles = [
        'saas' => ['fa-cloud', 'wireless'],
        'service' => ['fa-cloud', 'wireless'],
        'cloud' => ['fa-cloud', 'wireless'],
        'productivity' => ['fa-briefcase', 'workstation'],
        'office' => ['fa-briefcase', 'workstation'],
        'security' => ['fa-shield-alt', 'firewall'],
        'antivirus' => ['fa-shield-alt', 'firewall'],
        'backup' => ['fa-hdd', 'network'],
        'web' => ['fa-globe', 'network'],
        'mobile' => ['fa-mobile-alt', 'phone'],
        'operating' => ['fa-layer-group', 'virtual'],
        'system' => ['fa-cogs', 'server'],
        'desktop' => ['fa-window-maximize', 'peripheral'],
    ];
    $style = ['fa-cube', 'other'];
    foreach ($styles as $needle => $candidate) {
        if ($type !== '' && strpos($type, $needle) !== false) {
            $style = $candidate;
            break;
        }
    }
    if (isset($category_icons[$type])) {
        $style[0] = $category_icons[$type];
    }

    return ['icon' => $style[0], 'tone' => $style[1]];
}

/** The tile for a license - its software type's icon on that type's colour. $size '' | 'sm' | 'lg'. */
function softwareTile($software_type, $size = '') {
    $style = softwareTypeStyle($software_type);
    $size_class = in_array($size, ['sm', 'lg'], true) ? " itflow-asset-icon-$size" : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-' . $style['tone'] . '" aria-hidden="true"><i class="fas fa-fw ' . $style['icon'] . '"></i></span>';
}

/**
 * Where a renewal date stands - null without one. 'state' is expired, soon
 * (within $soon_days) or active; 'short' fits a pill, 'text' a sentence.
 */
function softwareRenewal($expire, $soon_days = 30) {
    $expire = trim((string) $expire);
    if ($expire === '' || strpos($expire, '0000-00-00') === 0) {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', substr($expire, 0, 10));
    if (!$date) {
        return null;
    }
    $days = (int) (new DateTime('today'))->diff($date)->format('%r%a');
    $ymd = $date->format('Y-m-d');

    if ($days < 0) {
        $ago = -$days;
        $text = $ago === 1 ? 'Expired yesterday' : ($ago < 60 ? "Expired $ago days ago" : 'Expired ' . timeAgo($ymd));
        return ['state' => 'expired', 'tone' => 'danger', 'short' => 'Expired', 'text' => $text, 'days' => $days, 'date' => $ymd];
    }
    if ($days <= $soon_days) {
        $text = $days === 0 ? 'Renews today' : ($days === 1 ? 'Renews tomorrow' : "Renews in $days days");
        return ['state' => 'soon', 'tone' => $days <= 7 ? 'danger' : 'warning', 'short' => $text, 'text' => $text, 'days' => $days, 'date' => $ymd];
    }
    return ['state' => 'active', 'tone' => 'success', 'short' => 'Active', 'text' => 'Renews ' . timeAgo($ymd), 'days' => $days, 'date' => $ymd];
}

/** A renewal as a soft pill - expired red, soon amber, otherwise green. */
function softwareRenewalPill($renewal, $use_text = false) {
    if (!$renewal) {
        return '';
    }
    $tone = $renewal['tone'];
    $icon = ['expired' => 'fa-calendar-times', 'soon' => 'fa-hourglass-half', 'active' => 'fa-calendar-check'][$renewal['state']];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($renewal['date']) . '">'
        . '<i class="fas ' . $icon . ' me-1"></i>' . escapeHtml($use_text ? $renewal['text'] : $renewal['short']) . '</span>';
}

/**
 * Seats in use against seats bought. 'state' is over, full, unused, ok - or
 * unlimited when the license has no seat count.
 */
function softwareSeats($used, $total) {
    $used = intval($used);
    $total = intval($total);
    if ($total <= 0) {
        return ['used' => $used, 'total' => 0, 'free' => null, 'over' => 0, 'pct' => 0, 'state' => 'unlimited', 'tone' => 'secondary',
            'text' => $used === 1 ? '1 in use' : "$used in use", 'title' => "$used in use, no seat limit"];
    }
    $over = max(0, $used - $total);
    $free = max(0, $total - $used);
    $state = $over ? 'over' : ($free === 0 ? 'full' : ($used === 0 ? 'unused' : 'ok'));
    $text = ['over' => "$over over", 'full' => 'All in use', 'unused' => 'None in use', 'ok' => "$free free"][$state];
    return [
        'used' => $used, 'total' => $total, 'free' => $free, 'over' => $over,
        'pct' => min(100, (int) round($used / $total * 100)),
        'state' => $state,
        'tone' => ['over' => 'danger', 'full' => 'warning', 'unused' => 'secondary', 'ok' => 'primary'][$state],
        'text' => $text,
        'title' => "$used of $total seats in use" . ($over ? " - $over over" : ''),
    ];
}

/** Seats as "3 / 5" over a thin bar, and what is left - or "4 in use" for a license without a limit. */
function softwareSeatBar($seats, $show_text = true) {
    $text = $show_text ? '<div class="small text-' . ($seats['state'] === 'over' ? 'danger-emphasis fw-semibold' : 'body-secondary') . '">' . escapeHtml($seats['text']) . '</div>' : '';
    if ($seats['state'] === 'unlimited') {
        return '<div class="itflow-seats" title="' . escapeHtml($seats['title']) . '"><span class="fw-semibold">' . $seats['used'] . '</span> <span class="small text-body-secondary">in use</span>'
            . ($show_text ? '<div class="small text-body-secondary">No seat limit</div>' : '') . '</div>';
    }
    return '<div class="itflow-seats" title="' . escapeHtml($seats['title']) . '">'
        . '<div class="d-flex align-items-baseline gap-1"><span class="fw-semibold' . ($seats['state'] === 'over' ? ' text-danger-emphasis' : '') . '">' . $seats['used'] . '</span><span class="text-body-secondary">/ ' . $seats['total'] . '</span></div>'
        . '<div class="progress itflow-seats-bar" role="progressbar" aria-label="Seats in use" aria-valuenow="' . $seats['used'] . '" aria-valuemin="0" aria-valuemax="' . $seats['total'] . '">'
        . '<div class="progress-bar bg-' . $seats['tone'] . '" style="width: ' . $seats['pct'] . '%"></div></div>'
        . $text . '</div>';
}

/** SQL for the seats in use on the license in $software_column - devices plus users, archived ones left out. */
function softwareSeatsUsedSql($software_column = 'software.software_id') {
    return "((SELECT COUNT(*) FROM software_assets AS seat_a JOIN assets AS seat_aa ON seat_aa.asset_id = seat_a.asset_id AND seat_aa.asset_archived_at IS NULL WHERE seat_a.software_id = $software_column)
        + (SELECT COUNT(*) FROM software_contacts AS seat_c JOIN contacts AS seat_cc ON seat_cc.contact_id = seat_c.contact_id AND seat_cc.contact_archived_at IS NULL WHERE seat_c.software_id = $software_column))";
}

/**
 * The list's attention tabs - each a WHERE fragment on the software table.
 * Renewing is the same 30 days the dashboard counts.
 */
function softwareStatusFilters() {
    $used = softwareSeatsUsedSql('software.software_id');
    return [
        'renewing' => ['label' => 'Renewing soon', 'icon' => 'fa-hourglass-half', 'title' => 'Renewing in the next 30 days',
            'sql' => "software_expire IS NOT NULL AND software_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY"],
        'expired' => ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'title' => 'Past their renewal date',
            'sql' => "software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire < CURDATE()"],
        'over' => ['label' => 'Over seats', 'icon' => 'fa-exclamation-triangle', 'title' => 'More devices and users than seats bought',
            'sql' => "software_seats > 0 AND $used > software_seats"],
        'unused' => ['label' => 'No seats assigned', 'icon' => 'fa-user-slash', 'title' => 'Nobody and nothing on them yet',
            'sql' => "$used = 0"],
    ];
}

/** A license key, monospace, with a copy button - long keys are cut short on screen but copy whole. */
function softwareKeyHtml($key, $class = '') {
    $key = escapeHtml($key);
    if ($key === '') {
        return '';
    }
    return '<span class="d-inline-flex align-items-center mw-100 ' . $class . '"><span class="font-monospace text-truncate itflow-license-key" title="' . $key . '">' . $key . '</span>'
        . '<button type="button" class="itflow-copy clipboardjs" title="Copy key" data-clipboard-text="' . $key . '"><i class="far fa-copy"></i></button></span>';
}

/** One license by id with its client, vendor and seats in use - raw, or null. */
function softwareRow($software_id) {
    global $mysqli;
    $software_id = intval($software_id);
    if (!$software_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software.*, client_id, client_name,
            vendor_id, vendor_name, vendor_description, vendor_account_number, vendor_phone, vendor_phone_country_code, vendor_extension,
            vendor_email, vendor_website, vendor_hours, vendor_sla, vendor_code, vendor_archived_at,
            " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
        FROM software
        LEFT JOIN clients ON client_id = software_client_id
        LEFT JOIN vendors ON vendor_id = software_vendor_id
        WHERE software_id = $software_id
        LIMIT 1"));
    return $row ?: null;
}

/**
 * The devices and users on a license, each with the keys handed to them.
 * Archived ones are left out - they do not use a seat.
 */
function softwareSeatHolders($software_id) {
    global $mysqli;
    $software_id = intval($software_id);

    $holders = ['assets' => [], 'contacts' => []];

    $sql = mysqli_query($mysqli, "SELECT assets.asset_id, asset_name, asset_type, asset_make, asset_model, asset_client_id,
            asset_contact_id, contacts.contact_name AS asset_contact_name, contacts.contact_photo AS asset_contact_photo,
            (SELECT GROUP_CONCAT(k.software_key ORDER BY k.software_key_id SEPARATOR '\n') FROM software_keys AS k
                JOIN software_key_asset_assignments AS ka ON ka.software_key_id = k.software_key_id
                WHERE k.software_key_software_id = $software_id AND ka.asset_id = assets.asset_id) AS held_keys
        FROM software_assets
        JOIN assets ON assets.asset_id = software_assets.asset_id
        LEFT JOIN contacts ON contacts.contact_id = asset_contact_id
        WHERE software_assets.software_id = $software_id AND asset_archived_at IS NULL
        ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['held_keys'] = $row['held_keys'] !== null ? explode("\n", $row['held_keys']) : [];
        $holders['assets'][] = $row;
    }

    $sql = mysqli_query($mysqli, "SELECT contacts.contact_id, contact_name, contact_title, contact_email, contact_photo, contact_client_id,
            (SELECT GROUP_CONCAT(k.software_key ORDER BY k.software_key_id SEPARATOR '\n') FROM software_keys AS k
                JOIN software_key_contact_assignments AS kc ON kc.software_key_id = k.software_key_id
                WHERE k.software_key_software_id = $software_id AND kc.contact_id = contacts.contact_id) AS held_keys
        FROM software_contacts
        JOIN contacts ON contacts.contact_id = software_contacts.contact_id
        WHERE software_contacts.software_id = $software_id AND contact_archived_at IS NULL
        ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['held_keys'] = $row['held_keys'] !== null ? explode("\n", $row['held_keys']) : [];
        $holders['contacts'][] = $row;
    }

    return $holders;
}

/** A license's individual keys, oldest first, each with the devices and users it is handed to. */
function softwareKeys($software_id) {
    global $mysqli;
    $software_id = intval($software_id);

    $keys = [];
    $sql = mysqli_query($mysqli, "SELECT software_key_id, software_key FROM software_keys WHERE software_key_software_id = $software_id ORDER BY software_key_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $keys[intval($row['software_key_id'])] = ['id' => intval($row['software_key_id']), 'key' => $row['software_key'], 'assets' => [], 'contacts' => []];
    }
    if (!$keys) {
        return [];
    }

    $ids = implode(',', array_keys($keys));
    $sql = mysqli_query($mysqli, "SELECT ka.software_key_id, assets.asset_id, asset_name, asset_type, asset_archived_at FROM software_key_asset_assignments AS ka
        JOIN assets ON assets.asset_id = ka.asset_id WHERE ka.software_key_id IN ($ids) ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $keys[intval($row['software_key_id'])]['assets'][] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT kc.software_key_id, contacts.contact_id, contact_name, contact_photo, contact_client_id, contact_archived_at FROM software_key_contact_assignments AS kc
        JOIN contacts ON contacts.contact_id = kc.contact_id WHERE kc.software_key_id IN ($ids) ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $keys[intval($row['software_key_id'])]['contacts'][] = $row;
    }

    return array_values($keys);
}

/**
 * The licenses one device ($kind 'asset') or user ('contact') holds a seat
 * on - not archived - with each license's seats in use and the keys handed to
 * this holder. For the Licenses tab on the asset and contact pages and quick looks.
 */
function softwareHeldBy($kind, $holder_id) {
    global $mysqli;
    $holder_id = intval($holder_id);
    if ($kind === 'asset') {
        [$seat_table, $holder_column, $assign_table] = ['software_assets', 'asset_id', 'software_key_asset_assignments'];
    } elseif ($kind === 'contact') {
        [$seat_table, $holder_column, $assign_table] = ['software_contacts', 'contact_id', 'software_key_contact_assignments'];
    } else {
        return [];
    }

    $sql = mysqli_query($mysqli, "SELECT software.software_id, software_name, software_version, software_type, software_license_type,
            software_seats, software_expire, software_key, software_client_id, vendor_name,
            " . softwareSeatsUsedSql('software.software_id') . " AS seats_used,
            (SELECT GROUP_CONCAT(k.software_key ORDER BY k.software_key_id SEPARATOR '\n') FROM software_keys AS k
                JOIN $assign_table AS a ON a.software_key_id = k.software_key_id
                WHERE k.software_key_software_id = software.software_id AND a.$holder_column = $holder_id) AS held_keys
        FROM $seat_table AS h
        JOIN software ON software.software_id = h.software_id
        LEFT JOIN vendors ON vendor_id = software_vendor_id
        WHERE h.$holder_column = $holder_id AND software_archived_at IS NULL
        ORDER BY software_name ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['held_keys'] = $row['held_keys'] !== null ? explode("\n", $row['held_keys']) : [];
        $rows[] = $row;
    }
    return $rows;
}

/**
 * The Licenses tab on the asset and contact pages: each license this device
 * or user holds, opening its quick look, with its seats, renewal and the key
 * to install - theirs if one was handed to them, otherwise the license key.
 * $rows from softwareHeldBy().
 */
function softwareHeldTable($rows, $kind, $holder_id, $can_edit) {
    $holder_id = intval($holder_id);
    $html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="text-nowrap table-light"><tr>'
        . '<th>License</th><th>Seats</th><th>Renews</th><th>Key</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $software_id = intval($row['software_id']);
        $renewal = softwareRenewal($row['software_expire']);
        $sub = implode(' · ', array_filter([$row['software_license_type'], $row['software_version']]));
        $keys = $row['held_keys'];
        $key_html = '';
        foreach ($keys as $key) {
            $key_html .= '<div>' . softwareKeyHtml($key) . '</div>';
        }
        if (!$keys && $row['software_key']) {
            $key_html = softwareKeyHtml($row['software_key']) . '<div class="small text-body-secondary">License key</div>';
        }
        $renew_html = $renewal
            ? escapeHtml($renewal['date']) . '<div class="small text-' . ($renewal['tone'] === 'success' ? 'body-secondary' : $renewal['tone'] . '-emphasis') . '">' . escapeHtml($renewal['text']) . '</div>'
            : '<span class="text-body-secondary">-</span>';
        $unlink = $can_edit
            ? '<a href="post.php?unlink_item&amp;from=' . $kind . '&amp;id=' . $holder_id . '&amp;to=software&amp;other=' . $software_id . '&amp;csrf_token=' . escapeHtml($_SESSION['csrf_token'] ?? '') . '" class="btn btn-sm btn-light confirm-link" title="Take off this license" data-confirm-text="Frees the seat, and any key it had."><i class="fas fa-fw fa-unlink"></i></a>'
            : '';
        $html .= '<tr>'
            . '<td><div class="d-flex align-items-center gap-2">' . softwareTile($row['software_type'], 'sm') . '<div class="min-w-0">'
            . '<a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software.php?id=' . $software_id . '">' . escapeHtml($row['software_name']) . '</a>'
            . ($sub !== '' ? '<div class="small text-body-secondary">' . escapeHtml($sub) . '</div>' : '')
            . '</div></div></td>'
            . '<td>' . softwareSeatBar(softwareSeats($row['seats_used'], $row['software_seats'])) . '</td>'
            . '<td class="text-nowrap">' . $renew_html . '</td>'
            . '<td class="itflow-license-key-cell">' . ($key_html !== '' ? $key_html : '<span class="text-body-secondary">-</span>') . '</td>'
            . '<td class="text-end">' . $unlink . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** The same, as the short list in the asset and contact quick looks. */
function softwareHeldList($rows) {
    $html = '<ul class="list-unstyled mb-0 itflow-links">';
    foreach ($rows as $row) {
        $seats = softwareSeats($row['seats_used'], $row['software_seats']);
        $renewal = softwareRenewal($row['software_expire']);
        $detail = implode(' · ', array_filter([
            $row['software_license_type'],
            $seats['state'] === 'unlimited' ? '' : $seats['used'] . ' / ' . $seats['total'] . ' seats',
            $renewal ? $renewal['text'] : '',
        ]));
        $key = $row['held_keys'][0] ?? $row['software_key'];
        $tone = $seats['state'] === 'over' || ($renewal && $renewal['state'] === 'expired') ? 'danger-emphasis' : ($renewal && $renewal['state'] === 'soon' ? 'warning-emphasis' : 'body-secondary');
        $html .= '<li class="itflow-link-row">' . softwareTile($row['software_type'], 'sm')
            . '<div class="min-w-0 flex-grow-1"><div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/software/software.php?id=' . intval($row['software_id']) . '">' . escapeHtml($row['software_name']) . '</a></div>'
            . ($detail !== '' ? '<div class="small text-' . $tone . ' text-truncate">' . escapeHtml($detail) . '</div>' : '') . '</div>'
            . ($key ? '<span class="small text-nowrap">' . softwareKeyHtml($key) . '</span>' : '')
            . '</li>';
    }
    return $html . '</ul>';
}

/**
 * Of $ids, the devices ($kind 'asset') or users ('contact') that belong to
 * $client_id and are not archived - what a form may put on a license.
 */
function softwareClientIds($kind, $ids, $client_id) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    $client_id = intval($client_id);
    if (!$ids || !$client_id) {
        return [];
    }
    [$table, $id_column, $client_column, $archived_column] = $kind === 'asset'
        ? ['assets', 'asset_id', 'asset_client_id', 'asset_archived_at']
        : ['contacts', 'contact_id', 'contact_client_id', 'contact_archived_at'];
    $sql = mysqli_query($mysqli, "SELECT $id_column FROM $table WHERE $id_column IN (" . implode(',', $ids) . ") AND $client_column = $client_id AND $archived_column IS NULL");
    $valid = [];
    while ($row = mysqli_fetch_row($sql)) {
        $valid[] = intval($row[0]);
    }
    return $valid;
}

/** Take back any of this license's keys handed to a device or user that has lost its seat. */
function softwareFreeKeys($software_id, $kind, $holder_id) {
    global $mysqli;
    $software_id = intval($software_id);
    $holder_id = intval($holder_id);
    [$assign_table, $holder_column] = $kind === 'asset'
        ? ['software_key_asset_assignments', 'asset_id']
        : ['software_key_contact_assignments', 'contact_id'];
    mysqli_query($mysqli, "DELETE a FROM $assign_table AS a JOIN software_keys AS k ON k.software_key_id = a.software_key_id
        WHERE k.software_key_software_id = $software_id AND a.$holder_column = $holder_id");
}

/**
 * Make a license's devices and users exactly these - adding the new ones and
 * dropping the rest (their keys go back to the pool). Only the license's own
 * client's, unarchived records are taken; archived holders are left alone.
 * Returns the names added and removed, for the log.
 */
function softwareSyncSeats($software_id, $client_id, $asset_ids, $contact_ids) {
    global $mysqli;
    $software_id = intval($software_id);

    $changes = ['added' => [], 'removed' => []];
    $kinds = [
        'asset' => ['software_assets', 'assets', 'asset_id', 'asset_name', 'asset_archived_at', $asset_ids],
        'contact' => ['software_contacts', 'contacts', 'contact_id', 'contact_name', 'contact_archived_at', $contact_ids],
    ];
    foreach ($kinds as $kind => [$seat_table, $table, $id_column, $name_column, $archived_column, $wanted]) {
        $wanted = softwareClientIds($kind, $wanted, $client_id);

        $current = [];
        $sql = mysqli_query($mysqli, "SELECT s.$id_column, $name_column FROM $seat_table AS s JOIN $table AS t ON t.$id_column = s.$id_column
            WHERE s.software_id = $software_id AND t.$archived_column IS NULL");
        while ($row = mysqli_fetch_row($sql)) {
            $current[intval($row[0])] = $row[1];
        }

        foreach (array_diff($wanted, array_keys($current)) as $id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO $seat_table SET software_id = $software_id, $id_column = $id");
            if (mysqli_affected_rows($mysqli) > 0) {
                $changes['added'][] = getFieldById($table, $id, $name_column);
            }
        }
        foreach (array_diff(array_keys($current), $wanted) as $id) {
            mysqli_query($mysqli, "DELETE FROM $seat_table WHERE software_id = $software_id AND $id_column = $id");
            softwareFreeKeys($software_id, $kind, $id);
            $changes['removed'][] = $current[$id];
        }
    }
    return $changes;
}

/** One line for the log about seats a save changed - '' when it changed none. */
function softwareSeatChangeText($changes) {
    $parts = [];
    if ($changes['added']) {
        $parts[] = 'added ' . implode(', ', $changes['added']);
    }
    if ($changes['removed']) {
        $parts[] = 'removed ' . implode(', ', $changes['removed']);
    }
    return $parts ? ' (seats: ' . implode('; ', $parts) . ')' : '';
}

/** A warning to add to the alert when a license has gone over its seats, or ''. */
function softwareSeatWarning($software_id) {
    $row = softwareRow($software_id);
    if (!$row) {
        return '';
    }
    $seats = softwareSeats($row['seats_used'], $row['software_seats']);
    if ($seats['state'] !== 'over') {
        return '';
    }
    // Raw name - flash messages are escaped when shown (alertMessageHtml)
    return ' - <strong>' . $row['software_name'] . '</strong> is now over its seats, ' . $seats['used'] . ' of ' . $seats['total'] . ' in use';
}

/** Add keys to a license, one per line of $text - blank lines and keys it already has are skipped. Returns how many were added. */
function softwareAddKeys($software_id, $text) {
    global $mysqli;
    $software_id = intval($software_id);

    $existing = [];
    $sql = mysqli_query($mysqli, "SELECT software_key FROM software_keys WHERE software_key_software_id = $software_id");
    while ($row = mysqli_fetch_row($sql)) {
        $existing[strtolower($row[0])] = true;
    }

    $added = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $key = mb_substr(trim($line), 0, 400);
        if ($key === '' || isset($existing[strtolower($key)])) {
            continue;
        }
        $existing[strtolower($key)] = true;
        mysqli_query($mysqli, "INSERT INTO software_keys SET software_key = '" . escapeSql($key) . "', software_key_software_id = $software_id");
        $added++;
    }
    return $added;
}

/** Date from a form - Y-m-d or null. */
function softwareFormDate($value) {
    $value = trim((string) $value);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

/**
 * The license fields a New / Edit form posts, as a SQL SET list - only the
 * client's own vendor, real dates, a whole number of seats (blank is no
 * limit). Null when there is no name. Fields missing from the post are blank,
 * never a PHP warning.
 */
function softwareFormSet($post, $client_id) {
    $text = function ($field, $length = 200) use ($post) {
        $value = trim((string) ($post[$field] ?? ''));
        return "'" . escapeSql($length ? mb_substr($value, 0, $length) : $value) . "'";
    };

    $name = trim((string) ($post['name'] ?? ''));
    if ($name === '') {
        return null;
    }
    $seats = trim((string) ($post['seats'] ?? ''));
    $purchase = softwareFormDate($post['purchase'] ?? '');
    $expire = softwareFormDate($post['expire'] ?? '');

    $fields = [
        'software_name' => $text('name'),
        'software_version' => $text('version'),
        'software_description' => $text('description', 0),
        'software_type' => $text('type'),
        'software_license_type' => $text('license_type'),
        'software_key' => $text('key'),
        'software_seats' => $seats === '' ? 'NULL' : max(0, intval($seats)),
        'software_purchase_reference' => $text('purchase_reference'),
        'software_purchase' => $purchase ? "'$purchase'" : 'NULL',
        'software_expire' => $expire ? "'$expire'" : 'NULL',
        'software_notes' => $text('notes', 0),
        'software_favorite' => empty($post['favorite']) ? 0 : 1,
        'software_vendor_id' => assetClientOwnedId('vendor', $post['vendor'] ?? 0, $client_id),
    ];
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return implode(', ', $set);
}

/**
 * A renewal date moved on by a term ('1m', '3m', '6m', '1y', '2y', '3y') -
 * from the current date, or from today if there is none or it has passed.
 * Null for a term it does not know.
 */
function softwareRenewalExtended($expire, $term) {
    $intervals = ['1m' => 'P1M', '3m' => 'P3M', '6m' => 'P6M', '1y' => 'P1Y', '2y' => 'P2Y', '3y' => 'P3Y'];
    if (!isset($intervals[$term])) {
        return null;
    }
    $today = new DateTime('today');
    $from = softwareFormDate(substr((string) $expire, 0, 10));
    $base = $from ? new DateTime($from) : $today;
    if ($base < $today) {
        $base = $today;
    }
    return $base->add(new DateInterval($intervals[$term]))->format('Y-m-d');
}

/** The renewal terms the Renew modal offers. */
const SOFTWARE_RENEWAL_TERMS = ['1m' => '1 month', '3m' => '3 months', '6m' => '6 months', '1y' => '1 year', '2y' => '2 years', '3y' => '3 years'];
