<?php

/*
 * Certificates - the TLS certificates on a client's websites, portals,
 * firewalls and appliances.
 *
 * A certificate has a host it is served on (certificate_domain, as host or
 * host:port), and what the certificate itself says - issuer, issued and
 * expiry dates, the names it covers, its SHA-256 fingerprint and the PEM.
 * Those come from a check of the host (certificateCheck(), which also says
 * whether what the host serves is OK, for another name, untrusted, expired
 * or not answering) or from a pasted PEM (certificateParse()). A check that
 * reaches nothing records that it couldn't, and changes nothing else.
 */

/** Expiring within this many days is "soon" - 20 for a short-lived certificate that should renew itself by then. */
const CERTIFICATE_SOON_DAYS = 30;

/** A certificate valid for no more than this many days is short-lived - issued by ACME (Let's Encrypt and friends) and renewed automatically. */
const CERTIFICATE_SHORT_LIVED_DAYS = 100;

/** What a check of the host can find. */
const CERTIFICATE_CHECK_STATES = [
    'ok' => ['label' => 'OK', 'tone' => 'success', 'icon' => 'fa-check', 'text' => 'The host serves it, valid and trusted'],
    'mismatch' => ['label' => 'Name mismatch', 'tone' => 'warning', 'icon' => 'fa-not-equal', 'text' => "What the host serves doesn't cover the host name - browsers will warn"],
    'untrusted' => ['label' => 'Untrusted', 'tone' => 'warning', 'icon' => 'fa-exclamation-triangle', 'text' => 'Self-signed, or its chain is incomplete - browsers will warn'],
    'expired' => ['label' => 'Expired', 'tone' => 'danger', 'icon' => 'fa-calendar-times', 'text' => 'The host serves an expired certificate'],
    'unreachable' => ['label' => 'Unreachable', 'tone' => 'secondary', 'icon' => 'fa-plug', 'text' => 'Nothing answered on that host and port'],
];

/** What each tracked column is called in a certificate's history. */
const CERTIFICATE_HISTORY_LABELS = [
    'certificate_name' => 'Name', 'certificate_description' => 'Description', 'certificate_domain' => 'Host', 'domain_name' => 'Domain',
    'certificate_issued_by' => 'Issuer', 'certificate_issued_at' => 'Issued', 'certificate_expire' => 'Expiry', 'certificate_names' => 'Names covered',
    'certificate_fingerprint' => 'Fingerprint', 'certificate_notes' => 'Notes',
];

/** May this agent do this with certificates? 1 is to see them, 2 to change them, 3 to delete them. */
function certificateCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/** The columns and joins for a certificate with its client and the domain it is linked to (the client's own). */
function certificateSelectSql() {
    return ['certificates.*, client_name, domains.domain_name AS domain_name, domains.domain_archived_at AS domain_archived_at',
        'LEFT JOIN clients ON client_id = certificate_client_id
        LEFT JOIN domains ON domains.domain_id = certificate_domain_id AND domains.domain_client_id = certificate_client_id'];
}

/** One certificate by id, with its client and domain - raw, or null. */
function certificateRow($certificate_id) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    if (!$certificate_id) {
        return null;
    }
    [$columns, $joins] = certificateSelectSql();
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT $columns FROM certificates $joins WHERE certificate_id = $certificate_id LIMIT 1"));
    return $row ?: null;
}

/**
 * Where a certificate is served, as it is kept - a host name or IP address,
 * with the port when it isn't 443 - from whatever was pasted
 * ("https://Portal.Example.com:8443/login"). ['host', 'port', 'display'], or
 * null when it is not a host. Internal names (fw01, nas.local) are fine.
 */
