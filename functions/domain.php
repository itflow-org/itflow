<?php


// Used to automatically attempt to get SSL certificates as part of adding domains
// The logic for the fetch (sync) button on the client_certificates page is in ajax.php, and allows ports other than 443
function getSslCertificate($full_name)
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

function getRdapBaseUrl($tld) {
    static $bootstrap = null;
 
    if ($bootstrap === null) {
        $cache_file = sys_get_temp_dir() . '/itflow_rdap_bootstrap.json';
 
        if (is_readable($cache_file) && (time() - filemtime($cache_file)) < 604800) {
            $bootstrap = json_decode(file_get_contents($cache_file), true);
        }
 
        if (empty($bootstrap['services'])) {
            $raw = rdapHttpGet('https://data.iana.org/rdap/dns.json', $code);
            if ($code === 200 && !empty($raw)) {
                $decoded = json_decode($raw, true);
                if (!empty($decoded['services'])) {
                    $bootstrap = $decoded;
                    @file_put_contents($cache_file, $raw);
                }
            }
        }
 
        if (empty($bootstrap['services'])) {
            $bootstrap = array('services' => array()); // don't retry every call this run
        }
    }
 
    foreach ($bootstrap['services'] as $service) {
        // [0] = list of TLDs, [1] = list of base URLs
        if (in_array($tld, $service[0])) {
            return rtrim($service[1][0], '/') . '/';
        }
    }
 
    return '';
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
        'dmarc' => '',
        'dkim' => '',
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
 
    // DMARC - the policy record at _dmarc
    $dmarc = @dns_get_record("_dmarc.$domain", DNS_TXT);
    if (is_array($dmarc)) {
        foreach ($dmarc as $record) {
            if (isset($record['txt']) && stripos($record['txt'], 'v=DMARC1') === 0) {
                $records['dmarc'] = $record['txt'];
                break;
            }
        }
    }

    // DKIM - there is no list of a domain's selectors, so look at the ones the
    // big senders use (Microsoft 365, Google Workspace, Mailchimp and the usual defaults)
    $selectors = array();
    foreach (DOMAIN_DKIM_SELECTORS as $selector) {
        $dkim = @dns_get_record("$selector._domainkey.$domain", DNS_TXT);
        $cname = $dkim ? array() : @dns_get_record("$selector._domainkey.$domain", DNS_CNAME);
        if (!empty($dkim) || !empty($cname)) {
            $selectors[] = $selector;
        }
    }
    $records['dkim'] = implode("\n", $selectors);

    // Registration data - RDAP first, legacy whois only if the TLD has no RDAP
    $rdap = getDomainRdap($domain);
    if ($rdap !== null) {
        $records['whois'] = substr(getRdapSummary($rdap), 0, 4000);
 
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
            $records['whois'] = substr(trim(strip_tags(implode("\n", $lines))), 0, 4000);
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

// ============================================================
// Domains in the app - what the list, the quick look, the forms
// and the refresher share
// ============================================================

/** Expiring within this many days is "soon" - the same 45 days the first expiry alert goes out at. */
const DOMAIN_SOON_DAYS = 45;

/** DKIM selectors looked for - Microsoft 365 (selector1, selector2), Google Workspace, the usual defaults and Mailchimp. */
const DOMAIN_DKIM_SELECTORS = ['selector1', 'selector2', 'google', 'default', 'k1', 's1'];

/** Who does what for a domain: each role, its column and how it is shown. */
const DOMAIN_ROLES = [
    'registrar' => ['column' => 'domain_registrar', 'label' => 'Registrar', 'icon' => 'fa-id-card'],
    'dnshost' => ['column' => 'domain_dnshost', 'label' => 'DNS', 'icon' => 'fa-sitemap'],
    'webhost' => ['column' => 'domain_webhost', 'label' => 'Website', 'icon' => 'fa-server'],
    'mailhost' => ['column' => 'domain_mailhost', 'label' => 'Email', 'icon' => 'fa-envelope'],
];

/** What each tracked column is called in a domain's history. */
const DOMAIN_HISTORY_LABELS = [
    'domain_name' => 'Name', 'domain_description' => 'Description', 'domain_expire' => 'Expiry', 'domain_auto_renew' => 'Auto-renew',
    'registrar_name' => 'Registrar', 'dnshost_name' => 'DNS host', 'webhost_name' => 'Web host', 'mailhost_name' => 'Mail host',
    'domain_ip' => 'A records', 'domain_name_servers' => 'Name servers', 'domain_mail_servers' => 'MX records', 'domain_txt' => 'TXT records',
    'domain_dmarc' => 'DMARC', 'domain_dkim' => 'DKIM', 'domain_raw_whois' => 'WHOIS', 'domain_notes' => 'Notes',
];

/** May this agent do this with domains? 1 is to see them, 2 to change them, 3 to delete them. */
function domainCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/** The columns and joins for a domain with its client and the vendor behind each role (registrar_name, dnshost_name...). */
function domainSelectSql() {
    $columns = 'domains.*, client_name';
    $joins = 'LEFT JOIN clients ON client_id = domain_client_id';
    foreach (DOMAIN_ROLES as $role => $info) {
        $columns .= ", $role.vendor_name AS {$role}_name, $role.vendor_client_id AS {$role}_client_id, $role.vendor_archived_at AS {$role}_archived_at";
        $joins .= " LEFT JOIN vendors AS $role ON $role.vendor_id = domains.{$info['column']}";
    }
    return [$columns, $joins];
}

/** One domain by id with its client and vendors - raw, or null. */
function domainRow($domain_id) {
    global $mysqli;
    $domain_id = intval($domain_id);
    if (!$domain_id) {
        return null;
    }
    [$columns, $joins] = domainSelectSql();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $columns FROM domains $joins WHERE domain_id = $domain_id LIMIT 1"));
    return $row ?: null;
}

/**
 * A domain name as it is kept - lowercase, without a scheme, a path, a port,
 * a leading www. or a trailing dot, and international names in their ASCII
 * form - from whatever was pasted ("https://www.Example.com/contact"). '' when
 * it is not a domain name.
 */
function domainNormalizeName($input) {
    $name = strtolower(trim(html_entity_decode((string) $input, ENT_QUOTES, 'UTF-8')));
    $name = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $name);
    $name = preg_replace('#^[^@/]*@#', '', $name);
    $name = preg_split('#[/?\#\s]#', $name)[0];
    $name = preg_replace('#:\d+$#', '', $name);
    $name = trim($name, '.');
    $name = preg_replace('#^www\.#', '', $name);
    if ($name !== '' && preg_match('/[^\x20-\x7e]/', $name) && function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        $name = $ascii ?: $name;
    }
    if ($name === '' || strlen($name) > 200 || strpos($name, '.') === false
        || !filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || !preg_match('/\.[a-z][a-z0-9\-]*[a-z0-9]$|\.xn--[a-z0-9\-]+$/', $name)) {
        return '';
    }
    return $name;
}

/** A vendor id a domain may use - one of the client's own, or one of ours (the MSP hosting DNS or mail) - else 0. */
function domainVendorId($vendor_id, $client_id) {
    global $mysqli;
    $vendor_id = intval($vendor_id);
    $client_id = intval($client_id);
    if (!$vendor_id) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id IN ($client_id, 0) LIMIT 1"));
    return $row ? intval($row[0]) : 0;
}

/** The vendors a domain's pickers offer - the client's, then ours - plus any already picked, archived or not. */
function domainVendorChoices($client_id, array $keep_ids = []) {
    global $mysqli;
    $client_id = intval($client_id);
    $keep = implode(',', array_filter(array_map('intval', $keep_ids))) ?: '0';
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description, vendor_client_id FROM vendors
        WHERE (vendor_client_id IN ($client_id, 0) AND vendor_archived_at IS NULL) OR vendor_id IN ($keep)
        ORDER BY vendor_client_id = 0, vendor_favorite DESC, vendor_name ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * The fields a New / Edit form posts, as a SQL SET list, with the tidied name.
 * Null with $error set when it cannot be saved: not a domain name, already on
 * this client, or an expiry that is not a date. The vendors must be the
 * client's or ours.
 */
function domainFormSet($post, $client_id, $domain_id = 0, &$error = '') {
    global $mysqli;
    $error = '';
    $client_id = intval($client_id);
    $domain_id = intval($domain_id);
    $name = domainNormalizeName($post['name'] ?? '');
    if ($name === '') {
        $error = "That isn't a domain name - something like example.com.";
        return null;
    }
    $name_sql = escapeSql($name);
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM domains WHERE domain_name = '$name_sql' AND domain_client_id = $client_id AND domain_id <> $domain_id LIMIT 1"))) {
        $error = "$name is already on this client.";
        return null;
    }
    $expire = trim((string) ($post['expire'] ?? ''));
    if ($expire !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $expire);
        if (!$date || $date->format('Y-m-d') !== $expire) {
            $error = 'The expiry date is not a date.';
            return null;
        }
    }
    $fields = [
        'domain_name' => "'$name_sql'",
        'domain_description' => "'" . escapeSql(mb_substr(trim((string) ($post['description'] ?? '')), 0, 500)) . "'",
        'domain_expire' => $expire === '' ? 'NULL' : "'$expire'",
        'domain_auto_renew' => intval($post['auto_renew'] ?? 0) ? 1 : 0,
        'domain_favorite' => intval($post['favorite'] ?? 0) ? 1 : 0,
        'domain_notes' => "'" . escapeSql(trim((string) ($post['notes'] ?? ''))) . "'",
    ];
    foreach (DOMAIN_ROLES as $role => $info) {
        $fields[$info['column']] = domainVendorId($post[$role] ?? 0, $client_id);
    }
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return ['set' => implode(', ', $set), 'name' => $name, 'expire' => $expire];
}

