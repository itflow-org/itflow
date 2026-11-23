<?php

/*
 * ITFlow - a network platform's networks and VLANs, kept up as its sites' clients' networks
 *
 * A kind with a 'networks' function (UniFi's rmmUnifiNetworks()) lists a site's networks; the sync
 * (rmmSync()) brings each into the site's client as one of its networks after its devices. Each
 * is matched to a network the client has with the same subnet, or made, and an rmm_networks row
 * keeps which it is. After that each detail follows the platform until it is changed in ITFlow,
 * as an asset's do (rmmAssetFollow()): rmm_network_synced holds what the sync last wrote. A
 * network deleted in ITFlow (rmm_network_network_id 0) isn't made again until Bring back; an
 * archived one is left as it is.
 *
 * A network as a kind gives it: remote_id, name, purpose (lan, guest or vpn), vlan (0 for none),
 * subnet (the gateway's address with the prefix - 192.168.1.1/24), dhcp_start, dhcp_stop and dns
 * (the addresses DHCP hands out as DNS servers, [] for the gateway's own).
 */

/** Whether the 4.1.6 database update is in - networks from network platforms. */
function rmmNetworksReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        $result = rmmNetworkReady() ? mysqli_query($mysqli, "SHOW TABLES LIKE 'rmm_networks'") : false;
        $ready = $result && mysqli_num_rows($result) > 0;
    }
    return $ready;
}

/** The details the sync writes to a network - its field => [column, what the history calls it]. */
function rmmNetworkFields(): array
{
    return [
        'name' => ['network_name', 'name'], 'type' => ['network_type', 'type'], 'vlan' => ['network_vlan', 'VLAN'],
        'network' => ['network', 'subnet'], 'gateway' => ['network_gateway', 'gateway'], 'primary_dns' => ['network_primary_dns', 'DNS'],
        'secondary_dns' => ['network_secondary_dns', 'second DNS'], 'dhcp_range' => ['network_dhcp_range', 'DHCP range'],
    ];
}

/** A kind's network as the values of an ITFlow network - [field => value], or null when it has no subnet ITFlow can keep. */
function rmmNetworkValues(array $net): ?array
{
    $subnet = trim((string) ($net['subnet'] ?? ''));
    $info = networkSubnetInfo($subnet);
    if (!$info) {
        return null;
    }
    $name = rmmText($net['name'] ?? '', 200);
    $host = normalizeIpAddress(trim(explode('/', $subnet)[0]));
    $gateway = $host !== false && $host !== $info['network'] && isIpInSubnet($host, $info['cidr']) ? $host : '';

    $range = '';
    $parsed = networkParseRange(trim((string) ($net['dhcp_start'] ?? '')) . ' - ' . trim((string) ($net['dhcp_stop'] ?? '')), $info);
    if ($parsed) {
        $range = intToIpv4($parsed[0]) . ' - ' . intToIpv4($parsed[1]);
    }
    $dns = [];
    foreach ((array) ($net['dns'] ?? []) as $server) {
        $server = normalizeIpAddress(trim((string) $server));
        if ($server !== false && !in_array($server, $dns, true)) {
            $dns[] = $server;
        }
    }
    // DHCP handing out the gateway as DNS - its own, when the platform names none
    if (!$dns && $range !== '' && $gateway !== '') {
        $dns[] = $gateway;
    }

    // VLAN 1 is the untagged default network - no VLAN to write down
    $vlan = intval($net['vlan'] ?? 0);
    $purpose = (string) ($net['purpose'] ?? 'lan');
    return [
        'name' => $name !== '' ? $name : $info['cidr'],
        'type' => $purpose === 'guest' ? 'Guest' : ($purpose === 'vpn' ? 'VPN' : networkGuessType($name)),
        'vlan' => $vlan >= 2 && $vlan <= 4094 ? (string) $vlan : '',
        'network' => $info['cidr'],
        'gateway' => $gateway,
        'primary_dns' => $dns[0] ?? '',
        'secondary_dns' => $dns[1] ?? '',
        'dhcp_range' => $range,
    ];
}

