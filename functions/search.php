<?php

/*
 * Global search.
 *
 * One search behind two surfaces:
 *   - the live search palette in the top bar (press Ctrl+K), which asks
 *     agent/ajax.php?global_search as you type
 *   - the full results page, agent/global_search.php, reached by pressing Enter
 *
 * Both call globalSearch(), so the two can never disagree about what a user is
 * allowed to find. Every group is gated on the module its own list page enforces
 * and runs through clientScopeSql(), the same two checks the record's own page
 * applies.
 *
 * The palette is for getting somewhere: it matches names, numbers, emails,
 * phone numbers, IPs, MACs and serials, which is cheap enough to run on every
 * keystroke. The results page adds $deep, which also searches the long text -
 * ticket details, ticket conversations and document contents.
 *
 * Values come back RAW, not HTML-escaped. The palette renders them with
 * textContent; the results page escapes them at output.
 */

/**
 * The query, prepared once for every group: equality, LIKE (with % and _
 * matched literally, so searching "100%" does not match everything), and the
 * digits of a phone number when the query looks like one.
 */
function globalSearchTerms($raw) {
    // escapeSql() strips tags, so do it first: the length check, the
    // highlighting and the SQL then all agree on what was searched for
    $raw = trim(strip_tags((string) $raw));
    $raw = mb_substr($raw, 0, 100);

    $digits = preg_replace('/\D/', '', $raw);

    return [
        'raw'   => $raw,
        'eq'    => escapeSql($raw),
        'like'  => escapeSql(addcslashes($raw, '%_\\')),
        'phone' => (strlen($digits) >= 3 && preg_match('/^[\d\s().+-]+$/', $raw)) ? $digits : null,
    ];
}

/**
 * A record number typed the way people actually type them: "1042", "#1042",
 * "TCK-1042", "tck1042". The letters only count when they are this record type's
 * own prefix, otherwise "srv01" would match ticket 1.
 */
function globalSearchNumber($raw, $prefix) {
    $prefix = rtrim((string) $prefix, '-');
    $prefix_pattern = $prefix === '' ? '' : '|' . preg_quote($prefix, '/') . '-?';

    if (preg_match('/^(?:#' . $prefix_pattern . ')?\s*0*(\d{1,9})$/i', $raw, $match)) {
        return intval($match[1]);
    }

    return null;
}

/** Plain text around the first match in a long HTML field, for the results page. */
function globalSearchSnippet($html, $raw, $radius = 80) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $position = mb_stripos($text, $raw);

    if ($position === false) {
        return mb_substr($text, 0, $radius * 2) . (mb_strlen($text) > $radius * 2 ? '…' : '');
    }

    $start = max(0, $position - $radius);
    $snippet = mb_substr($text, $start, mb_strlen($raw) + $radius * 2);

    return ($start > 0 ? '…' : '') . $snippet . ($start + mb_strlen($snippet) < mb_strlen($text) ? '…' : '');
}

function globalSearchMoney($amount, $currency_code) {
    global $currency_format;

    if (!$currency_format) {
        return number_format(floatval($amount), 2);
    }

    return numfmt_format_currency($currency_format, floatval($amount), $currency_code ?: 'USD');
}

