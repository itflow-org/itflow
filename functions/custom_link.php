<?php

/*
 * Custom links - your own links added to ITFlow's menus (Admin > Custom
 * Links): the agent menu, the top bar (as an icon), the client portal's menu,
 * the admin menu or the reports menu. Each has a name, the address it goes
 * to, a line about it (shown when the link is hovered), an icon, whether it
 * opens in a new tab, and its place in its menu. Archived ones are kept but
 * not shown. The handlers are admin/post/custom_link.php; the menus read
 * customLinksFor().
 */

/** Where a link can show - [location => label, icon, what it is]. */
function customLinkLocations() {
    return [
        1 => ['label' => 'Agent menu', 'icon' => 'fa-bars', 'about' => 'The menu down the left of every agent page, under Tools'],
        2 => ['label' => 'Top bar', 'icon' => 'fa-window-maximize', 'about' => 'An icon in the bar along the top of every agent page - its name shows on hover'],
        3 => ['label' => 'Client portal', 'icon' => 'fa-user-friends', 'about' => 'The client portal\'s menu, for every contact who signs in'],
        4 => ['label' => 'Admin menu', 'icon' => 'fa-user-shield', 'about' => 'The menu down the left of the admin pages, for admins only'],
        5 => ['label' => 'Reports menu', 'icon' => 'fa-chart-line', 'about' => 'The menu down the left of the reports'],
    ];
}

/* Icons offered for a link - the ones links tend to want first, then the category window's. Any Font Awesome solid icon's name works. */
const CUSTOM_LINK_ICONS = [
    'external-link-alt', 'link', 'globe', 'book', 'question-circle', 'headset', 'life-ring', 'tachometer-alt', 'chart-line', 'chart-pie',
    'video', 'calendar-alt', 'download', 'upload', 'home', 'cloud', 'shield-alt', 'lock', 'key', 'server',
];

/** The icons the link window offers, with $keep (the link's own) first when it isn't among them. */
function customLinkIconChoices($keep = '') {
    $icons = array_values(array_unique(array_merge(CUSTOM_LINK_ICONS, CATEGORY_ICONS)));
    $keep = categoryIcon($keep);
    if ($keep !== '' && !in_array($keep, $icons, true)) {
        array_unshift($icons, $keep);
    }
    return $icons;
}

/** A link's icon as drawn - its own, or the external link arrow when it has none. */
function customLinkIcon($icon) {
    $icon = categoryIcon($icon);
    return $icon !== '' ? $icon : 'external-link-alt';
}

/*
 * What is wrong with an address a link would go to - '' when nothing. A page
 * of ITFlow ("/agent/clients.php"), a web address, an email (mailto:), a
 * phone number (tel:), or one of the remote tools and shares asset links
 * open (rdp:, ssh:, smb: and the like - escapeUrl()). Never javascript:,
 * data: or anything else that would run in the page.
 */
function customLinkUriProblem($uri) {
    $uri = trim((string) $uri);
    if ($uri === '') {
        return 'Add the address it goes to.';
    }
    if (mb_strlen($uri) > 500) {
        return 'That address is longer than 500 characters.';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $uri)) {
        return 'That address has a line break or another control character in it.';
    }
    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $uri, $m)) {
        $allowed = ['http', 'https', 'mailto', 'tel', 'file', 'ftp', 'ftps', 'sftp', 'dav', 'webdav', 'caldav', 'carddav', 'ssh', 'telnet', 'smb', 'rdp', 'vnc',
            'rustdesk', 'anydesk', 'connectwise', 'splashtop', 'sip', 'sips', 'ldap', 'ldaps'];
        if (!in_array(strtolower($m[1]), $allowed, true)) {
            return 'A link can\'t start with "' . $m[1] . ':" - use a web address (https://...), a page of ITFlow (/agent/...), mailto: or tel:.';
        }
    }
    return '';
}

/** The address a menu link goes to - as saved when it may be a link (customLinkUriProblem()), else '#', so nothing saved before the check can run. Raw - escape it. */
function customLinkHref($uri) {
    $uri = trim((string) $uri);
    return customLinkUriProblem($uri) === '' ? $uri : '#';
}