/** Replace a domain's tags with these - domain tags (type 7) only. */
function domainSetTags($domain_id, $tag_ids) {
    global $mysqli;
    $domain_id = intval($domain_id);
    mysqli_query($mysqli, "DELETE FROM domain_tags WHERE domain_id = $domain_id");
    $tag_ids = array_values(array_unique(array_filter(array_map('intval', (array) $tag_ids))));
    if (!$tag_ids) {
        return;
    }
    mysqli_query($mysqli, "INSERT IGNORE INTO domain_tags (domain_id, tag_id)
        SELECT $domain_id, tag_id FROM tags WHERE tag_type = 7 AND tag_id IN (" . implode(',', $tag_ids) . ")");
}

/**
 * Look a domain up - DNS (A, NS, MX, TXT, DMARC, DKIM), registration data
 * and the expiry date. Null when nothing answers for it (a typo, a lapsed
 * domain, or no network), so callers keep what they had rather than wiping it.
 */
function domainLookup($name) {
    $name = domainNormalizeName($name);
    if ($name === '' || !@checkdnsrr($name, 'SOA')) {
        return null;
    }
    $records = getDnsRecords($name);
    $expire = getDomainExpirationDate($name);
    $records['expire'] = $expire ?: (string) $records['expire'];
    return $records;
}

/**
 * Save what a lookup found. The expiry is the registry's when it gave one,
 * otherwise the date already there stays (some registries don't publish it);
 * the WHOIS summary stays when this lookup got none. False when there was
 * nothing to save.
 */
function domainApplyLookup($domain_id, $lookup) {
    global $mysqli;
    $domain_id = intval($domain_id);
    if (!$lookup || !$domain_id) {
        return false;
    }
    $set = [
        "domain_ip = '" . escapeSql($lookup['a']) . "'",
        "domain_name_servers = '" . escapeSql(mb_substr($lookup['ns'], 0, 255)) . "'",
        "domain_mail_servers = '" . escapeSql(mb_substr($lookup['mx'], 0, 255)) . "'",
        "domain_txt = '" . escapeSql($lookup['txt']) . "'",
        "domain_dmarc = '" . escapeSql($lookup['dmarc'] ?? '') . "'",
        "domain_dkim = '" . escapeSql(mb_substr($lookup['dkim'] ?? '', 0, 255)) . "'",
        'domain_refreshed_at = NOW()',
    ];
    if (trim((string) $lookup['whois']) !== '') {
        $set[] = "domain_raw_whois = '" . escapeSql($lookup['whois']) . "'";
    }
    if (!empty($lookup['expire']) && strtotime($lookup['expire'])) {
        $set[] = "domain_expire = '" . escapeSql($lookup['expire']) . "'";
    }
    mysqli_query($mysqli, "UPDATE domains SET " . implode(', ', $set) . " WHERE domain_id = $domain_id");
    return true;
}

/** What a domain's history tracks, as it stands now - compared before and after a change by domainWriteHistory(). */
function domainHistorySnapshot($domain_id) {
    $row = domainRow($domain_id);
    if (!$row) {
        return [];
    }
    $snapshot = [];
    foreach (array_keys(DOMAIN_HISTORY_LABELS) as $column) {
        $snapshot[$column] = (string) ($row[$column] ?? '');
    }
    return $snapshot;
}

/** Record what changed between two snapshots in the domain's history. */
function domainWriteHistory($domain_id, array $before, array $after) {
    global $mysqli;
    $domain_id = intval($domain_id);
    foreach ($after as $column => $new_value) {
        $old_value = $before[$column] ?? '';
        if ((string) $old_value === (string) $new_value) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO domain_history SET domain_history_column = '" . escapeSql($column) . "',
            domain_history_old_value = '" . escapeSql($old_value) . "', domain_history_new_value = '" . escapeSql($new_value) . "',
            domain_history_domain_id = $domain_id");
    }
}

/** A domain's history, newest first - each change with its label. */
function domainHistory($domain_id, $limit = 25) {
    global $mysqli;
    $domain_id = intval($domain_id);
    $limit = max(1, intval($limit));
    $sql = mysqli_query($mysqli, "SELECT domain_history_column, domain_history_old_value, domain_history_new_value, domain_history_modified_at
        FROM domain_history WHERE domain_history_domain_id = $domain_id
        ORDER BY domain_history_modified_at DESC, domain_history_id DESC LIMIT $limit");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['label'] = DOMAIN_HISTORY_LABELS[$row['domain_history_column']] ?? ucfirst(str_replace('_', ' ', preg_replace('/^domain_/', '', $row['domain_history_column'])));
        $rows[] = $row;
    }
    return $rows;
}

/**
 * When it expires: 'expired', 'soon' (within 45 days) or 'active', with a
 * tone, a line to show and a short form for the list. A domain set to
 * auto-renew is blue rather than amber when it is close. Null without a date.
 */
function domainExpiry($expire, $auto_renew = 0) {
    $renewal = softwareRenewal($expire, DOMAIN_SOON_DAYS);
    if (!$renewal) {
        return null;
    }
    $days = $renewal['days'];
    $auto = intval($auto_renew) === 1;
    if ($renewal['state'] === 'expired') {
        $renewal['short'] = 'Expired';
    } elseif ($renewal['state'] === 'soon') {
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");
        $renewal['text'] = ($auto ? 'Auto-renews ' : 'Expires ') . $when;
        $renewal['short'] = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "$days days");
        $renewal['tone'] = $auto ? 'info' : ($days <= 7 ? 'danger' : 'warning');
    } else {
        $renewal['text'] = ($auto ? 'Auto-renews ' : 'Expires ') . timeAgo($renewal['date']);
        $renewal['short'] = timeAgo($renewal['date']);
    }
    $renewal['auto'] = $auto;
    return $renewal;
}

