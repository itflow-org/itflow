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

/** A brand icon for the operating system, where one is recognisable (Font Awesome 5 class). */
function assetOsIcon($os) {
    $os = strtolower((string) $os);
    $icons = [
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
    foreach ($icons as $needle => $icon) {
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

/** Icon for a link by its scheme. */
function assetLinkIcon($url) {
    $scheme = strtolower((string) parse_url((string) $url, PHP_URL_SCHEME));
    $icons = [
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
    return $icons[$scheme] ?? 'fas fa-external-link-alt';
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
