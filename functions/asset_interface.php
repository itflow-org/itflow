<?php

/*
 * ITFlow - asset interfaces: the ports and network cards of an asset, the
 * network and address on each, and what each is plugged into.
 *
 * An interface's IP is an address, DHCP, or nothing (a switch port). Saved
 * with a network, the address must be inside it; two devices on one address
 * is allowed (a floating address, a mistake being documented) but said. The
 * network and the interface it connects to must be the same client's, and a
 * connection is one-to-one: connecting a port that was connected elsewhere
 * moves it. The network page reads these with networkAddresses()
 * (functions/network.php).
 */

/** Asset types drawn as a row of ports on the asset page, not only a table. */
const INTERFACE_PORT_MAP_TYPES = ['Switch', 'Firewall/Router', 'Access Point'];

/** One interface by id, with its asset, network and what it is connected to - raw, or null. */
function interfaceRow($interface_id) {
    global $mysqli;
    $interface_id = intval($interface_id);
    if (!$interface_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_interfaces.*, asset_id, asset_name, asset_type, asset_client_id, asset_archived_at,
            network_name, network, network_type, network_archived_at
        FROM asset_interfaces
        JOIN assets ON asset_id = interface_asset_id
        LEFT JOIN networks ON network_id = interface_network_id AND interface_network_id > 0
        WHERE interface_id = $interface_id LIMIT 1"));
    if (!$row) {
        return null;
    }
    $row['link'] = interfaceLinks([$interface_id])[$interface_id] ?? null;
    return $row;
}

/**
 * For post handlers: the interface a request names, after the module and
 * client checks - or back with an error. Sets the global $client_id.
 */
function interfaceForRequest($interface_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = interfaceRow($interface_id);
    if (!$row) {
        flashAlert('That interface no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['asset_client_id']);
    enforceClientAccess();
    return $row;
}

/** For modals: the same, or the modal's error. */
function interfaceForModal($interface_id, $level = 2) {
    global $client_id;
    if (!networkCan($level)) {
        echo json_encode(['error' => 'Your role cannot change interfaces.']);
        exit;
    }
    $row = interfaceRow($interface_id);
    if (!$row) {
        echo json_encode(['error' => 'That interface no longer exists.']);
        exit;
    }
    $client_id = intval($row['asset_client_id']);
    if (!hasClientAccess($client_id)) {
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    return $row;
}

/** The asset interfaces are being added to, for a post handler - after the same checks. */
function interfaceAssetForRequest($asset_id, $level = 2) {
    global $mysqli, $client_id;
    enforceUserPermission('module_support', $level);
    $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_client_id FROM assets WHERE asset_id = " . intval($asset_id) . " LIMIT 1"));
    if (!$asset) {
        flashAlert('That asset no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($asset['asset_client_id']);
    enforceClientAccess();
    return $asset;
}

/** The same, for a modal. */
function interfaceAssetForModal($asset_id) {
    global $mysqli, $client_id;
    if (!networkCan(2)) {
        echo json_encode(['error' => 'Your role cannot change interfaces.']);
        exit;
    }
    $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_client_id FROM assets WHERE asset_id = " . intval($asset_id) . " LIMIT 1"));
    if (!$asset) {
        echo json_encode(['error' => 'That asset no longer exists.']);
        exit;
    }
    $client_id = intval($asset['asset_client_id']);
    if (!hasClientAccess($client_id)) {
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    return $asset;
}

/** The interfaces a bulk action names (interface_ids[]) that this agent may change - [id => row]. */
function interfacesForBulk($level = 2) {
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['interface_ids'] ?? []))) as $interface_id) {
        $row = interfaceRow($interface_id);
        if ($row && hasClientAccess($row['asset_client_id'])) {
            $rows[$interface_id] = $row;
        }
    }
    return $rows;
}

/**
 * What some interfaces are connected to - [interface_id => the other end:
 * interface_id, interface_name, interface_description, asset_id, asset_name,
 * asset_type, asset_archived_at].
 */
function interfaceLinks(array $interface_ids) {
    global $mysqli;
    $interface_ids = array_values(array_filter(array_map('intval', $interface_ids)));
    if (!$interface_ids) {
        return [];
    }
    $in = implode(',', $interface_ids);
    $links = [];
    $sql = mysqli_query($mysqli, "SELECT l.interface_a_id, l.interface_b_id, i.interface_id, i.interface_name, i.interface_description, a.asset_id, a.asset_name, a.asset_type, a.asset_archived_at
        FROM asset_interface_links l
        JOIN asset_interfaces i ON i.interface_id = IF(l.interface_a_id IN ($in), l.interface_b_id, l.interface_a_id)
        JOIN assets a ON a.asset_id = i.interface_asset_id
        WHERE l.interface_a_id IN ($in) OR l.interface_b_id IN ($in)
        ORDER BY l.interface_link_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach ([intval($row['interface_a_id']), intval($row['interface_b_id'])] as $end) {
            if (in_array($end, $interface_ids, true) && $end !== intval($row['interface_id']) && !isset($links[$end])) {
                $links[$end] = $row;
            }
        }
    }
    return $links;
}

/**
 * Connect an interface to another, or to nothing ($other_id 0). One cable per
 * port: whatever either end was connected to before is let go.
 */
function interfaceConnect($interface_id, $other_id) {
    global $mysqli;
    $interface_id = intval($interface_id);
    $other_id = intval($other_id);
    $ends = array_filter([$interface_id, $other_id]);
    if (!$ends) {
        return;
    }
    $in = implode(',', $ends);
    mysqli_query($mysqli, "DELETE FROM asset_interface_links WHERE interface_a_id IN ($in) OR interface_b_id IN ($in)");
    if ($interface_id && $other_id && $interface_id !== $other_id) {
        mysqli_query($mysqli, "INSERT INTO asset_interface_links SET interface_a_id = $interface_id, interface_b_id = $other_id");
    }
}

/** The interface another can be connected to - the same client's, on a different asset, not archived - or 0. */
function interfaceConnectTarget($other_id, $client_id, $asset_id) {
    global $mysqli;
    $other_id = intval($other_id);
    if (!$other_id) {
        return 0;
    }
    $ok = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_interfaces JOIN assets ON asset_id = interface_asset_id
        WHERE interface_id = $other_id AND asset_client_id = " . intval($client_id) . " AND asset_id <> " . intval($asset_id) . "
        AND interface_archived_at IS NULL AND asset_archived_at IS NULL LIMIT 1"));
    return $ok ? $other_id : 0;
}

/** Make an interface the asset's primary - the one its address shows by on lists. */
function interfaceMakePrimary($interface_id, $asset_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_primary = IF(interface_id = " . intval($interface_id) . ", 1, 0) WHERE interface_asset_id = " . intval($asset_id));
}

/** The interface types set up in Admin > Categories - [name => [color, icon]]. */
function interfaceTypes() {
    global $mysqli;
    static $types = null;
    if ($types === null) {
        $types = [];
        $sql = mysqli_query($mysqli, "SELECT category_name, category_color, category_icon FROM categories WHERE category_type = 'network_interface' AND category_archived_at IS NULL ORDER BY category_order ASC, category_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $types[$row['category_name']] = [$row['category_color'], $row['category_icon']];
        }
    }
    return $types;
}

/** "Trunk 1, 10, 20-29" from what was typed - numbers and ranges tidied, anything else kept as written. */
function interfaceVlansText($text) {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/(\d)\s*[-–]\s*(\d)/u', '$1-$2', $text);
    if (!preg_match('/^(?:(trunk|native|access|tagged|untagged)\s*:?\s*)?([\d\s,\-–]+)$/i', $text, $match)) {
        return mb_substr($text, 0, 200);
    }
    $parts = [];
    foreach (preg_split('/\s*,\s*|\s+/', trim($match[2])) as $part) {
        $part = str_replace('–', '-', $part);
        if ($part === '' || $part === '-') {
            continue;
        }
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $range)) {
            $parts[] = intval($range[1]) . '-' . intval($range[2]);
        } elseif (ctype_digit($part)) {
            $parts[] = (string) intval($part);
        } else {
            return mb_substr($text, 0, 200);
        }
    }
    return mb_substr(trim(($match[1] ? ucfirst(strtolower($match[1])) . ' ' : '') . implode(', ', $parts)), 0, 200);
}

/**
 * The fields of New Interface and Edit Interface, checked, as a SET clause -
 * or '' with $error. $warnings collects what is allowed but worth saying (the
 * address is on another device too). IP mode is 'static' (the address typed),
 * 'dhcp' or 'none'. Both are for flashAlert() - raw text with <strong>.
 */
function interfaceFormSet($post, $asset, $interface_id, &$error = '', &$warnings = []) {
    global $mysqli;
    $client_id = intval($asset['asset_client_id']);
    $asset_id = intval($asset['asset_id']);
    $interface_id = intval($interface_id);
    $warnings = [];

    $name = trim((string) ($post['name'] ?? ''));
    if ($name === '') {
        $error = 'An interface needs a name or port number.';
        return '';
    }
    $name_sql = escapeSql(mb_substr($name, 0, 200));
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_name = '$name_sql' AND interface_id <> $interface_id AND interface_archived_at IS NULL LIMIT 1"))) {
        $error = $asset['asset_name'] . ' already has an interface called <strong>' . $name . '</strong>.';
        return '';
    }

    $network_id = intval(assetClientOwnedId('network', $post['network_id'] ?? 0, $client_id));
    $network = $network_id ? networkRow($network_id) : null;

    $mode = $post['ip_mode'] ?? 'static';
    $ip = trim((string) ($post['ip'] ?? ''));
    if ($mode === 'dhcp' || strtoupper($ip) === 'DHCP') {
        $ip = 'DHCP';
    } elseif ($mode === 'none' || $ip === '') {
        $ip = '';
    } else {
        $ip_normal = normalizeIpAddress($ip);
        if ($ip_normal === false) {
            $error = '<strong>' . $ip . '</strong> is not an IP address.';
            return '';
        }
        $ip = $ip_normal;
        if ($network && networkSubnetInfo($network['network']) && !isIpInSubnet($ip, $network['network'])) {
            $error = '<strong>' . $ip . '</strong> is outside ' . $network['network_name'] . ' (' . $network['network'] . ').';
            return '';
        }
        if ($network_id) {
            $clash = interfaceIpClash($ip, $network_id, $interface_id, $asset_id);
            if ($clash) {
                $warnings[] = $ip . ' is also on <strong>' . $clash . '</strong>';
            }
        }
    }

    $mac = normalizeMacAddress($post['mac'] ?? '');
    if ($mac === false) {
        $error = '<strong>' . trim($post['mac']) . '</strong> is not a MAC address - it is six pairs, like 00:1A:2B:3C:4D:5E.';
        return '';
    }

    $other = [];
    foreach (['nat_ip' => 'The NAT address', 'ipv6' => 'The IPv6 address'] as $field => $label) {
        $value = trim((string) ($post[$field] ?? ''));
        if ($value !== '') {
            $normal = normalizeIpAddress($value);
            if ($normal === false || ($field === 'ipv6' && strpos($normal, ':') === false)) {
                $error = $label . ' <strong>' . $value . '</strong> is not ' . ($field === 'ipv6' ? 'an IPv6 address.' : 'an IP address.');
                return '';
            }
            $value = $normal;
        }
        $other[$field] = $value;
    }

    $type = trim((string) ($post['type'] ?? ''));

    return "interface_name = '$name_sql',
        interface_description = '" . escapeSql(mb_substr(trim((string) ($post['description'] ?? '')), 0, 200)) . "',
        interface_type = '" . escapeSql(mb_substr($type, 0, 50)) . "',
        interface_mac = '" . escapeSql($mac) . "',
        interface_ip = '" . escapeSql($ip) . "',
        interface_nat_ip = '" . escapeSql($other['nat_ip']) . "',
        interface_ipv6 = '" . escapeSql($other['ipv6']) . "',
        interface_vlans = '" . escapeSql(interfaceVlansText($post['vlans'] ?? '')) . "',
        interface_notes = '" . escapeSql(trim((string) ($post['notes'] ?? ''))) . "',
        interface_network_id = $network_id";
}

