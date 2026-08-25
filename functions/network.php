<?php

/*
 * ITFlow - IP address and subnet helpers
 *
 * Backs the per-network IP address list (agent/network.php). Everything here
 * works on both IPv4 and IPv6 by comparing the packed binary form, so there is
 * no ip2long/32-bit path to go wrong.
 */

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