function certificateHost($input) {
    $value = strtolower(trim(html_entity_decode((string) $input, ENT_QUOTES, 'UTF-8')));
    $value = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $value);
    $value = preg_replace('#^[^@/]*@#', '', $value);
    $value = preg_split('#[/?\#]#', $value)[0];
    if ($value === '' || preg_match('/\s/', $value)) {
        return null;
    }
    $port = 443;
    if (preg_match('/^\[([0-9a-f:.]+)\](?::(\d+))?$/', $value, $match)) {
        // [IPv6]:port
        $host = $match[1];
        $port = isset($match[2]) ? intval($match[2]) : 443;
    } elseif (substr_count($value, ':') === 1) {
        [$host, $port_part] = explode(':', $value);
        if (!ctype_digit($port_part)) {
            return null;
        }
        $port = intval($port_part);
    } else {
        $host = $value;
    }
    $host = trim($host, '.');
    if ($port < 1 || $port > 65535) {
        return null;
    }
    $is_ip = filter_var($host, FILTER_VALIDATE_IP) !== false;
    if (!$is_ip && ($host === '' || strlen($host) > 200 || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
        return null;
    }
    $shown = strpos($host, ':') !== false ? "[$host]" : $host;
    return ['host' => $host, 'port' => $port, 'display' => $port === 443 ? $shown : "$shown:$port", 'ip' => $is_ip];
}

/**
 * What a certificate says about itself, from its PEM - the issuer, the dates,
 * the names it covers, its SHA-256 fingerprint, whether it signed itself.
 * The PEM given may be a whole chain or carry a key; only the first
 * certificate is kept. Null when there is no certificate in it.
 */
function certificateParse($pem) {
    if (!preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', (string) $pem, $match)) {
        return null;
    }
    $pem = $match[0];
    $parsed = @openssl_x509_parse($pem);
    if (!$parsed || empty($parsed['validTo_time_t'])) {
        return null;
    }
    $field = function ($value) {
        return is_array($value) ? (string) end($value) : (string) $value;
    };
    $names = [];
    foreach (explode(',', (string) ($parsed['extensions']['subjectAltName'] ?? '')) as $alt_name) {
        $alt_name = trim($alt_name);
        if (stripos($alt_name, 'DNS:') === 0) {
            $names[] = strtolower(substr($alt_name, 4));
        } elseif (stripos($alt_name, 'IP Address:') === 0) {
            $names[] = strtolower(trim(substr($alt_name, 11)));
        }
    }
    $subject_cn = $field($parsed['subject']['CN'] ?? '');
    if (!$names && $subject_cn !== '') {
        $names[] = strtolower($subject_cn);
    }
    $issuer = $field($parsed['issuer']['O'] ?? '') ?: $field($parsed['issuer']['CN'] ?? '');
    $fingerprint = @openssl_x509_fingerprint($pem, 'sha256');
    $valid_from = intval($parsed['validFrom_time_t']);
    $valid_to = intval($parsed['validTo_time_t']);
    return [
        'pem' => $pem,
        'issuer' => mb_substr(strip_tags($issuer), 0, 200),
        'subject' => $subject_cn,
        'issued_at' => date('Y-m-d', $valid_from),
        'expire' => date('Y-m-d', $valid_to),
        'expired' => $valid_to < time(),
        'names' => array_values(array_unique($names)),
        'fingerprint' => $fingerprint ? strtoupper(implode(':', str_split($fingerprint, 2))) : '',
        'self_signed' => ($parsed['issuer'] ?? null) == ($parsed['subject'] ?? null),
        'days' => intval(round(($valid_to - $valid_from) / 86400)),
    ];
}

/** Does a certificate covering these names cover this host? Exact, or one label under a wildcard. */
function certificateCovers($host, array $names) {
    $host = strtolower(trim((string) $host, '.'));
    foreach ($names as $name) {
        $name = strtolower(trim((string) $name, '.'));
        if ($name === $host) {
            return true;
        }
        if (strpos($name, '*.') === 0) {
            $suffix = substr($name, 1);
            $label = substr($host, 0, -strlen($suffix));
            if (substr($host, -strlen($suffix)) === $suffix && $label !== '' && strpos($label, '.') === false) {
                return true;
            }
        }
    }
    return false;
}

/**
 * What a check finds, from the certificate a host served: expired first, then
 * one for another name, then one nothing trusts (self-signed or a broken
 * chain), otherwise OK.
 */
function certificateStatus(array $parsed, $host, $trusted) {
    if ($parsed['expired']) {
        return 'expired';
    }
    if (!certificateCovers($host, $parsed['names'])) {
        return 'mismatch';
    }
    return $trusted ? 'ok' : 'untrusted';
}

