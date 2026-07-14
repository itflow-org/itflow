<?php

// DNS, SSL and domain expiration lookups (WHOIS/RDAP)
// Split from the former monolithic functions.php


// Get domain general info (whois + NS/A/MX records)
function getDomainRecords($name)
{
    $records = array();

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !checkdnsrr($name, 'SOA')) {
        $records['a'] = '';
        $records['ns'] = '';
        $records['mx'] = '';
        $records['whois'] = '';
        return $records;
    }

    $domain = escapeshellarg(str_replace('www.', '', $name));

    // Get A, NS, MX, TXT, and WHOIS records
    $records['a'] = trim(strip_tags(shell_exec("dig +short $domain")));
    $records['ns'] = trim(strip_tags(shell_exec("dig +short NS $domain")));
    $records['mx'] = trim(strip_tags(shell_exec("dig +short MX $domain")));
    $records['txt'] = trim(strip_tags(shell_exec("dig +short TXT $domain")));
    $records['whois'] = substr(trim(strip_tags(shell_exec("whois -H $domain | head -30 | sed 's/   //g'"))), 0, 254);

    // Sort A records (if multiple records exist)
    if (!empty($records['a'])) {
        $a_records = explode("\n", $records['a']);
        array_walk($a_records, function(&$record) {
            $record = trim($record);
        });
        sort($a_records);
        $records['a'] = implode("\n", $a_records);
    }

    // Sort NS records (if multiple records exist)
    if (!empty($records['ns'])) {
        $ns_records = explode("\n", $records['ns']);
        array_walk($ns_records, function(&$record) {
            $record = trim($record);
        });
        sort($ns_records);
        $records['ns'] = implode("\n", $ns_records);
    }

    // Sort MX records (if multiple records exist)
    if (!empty($records['mx'])) {
        $mx_records = explode("\n", $records['mx']);
        array_walk($mx_records, function(&$record) {
            $record = trim($record);
        });
        sort($mx_records);
        $records['mx'] = implode("\n", $mx_records);
    }

    // Sort TXT records (if multiple records exist)
    if (!empty($records['txt'])) {
        $txt_records = explode("\n", $records['txt']);
        array_walk($txt_records, function(&$record) {
            $record = trim($record);
        });
        sort($txt_records);
        $records['txt'] = implode("\n", $txt_records);
    }

    return $records;
}

// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSSL($full_name)
{

    // Parse host and port
    $name = parse_url("//$full_name", PHP_URL_HOST);
    $port = parse_url("//$full_name", PHP_URL_PORT);

    // Default port
    if (!$port) {
        $port = "443";
    }

    $certificate = array();
    $certificate['success'] = false;

    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $certificate['expire'] = '';
        $certificate['issued_by'] = '';
        $certificate['public_key'] = '';
        return $certificate;
    }

    // Get SSL/TSL certificate (using verify peer false to allow for self-signed certs) for domain on default port
    $socket = "ssl://$name:$port";
    $get = stream_context_create(array("ssl" => array("capture_peer_cert" => true, "verify_peer" => false,)));
    $read = stream_socket_client($socket, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $get);

    // If the socket connected
    if ($read) {
        $cert = stream_context_get_params($read);
        $cert_public_key_obj = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
        openssl_x509_export($cert['options']['ssl']['peer_certificate'], $export);

        if ($cert_public_key_obj) {
            $certificate['success'] = true;
            $certificate['expire'] = date('Y-m-d', $cert_public_key_obj['validTo_time_t']);
            $certificate['issued_by'] = strip_tags($cert_public_key_obj['issuer']['O']);
            $certificate['public_key'] = $export;
        }
    }

    return $certificate;
}

function getDomainExpirationDate($domain) {
    // Execute the whois command
    $result = shell_exec("whois " . escapeshellarg($domain));
    if (!$result || !checkdnsrr($domain, 'SOA')) {
        return null; // Return null if WHOIS query fails
    }

    $expireDate = '';

    // Regular expressions to match different date formats
    $patterns = [
        '/Expiration Date: (.+)/',
        '/Registry Expiry Date: (.+)/',
        '/expires: (.+)/',
        '/Expiry Date: (.+)/',
        '/renewal date: (.+)/',
        '/Expires On: (.+)/',
        '/paid-till: (.+)/',
        '/Expiration Time: (.+)/',
        '/\[Expires on\]\s+(.+)/',
        '/expire: (.+)/',
        '/validity: (.+)/',
        '/Expires on.*: (.+)/i',
        '/Expiry on.*: (.+)/i',
        '/renewal: (.+)/i',
        '/Expir\w+ Date: (.+)/i',
        '/Valid Until: (.+)/i',
        '/Valid until: (.+)/i',
        '/expire-date: (.+)/i',
        '/Expiration Date: (.+)/i',
        '/Registry Expiry Date: (.+)/i',
        '/Expire Date: (.+)/i',
        '/expiry: (.+)/i',
        '/expires: (.+)/i',
        '/Registry Expiry Date: (.+)/i',
        '/Expiration Time: (.+)/i',
        '/validity: (.+)/i',
        '/expires: (.+)/i',
        '/paid-till: (.+)/i',
        '/Expire Date: (.+)/i',
        '/Expiration Date: (.+)/i',
        '/expire: (.+)/i',
        '/expiry: (.+)/i',
        '/renewal date: (.+)/i',
        '/Expiration Date: (.+)/i',
        '/Expiration Time: (.+)/i',
        '/Expires: (.+)/i',
    ];

    // Known date formats
    $knownFormats = [
        "d-M-Y",
        "d-F-Y",
        "d-m-Y",
        "Y-m-d",
        "d.m.Y",
        "Y.m.d",
        "Y/m/d",
        "Y/m/d H:i:s",
        "Ymd",
        "Ymd H:i:s",
        "d/m/Y",
        "Y. m. d.",
        "Y.m.d H:i:s",
        "d-M-Y H:i:s",
        "D M d H:i:s T Y",
        "D M d Y",
        "Y-m-d\TH:i:s",
        "Y-m-d\TH:i:s\Z",
        "Y-m-d H:i:s\Z",
        "Y-m-d H:i:s",
        "d M Y H:i:s",
        "d/m/Y H:i:s",
        "d/m/Y H:i:s T",
        "B d Y",
        "d.m.Y H:i:s",
        "before M-Y",
        "before Y-m-d",
        "before Ymd",
        "Y-m-d H:i:s (\T\Z\Z)",
        "Y-M-d.",
    ];

    // Check each pattern to find a match
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $result, $matches)) {
            $expireDate = trim($matches[1]);
            break;
        }
    }

    if ($expireDate) {
        // Try parsing with known formats
        foreach ($knownFormats as $format) {
            $parsedDate = DateTime::createFromFormat($format, $expireDate);
            if ($parsedDate && $parsedDate->format($format) === $expireDate) {
                return $parsedDate->format('Y-m-d');
            }
        }

        // If none of the formats matched, try to parse it directly
        $parsedDate = date_create($expireDate);
        if ($parsedDate) {
            return $parsedDate->format('Y-m-d');
        }
    }

    return null; // Return null if expiration date is not found
}
