<?php


// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSslCertificate($full_name) {

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

// ============================================================
// RDAP + native-DNS domain lookups (no shell_exec)
// Restored from commit d1e1609b; lost during the functions.php split.
// ============================================================

// --- RDAP helpers ---------------------------------------------------------

function rdapHttpGet($url, &$http_code = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'ITFlow-Domain-Check',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => true, // RDAP bootstrap/redirectors use 30x
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_HTTPHEADER     => array('Accept: application/rdap+json'),
    ));
 
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    return ($response === false) ? '' : $response;
}

function getDomainRdap($domain) {
    static $cache = array();
 
    if (array_key_exists($domain, $cache)) {
        return $cache[$domain];
    }
    $cache[$domain] = null;
 
    $tld = substr(strrchr($domain, '.'), 1);
    if (empty($tld)) {
        return null;
    }
 
    // Primary: IANA bootstrap -> registry RDAP server directly
    $base = getRdapBaseUrl($tld);
    if (!empty($base)) {
        $raw = rdapHttpGet($base . 'domain/' . rawurlencode($domain), $code);
        if ($code === 200 && !empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cache[$domain] = $decoded;
                return $decoded;
            }
        }
        if ($code === 404) {
            return null; // Domain genuinely not found - don't bother the fallback
        }
    }
 
    // Fallback: rdap.org redirector (covers gaps and bootstrap fetch failures)
    $raw = rdapHttpGet('https://rdap.org/domain/' . rawurlencode($domain), $code);
    if ($code === 200 && !empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $cache[$domain] = $decoded;
        }
    }
 
    return $cache[$domain];
}

function getRdapEventDate($rdap, $action) {
    if (empty($rdap['events']) || !is_array($rdap['events'])) {
        return '';
    }
    foreach ($rdap['events'] as $event) {
        if (isset($event['eventAction'], $event['eventDate']) && $event['eventAction'] === $action) {
            return $event['eventDate'];
        }
    }
    return '';
}

function getRdapRegistrar($rdap) {
    if (empty($rdap['entities']) || !is_array($rdap['entities'])) {
        return '';
    }
    foreach ($rdap['entities'] as $entity) {
        if (empty($entity['roles']) || !in_array('registrar', $entity['roles'])) {
            continue;
        }
        if (!empty($entity['vcardArray'][1]) && is_array($entity['vcardArray'][1])) {
            foreach ($entity['vcardArray'][1] as $field) {
                if (isset($field[0], $field[3]) && $field[0] === 'fn' && is_string($field[3])) {
                    return $field[3];
                }
            }
        }
    }
    return '';
}

function getRdapSummary($rdap) {
    $lines = array();
 
    $registrar = getRdapRegistrar($rdap);
    if (!empty($registrar)) {
        $lines[] = "Registrar: $registrar";
    }
 
    $registered = getRdapEventDate($rdap, 'registration');
    if (!empty($registered)) {
        $lines[] = 'Registered: ' . substr($registered, 0, 10);
    }
 
    $expires = getRdapEventDate($rdap, 'expiration');
    if (!empty($expires)) {
        $lines[] = 'Expires: ' . substr($expires, 0, 10);
    }
 
    if (!empty($rdap['status']) && is_array($rdap['status'])) {
        $lines[] = 'Status: ' . implode(', ', array_slice($rdap['status'], 0, 3));
    }
 
    if (!empty($rdap['nameservers']) && is_array($rdap['nameservers'])) {
        $ns = array();
        foreach ($rdap['nameservers'] as $nameserver) {
            if (!empty($nameserver['ldhName'])) {
                $ns[] = strtolower($nameserver['ldhName']);
            }
        }
        if (!empty($ns)) {
            sort($ns);
            $lines[] = 'Nameservers: ' . implode(', ', $ns);
        }
    }
 
    return implode("\n", $lines);
}