/** Open a TLS connection to a host - [stream or false]. Verification is the caller's choice; SNI is always sent. */
function certificateConnect($host, $port, $verify) {
    $options = ['ssl' => [
        'capture_peer_cert' => true,
        'verify_peer' => $verify,
        'verify_peer_name' => false,
        'allow_self_signed' => false,
        'SNI_enabled' => true,
        'peer_name' => $host,
    ]];
    $target = strpos($host, ':') !== false ? "[$host]" : $host;
    return @stream_socket_client("ssl://$target:$port", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, stream_context_create($options));
}

/**
 * Check a host - connect, take the certificate it serves, and say what it is.
 * ['reachable' => false] when nothing answered; otherwise what
 * certificateParse() gives plus 'status' (see CERTIFICATE_CHECK_STATES).
 */
function certificateCheck($host_input) {
    $host = is_array($host_input) ? $host_input : certificateHost($host_input);
    if (!$host) {
        return ['reachable' => false];
    }
    $stream = certificateConnect($host['host'], $host['port'], false);
    if (!$stream) {
        return ['reachable' => false];
    }
    $params = stream_context_get_params($stream);
    fclose($stream);
    $pem = '';
    if (!empty($params['options']['ssl']['peer_certificate'])) {
        @openssl_x509_export($params['options']['ssl']['peer_certificate'], $pem);
    }
    $parsed = certificateParse($pem);
    if (!$parsed) {
        return ['reachable' => false];
    }
    // Trusted - a second handshake that checks the chain against the system's CAs
    $trusted = false;
    if (!$parsed['expired'] && !$parsed['self_signed']) {
        $verified = certificateConnect($host['host'], $host['port'], true);
        if ($verified) {
            $trusted = true;
            fclose($verified);
        }
    }
    $parsed['reachable'] = true;
    $parsed['status'] = certificateStatus($parsed, $host['host'], $trusted);
    return $parsed;
}

/** Save what a pasted PEM or a check says the certificate is. */
function certificateApplyParsed($certificate_id, array $parsed) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    mysqli_query($mysqli, "UPDATE certificates SET
        certificate_public_key = '" . escapeSql($parsed['pem']) . "',
        certificate_issued_by = '" . escapeSql($parsed['issuer']) . "',
        certificate_issued_at = '" . escapeSql($parsed['issued_at']) . "',
        certificate_expire = '" . escapeSql($parsed['expire']) . "',
        certificate_names = '" . escapeSql(implode("\n", $parsed['names'])) . "',
        certificate_fingerprint = '" . escapeSql($parsed['fingerprint']) . "'
        WHERE certificate_id = $certificate_id");
}

/**
 * Save what a check found - the certificate and the result when the host
 * answered, only the result (unreachable) when it didn't. True when it answered.
 */
function certificateApplyCheck($certificate_id, array $check) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    if (empty($check['reachable'])) {
        mysqli_query($mysqli, "UPDATE certificates SET certificate_check_status = 'unreachable', certificate_checked_at = NOW() WHERE certificate_id = $certificate_id");
        return false;
    }
    certificateApplyParsed($certificate_id, $check);
    mysqli_query($mysqli, "UPDATE certificates SET certificate_check_status = '" . escapeSql($check['status']) . "', certificate_checked_at = NOW() WHERE certificate_id = $certificate_id");
    return true;
}

/**
 * Check the host a certificate is served on and save what it serves, with
 * its history (not for one just added - there is nothing before it). With
 * $keep_pem, a certificate pasted in the form that differs from what the
 * host serves is kept - only the result of the check is saved. The check,
 * or null without a host.
 */
function certificateCheckAndSave($certificate_id, $keep_pem = '', $with_history = true) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    $row = certificateRow($certificate_id);
    $host = $row ? certificateHost($row['certificate_domain']) : null;
    if (!$host) {
        return null;
    }
    $check = certificateCheck($host);
    $before = certificateHistorySnapshot($certificate_id);
    $kept_parsed = $keep_pem !== '' && !empty($check['reachable']) ? certificateParse($keep_pem) : null;
    if ($kept_parsed && $kept_parsed['fingerprint'] !== $check['fingerprint']) {
        mysqli_query($mysqli, "UPDATE certificates SET certificate_check_status = '" . escapeSql($check['status']) . "', certificate_checked_at = NOW() WHERE certificate_id = $certificate_id");
    } else {
        certificateApplyCheck($certificate_id, $check);
    }
    if ($with_history) {
        certificateWriteHistory($certificate_id, $before, certificateHistorySnapshot($certificate_id));
    }
    return $check;
}