/** The expiry as a soft pill - red expired, amber soon, blue soon but auto-renewing, otherwise green. */
function domainExpiryPill($expiry, $use_text = false) {
    if (!$expiry) {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal" title="No expiry date - look it up or set it">'
            . '<i class="fas fa-question me-1"></i>Unknown</span>';
    }
    $tone = $expiry['tone'];
    $icon = $expiry['state'] === 'expired' ? 'fa-calendar-times' : ($expiry['auto'] ? 'fa-sync-alt' : ($expiry['state'] === 'soon' ? 'fa-hourglass-half' : 'fa-calendar-check'));
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($expiry['date'] . ($expiry['auto'] ? ' - set to auto-renew' : '')) . '">'
        . '<i class="fas ' . $icon . ' me-1"></i>' . escapeHtml($use_text ? $expiry['text'] : $expiry['short']) . '</span>';
}

/** A domain's tile - a globe, in the same rounded square as the other tiles. $size 'xs' | 'sm' | '' | 'lg'. */
function domainTile($size = '') {
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size" . ($size === 'xs' ? ' itflow-vendor-tile-xs' : '') : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-network" aria-hidden="true"><i class="fas fa-fw fa-globe"></i></span>';
}

/** A domain wherever a page mentions one: its tile and its name, opening its quick look. 'archived' strikes it through. */
function domainLink($domain_id, $name, $options = []) {
    $domain_id = intval($domain_id);
    $label = escapeHtml(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    return '<span class="itflow-contact-ref">' . domainTile('xs')
        . "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/domain/domain.php?id=$domain_id\">$label</a></span>";
}

/** Record lines kept one per line (or comma-separated in older data) as a list. */
function domainLines($text) {
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $text)), 'strlen'));
}