/** Where a client-scoped record lives: the client's own copy of a page, or the global one. */
function globalSearchClientUrl($page, $client_id, $params = []) {
    if (intval($client_id) > 0) {
        $params = array_merge(['client_id' => intval($client_id)], $params);
    }

    return "/agent/$page" . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Every searchable record type, in display order. A group whose module the
 * user cannot read, or whose feature is switched off, is left out entirely.
 */
function globalSearchGroups($terms, $deep) {
    global $config_ticket_prefix, $config_invoice_prefix, $config_quote_prefix, $config_project_prefix;
    global $config_module_enable_ticketing, $config_module_enable_accounting;

    $eq = $terms['eq'];
    $like = $terms['like'];
    $phone = $terms['phone'];
    $q = $terms['raw'];

    $ticketing = intval($config_module_enable_ticketing) === 1;
    $accounting = intval($config_module_enable_accounting) === 1;

    $groups = [];

    $groups[] = [
        'key' => 'clients', 'label' => 'Clients', 'icon' => 'users', 'module' => 'module_client',
        'select' => 'client_id, client_name, client_type, client_abbreviation',
        'from' => 'clients',
        'where' => "client_archived_at IS NULL
            AND (client_name LIKE '%$like%' OR client_abbreviation LIKE '%$like%' OR client_website LIKE '%$like%' " . tagSearchSql('client', $like, 'clients.client_id') . ")",
        'scope' => 'clients.client_id',
        'tags' => ['client', 'clients.client_id'],
        'order' => "(client_name = '$eq') DESC, (client_name LIKE '$like%') DESC, client_accessed_at DESC, client_name",
        'list_url' => '/agent/clients.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['client_name'],
                'subtitle' => $row['client_type'],
                'url' => '/agent/client_overview.php?client_id=' . intval($row['client_id']),
            ];
        },
    ];

    $ticket_number = globalSearchNumber($q, $config_ticket_prefix);
    $ticket_number_match = $ticket_number !== null ? "OR ticket_number = $ticket_number" : '';
    $ticket_deep_match = $deep ? "OR ticket_details LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'tickets', 'label' => 'Tickets', 'icon' => 'life-ring', 'module' => 'module_support', 'enabled' => $ticketing,
        'select' => 'ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_closed_at,
            client_name, ticket_status_name, ticket_status_color',
        'from' => 'tickets
            LEFT JOIN clients ON ticket_client_id = client_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id',
        'where' => "ticket_archived_at IS NULL
            AND (ticket_subject LIKE '%$like%' OR CONCAT(ticket_prefix, ticket_number) LIKE '%$like%' $ticket_number_match $ticket_deep_match
                " . tagSearchSql('ticket', $like, 'tickets.ticket_id') . ")",
        'scope' => 'ticket_client_id',
        'tags' => ['ticket', 'tickets.ticket_id'],
        'order' => ($ticket_number !== null ? "(ticket_number = $ticket_number) DESC, " : '') . "(ticket_closed_at IS NULL) DESC, ticket_id DESC",
        'exact' => $ticket_number !== null ? "ticket_number = $ticket_number" : null,
        'list_url' => '/agent/tickets.php?' . http_build_query(['state' => 'all', 'q' => $q]),
        'item' => function ($row) {
            return [
                'ref' => $row['ticket_prefix'] . $row['ticket_number'],
                'title' => $row['ticket_subject'],
                'context' => $row['client_name'],
                'badge' => $row['ticket_status_name'] ? ['text' => $row['ticket_status_name'], 'hex' => $row['ticket_status_color']] : null,
                'url' => globalSearchClientUrl('ticket.php', $row['ticket_client_id'], ['ticket_id' => intval($row['ticket_id'])]),
            ];
        },
    ];

    $project_number = globalSearchNumber($q, $config_project_prefix);
    $project_number_match = $project_number !== null ? "OR project_number = $project_number" : '';
    $project_deep_match = $deep ? "OR project_description LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'projects', 'label' => 'Projects', 'icon' => 'project-diagram', 'module' => 'module_support', 'enabled' => $ticketing,
        'select' => 'project_id, project_prefix, project_number, project_name, project_client_id, project_completed_at, project_archived_at, client_name',
        'from' => 'projects LEFT JOIN clients ON project_client_id = client_id',
        'where' => "(project_name LIKE '%$like%' OR CONCAT(project_prefix, project_number) LIKE '%$like%' $project_number_match $project_deep_match)",
        'scope' => 'project_client_id',
        'order' => ($project_number !== null ? "(project_number = $project_number) DESC, " : '') . "(project_completed_at IS NULL AND project_archived_at IS NULL) DESC, project_id DESC",
        'exact' => $project_number !== null ? "project_number = $project_number" : null,
        'list_url' => '/agent/projects.php?' . http_build_query(['show' => 'all', 'q' => $q]),
        'item' => function ($row) {
            $state = $row['project_archived_at'] ? 'Archived' : ($row['project_completed_at'] ? 'Completed' : 'Active');
            return [
                'ref' => $row['project_prefix'] . $row['project_number'],
                'title' => $row['project_name'],
                'context' => intval($row['project_client_id']) ? $row['client_name'] : 'Internal',
                'badge' => ['text' => $state, 'variant' => ['Active' => 'primary', 'Completed' => 'success', 'Archived' => 'secondary'][$state]],
                'url' => '/agent/' . projectUrl($row['project_id'], $row['project_client_id']),
            ];
        },
    ];

    // Stored numbers may or may not carry punctuation, so compare digits to digits
    $contact_phone_match = $phone
        ? "OR REGEXP_REPLACE(contact_phone, '[^0-9]', '') LIKE '%$phone%' OR REGEXP_REPLACE(contact_mobile, '[^0-9]', '') LIKE '%$phone%'"
        : '';
    $groups[] = [
        'key' => 'contacts', 'label' => 'Contacts', 'icon' => 'address-book', 'module' => 'module_client',
        'select' => 'contact_id, contact_name, contact_title, contact_email, contact_photo, contact_client_id, client_name',
        'from' => 'contacts LEFT JOIN clients ON contact_client_id = client_id',
        'where' => "contact_archived_at IS NULL
            AND (contact_name LIKE '%$like%' OR contact_email LIKE '%$like%' OR contact_title LIKE '%$like%' $contact_phone_match
                " . tagSearchSql('contact', $like, 'contacts.contact_id') . ")",
        'scope' => 'contact_client_id',
        'tags' => ['contact', 'contacts.contact_id'],
        'order' => "(contact_name = '$eq') DESC, (contact_name LIKE '$like%') DESC, contact_name",
        'list_url' => '/agent/contacts.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['contact_name'],
                'subtitle' => implode(' · ', array_filter([$row['contact_title'], $row['contact_email']])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('contact.php', $row['contact_client_id'], ['contact_id' => intval($row['contact_id'])]),
                // Drawn in place of the group icon - the person's avatar, as everywhere else
                'avatar' => contactAvatarData($row['contact_client_id'], $row['contact_id'], $row['contact_name'], $row['contact_photo']),
            ];
        },
    ];

    // Sites - by name, street, town or postcode, or the site's phone number
    $location_phone_match = $phone ? "OR location_phone LIKE '%$phone%'" : '';
    $groups[] = [
        'key' => 'locations', 'label' => 'Locations', 'icon' => 'map-marker-alt', 'module' => 'module_client',
        'select' => 'location_id, location_name, location_address, location_city, location_state, location_zip, location_primary, location_client_id, client_name',
        'from' => 'locations LEFT JOIN clients ON location_client_id = client_id',
        'where' => "location_archived_at IS NULL
            AND (location_name LIKE '%$like%' OR location_address LIKE '%$like%' OR location_city LIKE '%$like%' OR location_zip LIKE '%$like%' $location_phone_match
                " . tagSearchSql('location', $like, 'locations.location_id') . ")",
        'scope' => 'location_client_id',
        'tags' => ['location', 'locations.location_id'],
        'order' => "(location_name = '$eq') DESC, (location_name LIKE '$like%') DESC, location_primary DESC, location_name",
        'list_url' => '/agent/locations.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['location_name'],
                'subtitle' => implode(' · ', array_filter([intval($row['location_primary']) ? 'Primary' : '', locationAddressText($row)])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('location.php', $row['location_client_id'], ['location_id' => intval($row['location_id'])]),
            ];
        },
    ];

    // Any interface counts, not just the primary one - the address someone has
    // in hand is as likely to be the iDRAC as the OS NIC. A MAC typed as
    // aabb.ccdd.eeff finds 00:AA:BB... however it was saved.
    $asset_mac = normalizeMacAddress($q);
    $asset_mac_match = $asset_mac ? "OR REPLACE(REPLACE(REPLACE(UPPER(interface_mac), ':', ''), '-', ''), '.', '') = '" . str_replace(':', '', $asset_mac) . "'" : '';
    $groups[] = [
        'key' => 'assets', 'label' => 'Assets', 'icon' => 'desktop', 'module' => 'module_support',
        'select' => 'asset_id, asset_name, asset_type, asset_make, asset_model, asset_client_id, client_name,
            primary_interface.interface_ip AS asset_ip',
        'from' => 'assets
            LEFT JOIN clients ON asset_client_id = client_id
            LEFT JOIN asset_interfaces AS primary_interface
                ON primary_interface.interface_asset_id = asset_id AND primary_interface.interface_primary = 1',
        'where' => "asset_archived_at IS NULL
            AND (asset_name LIKE '%$like%' OR asset_serial LIKE '%$like%' OR asset_type LIKE '%$like%'
                OR asset_make LIKE '%$like%' OR asset_model LIKE '%$like%' OR asset_os LIKE '%$like%'
                OR asset_description LIKE '%$like%'
                OR EXISTS (SELECT 1 FROM asset_interfaces
                    WHERE interface_asset_id = asset_id AND interface_archived_at IS NULL
                    AND (interface_ip LIKE '%$like%' OR interface_nat_ip LIKE '%$like%' OR interface_ipv6 LIKE '%$like%' OR interface_mac LIKE '%$like%' $asset_mac_match))
                " . tagSearchSql('asset', $like, 'assets.asset_id') . ")",
        'scope' => 'asset_client_id',
        'tags' => ['asset', 'assets.asset_id'],
        // An exact address beats every partial one: 192.168.10.1 before 192.168.10.14
        'order' => "EXISTS (SELECT 1 FROM asset_interfaces WHERE interface_asset_id = asset_id
                AND (interface_ip = '$eq' OR interface_nat_ip = '$eq')) DESC,
            (asset_name = '$eq') DESC, (asset_name LIKE '$like%') DESC, asset_name",
        'list_url' => '/agent/assets.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['asset_name'],
                'subtitle' => implode(' · ', array_filter([$row['asset_type'], trim($row['asset_make'] . ' ' . $row['asset_model']), $row['asset_ip']])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('asset.php', $row['asset_client_id'], ['asset_id' => intval($row['asset_id'])]),
            ];
        },
    ];

    // Networks by name, subnet, VLAN, gateway, DNS and site - and an IPv4
    // address finds the networks it is inside (192.168.10.57 finds
    // 192.168.10.0/24), the smallest first
    $ip = normalizeIpAddress($q);
    $ip_eq = $ip !== false && strpos($ip, ':') === false ? escapeSql($ip) : '';
    $contains = $ip_eq === '' ? '(1 = 0)' : "(network REGEXP '^[0-9.]+/[0-9]+$'
        AND (INET_ATON('$ip_eq') >> (32 - CAST(SUBSTRING_INDEX(network, '/', -1) AS UNSIGNED)))
            = (INET_ATON(SUBSTRING_INDEX(network, '/', 1)) >> (32 - CAST(SUBSTRING_INDEX(network, '/', -1) AS UNSIGNED))))";
    $vlan_match = ctype_digit($q) ? "OR network_vlan = " . intval($q) : '';
    $groups[] = [
        'key' => 'networks', 'label' => 'Networks', 'icon' => 'network-wired', 'module' => 'module_support',
        'select' => "network_id, network_name, network_description, network_type, network, network_vlan, network_client_id, client_name, location_name, $contains AS q_contains",
        'from' => 'networks
            LEFT JOIN clients ON network_client_id = client_id
            LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0',
        'where' => "network_archived_at IS NULL
            AND (network_name LIKE '%$like%' OR network_description LIKE '%$like%' OR network_type LIKE '%$like%' OR network LIKE '%$like%'
                OR network_gateway LIKE '%$like%' OR network_primary_dns LIKE '%$like%' OR network_secondary_dns LIKE '%$like%'
                OR network_dhcp_range LIKE '%$like%' $vlan_match OR $contains
                " . tagSearchSql('network', $like, 'networks.network_id') . ")",
        'scope' => 'network_client_id',
        'tags' => ['network', 'networks.network_id'],
        'order' => "(network_name = '$eq') DESC, $contains DESC, CAST(SUBSTRING_INDEX(network, '/', -1) AS UNSIGNED) DESC, (network_name LIKE '$like%') DESC, network_name",
        'list_url' => '/agent/networks.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['network_name'],
                'subtitle' => implode(' · ', array_filter([$row['network'], $row['network_vlan'] ? 'VLAN ' . $row['network_vlan'] : '', networkType($row), $row['location_name']])),
                'context' => $row['client_name'],
                'badge' => intval($row['q_contains']) ? ['text' => 'In this subnet', 'variant' => 'info'] : null,
                'url' => '/agent/' . networkUrl($row['network_id'], $row['network_client_id']),
            ];
        },
    ];

    // Documented addresses - by address, hostname, MAC (in any of its usual
    // forms) and description - opening the network's address plan on it
    $mac = normalizeMacAddress($q);
    $mac_match = $mac ? "OR ip_mac = '$mac'" : '';
    $groups[] = [
        'key' => 'network_ips', 'label' => 'IP Addresses', 'icon' => 'map-pin', 'module' => 'module_support',
        'select' => 'ip_id, ip_address, ip_type, ip_hostname, ip_description, network_id, network_name, network_client_id, client_name',
        'from' => 'network_ips
            JOIN networks ON network_id = ip_network_id
            LEFT JOIN clients ON network_client_id = client_id',
        'where' => "network_archived_at IS NULL
            AND (ip_address LIKE '%$like%' OR ip_hostname LIKE '%$like%' OR ip_mac LIKE '%$like%' OR ip_description LIKE '%$like%' $mac_match)",
        'scope' => 'network_client_id',
        'order' => "(ip_address = '$eq') DESC, (ip_hostname = '$eq') DESC, INET6_ATON(ip_address)",
        'list_url' => '/agent/networks.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['ip_address'] . ($row['ip_hostname'] ? ' - ' . $row['ip_hostname'] : ''),
                'subtitle' => implode(' · ', array_filter([(NETWORK_IP_TYPES[$row['ip_type']] ?? NETWORK_IP_TYPES['Static'])[3], $row['ip_description'], $row['network_name']])),
                'context' => $row['client_name'],
                'url' => '/agent/' . networkUrl($row['network_id'], $row['network_client_id']) . '&' . http_build_query(['q' => $row['ip_address']]) . '#addresses',
            ];
        },
    ];

    // Licenses by name, vendor and order reference - and by key, so a key read
    // off a sticker or a screen finds the license it belongs to
    $groups[] = [
        'key' => 'licenses', 'label' => 'Licenses', 'icon' => 'cube', 'module' => 'module_support',
        'select' => 'software.software_id, software_name, software_version, software_license_type, software_expire, software_client_id, client_name, vendor_name',
        'from' => 'software
            LEFT JOIN clients ON software_client_id = client_id
            LEFT JOIN vendors ON vendor_id = software_vendor_id',
        'where' => "software_archived_at IS NULL
            AND (software_name LIKE '%$like%' OR software_description LIKE '%$like%' OR software_key LIKE '%$like%'
                OR software_purchase_reference LIKE '%$like%' OR vendor_name LIKE '%$like%'
                OR software.software_id IN (SELECT software_key_software_id FROM software_keys WHERE software_key LIKE '%$like%')
                " . tagSearchSql('license', $like, 'software.software_id') . ")",
        'scope' => 'software_client_id',
        'tags' => ['license', 'software.software_id'],
        'order' => "(software_key = '$eq') DESC, (software_name = '$eq') DESC, (software_name LIKE '$like%') DESC, software_name",
        'list_url' => '/agent/software.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $renewal = softwareRenewal($row['software_expire']);
            return [
                'title' => trim($row['software_name'] . ' ' . $row['software_version']),
                'subtitle' => implode(' · ', array_filter([$row['software_license_type'], $row['vendor_name'], $renewal && $renewal['state'] !== 'active' ? $renewal['text'] : ''])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('license.php', $row['software_client_id'], ['software_id' => intval($row['software_id'])]),
            ];
        },
    ];

    // What a client runs on - by name, category and description, and its notes in a deep search
    $service_deep_match = $deep ? "OR service_notes LIKE '%$like%' OR service_backup LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'services', 'label' => 'Services', 'icon' => 'stream', 'module' => 'module_support',
        'select' => 'service_id, service_name, service_description, service_category, service_importance, service_client_id, client_name',
        'from' => 'services LEFT JOIN clients ON service_client_id = client_id',
        'where' => "service_archived_at IS NULL
            AND (service_name LIKE '%$like%' OR service_description LIKE '%$like%' OR service_category LIKE '%$like%' $service_deep_match
                " . tagSearchSql('service', $like, 'services.service_id') . ")",
        'scope' => 'service_client_id',
        'tags' => ['service', 'services.service_id'],
        'order' => "(service_name = '$eq') DESC, (service_name LIKE '$like%') DESC, FIELD(service_importance, 'High', 'Medium', 'Low'), service_name",
        'list_url' => '/agent/services.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['service_name'],
                'subtitle' => implode(' · ', array_filter([$row['service_category'], $row['service_description']])),
                'context' => $row['client_name'],
                'badge' => $row['service_importance'] === 'High' ? ['text' => 'High', 'variant' => 'danger'] : null,
                'url' => '/agent/' . serviceUrl($row['service_id'], $row['service_client_id']),
            ];
        },
    ];

    // Name and description only. Usernames and passwords are encrypted at rest,
    // and the results page reveals passwords through the audited endpoint, never
    // in the markup.
    $groups[] = [
        'key' => 'credentials', 'label' => 'Credentials', 'icon' => 'key', 'module' => 'module_credential',
        'select' => 'credential_id, credential_name, credential_description, credential_client_id, client_name',
        'from' => 'credentials LEFT JOIN clients ON credential_client_id = client_id',
        'where' => "credential_archived_at IS NULL
            AND (credential_name LIKE '%$like%' OR credential_description LIKE '%$like%' OR credential_uri LIKE '%$like%' OR credential_uri_2 LIKE '%$like%'
                " . tagSearchSql('credential', $like, 'credentials.credential_id') . ")",
        'scope' => 'credential_client_id',
        'tags' => ['credential', 'credentials.credential_id'],
        'order' => "(credential_name = '$eq') DESC, (credential_name LIKE '$like%') DESC, credential_name",
        'list_url' => '/agent/credentials.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'id' => intval($row['credential_id']),
                'title' => $row['credential_name'],
                'subtitle' => $row['credential_description'],
                'context' => $row['client_name'],
                // Straight to its quick look on the client's credentials page
                'url' => globalSearchClientUrl('credentials.php', $row['credential_client_id'], ['open' => intval($row['credential_id'])]),
            ];
        },
    ];

    $groups[] = [
        'key' => 'domains', 'label' => 'Domains', 'icon' => 'globe', 'module' => 'module_support',
        'select' => 'domain_id, domain_name, domain_description, domain_expire, domain_auto_renew, domain_client_id, client_name',
        'from' => 'domains LEFT JOIN clients ON domain_client_id = client_id',
        'where' => "domain_archived_at IS NULL AND (domain_name LIKE '%$like%' OR domain_description LIKE '%$like%' " . tagSearchSql('domain', $like, 'domains.domain_id') . ")",
        'scope' => 'domain_client_id',
        'tags' => ['domain', 'domains.domain_id'],
        'order' => "(domain_name = '$eq') DESC, (domain_name LIKE '$like%') DESC, domain_name",
        'list_url' => '/agent/domains.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['domain_name'],
                'subtitle' => implode(' · ', array_filter([($expiry = domainExpiry($row['domain_expire'], $row['domain_auto_renew'])) ? $expiry['text'] : '', $row['domain_description']])),
                'context' => $row['client_name'],
                // Straight to its quick look on the client's domains page
                'url' => globalSearchClientUrl('domains.php', $row['domain_client_id'], ['open' => intval($row['domain_id'])]),
            ];
        },
    ];

    $groups[] = [
        'key' => 'certificates', 'label' => 'Certificates', 'icon' => 'lock', 'module' => 'module_support',
        'select' => 'certificate_id, certificate_name, certificate_description, certificate_domain, certificate_expire, certificate_issued_at, certificate_check_status, certificate_client_id, client_name',
        'from' => 'certificates LEFT JOIN clients ON certificate_client_id = client_id',
        'where' => "certificate_archived_at IS NULL AND (certificate_name LIKE '%$like%' OR certificate_domain LIKE '%$like%' OR certificate_names LIKE '%$like%'
            OR certificate_description LIKE '%$like%' OR certificate_fingerprint LIKE '%$like%')",
        'scope' => 'certificate_client_id',
        'order' => "(certificate_name = '$eq' OR certificate_domain = '$eq') DESC, (certificate_name LIKE '$like%') DESC, certificate_name",
        'list_url' => '/agent/certificates.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $expiry = certificateExpiry($row);
            $status = CERTIFICATE_CHECK_STATES[$row['certificate_check_status'] ?? ''] ?? null;
            return [
                'title' => $row['certificate_name'],
                'subtitle' => implode(' · ', array_filter([
                    $row['certificate_domain'] !== $row['certificate_name'] ? $row['certificate_domain'] : '',
                    $status && $row['certificate_check_status'] !== 'ok' ? $status['label'] : '',
                    $expiry ? $expiry['text'] : '',
                    $row['certificate_description'],
                ])),
                'context' => $row['client_name'],
                // Straight to its quick look on the client's certificates page
                'url' => globalSearchClientUrl('certificates.php', $row['certificate_client_id'], ['open' => intval($row['certificate_id'])]),
            ];
        },
    ];

    $document_deep_match = $deep ? "OR MATCH(document_content_raw) AGAINST ('$eq')" : '';
    $groups[] = [
        'key' => 'documents', 'label' => 'Documents', 'icon' => 'file-alt', 'module' => 'module_support',
        'select' => 'document_id, document_name, document_description, document_client_id, client_name',
        'from' => 'documents LEFT JOIN clients ON document_client_id = client_id',
        'where' => "document_archived_at IS NULL
            AND (document_name LIKE '%$like%' OR document_description LIKE '%$like%' $document_deep_match
                " . tagSearchSql('document', $like, 'documents.document_id') . ")",
        'scope' => 'document_client_id',
        'tags' => ['document', 'documents.document_id'],
        'order' => "(document_name LIKE '$like%') DESC, document_updated_at DESC",
        'list_url' => null,
        'item' => function ($row) {
            return [
                'title' => $row['document_name'],
                'subtitle' => $row['document_description'],
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('document.php', $row['document_client_id'], ['document_id' => intval($row['document_id'])]),
                'tile' => ['tone' => 'document', 'icon' => 'file-alt', 'label' => 'Document'],
            ];
        },
    ];

    $groups[] = [
        'key' => 'files', 'label' => 'Files', 'icon' => 'paperclip', 'module' => 'module_support',
        'select' => 'file_id, file_name, file_ext, file_description, file_client_id, file_folder_id, client_name, folder_name',
        'from' => 'files
            LEFT JOIN clients ON file_client_id = client_id
            LEFT JOIN folders ON file_folder_id = folder_id',
        'where' => "file_archived_at IS NULL AND (file_name LIKE '%$like%' OR file_description LIKE '%$like%')",
        'scope' => 'file_client_id',
        'order' => "(file_name LIKE '$like%') DESC, file_id DESC",
        'list_url' => null,
        'item' => function ($row) {
            return [
                'title' => $row['file_name'],
                'subtitle' => implode(' · ', array_filter([$row['folder_name'], $row['file_description']])),
                'context' => $row['client_name'],
                // Its folder, narrowed to it - one click from its quick look
                'url' => globalSearchClientUrl('files.php', $row['file_client_id'], ['folder_id' => intval($row['file_folder_id']), 'q' => $row['file_name']]),
                'tile' => ['tone' => fileTypeInfo($row['file_ext'])['tone'], 'icon' => fileTypeInfo($row['file_ext'])['icon'], 'label' => fileTypeInfo($row['file_ext'])['label']],
            ];
        },
    ];

    $invoice_number = globalSearchNumber($q, $config_invoice_prefix);
    $invoice_number_match = $invoice_number !== null ? "OR invoice_number = $invoice_number" : '';
    $groups[] = [
        'key' => 'invoices', 'label' => 'Invoices', 'icon' => 'file-invoice-dollar', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_status, invoice_amount,
            invoice_currency_code, invoice_client_id, client_name',
        'from' => 'invoices LEFT JOIN clients ON invoice_client_id = client_id',
        'where' => "invoice_archived_at IS NULL
            AND (CONCAT(invoice_prefix, invoice_number) LIKE '%$like%' OR invoice_scope LIKE '%$like%' $invoice_number_match)",
        'scope' => 'invoice_client_id',
        'order' => ($invoice_number !== null ? "(invoice_number = $invoice_number) DESC, " : '') . 'invoice_number DESC',
        'exact' => $invoice_number !== null ? "invoice_number = $invoice_number" : null,
        'list_url' => '/agent/invoices.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'ref' => $row['invoice_prefix'] . $row['invoice_number'],
                'title' => $row['invoice_scope'] ?: 'Invoice',
                'subtitle' => globalSearchMoney($row['invoice_amount'], $row['invoice_currency_code']),
                'context' => $row['client_name'],
                'badge' => ['text' => $row['invoice_status'], 'variant' => getInvoiceBadgeColor($row['invoice_status'])],
                'url' => globalSearchClientUrl('invoice.php', $row['invoice_client_id'], ['invoice_id' => intval($row['invoice_id'])]),
            ];
        },
    ];

    $quote_number = globalSearchNumber($q, $config_quote_prefix);
    $quote_number_match = $quote_number !== null ? "OR quote_number = $quote_number" : '';
    $groups[] = [
        'key' => 'quotes', 'label' => 'Quotes', 'icon' => 'comment-dollar', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'quote_id, quote_prefix, quote_number, quote_scope, quote_status, quote_amount, quote_monthly_amount,
            quote_currency_code, quote_client_id, client_name',
        'from' => 'quotes LEFT JOIN clients ON quote_client_id = client_id',
        'where' => "quote_archived_at IS NULL
            AND (CONCAT(quote_prefix, quote_number) LIKE '%$like%' OR quote_scope LIKE '%$like%' $quote_number_match)",
        'scope' => 'quote_client_id',
        'order' => ($quote_number !== null ? "(quote_number = $quote_number) DESC, " : '') . 'quote_number DESC',
        'exact' => $quote_number !== null ? "quote_number = $quote_number" : null,
        'list_url' => '/agent/quotes.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $quote_badge_variants = ['Sent' => 'warning', 'Viewed' => 'primary', 'Accepted' => 'success', 'Declined' => 'danger', 'Invoiced' => 'info'];
            return [
                'ref' => $row['quote_prefix'] . $row['quote_number'],
                'title' => $row['quote_scope'] ?: 'Quote',
                'subtitle' => globalSearchMoney($row['quote_amount'], $row['quote_currency_code'])
                    . (floatval($row['quote_monthly_amount']) != 0 ? ' + ' . globalSearchMoney($row['quote_monthly_amount'], $row['quote_currency_code']) . '/mo' : ''),
                'context' => $row['client_name'],
                'badge' => ['text' => $row['quote_status'], 'variant' => $quote_badge_variants[$row['quote_status']] ?? 'secondary'],
                'url' => globalSearchClientUrl('quote.php', $row['quote_client_id'], ['quote_id' => intval($row['quote_id'])]),
            ];
        },
    ];

    // Expenses by what they were for, their reference, or the amount ("149.99", "$1,250")
    $expense_amount_text = str_replace([',', '$'], '', $q);
    $expense_amount = preg_match('/^-?\d+(\.\d{1,2})?$/', $expense_amount_text) ? abs(round(floatval($expense_amount_text), 2)) : 0;
    $expense_amount_match = $expense_amount > 0 ? "OR ABS(expense_amount) = $expense_amount" : '';
    $groups[] = [
        'key' => 'expenses', 'label' => 'Expenses', 'icon' => 'shopping-cart', 'module' => 'module_financial', 'enabled' => $accounting,
        'select' => 'expense_id, expense_date, expense_amount, expense_currency_code, expense_description, expense_reference, vendor_name, client_name',
        'from' => 'expenses LEFT JOIN vendors ON expense_vendor_id = vendor_id LEFT JOIN clients ON expense_client_id = client_id',
        'where' => "expense_vendor_id > 0
            AND (expense_description LIKE '%$like%' OR expense_reference LIKE '%$like%' $expense_amount_match)",
        'scope' => 'expense_client_id',
        'order' => ($expense_amount > 0 ? "(ABS(expense_amount) = $expense_amount) DESC, " : '') . 'expense_date DESC, expense_id DESC',
        'list_url' => '/agent/expenses.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $description = trim((string) $row['expense_description']);
            return [
                'title' => $description !== '' ? mb_strimwidth($description, 0, 80, '...') : ($row['vendor_name'] ?: 'Expense'),
                'subtitle' => globalSearchMoney($row['expense_amount'], $row['expense_currency_code']) . ' · ' . $row['expense_date']
                    . ($row['expense_reference'] ? ' · #' . $row['expense_reference'] : ''),
                'context' => implode(' · ', array_filter([$row['vendor_name'], $row['client_name']])),
                'url' => '/agent/expenses.php?open=' . intval($row['expense_id']),
            ];
        },
    ];

    // Income - other income by what it was or its reference, a payment by its reference (check or
    // transaction number), either by the amount. The union is already in the agent's client scope.
    $income_amount_match = $expense_amount > 0 ? "OR ABS(income.income_amount) = $expense_amount" : '';
    $groups[] = [
        'key' => 'income', 'label' => 'Income', 'icon' => 'hand-holding-usd', 'module' => 'module_financial', 'enabled' => $accounting,
        'select' => 'income.*',
        'from' => '(' . incomeUnionSql() . ') AS income',
        'where' => "((income.income_type = 'revenue' AND income.income_description LIKE '%$like%') OR income.income_reference LIKE '%$like%' $income_amount_match)",
        'scope' => null,
        'order' => ($expense_amount > 0 ? "(ABS(income.income_amount) = $expense_amount) DESC, " : '') . 'income.income_date DESC, income.income_id DESC',
        'list_url' => '/agent/income.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            $payment = $row['income_type'] === 'payment';
            $what = trim(strtok((string) $row['income_description'], "\n"));
            return [
                'title' => $payment ? 'Payment on ' . ($row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : 'an invoice since deleted')
                    : ($what !== '' ? mb_strimwidth($what, 0, 80, '...') : ($row['category_name'] ?: 'Other income')),
                'subtitle' => globalSearchMoney($row['income_amount'], $row['income_currency_code']) . ' · ' . $row['income_date']
                    . ($row['income_reference'] ? ' · #' . $row['income_reference'] : ''),
                'context' => implode(' · ', array_filter([$row['client_name'], $payment ? '' : (string) $row['category_name']])),
                'url' => '/agent/income.php?open=' . $row['income_type'] . '-' . intval($row['income_id']),
            ];
        },
    ];

    $vendor_phone_match = $phone ? "OR REGEXP_REPLACE(vendor_phone, '[^0-9]', '') LIKE '%$phone%'" : '';
    $groups[] = [
        'key' => 'vendors', 'label' => 'Vendors', 'icon' => 'building', 'module' => 'module_client',
        'select' => 'vendor_id, vendor_name, vendor_description, vendor_phone, vendor_phone_country_code, vendor_client_id, client_name',
        'from' => 'vendors LEFT JOIN clients ON vendor_client_id = client_id',
        'where' => "vendor_archived_at IS NULL
            AND (vendor_name LIKE '%$like%' OR vendor_description LIKE '%$like%' OR vendor_account_number LIKE '%$like%'
                OR vendor_contact_name LIKE '%$like%' OR vendor_email LIKE '%$like%' OR vendor_contact_email LIKE '%$like%' $vendor_phone_match
                " . tagSearchSql('vendor', $like, 'vendors.vendor_id') . ")",
        'scope' => 'vendor_client_id',
        'tags' => ['vendor', 'vendors.vendor_id'],
        'order' => "(vendor_name = '$eq') DESC, (vendor_name LIKE '$like%') DESC, vendor_favorite DESC, vendor_name",
        'list_url' => '/agent/vendors.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['vendor_name'],
                'subtitle' => implode(' · ', array_filter([$row['vendor_description'], $row['vendor_phone'] ? formatPhoneNumber($row['vendor_phone'], $row['vendor_phone_country_code']) : ''])),
                'context' => intval($row['vendor_client_id']) ? $row['client_name'] : 'Our vendor',
                'url' => globalSearchClientUrl('vendor.php', $row['vendor_client_id'], ['vendor_id' => intval($row['vendor_id'])]),
            ];
        },
    ];

    $recurring_deep_match = $deep ? "OR recurring_ticket_details LIKE '%$like%'" : '';
    $groups[] = [
        'key' => 'recurring_tickets', 'label' => 'Recurring Tickets', 'icon' => 'redo-alt', 'module' => 'module_support', 'enabled' => $ticketing,
        'select' => 'recurring_ticket_id, recurring_ticket_subject, recurring_ticket_frequency, recurring_ticket_next_run,
            recurring_ticket_client_id, client_name',
        'from' => 'recurring_tickets LEFT JOIN clients ON recurring_ticket_client_id = client_id',
        'where' => "(recurring_ticket_subject LIKE '%$like%' $recurring_deep_match)",
        'scope' => 'recurring_ticket_client_id',
        'order' => "(recurring_ticket_subject LIKE '$like%') DESC, recurring_ticket_next_run",
        'list_url' => '/agent/recurring_tickets.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['recurring_ticket_subject'],
                'subtitle' => implode(' · ', array_filter([$row['recurring_ticket_frequency'], $row['recurring_ticket_next_run'] ? 'Next ' . $row['recurring_ticket_next_run'] : ''])),
                'context' => $row['client_name'],
                'url' => globalSearchClientUrl('recurring_tickets.php', $row['recurring_ticket_client_id'], ['q' => $row['recurring_ticket_subject']]),
            ];
        },
    ];

    $groups[] = [
        'key' => 'products', 'label' => 'Products', 'icon' => 'box-open', 'module' => 'module_sales', 'enabled' => $accounting,
        'select' => 'product_id, product_name, product_type, product_code, product_vendor_part, product_price, product_currency_code, product_frequency, product_seat_source',
        'from' => 'products',
        'where' => "product_archived_at IS NULL
            AND (product_name LIKE '%$like%' OR product_code LIKE '%$like%' OR product_vendor_part LIKE '%$like%' OR product_description LIKE '%$like%')",
        'scope' => null,
        'order' => "(product_name = '$eq') DESC, (product_name LIKE '$like%') DESC, product_name",
        'list_url' => '/agent/products.php?' . http_build_query(['q' => $q]),
        'item' => function ($row) {
            return [
                'title' => $row['product_name'],
                'subtitle' => implode(' · ', array_filter([$row['product_code'], globalSearchMoney($row['product_price'], $row['product_currency_code'])
                    . ($row['product_frequency'] ? ' ' . strtolower(productBilledText($row['product_frequency'], $row['product_seat_source'])) : '')])),
                'url' => '/agent/product.php?' . http_build_query(['product_id' => intval($row['product_id'])]),
            ];
        },
    ];

    // Ticket conversations: matched inside reply bodies, so results page only.
    if ($deep) {
        $groups[] = [
            'key' => 'ticket_replies', 'label' => 'Ticket Conversations', 'icon' => 'comments', 'module' => 'module_support', 'enabled' => $ticketing,
            'select' => 'ticket_reply_id, ticket_reply, ticket_reply_created_at, ticket_id, ticket_prefix, ticket_number,
                ticket_subject, ticket_client_id, client_name',
            'from' => 'ticket_replies
                JOIN tickets ON ticket_reply_ticket_id = ticket_id
                LEFT JOIN clients ON ticket_client_id = client_id',
            'where' => "ticket_reply_archived_at IS NULL AND ticket_archived_at IS NULL AND ticket_reply LIKE '%$like%'",
            'scope' => 'ticket_client_id',
            'order' => 'ticket_reply_id DESC',
            'list_url' => null,
            'item' => function ($row) use ($q) {
                return [
                    'ref' => $row['ticket_prefix'] . $row['ticket_number'],
                    'title' => $row['ticket_subject'],
                    'snippet' => globalSearchSnippet($row['ticket_reply'], $q),
                    'context' => $row['client_name'],
                    'url' => globalSearchClientUrl('ticket.php', $row['ticket_client_id'], ['ticket_id' => intval($row['ticket_id'])]),
                ];
            },
        ];
    }

    // Tags by name or description - each opens its kind's list filtered by it,
    // for the kinds this agent can open. Tag names aren't a client's, so no scope
    $tag_types = [];
    foreach (tagKinds() as $tag_type => $tag_entity) {
        if (lookupUserPermission(tagEntity($tag_entity)['module']) >= 1) {
            $tag_types[] = $tag_type;
        }
    }
    $groups[] = [
        'key' => 'tags', 'label' => 'Tags', 'icon' => 'tags', 'module' => null, 'enabled' => (bool) $tag_types,
        'select' => 'tag_id, tag_name, tag_description, tag_type, tag_color, tag_icon',
        'from' => 'tags',
        'where' => "tag_archived_at IS NULL AND tag_type IN (" . implode(',', $tag_types ?: [0]) . ")
            AND (tag_name LIKE '%$like%' OR tag_description LIKE '%$like%')",
        'scope' => '',
        'order' => "(tag_name = '$eq') DESC, (tag_name LIKE '$like%') DESC, tag_name, tag_type",
        'list_url' => null,
        'item' => function ($row) {
            $config = tagEntityByType($row['tag_type']);
            return [
                'title' => $row['tag_name'],
                'subtitle' => implode(' · ', array_filter([$config['title'] . ' tag', $row['tag_description']])),
                'url' => tagListUrl($config['entity'], $row),
                // Drawn in place of the group icon - the tag's own colour and icon
                'tag' => tagSearchSpec($row),
            ];
        },
    ];

    return $groups;
}