/** The client's domain a host belongs to - the domain itself, or the longest one it is under - or 0. */
function certificateDomainFor($host, $client_id) {
    global $mysqli;
    $host = strtolower((string) $host);
    $client_id = intval($client_id);
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        return 0;
    }
    $host_sql = escapeSql($host);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT domain_id FROM domains
        WHERE domain_client_id = $client_id AND domain_archived_at IS NULL
        AND ('$host_sql' = domain_name OR '$host_sql' LIKE CONCAT('%.', domain_name))
        ORDER BY LENGTH(domain_name) DESC LIMIT 1"));
    return $row ? intval($row[0]) : 0;
}

/** A domain id a certificate may link to - one of its client's - else 0. */
function certificateDomainId($domain_id, $client_id) {
    global $mysqli;
    $domain_id = intval($domain_id);
    $client_id = intval($client_id);
    if (!$domain_id) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT domain_id FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id LIMIT 1"));
    return $row ? intval($row[0]) : 0;
}

/**
 * The fields a New / Edit form posts, as a SQL SET list. A pasted PEM is the
 * last word on the issuer, dates, names and fingerprint; with none, the
 * issuer and expiry typed by hand are all there is. With no name the
 * host is the name; with no domain picked, the client's domain the host is
 * under. Null with $error set when it cannot be saved. Also returns the
 * host and what the PEM said, if there was one.
 */
function certificateFormSet($post, $client_id, &$error = '') {
    $error = '';
    $client_id = intval($client_id);
    $host_input = trim((string) ($post['domain'] ?? ''));
    $host = $host_input === '' ? null : certificateHost($host_input);
    if ($host_input !== '' && !$host) {
        $error = "That isn't a host - something like www.example.com, or portal.example.com:8443.";
        return null;
    }
    $pem_input = trim((string) ($post['public_key'] ?? ''));
    $parsed = $pem_input === '' ? null : certificateParse($pem_input);
    if ($pem_input !== '' && !$parsed) {
        $error = "That isn't a certificate - paste the PEM, from -----BEGIN CERTIFICATE----- to -----END CERTIFICATE-----.";
        return null;
    }
    $name = trim((string) ($post['name'] ?? '')) ?: ($host ? $host['display'] : ($parsed['subject'] ?? ''));
    if ($name === '') {
        $error = 'A certificate needs a name, or the host it is served on.';
        return null;
    }
    $expire = trim((string) ($post['expire'] ?? ''));
    if ($expire !== '' && (!($date = DateTime::createFromFormat('!Y-m-d', $expire)) || $date->format('Y-m-d') !== $expire)) {
        $error = 'The expiry date is not a date.';
        return null;
    }
    $domain_id = certificateDomainId($post['domain_id'] ?? 0, $client_id) ?: ($host ? certificateDomainFor($host['host'], $client_id) : 0);

    $text = function ($value, $length) {
        return "'" . escapeSql(mb_substr(trim((string) $value), 0, $length)) . "'";
    };
    $fields = [
        'certificate_name' => $text($name, 200),
        'certificate_description' => $text($post['description'] ?? '', 500),
        'certificate_domain' => $text($host ? $host['display'] : '', 200),
        'certificate_notes' => "'" . escapeSql(trim((string) ($post['notes'] ?? ''))) . "'",
        'certificate_favorite' => intval($post['favorite'] ?? 0) ? 1 : 0,
        'certificate_domain_id' => $domain_id,
    ];
    if ($parsed) {
        $fields['certificate_public_key'] = "'" . escapeSql($parsed['pem']) . "'";
        $fields['certificate_issued_by'] = $text($parsed['issuer'], 200);
        $fields['certificate_issued_at'] = "'" . $parsed['issued_at'] . "'";
        $fields['certificate_expire'] = "'" . $parsed['expire'] . "'";
        $fields['certificate_names'] = "'" . escapeSql(implode("\n", $parsed['names'])) . "'";
        $fields['certificate_fingerprint'] = "'" . escapeSql($parsed['fingerprint']) . "'";
    } else {
        // No certificate - what was typed by hand, and nothing a certificate would have said
        $fields['certificate_public_key'] = "''";
        $fields['certificate_issued_by'] = $text($post['issued_by'] ?? '', 200);
        $fields['certificate_issued_at'] = 'NULL';
        $fields['certificate_expire'] = $expire === '' ? 'NULL' : "'$expire'";
        $fields['certificate_names'] = "''";
        $fields['certificate_fingerprint'] = "''";
    }
    $set = [];
    foreach ($fields as $column => $value) {
        $set[] = "$column = $value";
    }
    return ['set' => implode(', ', $set), 'name' => $name, 'host' => $host, 'parsed' => $parsed];
}