/**
 * Who does what - each vendor with the roles it holds for this domain, so a
 * registrar that also runs DNS and the website shows once: [vendor_id =>
 * ['name', 'client_id', 'archived', 'roles' => [labels]]].
 */
function domainVendors($row) {
    $vendors = [];
    foreach (DOMAIN_ROLES as $role => $info) {
        $vendor_id = intval($row[$info['column']] ?? 0);
        if (!$vendor_id || ($row["{$role}_name"] ?? null) === null) {
            continue;
        }
        if (!isset($vendors[$vendor_id])) {
            $vendors[$vendor_id] = ['name' => $row["{$role}_name"], 'client_id' => intval($row["{$role}_client_id"]), 'archived' => !empty($row["{$role}_archived_at"]), 'roles' => []];
        }
        $vendors[$vendor_id]['roles'][] = $info['label'];
    }
    return $vendors;
}

/** The vendors as small links, each with the roles it holds. */
function domainVendorsHtml($row) {
    $html = '';
    foreach (domainVendors($row) as $vendor_id => $vendor) {
        $html .= '<div class="d-flex align-items-center gap-2 min-w-0">' . vendorLink($vendor_id, $vendor['name'], ['archived' => $vendor['archived']])
            . '<span class="small text-body-secondary text-nowrap">' . escapeHtml(implode(' · ', $vendor['roles'])) . '</span></div>';
    }
    return $html;
}