/** A network's details as the sync compares them - [field => value], or null when it is gone. */
function rmmNetworkCurrent(int $network_id): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT network_id, network_client_id, network_archived_at, network_name, network_type, network_vlan, network,
        network_gateway, network_primary_dns, network_secondary_dns, network_dhcp_range FROM networks WHERE network_id = $network_id"));
    if (!$row) {
        return null;
    }
    $current = ['client_id' => intval($row['network_client_id']), 'archived' => $row['network_archived_at'] !== null];
    foreach (rmmNetworkFields() as $field => [$column]) {
        $current[$field] = $row[$column] === null ? '' : (string) $row[$column];
    }
    return $current;
}

/** The SQL that sets these details of a network. */
function rmmNetworkSetSql(array $set): string
{
    $fields = rmmNetworkFields();
    $parts = [];
    foreach ($set as $field => $value) {
        $column = $fields[$field][0];
        $parts[] = $field === 'vlan' ? "$column = " . ($value === '' ? 'NULL' : intval($value)) : "$column = '" . escapeSql((string) $value) . "'";
    }
    return implode(', ', $parts);
}

/**
 * Write what the platform says to a network, detail by detail: an empty one is filled in, one the
 * sync wrote last time follows, and one changed in ITFlow is left as it is. [what changed (the
 * history's words), what is the sync's now] - as rmmAssetFollow() does for an asset.
 */
function rmmNetworkFollow(int $network_id, array $current, array $values, array $synced_before): array
{
    global $mysqli;
    $set = [];
    $synced = [];
    $ours = function ($field) use ($current, $synced_before) {
        return array_key_exists($field, $synced_before) && (string) $synced_before[$field] === (string) ($current[$field] ?? '');
    };
    // The DNS servers go together - one set in ITFlow keeps the other as it is too
    $dns_kept = false;
    foreach (['primary_dns', 'secondary_dns'] as $field) {
        $had = (string) ($current[$field] ?? '');
        if ($had !== '' && $had !== (string) ($values[$field] ?? '') && !$ours($field)) {
            $dns_kept = true;
        }
    }
    foreach ($values as $field => $new) {
        $new = (string) $new;
        $had = (string) ($current[$field] ?? '');
        $was_ours = $ours($field);
        if ($dns_kept && in_array($field, ['primary_dns', 'secondary_dns'], true)) {
            if (array_key_exists($field, $synced_before) && $was_ours) {
                $synced[$field] = $synced_before[$field];
            }
            continue;
        }
        if ($new === '') {
            if ($was_ours) {
                $synced[$field] = $synced_before[$field];
            }
            continue;
        }
        if ($had === $new) {
            $synced[$field] = $new;
            continue;
        }
        if ($had === '' || $was_ours) {
            $set[$field] = $new;
            $synced[$field] = $new;
            continue;
        }
        if (array_key_exists($field, $synced_before)) {
            $synced[$field] = $synced_before[$field];
        }
    }
    if ($set) {
        mysqli_query($mysqli, "UPDATE networks SET " . rmmNetworkSetSql($set) . " WHERE network_id = $network_id");
    }
    $fields = rmmNetworkFields();
    return [array_map(function ($field) use ($fields) {
        return $fields[$field][1];
    }, array_keys($set)), $synced];
}

/** "LAN, Guest and IoT" - a list in words. */
function rmmNetworkWords(array $words): string
{
    return count($words) > 1 ? implode(', ', array_slice($words, 0, -1)) . ' and ' . end($words) : (string) ($words[0] ?? '');
}

/**
 * One of a site's networks, brought into its client: matched or made, then kept up. Counts it in
 * $counts (networks, networks_created, networks_linked, networks_updated, networks_returned).
 */