// --- Legacy whois fallback (port-43 socket, for TLDs without RDAP) --------

function whoisSocketQuery($server, $query) {
    $response = '';
 
    $fp = @fsockopen($server, 43, $errno, $errstr, 5);
    if (!$fp) {
        return '';
    }
 
    stream_set_timeout($fp, 5);
    fwrite($fp, $query . "\r\n");
 
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) {
            break;
        }
        $response .= $line;
 
        // Sanity cap - expiry/registrar fields always appear well before this
        if (strlen($response) > 32768) {
            break;
        }
    }
    fclose($fp);
 
    return $response;
}

function getDomainWhois($domain) {
    $tld = substr(strrchr($domain, '.'), 1);
    if (empty($tld)) {
        return '';
    }
 
    // Ask IANA which whois server handles this TLD
    $server = '';
    $iana_response = whoisSocketQuery('whois.iana.org', $tld);
    if (preg_match('/^whois:\s*(\S+)/mi', $iana_response, $matches)) {
        $server = $matches[1];
    }
    if (empty($server)) {
        return '';
    }
 
    // Verisign registries match nameservers too unless you use exact-match syntax
    $query = in_array($tld, array('com', 'net')) ? "=$domain" : $domain;
 
    $result = whoisSocketQuery($server, $query);
 
    // Thin registries (.com/.net) refer to the registrar's whois - follow it once
    if (preg_match('/Registrar WHOIS Server:\s*(\S+)/i', $result, $matches)) {
        $referral = rtrim(trim($matches[1]), '/');
        $referral = preg_replace('#^r?whois://#i', '', $referral);
        if (!empty($referral) && strcasecmp($referral, $server) !== 0) {
            $referred_result = whoisSocketQuery($referral, $domain);
            if (trim($referred_result) !== '') {
                $result = $referred_result;
            }
        }
    }
 
    return $result;
}

// --- Public API (names match current callers) ----------------------------

// getDnsRecords: native dns_get_record() for A/NS/MX/TXT + RDAP whois summary
function getDnsRecords($name) {
    $records = array(
        'a' => '',
        'ns' => '',
        'mx' => '',
        'txt' => '',
        'whois' => '',
        'expire' => ''
    );
 
    // Only run if we think the domain is valid
    if (!filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !checkdnsrr($name, 'SOA')) {
        return $records;
    }
 
    // Anchored so we don't mangle domains that merely start with "www"
    $domain = preg_replace('/^www\./i', '', strtolower(trim($name)));
 
    // A records
    $a = @dns_get_record($domain, DNS_A);
    if (is_array($a) && !empty($a)) {
        $a_records = array_column($a, 'ip');
        sort($a_records);
        $records['a'] = implode("\n", $a_records);
    }
 
    // NS records
    $ns = @dns_get_record($domain, DNS_NS);
    if (is_array($ns) && !empty($ns)) {
        $ns_records = array_column($ns, 'target');
        sort($ns_records);
        $records['ns'] = implode("\n", $ns_records);
    }
 
    // MX records - mimic dig +short output format ("10 mail.example.com")
    $mx = @dns_get_record($domain, DNS_MX);
    if (is_array($mx) && !empty($mx)) {
        $mx_records = array();
        foreach ($mx as $record) {
            $mx_records[] = $record['pri'] . ' ' . $record['target'];
        }
        sort($mx_records, SORT_NATURAL);
        $records['mx'] = implode("\n", $mx_records);
    }
 
    // TXT records
    $txt = @dns_get_record($domain, DNS_TXT);
    if (is_array($txt) && !empty($txt)) {
        $txt_records = array_column($txt, 'txt');
        sort($txt_records);
        $records['txt'] = implode("\n", $txt_records);
    }
 
    // Registration data - RDAP first, legacy whois only if the TLD has no RDAP
    $rdap = getDomainRdap($domain);
    if ($rdap !== null) {
        $records['whois'] = substr(getRdapSummary($rdap), 0, 254);
 
        $expires = getRdapEventDate($rdap, 'expiration');
        if (!empty($expires)) {
            $parsed = date_create($expires);
            if ($parsed) {
                $records['expire'] = $parsed->format('Y-m-d');
            }
        }
    } else {
        $whois_raw = getDomainWhois($domain);
        if (!empty($whois_raw) && stripos($whois_raw, 'rate limit') === false) {
            // Approximate the old `head -30 | sed 's/   //g'`
            $lines = array_slice(explode("\n", $whois_raw), 0, 30);
            $lines = array_map(function ($line) {
                return preg_replace('/   +/', ' ', rtrim($line));
            }, $lines);
            $records['whois'] = substr(trim(strip_tags(implode("\n", $lines))), 0, 254);
        }
    }
 
    return $records;
}