/**
 * The email checks MSPs get asked about - is there mail (MX), SPF and how
 * strict it is, the DMARC policy, DKIM at the common selectors. Each is
 * [label, state ok|warn|bad|off|unknown, text]. DMARC and DKIM are unknown
 * until the domain has been looked up since those checks existed.
 */
function domainMailChecks($row) {
    $looked_up = !empty($row['domain_refreshed_at']);
    $has_mail = domainLines($row['domain_mail_servers'] ?? '') !== [];
    $spf = array_values(array_filter(domainLines(str_replace(',', ' ', (string) ($row['domain_txt'] ?? ''))), function ($line) {
        return stripos(trim($line, '"'), 'v=spf1') === 0;
    }));
    $checks = [];

    $checks['mx'] = $has_mail
        ? ['label' => 'MX', 'state' => 'ok', 'text' => 'Takes mail at ' . implode(', ', array_map(function ($mx) { return preg_replace('/^\d+\s+/', '', $mx); }, array_slice(domainLines($row['domain_mail_servers']), 0, 2)))]
        : ['label' => 'MX', 'state' => 'off', 'text' => 'No mail servers - it takes no email'];

    if (count($spf) > 1) {
        $checks['spf'] = ['label' => 'SPF', 'state' => 'bad', 'text' => count($spf) . ' SPF records - receivers treat that as no SPF at all. Merge them into one.'];
    } elseif (!$spf) {
        $checks['spf'] = ['label' => 'SPF', 'state' => $has_mail ? 'bad' : 'warn', 'text' => $has_mail ? 'No SPF record - anyone can send as this domain' : 'No SPF record - add "v=spf1 -all" so nobody can send as it'];
    } else {
        $record = trim($spf[0], '"');
        if (preg_match('/[\-~]all\b/i', $record) || stripos($record, 'redirect=') !== false) {
            $checks['spf'] = ['label' => 'SPF', 'state' => 'ok', 'text' => (stripos($record, '-all') !== false ? 'SPF, hard fail (-all)' : (stripos($record, '~all') !== false ? 'SPF, soft fail (~all)' : 'SPF, redirected')) . ': ' . $record];
        } else {
            $checks['spf'] = ['label' => 'SPF', 'state' => 'warn', 'text' => 'SPF that lets anyone through (no -all or ~all): ' . $record];
        }
    }

    $dmarc = trim((string) ($row['domain_dmarc'] ?? ''));
    if (!$looked_up && $dmarc === '') {
        $checks['dmarc'] = ['label' => 'DMARC', 'state' => 'unknown', 'text' => 'Not checked yet - look the domain up'];
    } elseif ($dmarc === '') {
        $checks['dmarc'] = ['label' => 'DMARC', 'state' => $has_mail ? 'bad' : 'warn', 'text' => 'No DMARC record'];
    } else {
        $policy = preg_match('/\bp\s*=\s*(none|quarantine|reject)/i', $dmarc, $match) ? strtolower($match[1]) : 'none';
        $checks['dmarc'] = $policy === 'none'
            ? ['label' => 'DMARC', 'state' => 'warn', 'text' => 'DMARC p=none - reports only, spoofed mail still delivered: ' . $dmarc]
            : ['label' => 'DMARC', 'state' => 'ok', 'text' => "DMARC p=$policy: $dmarc"];
    }

    $dkim = domainLines($row['domain_dkim'] ?? '');
    if (!$looked_up && !$dkim) {
        $checks['dkim'] = ['label' => 'DKIM', 'state' => 'unknown', 'text' => 'Not checked yet - look the domain up'];
    } elseif ($dkim) {
        $checks['dkim'] = ['label' => 'DKIM', 'state' => 'ok', 'text' => 'DKIM at ' . implode(', ', $dkim)];
    } else {
        $checks['dkim'] = ['label' => 'DKIM', 'state' => $has_mail ? 'warn' : 'off', 'text' => 'No DKIM at the usual selectors (' . implode(', ', DOMAIN_DKIM_SELECTORS) . ') - it may use another'];
    }
    return $checks;
}