function rmmNetworkSave(array $provider, array $rmm_client, int $client_id, array $net, array &$counts): void
{
    global $mysqli;
    $values = rmmNetworkValues($net);
    $remote_id = rmmText($net['remote_id'] ?? '', 100);
    if (!$values || $remote_id === '') {
        return;
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $rmm_client_id = intval($rmm_client['rmm_client_id']);
    $name = rmmProviderName($provider);
    $short = rmmKind($provider)['short'];
    $remote = escapeSql($remote_id);
    $counts['networks']++;

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_networks WHERE rmm_network_provider_id = $provider_id
        AND rmm_network_rmm_client_id = $rmm_client_id AND rmm_network_remote_id = '$remote'"));
    $network_id = $row ? intval($row['rmm_network_network_id']) : 0;
    $synced_before = $row ? (json_decode((string) $row['rmm_network_synced'], true) ?: []) : [];
    $current = $network_id ? rmmNetworkCurrent($network_id) : null;
    if ($row && $row['rmm_network_removed_at'] !== null) {
        $counts['networks_returned']++;
    }

    if ($row && (!$network_id || !$current || $current['client_id'] !== $client_id)) {
        // Deleted in ITFlow, or moved to another client there - not made again (Bring back makes it again)
        mysqli_query($mysqli, "UPDATE rmm_networks SET rmm_network_network_id = 0, rmm_network_client_id = $client_id, rmm_network_name = '" . escapeSql($values['name']) . "',
            rmm_network_synced_at = NOW(), rmm_network_removed_at = NULL WHERE rmm_network_id = " . intval($row['rmm_network_id']));
        return;
    }

    $made = false;
    if (!$row) {
        // The client's network with the same subnet, that none of this platform's networks has already
        $match = mysqli_fetch_row(mysqli_query($mysqli, "SELECT network_id FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL
            AND network = '" . escapeSql($values['network']) . "' AND network_id NOT IN (SELECT rmm_network_network_id FROM rmm_networks
            WHERE rmm_network_provider_id = $provider_id AND rmm_network_network_id > 0 AND rmm_network_removed_at IS NULL) ORDER BY network_id LIMIT 1"));
        if ($match) {
            $network_id = intval($match[0]);
            $current = rmmNetworkCurrent($network_id);
            $counts['networks_linked']++;
        } else {
            mysqli_query($mysqli, "INSERT INTO networks SET " . rmmNetworkSetSql($values) . ", network_description = '', network_client_id = $client_id");
            $network_id = intval(mysqli_insert_id($mysqli));
            $current = rmmNetworkCurrent($network_id);
            $made = true;
            $counts['networks_created']++;
            logAudit('Network', 'Create', escapeSql("$name brought in network {$values['name']} from its site {$rmm_client['rmm_client_name']}"), $client_id, $network_id);
        }
    }

    // Archived in ITFlow - left as it is
    if ($current && $current['archived']) {
        [$changed, $synced] = [[], $synced_before];
    } else {
        [$changed, $synced] = $current ? rmmNetworkFollow($network_id, $current, $values, $made ? array_map('strval', $values) : $synced_before) : [[], []];
    }
    $here = (string) ($current['name'] ?? $values['name']);
    if (!$row && !$made) {
        logAudit('Network', 'Edit', escapeSql("$name linked network $here to its network {$values['name']} in $short" . ($changed ? ' and filled in its ' . rmmNetworkWords($changed) : '')), $client_id, $network_id);
    } elseif ($changed && !$made) {
        $counts['networks_updated']++;
        logAudit('Network', 'Edit', escapeSql("$name changed the " . rmmNetworkWords($changed) . " of network $here"), $client_id, $network_id);
    }

    $synced_sql = escapeSql(json_encode($synced));
    $name_sql = escapeSql($values['name']);
    if ($row) {
        mysqli_query($mysqli, "UPDATE rmm_networks SET rmm_network_client_id = $client_id, rmm_network_name = '$name_sql', rmm_network_synced = '$synced_sql',
            rmm_network_synced_at = NOW(), rmm_network_removed_at = NULL WHERE rmm_network_id = " . intval($row['rmm_network_id']));
    } else {
        mysqli_query($mysqli, "INSERT INTO rmm_networks SET rmm_network_provider_id = $provider_id, rmm_network_rmm_client_id = $rmm_client_id, rmm_network_remote_id = '$remote',
            rmm_network_client_id = $client_id, rmm_network_network_id = $network_id, rmm_network_name = '$name_sql', rmm_network_synced = '$synced_sql', rmm_network_synced_at = NOW()");
    }
}

/**
 * A linked site's networks, from its platform - each saved, and the ones it no longer lists marked.
 * '' when that worked, or what went wrong; a platform whose version has no networks to list is fine.
 */
function rmmNetworksSync(array $provider, array $rmm_client, array &$counts): string
{
    global $mysqli;
    $kind = rmmKind($provider);
    if (!isset($kind['networks']) || !rmmNetworksReady()) {
        return '';
    }
    $answer = $kind['networks']($provider, (string) $rmm_client['rmm_client_remote_id']);
    if (!$answer['ok']) {
        return (string) $answer['error'];
    }
    if (!empty($answer['unoffered'])) {
        return '';
    }
    $client_id = intval($rmm_client['rmm_client_client_id']);
    $listed = [];
    foreach ($answer['networks'] as $net) {
        if (is_array($net)) {
            rmmNetworkSave($provider, $rmm_client, $client_id, $net, $counts);
            $listed[] = escapeSql(rmmText($net['remote_id'] ?? '', 100));
        }
    }
    // The ones it no longer lists - not when it lists none at all, which is more often its access than the site
    if ($listed) {
        $rmm_client_id = intval($rmm_client['rmm_client_id']);
        mysqli_query($mysqli, "UPDATE rmm_networks SET rmm_network_removed_at = NOW() WHERE rmm_network_rmm_client_id = $rmm_client_id
            AND rmm_network_removed_at IS NULL AND rmm_network_remote_id NOT IN ('" . implode("', '", $listed) . "')");
        $counts['networks_removed'] += mysqli_affected_rows($mysqli);
    }
    return '';
}

/** The words for what a sync did with networks - "4 networks - 1 new, 3 matched to networks already here", or ''. */
function rmmNetworksNote(array $counts, string $short): string
{
    if (!($counts['networks'] ?? 0) && !($counts['networks_removed'] ?? 0)) {
        return '';
    }
    $note = number_format($counts['networks']) . ' network' . ($counts['networks'] === 1 ? '' : 's');
    $done = [];
    foreach (['networks_created' => 'new', 'networks_linked' => 'matched to networks already here', 'networks_updated' => 'updated',
        'networks_removed' => "no longer in $short", 'networks_returned' => "back in $short"] as $key => $words) {
        if ($counts[$key] ?? 0) {
            $done[] = number_format($counts[$key]) . " $words";
        }
    }
    return $note . ($done ? ' - ' . implode(', ', $done) : '');
}

/** What the sync knew of a site's networks is forgotten - unlinked, or linked to another client. The networks stay. */
function rmmNetworksForget(int $rmm_client_id): void
{
    global $mysqli;
    if (rmmNetworksReady()) {
        mysqli_query($mysqli, "DELETE FROM rmm_networks WHERE rmm_network_rmm_client_id = $rmm_client_id");
    }
}

/** Networks deleted in ITFlow - their platform's rows say so, so the sync doesn't make them again. */
function rmmNetworksDeleted(array $network_ids): void
{
    global $mysqli;
    $ids = array_filter(array_map('intval', $network_ids));
    if ($ids && rmmNetworksReady()) {
        mysqli_query($mysqli, "UPDATE rmm_networks SET rmm_network_network_id = 0 WHERE rmm_network_network_id IN (" . implode(', ', $ids) . ")");
    }
}

/** The platform rows of a network - with their platform's and site's names - for its page. */
function rmmNetworksFor(int $network_id): array
{
    global $mysqli;
    $rows = [];
    if (!rmmNetworksReady() || !$network_id) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT rmm_networks.*, rmm_providers.*, rmm_client_name FROM rmm_networks
        JOIN rmm_providers ON rmm_provider_id = rmm_network_provider_id
        JOIN rmm_clients ON rmm_clients.rmm_client_id = rmm_network_rmm_client_id
        WHERE rmm_network_network_id = $network_id ORDER BY rmm_network_removed_at IS NOT NULL, rmm_network_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}