/** Another device with this address on this network - its name and port, or ''. */
function interfaceIpClash($ip, $network_id, $interface_id = 0, $asset_id = 0) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name, interface_name FROM asset_interfaces
        JOIN assets ON asset_id = interface_asset_id
        WHERE interface_network_id = " . intval($network_id) . " AND interface_ip = '" . escapeSql($ip) . "'
        AND interface_id <> " . intval($interface_id) . " AND asset_id <> " . intval($asset_id) . "
        AND interface_archived_at IS NULL AND asset_archived_at IS NULL LIMIT 1"));
    return $row ? $row['asset_name'] . ' ' . $row['interface_name'] : '';
}

/**
 * The client's networks for an interface's picker - the ones not archived,
 * and the one it is on. Rows with network_id, network_name, network, type.
 */
function interfaceNetworkOptions($client_id, $current_id = 0) {
    global $mysqli;
    $options = [];
    $sql = mysqli_query($mysqli, "SELECT network_id, network_name, network, network_type, network_description, network_vlan, network_gateway, network_dhcp_range, network_archived_at, location_name
        FROM networks LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
        WHERE network_client_id = " . intval($client_id) . " AND (network_archived_at IS NULL OR network_id = " . intval($current_id) . ")
        ORDER BY network_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['network_type'] = networkType($row);
        $options[] = $row;
    }
    return $options;
}