/**
 * Run the search.
 *
 * Returns the groups that matched, in display order, each as:
 *   key, label, icon, items[], more (bool), total (int, only with $with_totals), list_url
 * A group with an exact record-number hit (ticket 1042 for "TCK-1042") moves to
 * the front, since that is unambiguously what was asked for.
 */
function globalSearch($raw_query, $limit = 5, $deep = false, $with_totals = false) {
    global $mysqli;

    $terms = globalSearchTerms($raw_query);
    if (mb_strlen($terms['raw']) < 2) {
        return [];
    }

    $limit = max(1, intval($limit));
    $results = [];
    $matched_tag_ids = [];

    foreach (globalSearchGroups($terms, $deep) as $group) {
        if (array_key_exists('enabled', $group) && !$group['enabled']) {
            continue;
        }
        // A group with no module (Tags) checks access in its own query
        if ($group['module'] !== null && lookupUserPermission($group['module']) < 1) {
            continue;
        }

        $scope = $group['scope'] ? clientScopeSql($group['scope']) : '';
        $where = "{$group['where']} $scope";

        // A record found by a tag says which - the ids of its tags that match
        $select = $group['select'];
        if (!empty($group['tags'])) {
            $select .= ', ' . tagMatchSql($group['tags'][0], $terms['like'], $group['tags'][1]) . ' AS q_tag_ids';
        }

        $sql = mysqli_query(
            $mysqli,
            "SELECT $select FROM {$group['from']} WHERE $where ORDER BY {$group['order']} LIMIT " . ($limit + 1)
        );

        $items = [];
        while ($row = mysqli_fetch_assoc($sql)) {
            $item = $group['item']($row);
            if (!empty($row['q_tag_ids'])) {
                $item['tag_ids'] = array_map('intval', explode(',', $row['q_tag_ids']));
                $matched_tag_ids = array_merge($matched_tag_ids, $item['tag_ids']);
            }
            $items[] = $item;
        }

        if (!$items) {
            continue;
        }

        $more = count($items) > $limit;
        $items = array_slice($items, 0, $limit);

        $exact = false;
        if (!empty($group['exact'])) {
            $exact_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM {$group['from']} WHERE $where AND {$group['exact']} LIMIT 1"));
            $exact = (bool) $exact_row;
        }

        $total = null;
        if ($with_totals) {
            $total = $more
                ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM {$group['from']} WHERE $where"))[0])
                : count($items);
        }

        $results[] = [
            'key' => $group['key'],
            'label' => $group['label'],
            'icon' => $group['icon'],
            'items' => $items,
            'more' => $more,
            'total' => $total,
            'list_url' => $group['list_url'],
            'exact' => $exact,
        ];
    }

    // The matching tags as the page draws them, in one query for every group
    if ($matched_tag_ids) {
        $specs = [];
        $sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_color, tag_icon FROM tags WHERE tag_id IN (" . implode(',', array_unique($matched_tag_ids)) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $specs[intval($row['tag_id'])] = tagSearchSpec($row);
        }
        foreach ($results as &$result) {
            foreach ($result['items'] as &$item) {
                if (isset($item['tag_ids'])) {
                    $item['tags'] = array_values(array_intersect_key($specs, array_flip($item['tag_ids'])));
                    unset($item['tag_ids']);
                }
            }
            unset($item);
        }
        unset($result);
    }

    // Exact record-number hits first; otherwise keep display order (usort is stable in PHP 8)
    usort($results, function ($a, $b) {
        return intval($b['exact']) - intval($a['exact']);
    });

    return $results;
}