/** The names a certificate covers, as a list. */
function certificateNames($row) {
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($row['certificate_names'] ?? ''))), 'strlen'));
}

/** Is it short-lived - an ACME certificate that renews itself? From its own dates, when they are known. */
function certificateShortLived($row) {
    $issued = strtotime((string) ($row['certificate_issued_at'] ?? ''));
    $expire = strtotime((string) ($row['certificate_expire'] ?? ''));
    return $issued && $expire && ($expire - $issued) / 86400 <= CERTIFICATE_SHORT_LIVED_DAYS;
}

/**
 * When it expires: 'expired', 'soon' or 'active', with a tone and a line to
 * show. A short-lived certificate is only "soon" inside 20 days - by then it
 * should already have renewed itself, so it is a warning rather than routine.
 */
function certificateExpiry($row) {
    $short_lived = certificateShortLived($row);
    $renewal = softwareRenewal($row['certificate_expire'] ?? '', $short_lived ? 20 : CERTIFICATE_SOON_DAYS);
    if (!$renewal) {
        return null;
    }
    $days = $renewal['days'];
    if ($renewal['state'] === 'expired') {
        $renewal['short'] = 'Expired';
    } elseif ($renewal['state'] === 'soon') {
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");
        $renewal['text'] = 'Expires ' . $when . ($short_lived ? ' - it should have renewed by now' : '');
        $renewal['short'] = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "$days days");
    } else {
        $renewal['text'] = ($short_lived ? 'Renews itself, expires ' : 'Expires ') . timeAgo($renewal['date']);
        $renewal['short'] = timeAgo($renewal['date']);
    }
    $renewal['short_lived'] = $short_lived;
    return $renewal;
}

/** The expiry as a soft pill - red expired, amber soon, green otherwise (with a renew icon when it renews itself). */
function certificateExpiryPill($expiry, $use_text = false) {
    if (!$expiry) {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal" title="No expiry date - check the host or paste the certificate">'
            . '<i class="fas fa-question me-1"></i>Unknown</span>';
    }
    $tone = $expiry['tone'];
    $icon = $expiry['state'] === 'expired' ? 'fa-calendar-times' : ($expiry['state'] === 'soon' ? 'fa-hourglass-half' : ($expiry['short_lived'] ? 'fa-sync-alt' : 'fa-calendar-check'));
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($expiry['date'] . ($expiry['short_lived'] ? ' - short-lived, renews itself' : '')) . '">'
        . '<i class="fas ' . $icon . ' me-1"></i>' . escapeHtml($use_text ? $expiry['text'] : $expiry['short']) . '</span>';
}

/** What the last check of the host found, as a pill - or "Not checked" without a host or a check. */
function certificateStatusPill($row, $use_text = false) {
    $status = (string) ($row['certificate_check_status'] ?? '');
    if (!isset(CERTIFICATE_CHECK_STATES[$status])) {
        $label = trim((string) ($row['certificate_domain'] ?? '')) === '' ? 'No host to check' : 'Not checked';
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal"><i class="fas fa-minus me-1"></i>' . $label . '</span>';
    }
    $state = CERTIFICATE_CHECK_STATES[$status];
    $when = !empty($row['certificate_checked_at']) ? ' - checked ' . timeAgo($row['certificate_checked_at']) : '';
    return '<span class="badge rounded-pill bg-' . $state['tone'] . '-subtle text-' . $state['tone'] . '-emphasis border border-' . $state['tone'] . '-subtle fw-normal" title="' . escapeHtml($state['text'] . $when) . '">'
        . '<i class="fas ' . $state['icon'] . ' me-1"></i>' . escapeHtml($use_text ? $state['text'] : $state['label']) . '</span>';
}

/** A certificate's tile - a padlock, in the same rounded square as the other tiles. $size 'xs' | 'sm' | '' | 'lg'. */
function certificateTile($size = '') {
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size" . ($size === 'xs' ? ' itflow-vendor-tile-xs' : '') : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-phone" aria-hidden="true"><i class="fas fa-fw fa-lock"></i></span>';
}