/** A network in a picker - its type icon in its colour, and its subnet, VLAN and site under the name. */
function selectOptionNetwork($row) {
    $style = NETWORK_TYPES[networkType($row)] ?? NETWORK_TYPES['LAN'];
    return selectOptionData([
        'icon' => 'fas ' . $style[0],
        'color' => networkToneColor($style[1]),
        'description' => implode(' · ', array_filter([$row['network'], $row['network_vlan'] ? 'VLAN ' . $row['network_vlan'] : '', $row['location_name'] ?? ''])),
        'keywords' => $row['network_type'] ?? '',
    ]);
}

/** The colour behind a tone's tile, for a picker's icon badge. */
function networkToneColor($tone) {
    return [
        'workstation' => '#3d72bb', 'server' => '#6d5fc4', 'virtual' => '#9a5cc6', 'network' => '#23897a', 'firewall' => '#c04e4e',
        'wireless' => '#1b8fc2', 'phone' => '#b8862f', 'peripheral' => '#5b6f8a', 'other' => '#6c757d',
    ][$tone] ?? '#6c757d';
}

/**
 * What an interface can be connected to: every interface of the client's
 * other assets (not archived), grouped by asset, with what each is connected
 * to now - picking one that is connected elsewhere moves the cable.
 * [asset_id => ['asset' => row, 'interfaces' => rows with link_name]].
 */