/** A link's row - null when there is none. */
function customLinkRow($custom_link_id) {
    global $mysqli;
    $custom_link_id = intval($custom_link_id);
    if (!$custom_link_id) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT custom_link_id, custom_link_name, custom_link_description, custom_link_uri, custom_link_new_tab, custom_link_icon,
            custom_link_location, custom_link_order, custom_link_created_at, custom_link_updated_at, custom_link_archived_at
        FROM custom_links WHERE custom_link_id = $custom_link_id LIMIT 1")) ?: null;
}

/*
 * The links a menu shows, in their order - $location from customLinkLocations().
 * Read once a page. Each row with 'href' (customLinkHref()) and 'icon'
 * (customLinkIcon()) added, both raw.
 */
function customLinksFor($location) {
    global $mysqli;
    static $by_location = null;
    if ($by_location === null) {
        $by_location = [];
        $sql = mysqli_query($mysqli, "SELECT custom_link_id, custom_link_name, custom_link_description, custom_link_uri, custom_link_new_tab, custom_link_icon, custom_link_location
            FROM custom_links WHERE custom_link_archived_at IS NULL ORDER BY custom_link_order ASC, custom_link_name ASC");
        while ($sql && $row = mysqli_fetch_assoc($sql)) {
            $row['href'] = customLinkHref($row['custom_link_uri']);
            $row['icon'] = customLinkIcon($row['custom_link_icon']);
            $by_location[intval($row['custom_link_location'])][] = $row;
        }
    }
    return $by_location[intval($location)] ?? [];
}

/*
 * A link's fields from a form, cleaned - name, description, uri, new_tab,
 * icon and location (one of customLinkLocations(), the agent menu when it
 * isn't). Raw: escape them for SQL with customLinkSetSql().
 */
function customLinkFromInput(array $in) {
    $location = intval($in['location'] ?? 1);
    return [
        'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 200),
        'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 500),
        'uri' => trim((string) ($in['uri'] ?? '')),
        'new_tab' => !empty($in['new_tab']) ? 1 : 0,
        'icon' => categoryIcon($in['icon'] ?? ''),
        'location' => isset(customLinkLocations()[$location]) ? $location : 1,
    ];
}

/** What is wrong with a link's fields - '' when nothing. */
function customLinkProblem(array $f) {
    if ($f['name'] === '') {
        return 'Give the link a name.';
    }
    return customLinkUriProblem($f['uri']);
}

/** The SQL SET list for a link's fields (customLinkFromInput()). */
function customLinkSetSql(array $f) {
    return "custom_link_name = '" . escapeSql($f['name']) . "', custom_link_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL')
        . ", custom_link_uri = '" . escapeSql($f['uri']) . "', custom_link_new_tab = " . intval($f['new_tab'])
        . ", custom_link_icon = '" . escapeSql($f['icon']) . "', custom_link_location = " . intval($f['location']);
}

/** The place after the last link in a menu. */
function customLinkNextOrder($location) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(custom_link_order), 0) FROM custom_links WHERE custom_link_location = " . intval($location)))[0] ?? 0) + 1;
}

/** Add a link, at the end of its menu - its id. */
function customLinkCreate(array $f) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO custom_links SET " . customLinkSetSql($f) . ", custom_link_order = " . customLinkNextOrder($f['location']));
    return mysqli_insert_id($mysqli);
}

/*
 * Save a link's fields - one moved to another menu goes to the end of it.
 * What changed, as audit log phrases ("address from x to y") - raw.
 */