/** The email checks as small pills - green, amber, red, grey. */
function domainMailPillsHtml(array $checks) {
    $tones = ['ok' => 'success', 'warn' => 'warning', 'bad' => 'danger', 'off' => 'secondary', 'unknown' => 'secondary'];
    $icons = ['ok' => 'fa-check', 'warn' => 'fa-exclamation', 'bad' => 'fa-times', 'off' => 'fa-minus', 'unknown' => 'fa-question'];
    $html = '';
    foreach ($checks as $check) {
        $tone = $tones[$check['state']];
        $html .= '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($check['text']) . '">'
            . '<i class="fas ' . $icons[$check['state']] . ' me-1"></i>' . escapeHtml($check['label']) . '</span>';
    }
    return '<span class="d-inline-flex flex-wrap gap-1">' . $html . '</span>';
}

/** The SQL for "takes mail but SPF is missing or DMARC is missing or only p=none" - the Email gaps filter. */
function domainMailGapSql($alias = 'domains') {
    return "(COALESCE($alias.domain_mail_servers, '') <> '' AND (COALESCE($alias.domain_txt, '') NOT LIKE '%v=spf1%'
        OR ($alias.domain_refreshed_at IS NOT NULL AND (COALESCE($alias.domain_dmarc, '') = '' OR $alias.domain_dmarc LIKE '%p=none%'))))";
}