function interfaceConnectionOptions($client_id, $asset_id, $interface_id = 0) {
    global $mysqli;
    $groups = [];
    $sql = mysqli_query($mysqli, "SELECT interface_id, interface_name, interface_description, interface_type, asset_id, asset_name, asset_type
        FROM asset_interfaces JOIN assets ON asset_id = interface_asset_id
        WHERE asset_client_id = " . intval($client_id) . " AND asset_id <> " . intval($asset_id) . " AND asset_archived_at IS NULL AND interface_archived_at IS NULL
        ORDER BY asset_name ASC, asset_id ASC");
    $ids = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $groups[intval($row['asset_id'])]['asset'] = ['asset_id' => $row['asset_id'], 'asset_name' => $row['asset_name'], 'asset_type' => $row['asset_type']];
        $groups[intval($row['asset_id'])]['interfaces'][] = $row;
        $ids[] = intval($row['interface_id']);
    }
    $links = interfaceLinks($ids);
    foreach ($groups as &$group) {
        foreach ($group['interfaces'] as &$row) {
            $link = $links[intval($row['interface_id'])] ?? null;
            $row['link_name'] = $link && intval($link['interface_id']) !== intval($interface_id) ? $link['asset_name'] . ' ' . $link['interface_name'] : '';
        }
        unset($row);
        usort($group['interfaces'], function ($a, $b) {
            return strnatcasecmp($a['interface_name'], $b['interface_name']);
        });
    }
    unset($group);
    return $groups;
}

/** An asset wherever an interface's other end is shown: its tile and name, opening its quick look. */
function interfaceAssetRef($asset_id, $asset_name, $asset_type, $archived = false) {
    return '<span class="itflow-contact-ref"><span class="itflow-asset-icon itflow-asset-icon-xs itflow-asset-tone-' . assetTypeTone($asset_type) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($asset_type) . '"></i></span>'
        . '<a class="ajax-modal text-truncate' . ($archived ? ' text-decoration-line-through text-body-secondary' : '') . '" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=' . intval($asset_id) . '">' . escapeHtml(html_entity_decode((string) $asset_name, ENT_QUOTES, 'UTF-8')) . '</a></span>';
}

/** An interface's address as shown: the address in monospace, a DHCP badge, or a dash. */
function interfaceIpHtml($ip) {
    $ip = trim((string) $ip);
    if ($ip === '') {
        return '<span class="text-body-secondary">-</span>';
    }
    if (strtoupper($ip) === 'DHCP') {
        return '<span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal">DHCP</span>';
    }
    return '<span class="font-monospace">' . escapeHtml($ip) . '</span>';
}

/** Sort interfaces as their ports are numbered - Gi1/0/2 before Gi1/0/10, the primary first. Keys are kept. */
function interfaceSort(array &$rows) {
    uasort($rows, function ($a, $b) {
        return intval($b['interface_primary'] ?? 0) <=> intval($a['interface_primary'] ?? 0) ?: strnatcasecmp($a['interface_name'], $b['interface_name']);
    });
}

/** The interface names a "New interfaces" run makes: prefix, then each number from start, padded. */
function interfaceSeriesNames($prefix, $start, $count, $pad) {
    $names = [];
    $start = max(0, intval($start));
    $count = max(0, min(128, intval($count)));
    $pad = max(0, min(4, intval($pad)));
    for ($number = $start; $number < $start + $count; $number++) {
        $names[] = $prefix . str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }
    return $names;
}