/** Recently opened clients, shown in the palette before anything is typed. */
function globalSearchRecentClients($limit = 6) {
    global $mysqli;

    if (lookupUserPermission('module_client') < 1) {
        return [];
    }

    $limit = max(1, intval($limit));

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, client_type FROM clients
        WHERE client_archived_at IS NULL AND client_accessed_at IS NOT NULL
        " . clientScopeSql('clients.client_id') . "
        ORDER BY client_accessed_at DESC LIMIT $limit"
    );

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = [
            'title' => $row['client_name'],
            'subtitle' => $row['client_type'],
            'url' => '/agent/client_overview.php?client_id=' . intval($row['client_id']),
        ];
    }

    return $items;
}

/**
 * The app's own pages, so "inv" can also take you to Invoices. Same gates as
 * the side nav, which is the only other place these links appear.
 */
function globalSearchPages($raw_query) {
    global $config_module_enable_ticketing, $config_module_enable_accounting, $session_is_admin;

    $query = mb_strtolower(trim((string) $raw_query));
    if (mb_strlen($query) < 2) {
        return [];
    }

    $ticketing = intval($config_module_enable_ticketing) === 1;
    $accounting = intval($config_module_enable_accounting) === 1;
    $support = lookupUserPermission('module_support') >= 1;
    $client = lookupUserPermission('module_client') >= 1;
    $sales = lookupUserPermission('module_sales') >= 1;
    $financial = lookupUserPermission('module_financial') >= 1;

    $pages = [
        ['Dashboard', '/agent/dashboard.php', true],
        ['Clients', '/agent/clients.php', $client],
        ['All Client Docs', '/agent/contacts.php', $client],
        ['Services', '/agent/services.php', $support],
        ['Vendors', '/agent/vendors.php', true],
        ['Calendar', '/agent/calendar.php', true],
        ['Tickets', '/agent/tickets.php', $support && $ticketing],
        ['Recurring Tickets', '/agent/recurring_tickets.php', $support && $ticketing],
        ['Projects', '/agent/projects.php', $support && $ticketing],
        ['Quotes', '/agent/quotes.php', $sales && $accounting],
        ['Invoices', '/agent/invoices.php', $sales && $accounting],
        ['Recurring Invoices', '/agent/recurring_invoices.php', $sales && $accounting],
        ['Products', '/agent/products.php', $sales && $accounting],
        ['Income', '/agent/income.php', $financial && $accounting],
        ['Expenses', '/agent/expenses.php', $financial && $accounting],
        ['Recurring Expenses', '/agent/recurring_expenses.php', $financial && $accounting],
        ['Trips', '/agent/trips.php', $accounting],
        ['Accounts', '/agent/accounts.php', $financial && $accounting],
        ['Transactions', '/agent/transactions.php', $financial && $accounting],
        ['Transfers', '/agent/transactions.php?show=transfers', $financial && $accounting],
        ['Reports', '/agent/reports/', lookupUserPermission('module_reporting') >= 1],
        ['Administration', '/admin/', (bool) $session_is_admin],
        ['My Account', '/agent/user/user_details.php', true],
    ];

    $items = [];
    foreach ($pages as [$label, $url, $allowed]) {
        if (!$allowed) {
            continue;
        }
        // Match the start of any word: "inv" finds Invoices and Recurring Invoices
        foreach (preg_split('/\s+/', mb_strtolower($label)) as $word) {
            if (strpos($word, $query) === 0 || strpos(mb_strtolower($label), $query) === 0) {
                $items[] = ['title' => $label, 'url' => $url];
                break;
            }
        }
    }

    return array_slice($items, 0, 4);
}
