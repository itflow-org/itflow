<?php

/*
 * ITFlow - networks, the addresses on them, and the IP and subnet maths
 *
 * A network is one subnet at a client (a LAN, the guest wireless, the voice
 * VLAN, an internet uplink) - its CIDR, VLAN, gateway, DNS, the range DHCP
 * hands out, and the site it is at. What is on it comes from two places: the
 * addresses documented against it (network_ips - fixed addresses, DHCP
 * reservations, addresses held back, virtual / floating addresses) and the
 * asset interfaces set to it. networkAddressPlan() lays the two together,
 * address by address, with the free ranges between, and says what is wrong:
 * two devices on one address, a fixed address inside the DHCP range, a
 * reservation whose MAC is not the device's, an interface outside the subnet.
 *
 * The maths works on IPv4 and IPv6 alike by comparing the packed binary form,
 * so there is no ip2long/32-bit path to go wrong; the address plan's free
 * ranges are IPv4 only (an IPv6 /64 has no useful "next free").
 */

/**
 * What a network can be for: [type => icon, tile tone, what it means].
 * Shown as the tile on every list and page.
 */
const NETWORK_TYPES = [
    'LAN' => ['fa-network-wired', 'network', 'The main wired network - workstations, printers'],
    'Wireless' => ['fa-wifi', 'wireless', 'Staff wireless'],
    'Guest' => ['fa-user-friends', 'wireless', 'Visitors - kept apart from everything else'],
    'Voice' => ['fa-phone', 'phone', 'Phones and the phone system'],
    'Servers' => ['fa-server', 'server', 'Servers and hypervisors'],
    'Storage' => ['fa-hdd', 'virtual', 'SAN, iSCSI and backup traffic'],
    'Management' => ['fa-tools', 'peripheral', 'Switch, firewall and iDRAC / iLO management'],
    'IoT' => ['fa-microchip', 'other', 'Cameras, door access, HVAC - what should not talk to the LAN'],
    'DMZ' => ['fa-shield-alt', 'firewall', 'Public-facing servers'],
    'VPN' => ['fa-lock', 'workstation', 'Remote access and site-to-site tunnels'],
    'WAN' => ['fa-globe', 'firewall', 'An internet connection - the circuit, the provider and its public addresses'],
    'Other' => ['fa-project-diagram', 'other', ''],
];

/** What a documented address is: [type => icon, tone, what it means, what it is called]. */
const NETWORK_IP_TYPES = [
    'Static' => ['fa-thumbtack', 'secondary', 'Set on the device itself', 'Fixed'],
    'Reservation' => ['fa-bookmark', 'info', 'DHCP hands this device the same address every time', 'DHCP reservation'],
    'Reserved' => ['fa-ban', 'warning', 'Held back - not in use, do not hand it out', 'Held back'],
    'Virtual' => ['fa-clone', 'primary', 'A floating address - a cluster, VRRP / CARP or a load balancer', 'Floating'],
];

/** May this agent do this with networks? 1 is to see them, 2 to change them, 3 to delete them. */
function networkCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/**
 * The words that say what a network is for - [type => [pattern for the name,
 * pattern for the description (null: the name's)]], checked in order. The
 * same words database update 2.8.9 used to fill in the types of existing
 * networks; New Network uses them to pick the type as the name is typed
 * (agent/js/network_form.js). A description says "no internet access" as often
 * as it describes an uplink, so WAN takes stronger words there.
 */
const NETWORK_TYPE_RULES = [
    'Guest' => ['guest', null],
    'WAN' => ['wan|internet|uplink|circuit|broadband|fib(er|re)|starlink|(^|[^a-z])(isp|dsl|lte|5g)([^a-z]|$)', '(^|[^a-z])wan([^a-z]|$)|circuit|broadband|starlink|(^|[^a-z])(isp|dsl)([^a-z]|$)'],
    'Voice' => ['voip|voice|phone|pbx|telephon|(^|[^a-z])sip([^a-z]|$)', null],
    'Management' => ['mgmt|manage|ipmi|idrac|(^|[^a-z])(oob|ilo|bmc)([^a-z]|$)', null],
    'DMZ' => ['dmz', null],
    'IoT' => ['camera|cctv|hvac|badge|access control|building|(^|[^a-z])(iot|nvr)([^a-z]|$)', null],
    'Storage' => ['storage|iscsi|backup|(^|[^a-z])(san|nfs)([^a-z]|$)', null],
    'VPN' => ['vpn|tunnel|ipsec|wireguard', null],
    'Servers' => ['server|datacent|cluster|(^|[^a-z])srv', null],
    'Wireless' => ['wireless|wifi|wi-fi|wlan|ssid', null],
];