function customLinkUpdate(array $row, array $f) {
    global $mysqli;
    $order = intval($row['custom_link_location']) !== $f['location'] ? ", custom_link_order = " . customLinkNextOrder($f['location']) : '';
    mysqli_query($mysqli, "UPDATE custom_links SET " . customLinkSetSql($f) . "$order WHERE custom_link_id = " . intval($row['custom_link_id']));
    $locations = customLinkLocations();
    $changes = [];
    $was = [
        'name' => (string) $row['custom_link_name'], 'description' => (string) $row['custom_link_description'], 'uri' => (string) $row['custom_link_uri'],
        'new_tab' => intval($row['custom_link_new_tab']), 'icon' => categoryIcon($row['custom_link_icon']), 'location' => intval($row['custom_link_location']),
    ];
    foreach (['name' => 'name', 'uri' => 'address', 'description' => 'description', 'icon' => 'icon'] as $key => $label) {
        if ($was[$key] !== $f[$key]) {
            $changes[] = "$label from " . ($was[$key] !== '' ? $was[$key] : 'none') . ' to ' . ($f[$key] !== '' ? $f[$key] : 'none');
        }
    }
    if ($was['location'] !== $f['location']) {
        $changes[] = 'moved from the ' . ($locations[$was['location']]['label'] ?? 'menu ' . $was['location']) . ' to the ' . $locations[$f['location']]['label'];
    }
    if ($was['new_tab'] !== $f['new_tab']) {
        $changes[] = $f['new_tab'] ? 'opens in a new tab' : 'opens in the same tab';
    }
    return $changes;
}

/** A copy of a link, named "... (copy)", at the end of the same menu - its id. */
function customLinkCopy(array $row) {
    $f = customLinkFromInput([
        'name' => mb_substr((string) $row['custom_link_name'], 0, 193) . ' (copy)', 'description' => $row['custom_link_description'], 'uri' => $row['custom_link_uri'],
        'new_tab' => $row['custom_link_new_tab'], 'icon' => $row['custom_link_icon'], 'location' => $row['custom_link_location'],
    ]);
    return customLinkCreate($f);
}

/*
 * Put a menu's links in this order - the ids as listed, then the rest (the
 * archived ones) after them in the order they had. Numbered from 1. How
 * many moved.
 */
function customLinkReorder($location, array $ids) {
    global $mysqli;
    $location = intval($location);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT custom_link_id, custom_link_order FROM custom_links WHERE custom_link_location = $location
        ORDER BY custom_link_archived_at IS NOT NULL, custom_link_order ASC, custom_link_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['custom_link_id'])] = intval($row['custom_link_order']);
    }
    $ordered = [];
    foreach ($ids as $id) {
        $id = intval($id);
        if (isset($rows[$id]) && !in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    foreach (array_keys($rows) as $id) {
        if (!in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    $moved = 0;
    foreach ($ordered as $position => $id) {
        if ($rows[$id] !== $position + 1) {
            mysqli_query($mysqli, "UPDATE custom_links SET custom_link_order = " . ($position + 1) . " WHERE custom_link_id = $id");
            $moved++;
        }
    }
    return $moved;
}

/** The current links of a menu, in order - their ids. */
function customLinkMenuIds($location) {
    global $mysqli;
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT custom_link_id FROM custom_links WHERE custom_link_location = " . intval($location) . " AND custom_link_archived_at IS NULL
        ORDER BY custom_link_order ASC, custom_link_name ASC");
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    return $ids;
}

/*
 * A link as a line of a side menu - the agent, admin and reports menus: its
 * icon and name, what it is for on hover, and a small arrow when it opens in
 * a new tab. Lit up on the page it goes to, for a page of ITFlow.
 */
function customLinkNavItemHtml(array $row) {
    $new_tab = intval($row['custom_link_new_tab']) === 1;
    $path = (string) parse_url($row['href'], PHP_URL_PATH);
    $active = !preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $row['href']) && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $row['href'])
        && $path !== '' && basename($path) === basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $about = trim((string) $row['custom_link_description']);
    return '<li class="nav-item"><a href="' . escapeHtml($row['href']) . '" class="nav-link' . ($active ? ' active' : '') . '"'
        . ($new_tab ? ' target="_blank" rel="noopener noreferrer"' : '') . ($about !== '' ? ' title="' . escapeHtml($about) . '"' : '') . '>'
        . '<i class="nav-icon fas fa-' . escapeHtml($row['icon']) . '"></i>'
        . '<p>' . escapeHtml($row['custom_link_name']) . '</p>'
        . ($new_tab ? '<i class="fas fa-external-link-alt ms-auto ps-2 small opacity-50" title="Opens in a new tab" aria-hidden="true"></i>' : '')
        . '</a></li>';
}

/** A link's address as a list shows it - without https:// and a trailing slash. */
function customLinkUriDisplay($uri) {
    return rtrim(preg_replace('#^https?://#i', '', trim((string) $uri)), '/');
}