/** The registration facts in the WHOIS summary - registrar, registered, status - when it has them one per line. */
function domainRegistrationFacts($whois) {
    $facts = [];
    foreach (['registrar' => 'Registrar', 'registered' => 'Registered', 'status' => 'Status'] as $key => $label) {
        if (preg_match('/^' . $label . ':\s*(.+)$/mi', (string) $whois, $match) && strpos((string) $whois, "\n") !== false) {
            $facts[$key] = trim($match[1]);
        }
    }
    return $facts;
}

/** The certificates for a domain - linked to it, or for it or one of its subdomains on the same client. */
function domainCertificates($row) {
    global $mysqli;
    $domain_id = intval($row['domain_id']);
    $client_id = intval($row['domain_client_id']);
    $name = escapeSql($row['domain_name']);
    $name_like = escapeSql(str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $row['domain_name']));
    $sql = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_expire FROM certificates
        WHERE certificate_archived_at IS NULL AND certificate_client_id = $client_id
        AND (certificate_domain_id = $domain_id OR certificate_domain = '$name' OR certificate_domain LIKE '%.$name_like')
        ORDER BY certificate_expire IS NULL, certificate_expire ASC");
    $rows = [];
    while ($cert = mysqli_fetch_assoc($sql)) {
        $rows[] = $cert;
    }
    return $rows;
}

/** The services that use a domain. */
function domainServices($domain_id) {
    global $mysqli;
    $domain_id = intval($domain_id);
    $sql = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_importance FROM service_domains
        JOIN services ON services.service_id = service_domains.service_id WHERE service_domains.domain_id = $domain_id ORDER BY service_name");
    $rows = [];
    while ($service = mysqli_fetch_assoc($sql)) {
        $rows[] = $service;
    }
    return $rows;
}

/** The client's logins for the domain's registrar and hosts - linked to those vendors - for those who may see credentials. */
function domainLogins($row) {
    $vendor_ids = array_keys(domainVendors($row));
    if (!$vendor_ids || lookupUserPermission('module_credential') < 1) {
        return [];
    }
    $client_id = intval($row['domain_client_id']);
    return credentialRows("credential_client_id = $client_id AND credential_id IN (SELECT credential_id FROM vendor_credentials WHERE vendor_id IN (" . implode(',', array_map('intval', $vendor_ids)) . "))");
}

/** Deleting a domain for good - services stop listing it and certificates stop pointing at it. History and tags cascade. */
function domainDeleteForGood($domain_id) {
    global $mysqli;
    $domain_id = intval($domain_id);
    mysqli_query($mysqli, "DELETE FROM service_domains WHERE domain_id = $domain_id");
    mysqli_query($mysqli, "UPDATE certificates SET certificate_domain_id = 0 WHERE certificate_domain_id = $domain_id");
    mysqli_query($mysqli, "DELETE FROM domains WHERE domain_id = $domain_id");
}

/**
 * Bring an SSL certificate for a new domain in, when its website has one and
 * the client has none for that name yet. True when one was added.
 */
function domainAddCertificate($domain_id, $name, $client_id) {
    global $mysqli;
    $domain_id = intval($domain_id);
    $client_id = intval($client_id);
    $name_sql = escapeSql($name);
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM certificates WHERE certificate_client_id = $client_id AND certificate_domain IN ('$name_sql', 'www.$name_sql') LIMIT 1"))) {
        return false;
    }
    $certificate = getSslCertificate($name);
    if (empty($certificate['success'])) {
        return false;
    }
    mysqli_query($mysqli, "INSERT INTO certificates SET certificate_name = '$name_sql', certificate_domain = '$name_sql',
        certificate_issued_by = '" . escapeSql($certificate['issued_by']) . "', certificate_expire = '" . escapeSql($certificate['expire']) . "',
        certificate_public_key = '" . escapeSql($certificate['public_key']) . "', certificate_domain_id = $domain_id, certificate_client_id = $client_id");
    return true;
}