/** The type a network's name (or failing that, its description) says it is - for a network saved without one. */
function networkGuessType($name, $description = '') {
    foreach ([0 => $name, 1 => $description] as $which => $text) {
        $text = mb_strtolower(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'));
        if (trim($text) === '') {
            continue;
        }
        foreach (NETWORK_TYPE_RULES as $type => $patterns) {
            $pattern = $patterns[$which] ?? $patterns[0];
            if (preg_match('/' . $pattern . '/', $text)) {
                return $type;
            }
        }
    }
    return 'LAN';
}

/** A network's type - the one saved, or the one its name says. */
function networkType($row) {
    $type = (string) ($row['network_type'] ?? '');
    return isset(NETWORK_TYPES[$type]) ? $type : networkGuessType($row['network_name'] ?? '', $row['network_description'] ?? '');
}

/** The tile for a network type - the asset tile in that type's colour and icon. */
function networkTile($type, $size = '') {
    $style = NETWORK_TYPES[$type] ?? NETWORK_TYPES['LAN'];
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size" : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-' . $style[1] . '" aria-hidden="true" title="' . escapeHtml($type) . '"><i class="fas fa-fw ' . $style[0] . '"></i></span>';
}

/** A network's type as a label - its icon in its colour, then the name. */
function networkTypeLabel($type) {
    $style = NETWORK_TYPES[$type] ?? NETWORK_TYPES['LAN'];
    return '<span class="itflow-net-type itflow-asset-tone-' . $style[1] . '"><i class="fas fa-fw ' . $style[0] . '"></i>' . escapeHtml($type) . '</span>';
}

/** Where the network page is - inside its client. */
function networkUrl($network_id, $client_id) {
    return 'network.php?client_id=' . intval($client_id) . '&network_id=' . intval($network_id);
}

/**
 * A network wherever a page mentions one: its tile and name, opening its page
 * - or its quick look with 'quick' => true. 'type' picks the tile; 'archived'
 * strikes it through. Takes the name raw or already escaped.
 */
function networkLink($network_id, $name, $client_id, $options = []) {
    $network_id = intval($network_id);
    $label = escapeHtml(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    if (!empty($options['quick'])) {
        $link = "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/network/network.php?id=$network_id\">$label</a>";
    } else {
        $link = '<a class="' . $class . '" href="' . escapeHtml(networkUrl($network_id, $client_id)) . '">' . $label . '</a>';
    }
    return '<span class="itflow-contact-ref">' . networkTile($options['type'] ?? 'LAN', 'xs') . $link . '</span>';
}

/** One network by id, with its client, site and provider - raw, or null. */
function networkRow($network_id) {
    global $mysqli;
    $network_id = intval($network_id);
    if (!$network_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT networks.*, client_name, location_name, location_archived_at,
            vendor_name, vendor_phone, vendor_phone_country_code, vendor_extension, vendor_account_number, vendor_hours, vendor_archived_at
        FROM networks
        LEFT JOIN clients ON client_id = network_client_id
        LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
        LEFT JOIN vendors ON vendor_id = network_vendor_id AND network_vendor_id > 0
        WHERE network_id = $network_id LIMIT 1"));
    return $row ?: null;
}

/**
 * For post handlers: the network a request names, after the module and client
 * checks - or back with an error if it is gone. Sets the global $client_id.
 */
function networkForRequest($network_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = networkRow($network_id);
    if (!$row) {
        flashAlert('That network no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['network_client_id']);
    enforceClientAccess();
    return $row;
}

/** For modals: the same, or the modal's error. */
function networkForModal($network_id, $level = 1) {
    global $client_id;
    if (!networkCan($level)) {
        echo json_encode(['error' => 'Your role cannot ' . ($level > 1 ? 'change' : 'see') . ' networks.']);
        exit;
    }
    $row = networkRow($network_id);
    if (!$row) {
        echo json_encode(['error' => 'That network no longer exists.']);
        exit;
    }
    $client_id = intval($row['network_client_id']);
    if (!hasClientAccess($client_id)) {
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    return $row;
}

/** The networks a bulk action names (network_ids[]) that this agent may change - [id => row]. */
function networksForBulk($level) {
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['network_ids'] ?? []))) as $network_id) {
        $row = networkRow($network_id);
        if ($row && hasClientAccess($row['network_client_id'])) {
            $rows[$network_id] = $row;
        }
    }
    return $rows;
}

/** Archive a network - its addresses stay, and the interfaces on it keep pointing at it. */
function networkArchive($row) {
    global $mysqli, $session_name;
    $network_id = intval($row['network_id']);
    mysqli_query($mysqli, "UPDATE networks SET network_archived_at = NOW() WHERE network_id = $network_id");
    logAudit("Network", "Archive", "$session_name archived network " . escapeSql($row['network_name']), intval($row['network_client_id']), $network_id);
}

function networkRestore($row) {
    global $mysqli, $session_name;
    $network_id = intval($row['network_id']);
    mysqli_query($mysqli, "UPDATE networks SET network_archived_at = NULL WHERE network_id = $network_id");
    logAudit("Network", "Restore", "$session_name restored network " . escapeSql($row['network_name']), intval($row['network_client_id']), $network_id);
}

/** Delete for good - its documented addresses go with it (ON DELETE CASCADE); the interfaces on it are left on no network. */
function networkDelete($row) {
    global $mysqli, $session_name;
    $network_id = intval($row['network_id']);
    mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_network_id = 0 WHERE interface_network_id = $network_id");
    mysqli_query($mysqli, "DELETE FROM networks WHERE network_id = $network_id");
    logAudit("Network", "Delete", "$session_name deleted network " . escapeSql($row['network_name']), intval($row['network_client_id']));
}

/** One documented address by id, with its network - raw, or null. */
function networkIpRow($ip_id) {
    global $mysqli;
    $ip_id = intval($ip_id);
    if (!$ip_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT network_ips.*, network_id, network_name, network, network_client_id, network_dhcp_range, network_gateway
        FROM network_ips JOIN networks ON network_id = ip_network_id WHERE ip_id = $ip_id LIMIT 1"));
    return $row ?: null;
}

/** For post handlers: the documented address a request names, after the checks. Sets the global $client_id. */
function networkIpForRequest($ip_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = networkIpRow($ip_id);
    if (!$row) {
        flashAlert('That address is no longer documented.', 'error');
        redirect();
    }
    $client_id = intval($row['network_client_id']);
    enforceClientAccess();
    return $row;
}

/**
 * The fields of a documented address besides the address itself - type,
 * hostname, MAC, description - checked, as a SET clause; '' with $error. A
 * reservation is for a MAC, so it needs one.
 */
function networkIpFieldsSet($post, &$error = '') {
    $type = isset(NETWORK_IP_TYPES[$post['type'] ?? '']) ? $post['type'] : 'Static';
    $mac = normalizeMacAddress($post['mac'] ?? '');
    if ($mac === false) {
        $error = '<strong>' . trim((string) $post['mac']) . '</strong> is not a MAC address - it is six pairs, like 00:1A:2B:3C:4D:5E.';
        return '';
    }
    if ($type === 'Reservation' && $mac === '' && empty($post['allow_no_mac'])) {
        $error = 'A DHCP reservation is for a device - add the MAC address it is reserved for.';
        return '';
    }
    return "ip_type = '$type',
        ip_hostname = '" . escapeSql(mb_substr(trim((string) ($post['hostname'] ?? '')), 0, 200)) . "',
        ip_mac = " . ($mac === '' ? 'NULL' : "'$mac'") . ",
        ip_description = '" . escapeSql(mb_substr(trim((string) ($post['description'] ?? '')), 0, 200)) . "'";
}

/** A documented address's type as a small pill - its icon and name. */
function networkIpTypePill($type) {
    $style = NETWORK_IP_TYPES[$type] ?? NETWORK_IP_TYPES['Static'];
    return '<span class="badge rounded-pill bg-' . $style[1] . '-subtle text-' . $style[1] . '-emphasis fw-normal" title="' . escapeHtml($style[2]) . '"><i class="fas ' . $style[0] . ' me-1"></i>' . $style[3] . '</span>';
}

/*
 * ---------- subnet maths ----------
 */

/** An IPv4 address as a number, or null. */
function ipv4ToInt($ip) {
    $bin = @inet_pton(trim((string) $ip));
    return ($bin !== false && strlen($bin) === 4) ? unpack('N', $bin)[1] : null;
}

/** A number back to an IPv4 address. */
function intToIpv4($int) {
    return inet_ntop(pack('N', intval($int)));
}

/**
 * Everything worth knowing about a subnet: its canonical CIDR, the network
 * and last address, the first and last usable host, how many addresses and
 * hosts, and the dotted mask (IPv4). Null when it is not a subnet.
 * An IPv4 /31 is two usable hosts (RFC 3021), a /32 one.
 */
function networkSubnetInfo($cidr) {
    $parsed = parseSubnet((string) $cidr);
    if ($parsed === false) {
        return null;
    }
    $prefix = $parsed['prefix'];
    $network = $parsed['network'];
    $last = $network;
    for ($i = 0; $i < $parsed['bytes']; $i++) {
        $bits_left = $prefix - ($i * 8);
        if ($bits_left >= 8) {
            continue;
        }
        $host_mask = $bits_left <= 0 ? 0xFF : (0xFF >> $bits_left);
        $last[$i] = chr(ord($last[$i]) | $host_mask);
    }
    $info = [
        'family' => $parsed['bytes'] === 4 ? 4 : 6,
        'prefix' => $prefix,
        'cidr' => inet_ntop($network) . '/' . $prefix,
        'network' => inet_ntop($network),
        'last' => inet_ntop($last),
        'network_bin' => $network,
        'last_bin' => $last,
        'mask' => '',
        'size' => null,
        'usable' => null,
        'first_host' => inet_ntop($network),
        'last_host' => inet_ntop($last),
    ];
    if ($info['family'] === 4) {
        $start = unpack('N', $network)[1];
        $end = unpack('N', $last)[1];
        $info['size'] = $end - $start + 1;
        $info['mask'] = intToIpv4($prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
        if ($prefix <= 30) {
            $info['first_host'] = intToIpv4($start + 1);
            $info['last_host'] = intToIpv4($end - 1);
            $info['usable'] = $info['size'] - 2;
        } else {
            $info['usable'] = $info['size'];
        }
    }
    return $info;
}

/** "254 hosts · 255.255.255.0" - a subnet's size in words, or ''. */
function networkSizeText($info) {
    if (!$info) {
        return '';
    }
    if ($info['family'] === 6) {
        return 'IPv6 /' . $info['prefix'];
    }
    return number_format($info['usable']) . ' host' . ($info['usable'] === 1 ? '' : 's') . ' · ' . $info['mask'];
}

/**
 * The DHCP / assignable range written on a network - "192.168.1.100-192.168.1.200",
 * "192.168.1.100 - 200", "192.168.1.100 to .200" - as [first, last] numbers
 * inside the subnet. IPv4 only; null when it is not a range (it can hold a
 * note, "Static addressing only").
 */
function networkParseRange($text, $info) {
    $text = trim((string) $text);
    if ($text === '' || !$info || $info['family'] !== 4) {
        return null;
    }
    $parts = preg_split('/\s*(?:-|–|—|\bto\b)\s*/u', $text, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $start = normalizeIpAddress($parts[0]);
    if ($start === false || ipv4ToInt($start) === null) {
        return null;
    }
    $end_text = ltrim(trim($parts[1]), '.');
    if (normalizeIpAddress($end_text) === false || ipv4ToInt($end_text) === null) {
        // Only the host part - borrow the rest from the start address
        $start_octets = explode('.', $start);
        $end_octets = explode('.', $end_text);
        if (count($end_octets) > 3 || !ctype_digit(str_replace('.', '', $end_text))) {
            return null;
        }
        $end_text = implode('.', array_merge(array_slice($start_octets, 0, 4 - count($end_octets)), $end_octets));
    }
    $first = ipv4ToInt($start);
    $last = ipv4ToInt($end_text);
    if ($first === null || $last === null || $first > $last) {
        return null;
    }
    if (!isIpInSubnet(intToIpv4($first), $info['cidr']) || !isIpInSubnet(intToIpv4($last), $info['cidr'])) {
        return null;
    }
    return [$first, $last];
}

/** A run of addresses as written - "192.168.1.3 - 9" when only the last part differs, both in full otherwise. */
function networkRangeText($start, $end) {
    if ($start === $end) {
        return $start;
    }
    $cut = strrpos($start, '.');
    if ($cut !== false && strpos($start, ':') === false && substr($start, 0, $cut) === substr($end, 0, strrpos($end, '.'))) {
        return $start . ' - ' . substr($end, $cut + 1);
    }
    return $start . ' - ' . $end;
}

/** Do two subnets share any address? */
function networkSubnetsOverlap($a, $b) {
    if (!$a || !$b || $a['family'] !== $b['family']) {
        return false;
    }
    $prefix = min($a['prefix'], $b['prefix']);
    return applyIpMask($a['network_bin'], $prefix) === applyIpMask($b['network_bin'], $prefix);
}

/** The client's other networks whose subnet overlaps this one - the same range at another site, or a mistake. */
function networkOverlaps(array $network) {
    global $mysqli;
    $info = networkSubnetInfo($network['network']);
    if (!$info) {
        return [];
    }
    $overlaps = [];
    $sql = mysqli_query($mysqli, "SELECT network_id, network_name, network, network_type, network_description, network_location_id, location_name FROM networks
        LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
        WHERE network_client_id = " . intval($network['network_client_id']) . " AND network_archived_at IS NULL AND network_id <> " . intval($network['network_id'] ?? 0));
    while ($row = mysqli_fetch_assoc($sql)) {
        if (networkSubnetsOverlap($info, networkSubnetInfo($row['network']))) {
            $overlaps[] = $row;
        }
    }
    return $overlaps;
}

/** A MAC address in one form - 00:1A:2B:3C:4D:5E - from any of the usual ones; '' when empty, false when it is not one. */
function normalizeMacAddress($mac) {
    $mac = trim((string) $mac);
    if ($mac === '') {
        return '';
    }
    if (!preg_match('/^[0-9a-f]{2}([-:. ]?[0-9a-f]{2}){5}$/i', $mac) && !preg_match('/^[0-9a-f]{4}\.[0-9a-f]{4}\.[0-9a-f]{4}$/i', $mac)) {
        return false;
    }
    $hex = preg_replace('/[^0-9a-f]/i', '', $mac);
    return strlen($hex) === 12 ? strtoupper(implode(':', str_split($hex, 2))) : false;
}

/*
 * ---------- what is on a network ----------
 */

/**
 * The addresses on some networks: the documented ones and the interfaces of
 * assets (not archived) set to each - [network_id => ['documented' => rows,
 * 'interfaces' => rows]].
 */
function networkAddresses(array $network_ids) {
    global $mysqli;
    $network_ids = array_values(array_filter(array_map('intval', $network_ids)));
    $out = [];
    foreach ($network_ids as $id) {
        $out[$id] = ['documented' => [], 'interfaces' => []];
    }
    if (!$network_ids) {
        return $out;
    }
    $in = implode(',', $network_ids);
    $sql = mysqli_query($mysqli, "SELECT ip_id, ip_address, ip_type, ip_hostname, ip_mac, ip_description, ip_network_id, ip_created_at, ip_updated_at
        FROM network_ips WHERE ip_network_id IN ($in)");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[intval($row['ip_network_id'])]['documented'][] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT interface_id, interface_name, interface_description, interface_type, interface_ip, interface_mac, interface_nat_ip, interface_ipv6,
            interface_vlans, interface_primary, interface_network_id, asset_id, asset_name, asset_type, asset_status, asset_client_id
        FROM asset_interfaces
        JOIN assets ON asset_id = interface_asset_id
        WHERE interface_network_id IN ($in) AND interface_archived_at IS NULL AND asset_archived_at IS NULL
        ORDER BY asset_name, interface_primary DESC, interface_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[intval($row['interface_network_id'])]['interfaces'][] = $row;
    }
    return $out;
}

/**
 * How full some networks are: [network_id => used addresses, hosts (IPv4),
 * percent, documented count, devices]. Used counts each host address in the
 * subnet once - the gateway, documented, or on an interface.
 */
function networkUsage(array $networks, $addresses = null) {
    $addresses = $addresses ?? networkAddresses(array_keys($networks));
    $usage = [];
    foreach ($networks as $id => $network) {
        $info = networkSubnetInfo($network['network']);
        $used = [];
        $assets = [];
        // A host address in the subnet - not its network or broadcast address
        $counts = function ($ip) use ($info) {
            if ($ip === false) {
                return false;
            }
            if (!$info) {
                return true;
            }
            if (!isIpInSubnet($ip, $info['cidr'])) {
                return false;
            }
            return !($info['family'] === 4 && $info['prefix'] <= 30 && ($ip === $info['network'] || $ip === $info['last']));
        };
        $values = [(string) ($network['network_gateway'] ?? '')];
        foreach ($addresses[$id]['documented'] ?? [] as $row) {
            $values[] = $row['ip_address'];
        }
        foreach ($addresses[$id]['interfaces'] ?? [] as $row) {
            $assets[intval($row['asset_id'])] = true;
            $values[] = (string) $row['interface_ip'];
            $values[] = (string) $row['interface_ipv6'];
        }
        foreach ($values as $value) {
            $ip = normalizeIpAddress((string) $value);
            if ($counts($ip)) {
                $used[$ip] = true;
            }
        }
        $hosts = $info && $info['family'] === 4 ? $info['usable'] : null;
        $usage[$id] = [
            'used' => count($used),
            'hosts' => $hosts,
            'percent' => $hosts ? min(100, round(count($used) / $hosts * 100)) : null,
            'documented' => count($addresses[$id]['documented'] ?? []),
            'devices' => count($assets),
        ];
    }
    return $usage;
}

/** How full a network is, as a small bar - "47 of 254" - or the count alone for IPv6 / an unparsed subnet. */
function networkUsageBar($usage) {
    if ($usage['percent'] === null) {
        return '<span class="small text-body-secondary">' . number_format($usage['used']) . ' in use</span>';
    }
    $tone = $usage['percent'] >= 90 ? 'danger' : ($usage['percent'] >= 75 ? 'warning' : 'success');
    return '<div class="itflow-net-usage" title="' . $usage['used'] . ' of ' . number_format($usage['hosts']) . ' addresses in use - ' . $usage['percent'] . '%">'
        . '<div class="progress" role="progressbar" aria-valuenow="' . $usage['percent'] . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-' . $tone . '" style="width: ' . max($usage['used'] ? 3 : 0, $usage['percent']) . '%"></div></div>'
        . '<span class="small text-body-secondary text-nowrap">' . number_format($usage['used']) . ' / ' . number_format($usage['hosts']) . '</span></div>';
}

/**
 * The address plan of one network: every address in use, in order - who has
 * it (documented, and / or on which asset's interface), whether it is the
 * gateway, inside the DHCP range, and what is wrong with it - with the free
 * runs between (small enough IPv4 subnets only) and the DHCP range as one
 * band. Plus the interfaces that cannot be placed: on DHCP, with no address,
 * or with one outside the subnet.
 */
function networkAddressPlan(array $network, $addresses = null) {
    $id = intval($network['network_id']);
    $addresses = $addresses ?? networkAddresses([$id]);
    $documented = $addresses[$id]['documented'] ?? [];
    $interfaces = $addresses[$id]['interfaces'] ?? [];
    $info = networkSubnetInfo($network['network']);
    $pool = networkParseRange($network['network_dhcp_range'] ?? '', $info);
    $gateway = normalizeIpAddress((string) ($network['network_gateway'] ?? ''));

    $plan = ['info' => $info, 'pool' => $pool, 'rows' => [], 'dhcp' => [], 'unaddressed' => [], 'outside' => [], 'next_free' => '', 'problems' => 0, 'free' => null];

    // Every address, once
    $by_ip = [];
    $add = function ($ip) use (&$by_ip, $info, $pool, $gateway) {
        if (!isset($by_ip[$ip])) {
            $bin = inet_pton($ip);
            $int = strlen($bin) === 4 ? unpack('N', $bin)[1] : null;
            $by_ip[$ip] = ['kind' => 'address', 'ip' => $ip, 'bin' => $bin, 'documented' => [], 'interfaces' => [], 'flags' => [],
                'gateway' => $gateway !== false && $ip === $gateway,
                'in_pool' => $pool && $int !== null && $int >= $pool[0] && $int <= $pool[1]];
        }
    };
    foreach ($documented as $row) {
        $ip = normalizeIpAddress($row['ip_address']);
        if ($ip === false) {
            continue;
        }
        $add($ip);
        $by_ip[$ip]['documented'][] = $row;
    }
    foreach ($interfaces as $row) {
        $raw = trim((string) $row['interface_ip']);
        $ip = normalizeIpAddress($raw);
        if (strtoupper($raw) === 'DHCP') {
            $plan['dhcp'][] = $row;
        } elseif ($ip === false) {
            if ($raw === '') {
                $plan['unaddressed'][] = $row;
            } else {
                $plan['outside'][] = $row + ['problem' => 'is not an address'];
            }
        } elseif ($info && !isIpInSubnet($ip, $info['cidr'])) {
            $plan['outside'][] = $row + ['problem' => 'is outside ' . $info['cidr']];
        } else {
            $add($ip);
            $by_ip[$ip]['interfaces'][] = $row;
        }
        // The IPv6 address on the same interface counts as well
        $ip6 = normalizeIpAddress((string) $row['interface_ipv6']);
        if ($ip6 !== false && $info && $info['family'] === 6 && isIpInSubnet($ip6, $info['cidr'])) {
            $add($ip6);
            $by_ip[$ip6]['interfaces'][] = $row;
        }
    }
    if ($gateway !== false && (!$info || isIpInSubnet($gateway, $info['cidr']))) {
        $add($gateway);
    }

    // What is wrong with each
    foreach ($by_ip as $ip => &$entry) {
        $assets = array_unique(array_map(function ($row) {
            return intval($row['asset_id']);
        }, $entry['interfaces']));
        if (count($assets) > 1) {
            $entry['flags'][] = ['danger', 'Conflict - ' . count($assets) . ' devices have this address'];
        }
        if (count($entry['documented']) > 1) {
            $entry['flags'][] = ['warning', 'Documented ' . count($entry['documented']) . ' times'];
        }
        if ($info && !isIpInSubnet($ip, $info['cidr'])) {
            $entry['flags'][] = ['warning', 'Outside ' . $info['cidr'] . ' - documented on the wrong network'];
        } elseif ($info && $info['family'] === 4 && $info['prefix'] <= 30) {
            if ($ip === $info['network']) {
                $entry['flags'][] = ['danger', 'This is the network address - no device can use it'];
            } elseif ($ip === $info['last']) {
                $entry['flags'][] = ['danger', 'This is the broadcast address - no device can use it'];
            }
        }
        foreach ($entry['documented'] as $doc) {
            if ($entry['in_pool'] && in_array($doc['ip_type'], ['Static', 'Virtual'], true)) {
                $entry['flags'][] = ['warning', 'Inside the DHCP range - DHCP can hand it to something else'];
            }
            $doc_mac = normalizeMacAddress($doc['ip_mac'] ?? '');
            if ($doc['ip_type'] === 'Reservation' && $doc_mac) {
                foreach ($entry['interfaces'] as $interface) {
                    $mac = normalizeMacAddress($interface['interface_mac']);
                    if ($mac && $mac !== $doc_mac) {
                        $entry['flags'][] = ['warning', 'Reserved for ' . $doc_mac . ', but ' . $interface['asset_name'] . ' has ' . $mac];
                    }
                }
            }
            if ($doc['ip_type'] === 'Reserved' && $entry['interfaces']) {
                $entry['flags'][] = ['warning', 'Held back, but ' . $entry['interfaces'][0]['asset_name'] . ' is using it'];
            }
        }
        if ($entry['flags']) {
            $plan['problems']++;
        }
    }
    unset($entry);
    $plan['problems'] += count($plan['outside']);

    uasort($by_ip, function ($a, $b) {
        return strlen($a['bin']) <=> strlen($b['bin']) ?: strcmp($a['bin'], $b['bin']);
    });

    // The free runs and the DHCP band - IPv4 subnets up to a /20
    if ($info && $info['family'] === 4 && $info['prefix'] >= 20) {
        $first = ipv4ToInt($info['first_host']);
        $last = ipv4ToInt($info['last_host']);
        $used = [];
        foreach ($by_ip as $entry) {
            $int = strlen($entry['bin']) === 4 ? unpack('N', $entry['bin'])[1] : null;
            if ($int !== null && $int >= $first && $int <= $last) {
                $used[$int] = true;
            }
        }
        $rows = [];
        $entries = array_values($by_ip);
        $pool_shown = false;
        $free_start = null;
        $free_total = 0;
        $flush_free = function ($end) use (&$rows, &$free_start, &$free_total) {
            if ($free_start !== null && $end >= $free_start) {
                $rows[] = ['kind' => 'free', 'start' => intToIpv4($free_start), 'end' => intToIpv4($end), 'count' => $end - $free_start + 1];
                $free_total += $end - $free_start + 1;
            }
            $free_start = null;
        };
        // Addresses outside the usable range (the network or broadcast address) go first or last
        $index = 0;
        while ($index < count($entries) && (strlen($entries[$index]['bin']) !== 4 || unpack('N', $entries[$index]['bin'])[1] < $first)) {
            $rows[] = $entries[$index++];
        }
        for ($int = $first; $int <= $last; $int++) {
            if ($pool && $int >= $pool[0] && $int <= $pool[1]) {
                $flush_free($int - 1);
                if (!$pool_shown) {
                    $in_pool = 0;
                    foreach ($used as $u => $_) {
                        if ($u >= $pool[0] && $u <= $pool[1]) {
                            $in_pool++;
                        }
                    }
                    $rows[] = ['kind' => 'pool', 'start' => intToIpv4($pool[0]), 'end' => intToIpv4($pool[1]), 'count' => $pool[1] - $pool[0] + 1, 'used' => $in_pool];
                    $pool_shown = true;
                }
                if (isset($used[$int])) {
                    $rows[] = $by_ip[intToIpv4($int)];
                }
                continue;
            }
            if (isset($used[$int])) {
                $flush_free($int - 1);
                $rows[] = $by_ip[intToIpv4($int)];
                continue;
            }
            if ($free_start === null) {
                $free_start = $int;
                if ($plan['next_free'] === '') {
                    $plan['next_free'] = intToIpv4($int);
                }
            }
        }
        $flush_free($last);
        while ($index < count($entries)) {
            $int = strlen($entries[$index]['bin']) === 4 ? unpack('N', $entries[$index]['bin'])[1] : null;
            if ($int === null || $int > $last) {
                $rows[] = $entries[$index];
            }
            $index++;
        }
        $plan['rows'] = $rows;
        $plan['free'] = $free_total;
    } else {
        $plan['rows'] = array_values($by_ip);
    }

    return $plan;
}

/**
 * The fields of New Network and Edit Network, checked, as a SET clause - or ''
 * with $error. The subnet must be a real one - typed with a host address in
 * it, it is stored as its network address and $note says so; the gateway and
 * DNS servers must be addresses, the gateway and the DHCP range inside the
 * subnet. The site and the provider must be the client's (or, for the
 * provider, one of ours). $error and $note are for flashAlert() - raw text
 * with <strong>; anything showing them elsewhere runs alertMessageHtml().
 */
function networkFormSet($post, $client_id, &$error = '', &$note = '') {
    global $mysqli;
    $client_id = intval($client_id);

    $name = trim((string) ($post['name'] ?? ''));
    if ($name === '') {
        $error = 'A network needs a name.';
        return '';
    }

    $typed = trim((string) ($post['network'] ?? ''));
    $info = networkSubnetInfo($typed);
    if (!$info) {
        $error = '<strong>' . ($typed ?: 'Nothing') . '</strong> is not a subnet - write it as an address and its prefix, like 192.168.1.0/24.';
        return '';
    }
    $typed_address = trim(explode('/', $typed)[0]);
    if (normalizeIpAddress($typed_address) !== $info['network']) {
        $note = $typed . ' is stored as the subnet it is in, <strong>' . $info['cidr'] . '</strong>.';
    }

    $gateway = trim((string) ($post['gateway'] ?? ''));
    if ($gateway !== '') {
        $gateway_ip = normalizeIpAddress($gateway);
        if ($gateway_ip === false || !isIpInSubnet($gateway_ip, $info['cidr'])) {
            $error = 'The gateway <strong>' . $gateway . '</strong> ' . ($gateway_ip === false ? 'is not an address.' : 'is outside ' . $info['cidr'] . '.');
            return '';
        }
        $gateway = $gateway_ip;
    }

    $dns = [];
    foreach (['primary_dns', 'secondary_dns'] as $field) {
        $value = trim((string) ($post[$field] ?? ''));
        if ($value !== '' && normalizeIpAddress($value) === false) {
            $error = 'The DNS server <strong>' . $value . '</strong> is not an address.';
            return '';
        }
        $dns[$field] = $value === '' ? '' : normalizeIpAddress($value);
    }

    // A range is checked once it looks like one; a note ("Static only") is kept as written
    $range = trim((string) ($post['dhcp_range'] ?? ''));
    if ($range !== '' && preg_match('/\d+\.\d+|:/', $range)) {
        $parsed = networkParseRange($range, $info);
        if (!$parsed && $info['family'] === 4) {
            $error = 'The DHCP range <strong>' . $range . '</strong> is not a range inside ' . $info['cidr'] . ' - write it as 192.168.1.100 - 192.168.1.200.';
            return '';
        }
        if ($parsed) {
            $range = intToIpv4($parsed[0]) . ' - ' . intToIpv4($parsed[1]);
        }
    }

    $vlan = trim((string) ($post['vlan'] ?? ''));
    if ($vlan !== '' && (!ctype_digit($vlan) || intval($vlan) < 1 || intval($vlan) > 4094)) {
        $error = 'A VLAN is a number from 1 to 4094.';
        return '';
    }

    $type = isset(NETWORK_TYPES[$post['type'] ?? '']) ? $post['type'] : networkGuessType($name, $post['description'] ?? '');
    $location_id = $client_id ? intval(assetClientOwnedId('location', $post['location_id'] ?? 0, $client_id)) : 0;
    $vendor_id = intval($post['vendor_id'] ?? 0);
    if ($vendor_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id IN (0, $client_id) LIMIT 1"))) {
        $vendor_id = 0;
    }

    return "network_name = '" . escapeSql(mb_substr($name, 0, 200)) . "',
        network_description = '" . escapeSql(trim((string) ($post['description'] ?? ''))) . "',
        network_type = '$type',
        network_vlan = " . ($vlan === '' ? 'NULL' : intval($vlan)) . ",
        network = '" . escapeSql($info['cidr']) . "',
        network_gateway = '" . escapeSql($gateway) . "',
        network_primary_dns = '" . escapeSql($dns['primary_dns']) . "',
        network_secondary_dns = '" . escapeSql($dns['secondary_dns']) . "',
        network_dhcp_range = '" . escapeSql(mb_substr($range, 0, 200)) . "',
        network_location_id = $location_id,
        network_vendor_id = $vendor_id";
}

/*
 * Canonicalises an address so two spellings of the same thing compare equal -
 * 2001:DB8:0000::0001 and 2001:db8::1 both store as 2001:db8::1. This is what
 * makes the duplicate check (and the UNIQUE index behind it) reliable.
 *
 * Returns false when the value is not an IP address at all. Note that
 * inet_pton() rejects leading-zero IPv4 octets (192.168.001.005), which is
 * deliberate - that notation is read as octal by some resolvers.
 */
function normalizeIpAddress($ip) {

    $ip = trim($ip);

    if ($ip === '') {
        return false;
    }

    /*
     * inet_pton() rejects leading-zero IPv4 octets outright (192.168.010.05),
     * because that notation reads as octal to some resolvers. Nothing here
     * resolves anything - these are documentation records, and a switch or
     * router config screen is where people copy them from, so the zeros get
     * stripped and the row is kept rather than failing the import.
     */
    if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip)) {
        $octets = explode('.', $ip);
        foreach ($octets as $index => $octet) {
            $octets[$index] = ltrim($octet, '0');
            if ($octets[$index] === '') {
                $octets[$index] = '0';
            }
        }
        $ip = implode('.', $octets);
    }

    $bin = @inet_pton($ip);

    if ($bin === false) {
        return false;
    }

    return inet_ntop($bin);

}

/*
 * Parses a subnet into its binary network address and prefix length.
 *
 * Accepts CIDR (192.168.1.0/24, 2001:db8::/64) and the dotted-mask form
 * (192.168.1.0/255.255.255.0) that hand-entered records sometimes carry.
 *
 * Returns ['network' => <masked binary>, 'prefix' => int, 'bytes' => 4|16],
 * or false when the value isn't something we can reason about.
 */
function parseSubnet($subnet) {

    $subnet = trim($subnet);

    if (strpos($subnet, '/') === false) {
        return false;
    }

    list($address, $prefix) = explode('/', $subnet, 2);

    $bin = @inet_pton(trim($address));

    if ($bin === false) {
        return false;
    }

    $bytes = strlen($bin);
    $prefix = trim($prefix);

    // Dotted subnet mask (IPv4 only) - count the leading 1 bits
    if (strpos($prefix, '.') !== false) {

        $mask_bin = @inet_pton($prefix);

        if ($mask_bin === false || strlen($mask_bin) !== 4 || $bytes !== 4) {
            return false;
        }

        $mask_bits = '';
        for ($i = 0; $i < 4; $i++) {
            $mask_bits .= str_pad(decbin(ord($mask_bin[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Must be contiguous - 255.255.254.0 is a mask, 255.0.255.0 is a typo
        if (!preg_match('/^(1*)0*$/', $mask_bits, $match)) {
            return false;
        }

        $prefix = strlen($match[1]);

    } elseif (!ctype_digit($prefix)) {
        return false;
    }

    $prefix = intval($prefix);

    if ($prefix < 0 || $prefix > $bytes * 8) {
        return false;
    }

    return [
        'network' => applyIpMask($bin, $prefix),
        'prefix'  => $prefix,
        'bytes'   => $bytes,
    ];

}

/*
 * Zeroes every bit past the prefix length, giving the network address.
 * Operates on the packed binary form from inet_pton().
 */
function applyIpMask($bin, $prefix) {

    $bytes = strlen($bin);

    for ($i = 0; $i < $bytes; $i++) {

        $bits_left = $prefix - ($i * 8);

        if ($bits_left >= 8) {
            continue;
        }

        if ($bits_left <= 0) {
            $bin[$i] = chr(0);
        } else {
            $bin[$i] = chr(ord($bin[$i]) & ((0xFF << (8 - $bits_left)) & 0xFF));
        }

    }

    return $bin;

}

/*
 * True when the address falls inside the subnet. Families must match - an IPv4
 * address is never inside an IPv6 subnet and vice versa.
 *
 * Returns TRUE when the subnet itself can't be parsed. Networks created before
 * the CIDR field was there can hold anything, and refusing to let someone
 * document addresses on those is worse than not checking them.
 */
function isIpInSubnet($ip, $subnet) {

    $parsed = parseSubnet($subnet);

    if ($parsed === false) {
        return true;
    }

    $bin = @inet_pton(trim($ip));

    if ($bin === false || strlen($bin) !== $parsed['bytes']) {
        return false;
    }

    return applyIpMask($bin, $parsed['prefix']) === $parsed['network'];

}

/*
 * The part of an address a subnet fixes for every host in it, as a display
 * string with its trailing dot - 192.168.1.0/24 gives "192.168.1.".
 *
 * This is what the add/edit form prepends so only the host part is typeable.
 * Whole octets only, so it tracks the prefix: a /16 fixes two, a /26 fixes
 * three (the last octet is still typed, and the range check catches a host
 * outside the block).
 *
 * Returns an empty string when there is nothing useful to fix - IPv6, a prefix
 * under /8, a /32 with no host part at all, or a subnet that won't parse. The
 * form falls back to a plain full-address input in those cases.
 */
function ipSubnetFixedOctets($subnet) {

    $parsed = parseSubnet($subnet);

    if ($parsed === false || $parsed['bytes'] !== 4) {
        return '';
    }

    $fixed = intdiv($parsed['prefix'], 8);

    if ($fixed < 1 || $fixed > 3) {
        return '';
    }

    $octets = explode('.', inet_ntop($parsed['network']));

    return implode('.', array_slice($octets, 0, $fixed)) . '.';

}

/*
 * Turns what was typed into the host part of the form into a full address.
 *
 * A complete address is passed straight through, so pasting 192.168.1.10 into
 * the box still works, as does a CSV that carries full addresses.
 */
function expandIpSuffix($input, $subnet) {

    $input = trim($input);

    if ($input === '') {
        return $input;
    }

    // Already complete - typed in full, pasted, or imported
    if (normalizeIpAddress($input) !== false) {
        return $input;
    }

    $fixed = ipSubnetFixedOctets($subnet);

    if ($fixed === '') {
        return $input;
    }

    // The host part has to supply exactly the octets the prefix doesn't
    if (count(explode('.', $input)) !== 4 - substr_count($fixed, '.')) {
        return $input;
    }

    return $fixed . $input;

}

/*
 * The reverse, for the edit form: the host part of a stored address, or the
 * whole address when the subnet fixes nothing.
 */
function ipSuffixForDisplay($ip, $subnet) {

    $fixed = ipSubnetFixedOctets($subnet);

    // The trailing dot matters - it stops 192.168.1. matching 192.168.10.5
    if ($fixed === '' || strpos($ip, $fixed) !== 0) {
        return $ip;
    }

    return substr($ip, strlen($fixed));

}

/*
 * The two safeguards on a documented IP, in one place so add, edit, CSV import
 * and the live check behind the form can't drift apart: the address must be
 * valid and inside its network's subnet, and the network must not already have
 * it.
 *
 * $ip is rewritten by reference to its canonical, fully-expanded form, so
 * callers store what was checked rather than what was typed.
 *
 * Returns an error message for the user, or an empty string when it's good.
 * Pass the row's own id as $ignore_ip_id when editing so a row doesn't collide
 * with itself.
 *
 * The message is built for flashAlert(), which escapes at render - anything
 * putting it somewhere else has to run it through alertMessageHtml() first.
 */
function checkIpForNetwork(&$ip, $network_id, $ignore_ip_id = 0) {

    global $mysqli;

    $network_id = intval($network_id);
    $subnet = getFieldById('networks', $network_id, 'network');

    $original = trim($ip);

    // Host part only from the form, full address from a paste or a CSV
    $expanded = expandIpSuffix($original, $subnet);

    $normalized = normalizeIpAddress($expanded);

    if ($normalized === false) {
        // Report the expanded attempt, not the raw input - in a /24 the form
        // only takes the host part, so "abc" reads better as 192.168.1.abc
        return "<strong>$expanded</strong> is not a valid IP address";
    }

    $ip = $normalized;

    if (!isIpInSubnet($ip, $subnet)) {
        return "<strong>$ip</strong> is outside <strong>$subnet</strong>";
    }

    $ip_escaped = escapeSql($ip);
    $ignore_ip_id = intval($ignore_ip_id);

    $sql = mysqli_query(
        $mysqli,
        "SELECT ip_id FROM network_ips
        WHERE ip_network_id = $network_id
        AND ip_address = '$ip_escaped'
        AND ip_id != $ignore_ip_id
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) > 0) {
        return "<strong>$ip</strong> is already documented on this network";
    }

    return '';

}
