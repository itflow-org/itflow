<?php
/*
 * API - Network IPs - Create
 * POST /api/v1/network_ips/create.php
 *
 * Document an address on a network, as Add IP does - inside the subnet and not
 * documented on it already (checkIpForNetwork()). The host part alone (20 in a /24) is
 * filled out from the subnet. With ip_range_end, every address from ip_address
 * to it - up to 256 - skipping those outside the subnet or documented already.
 * A DHCP reservation is for one device, with its MAC.
 *
 * Parameters (POST, JSON body):
 *   api_key         required - Your API key
 *   network_id      required - The network
 *   client_id       the network's client - needed when the key's user is limited to certain clients
 *   ip_address      required - The address
 *   ip_range_end    optional - The last address of a run
 *   ip_type         optional - Static (the default), Reservation, Reserved or Virtual
 *   ip_hostname, ip_mac, ip_description   optional
 *
 * Answer: insert_id (the first, for a run), and added and skipped.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

unset($_POST['ip_id']);
require_once 'network_ip_find.php';

if ($network) {

    $network_id = intval($network['network_id']);
    $ip_address = trim((string) (is_scalar($_POST['ip_address'] ?? '') ? ($_POST['ip_address'] ?? '') : ''));
    $range_end = trim((string) (is_scalar($_POST['ip_range_end'] ?? '') ? ($_POST['ip_range_end'] ?? '') : ''));
    $post = $ip_post();

    if ($range_end !== '' && $post['type'] === 'Reservation') {
        $ip_refuse('A DHCP reservation is for one device - add them one at a time.');
    }
    $error = '';
    $fields = networkIpFieldsSet($post, $error);
    if ($fields === '') {
        $ip_refuse($error);
    }

    $added = [];
    $skipped = 0;
    if ($range_end !== '') {
        $first = normalizeIpAddress(expandIpSuffix($ip_address, $network['network']));
        $last = normalizeIpAddress(expandIpSuffix($range_end, $network['network']));
        $first_int = $first === false ? null : ipv4ToInt($first);
        $last_int = $last === false ? null : ipv4ToInt($last);
        if ($first_int === null || $last_int === null || $first_int > $last_int) {
            $ip_refuse('A range is two IPv4 addresses, the lower first.');
        }
        if ($last_int - $first_int >= 256) {
            $ip_refuse('A range is up to 256 addresses at a time.');
        }
        for ($int = $first_int; $int <= $last_int; $int++) {
            $ip = intToIpv4($int);
            if (checkIpForNetwork($ip, $network_id) !== '') {
                $skipped++;
                continue;
            }
            mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip) . "', $fields, ip_network_id = $network_id");
            $added[] = intval(mysqli_insert_id($mysqli));
        }
        if (!$added) {
            $ip_refuse("None of $first to $last could be documented - they are outside the subnet or documented already.");
        }
        $what = "$first - $last";
    } else {
        $ip_error = checkIpForNetwork($ip_address, $network_id);
        if ($ip_error) {
            $ip_refuse($ip_error);
        }
        mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip_address) . "', $fields, ip_network_id = $network_id");
        $added[] = intval(mysqli_insert_id($mysqli));
        $what = $ip_address;
    }

    $insert_id = $added[0] ?: false;
    $return_arr['added'] = count($added);
    $return_arr['skipped'] = $skipped;

    $label = escapeSql("$what on network " . $network['network_name']);
    logAudit("IP Address", "Create", "Documented " . count($added) . " address(es), $label via API ($api_key_name)", $client_id, $network_id);
    logAudit("API", "Success", "Documented $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