function getDomainExpirationDate($domain) {
    if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !checkdnsrr($domain, 'SOA')) {
        return null;
    }
 
    $domain = preg_replace('/^www\./i', '', strtolower(trim($domain)));
 
    // RDAP: expiration is a structured field - no regex, no date-format guessing
    $rdap = getDomainRdap($domain);
    if ($rdap !== null) {
        $expires = getRdapEventDate($rdap, 'expiration');
        if (!empty($expires)) {
            $parsed = date_create($expires);
            if ($parsed) {
                return $parsed->format('Y-m-d');
            }
        }
        return null; // RDAP answered but had no expiry (rare) - trust it, don't re-query
    }
 
    // Fallback for TLDs without RDAP: legacy whois parsing
    $result = getDomainWhois($domain);
    if (empty($result) || stripos($result, 'rate limit') !== false) {
        return null;
    }
 
    // Every expiry label seen in the wild, longest/most-specific first
    $labels = array(
        'Registrar Registration Expiration Date',
        'Registry Expiry Date',
        'Expiration Date',
        'Expiration Time',
        '\[Expires on\]',
        'Expires On',
        'Expiry Date',
        'Expire Date',
        'expire-date',
        'renewal date',
        'Valid Until',
        'paid-till',
        'validity',
        'renewal',
        'Expires',
        'expiry',
        'expire',
    );
 
    if (!preg_match('/(?:' . implode('|', $labels) . ')\s*:?\s+(.+)/i', $result, $matches)) {
        return null;
    }
    $expireDate = trim($matches[1]);
 
    // Known date formats (roundtrip-checked to avoid d-m-Y vs Y-m-d ambiguity)
    $knownFormats = array(
        'Y-m-d',
        'Y.m.d',
        'Y/m/d',
        'Ymd',
        'Y. m. d.',
        'Y-M-d.',
        'd-M-Y',
        'd-F-Y',
        'd-m-Y',
        'd.m.Y',
        'd/m/Y',
        'Y/m/d H:i:s',
        'Ymd H:i:s',
        'Y.m.d H:i:s',
        'Y-m-d H:i:s',
        'd-M-Y H:i:s',
        'd.m.Y H:i:s',
        'd/m/Y H:i:s',
        'd M Y H:i:s',
        'd/m/Y H:i:s T',
        'D M d H:i:s T Y',
        'D M d Y',
    );
 
    foreach ($knownFormats as $format) {
        $parsedDate = DateTime::createFromFormat($format, $expireDate);
        if ($parsedDate && $parsedDate->format($format) === $expireDate) {
            return $parsedDate->format('Y-m-d');
        }
    }
 
    // Fallback - handles ISO 8601 (2026-07-05T04:00:00Z) and most everything else
    $parsedDate = date_create($expireDate);
    if ($parsedDate) {
        $year = (int) $parsedDate->format('Y');
        // Reject obviously bogus parses
        if ($year >= 1995 && $year <= ((int) date('Y') + 100)) {
            return $parsedDate->format('Y-m-d');
        }
    }
 
    return null;
}