/** A certificate wherever a page mentions one: its tile and its name, opening its quick look. 'archived' strikes it through. */
function certificateLink($certificate_id, $name, $options = []) {
    $certificate_id = intval($certificate_id);
    $label = escapeHtml(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    return '<span class="itflow-contact-ref">' . certificateTile('xs')
        . "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/certificate/certificate.php?id=$certificate_id\">$label</a></span>";
}

/** A certificate as an option in a picker (itflow_select.js) - the padlock, and the host and when it expires underneath. */
function selectOptionCertificate($row) {
    $expiry = certificateExpiry($row);
    $host = trim((string) ($row['certificate_domain'] ?? ''));
    $description = implode(' - ', array_filter([$host !== '' && $host !== $row['certificate_name'] ? $host : '', $expiry ? $expiry['text'] : '']));
    return selectOptionData(['icon' => 'fas fa-lock', 'description' => $description, 'keywords' => $host]);
}

/** The certificate as a .crt file to download - a data: link, '' without a PEM. */
function certificateDownloadHref($pem) {
    $pem = trim((string) $pem);
    return $pem === '' ? '' : 'data:application/x-x509-ca-cert;base64,' . base64_encode($pem . "\n");
}

/** What a certificate's history tracks, as it stands now. */
function certificateHistorySnapshot($certificate_id) {
    $row = certificateRow($certificate_id);
    if (!$row) {
        return [];
    }
    $snapshot = [];
    foreach (array_keys(CERTIFICATE_HISTORY_LABELS) as $column) {
        $snapshot[$column] = (string) ($row[$column] ?? '');
    }
    return $snapshot;
}

/** Record what changed between two snapshots in the certificate's history. */
function certificateWriteHistory($certificate_id, array $before, array $after) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    foreach ($after as $column => $new_value) {
        $old_value = $before[$column] ?? '';
        if ((string) $old_value === (string) $new_value) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO certificate_history SET certificate_history_column = '" . escapeSql($column) . "',
            certificate_history_old_value = '" . escapeSql($old_value) . "', certificate_history_new_value = '" . escapeSql($new_value) . "',
            certificate_history_certificate_id = $certificate_id");
    }
}

/** A certificate's history, newest first - each change with its label. */
function certificateHistory($certificate_id, $limit = 25) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    $limit = max(1, intval($limit));
    $sql = mysqli_query($mysqli, "SELECT certificate_history_column, certificate_history_old_value, certificate_history_new_value, certificate_history_modified_at
        FROM certificate_history WHERE certificate_history_certificate_id = $certificate_id
        ORDER BY certificate_history_modified_at DESC, certificate_history_id DESC LIMIT $limit");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['label'] = CERTIFICATE_HISTORY_LABELS[$row['certificate_history_column']] ?? ucfirst(str_replace('_', ' ', preg_replace('/^certificate_/', '', $row['certificate_history_column'])));
        $rows[] = $row;
    }
    return $rows;
}

/** The services that use a certificate. */
function certificateServices($certificate_id) {
    global $mysqli;
    $certificate_id = intval($certificate_id);
    $sql = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_importance FROM service_certificates
        JOIN services ON services.service_id = service_certificates.service_id WHERE service_certificates.certificate_id = $certificate_id ORDER BY service_name");
    $rows = [];
    while ($service = mysqli_fetch_assoc($sql)) {
        $rows[] = $service;
    }
    return $rows;
}

/** The SQL for "expiring soon" - inside CERTIFICATE_SOON_DAYS, or 20 days for a short-lived one (see certificateExpiry()). */
function certificateSoonSql($alias = 'certificates') {
    return "$alias.certificate_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL IF($alias.certificate_issued_at IS NOT NULL
        AND DATEDIFF($alias.certificate_expire, $alias.certificate_issued_at) <= " . CERTIFICATE_SHORT_LIVED_DAYS . ", 20, " . CERTIFICATE_SOON_DAYS . ") DAY";
}

/** The SQL for "the last check found a problem" - the Problems filter. */
function certificateProblemSql($alias = 'certificates') {
    return "$alias.certificate_check_status IN ('mismatch', 'untrusted', 'expired', 'unreachable')";
}
