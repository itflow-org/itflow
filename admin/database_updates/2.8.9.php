<?php

/*
 * ITFlow - Database update to version 2.8.9 (from 2.8.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What a network is for - LAN, WAN, Guest, Voice, Servers... - and, for an
    // internet uplink, who provides it (one of the client's vendors, or ours)
    mysqli_query($mysqli, "ALTER TABLE `networks` ADD COLUMN `network_type` varchar(50) DEFAULT NULL AFTER `network_description`");
    mysqli_query($mysqli, "ALTER TABLE `networks` ADD COLUMN `network_vendor_id` int(11) NOT NULL DEFAULT 0 AFTER `network_location_id`");
    mysqli_query($mysqli, "ALTER TABLE `networks` ADD INDEX `network_location_id` (`network_location_id`)");

    // Existing networks get the type their name says - or failing that, their
    // description - from the same words functions/network.php reads for a
    // network with no type (networkGuessType()). A description says "no
    // internet access" as often as it describes an uplink, so WAN takes
    // stronger words there.
    mysqli_query($mysqli, "UPDATE `networks` SET `network_type` = COALESCE(
        CASE
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'guest' THEN 'Guest'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'wan|internet|uplink|circuit|broadband|fib(er|re)|starlink|(^|[^a-z])(isp|dsl|lte|5g)([^a-z]|$)' THEN 'WAN'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'voip|voice|phone|pbx|telephon|(^|[^a-z])sip([^a-z]|$)' THEN 'Voice'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'mgmt|manage|ipmi|idrac|(^|[^a-z])(oob|ilo|bmc)([^a-z]|$)' THEN 'Management'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'dmz' THEN 'DMZ'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'camera|cctv|hvac|badge|access control|building|(^|[^a-z])(iot|nvr)([^a-z]|$)' THEN 'IoT'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'storage|iscsi|backup|(^|[^a-z])(san|nfs)([^a-z]|$)' THEN 'Storage'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'vpn|tunnel|ipsec|wireguard' THEN 'VPN'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'server|datacent|cluster|(^|[^a-z])srv' THEN 'Servers'
            WHEN LOWER(IFNULL(network_name, '')) REGEXP 'wireless|wifi|wi-fi|wlan|ssid' THEN 'Wireless'
        END,
        CASE
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'guest' THEN 'Guest'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP '(^|[^a-z])wan([^a-z]|$)|circuit|broadband|starlink|(^|[^a-z])(isp|dsl)([^a-z]|$)' THEN 'WAN'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'voip|voice|phone|pbx|telephon|(^|[^a-z])sip([^a-z]|$)' THEN 'Voice'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'mgmt|manage|ipmi|idrac|(^|[^a-z])(oob|ilo|bmc)([^a-z]|$)' THEN 'Management'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'dmz' THEN 'DMZ'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'camera|cctv|hvac|badge|access control|building|(^|[^a-z])(iot|nvr)([^a-z]|$)' THEN 'IoT'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'storage|iscsi|backup|(^|[^a-z])(san|nfs)([^a-z]|$)' THEN 'Storage'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'vpn|tunnel|ipsec|wireguard' THEN 'VPN'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'server|datacent|cluster|(^|[^a-z])srv' THEN 'Servers'
            WHEN LOWER(IFNULL(network_description, '')) REGEXP 'wireless|wifi|wi-fi|wlan|ssid' THEN 'Wireless'
        END,
        'LAN')
        WHERE `network_type` IS NULL");

    // The prefix belongs with the address. Some networks kept it apart, in the
    // old subnet column - as a length (24) or a mask (255.255.255.0)
    $sql = mysqli_query($mysqli, "SELECT network_id, network, network_subnet FROM networks
        WHERE network NOT LIKE '%/%' AND network_subnet IS NOT NULL AND network_subnet <> ''");
    while ($row = mysqli_fetch_assoc($sql)) {
        $address = trim($row['network']);
        $subnet = trim($row['network_subnet']);
        $bin = @inet_pton($address);
        if ($bin === false) {
            continue;
        }
        $prefix = null;
        if (ctype_digit($subnet) && intval($subnet) <= strlen($bin) * 8) {
            $prefix = intval($subnet);
        } elseif (strlen($bin) === 4 && ($mask = @inet_pton($subnet)) !== false && strlen($mask) === 4) {
            $bits = '';
            foreach (str_split($mask) as $byte) {
                $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
            }
            if (preg_match('/^(1*)0*$/', $bits, $match)) {
                $prefix = strlen($match[1]);
            }
        }
        if ($prefix !== null) {
            $cidr = mysqli_real_escape_string($mysqli, inet_ntop($bin) . '/' . $prefix);
            mysqli_query($mysqli, "UPDATE networks SET network = '$cidr' WHERE network_id = " . intval($row['network_id']));
        }
    }

    // A documented address can be a fixed one, a DHCP reservation (with the MAC
    // it is reserved for), held back, or a virtual / floating address
    mysqli_query($mysqli, "ALTER TABLE `network_ips` ADD COLUMN `ip_type` varchar(30) NOT NULL DEFAULT 'Static' AFTER `ip_address`");
    mysqli_query($mysqli, "ALTER TABLE `network_ips` ADD COLUMN `ip_mac` varchar(200) DEFAULT NULL AFTER `ip_hostname`");

    // The VLANs on a port - "20", or "Trunk 1,10,20" - and the network page
    // finds the interfaces on a network by it
    mysqli_query($mysqli, "ALTER TABLE `asset_interfaces` ADD COLUMN `interface_vlans` varchar(200) DEFAULT NULL AFTER `interface_ipv6`");
    mysqli_query($mysqli, "ALTER TABLE `asset_interfaces` ADD INDEX `interface_network_id` (`interface_network_id`)");
