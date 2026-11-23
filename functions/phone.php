<?php

/*
 * Phone systems - a PBX that tells ITFlow what happens on it (Admin > Phone Systems, admin/phone_systems.php).
 * FusionPBX is the first kind. Nothing is asked of its API or its database: it is set up to send
 * ITFlow things, to addresses under api/phone/ that carry the system's key -
 *
 *   cdr.php   each call's record, as FreeSWITCH posts it at hangup (mod_xml_cdr) - a phone_calls row,
 *             the other party matched to a contact (phoneWho()) and the extension to an agent
 *   cid.php   the name for a caller ID, asked for as a call comes in (mod_cidlookup) - so desk
 *             phones show the contact and their client
 *   hook.php  desk phones saying what they are and what they are doing (Yealink action URLs,
 *             Polycom telephony notifications) - a phone_devices row, and a Phone asset; and as a
 *             call rings, is answered and ends, a phone_events row for the agent whose desk phone
 *             it is (Your account > Preferences), which pops up in ITFlow (js/phone_pop.js)
 *
 * Each domain (a FusionPBX tenant) it names is a phone_domains row, linked on the system's Domains
 * page (admin/phone_domains.php) to the client it is. A call on a client's domain is that client's,
 * with the contact matched among theirs. A call on your own company's domain (Admin > Settings >
 * Company, your client record) or one not linked yet is the caller's: their number is matched
 * among every client's contacts, then the clients' locations.
 */

/** How long calls are kept - those on a ticket stay */
const PHONE_CALLS_KEEP_DAYS = 400;
/** The longest a call record from FreeSWITCH is taken to be */
const PHONE_CDR_MAX_BYTES = 4194304;
/** The longest a phone's notification is taken to be */
const PHONE_HOOK_MAX_BYTES = 65536;

/** The kinds of phone system ITFlow knows */
function phoneSystemKinds(): array
{
    return [
        'fusionpbx' => [
            'name' => 'FusionPBX',
            'short' => 'FusionPBX',
            'icon' => 'fas fa-phone-volume',
            'about' => 'FusionPBX on FreeSWITCH - its call records, caller ID and phones',
            'web_placeholder' => 'https://pbx.example.com',
            // A call's own page there, by its uuid - with its recording
            'call_path' => '/app/xml_cdr/xml_cdr_details.php?id=',
        ],
    ];
}

/** The kind of a phone_systems row, or null when this ITFlow doesn't know it */
function phoneKind(array $row): ?array
{
    return phoneSystemKinds()[(string) ($row['phone_system_kind'] ?? '')] ?? null;
}

/** Whether the 4.1.4 database update is in - the phone tables */
function phoneTablesReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        try {
            mysqli_query($mysqli, "SELECT 1 FROM phone_calls LIMIT 1");
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** Whether any phone system is set up - what the Calls menu items wait for */
function phoneSystemsExist(): bool
{
    global $mysqli;
    static $exist = null;
    if ($exist === null) {
        $exist = phoneTablesReady() && intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_systems"))[0]) > 0;
    }
    return $exist;
}

/** A phone system by id - raw, or null */
function phoneSystemRow($phone_system_id): ?array
{
    global $mysqli;
    $phone_system_id = intval($phone_system_id);
    if (!$phone_system_id || !phoneTablesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM phone_systems WHERE phone_system_id = $phone_system_id")) ?: null;
}

/** What a phone system is called */
function phoneSystemName(array $row): string
{
    $name = trim((string) ($row['phone_system_name'] ?? ''));
    return $name !== '' ? $name : (phoneKind($row)['name'] ?? 'Phone system');
}

/** The phone system whose key this is - one that is set up in ITFlow - or null */
function phoneSystemByKey(string $key): ?array
{
    global $mysqli;
    if (strlen($key) < 32 || !phoneTablesReady()) {
        return null;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM phone_systems");
    while ($row = mysqli_fetch_assoc($sql)) {
        if ((string) $row['phone_system_key'] !== '' && hash_equals((string) $row['phone_system_key'], $key)) {
            return $row;
        }
    }
    return null;
}

/** A new key for a phone system's addresses */
function phoneSystemNewKey(): string
{
    return randomString(40);
}

/**
 * An address under api/phone/ for a phone system - with its key, then $query as it is (the
 * system fills in what is in it, so it isn't encoded here).
 */
function phoneUrl(array $system, string $endpoint, string $query = ''): string
{
    global $config_base_url;
    return 'https://' . rtrim((string) $config_base_url, '/') . "/api/phone/$endpoint.php?key=" . rawurlencode((string) $system['phone_system_key']) . ($query !== '' ? "&$query" : '');
}

/** A page of the phone system itself - its address and $path - or '' when it has no address */
function phoneWebUrl(array $system, string $path = ''): string
{
    $base = rtrim(trim((string) ($system['phone_system_web_url'] ?? '')), '/');
    return $base !== '' && preg_match('#^https?://#i', $base) ? $base . $path : '';
}

/** A call's own page on its phone system (with its recording) - or '' */
function phoneCallWebUrl(array $system, string $uuid): string
{
    $kind = phoneKind($system);
    return $kind && isset($kind['call_path']) && $uuid !== '' ? phoneWebUrl($system, $kind['call_path'] . rawurlencode($uuid)) : '';
}

/** A phone system's row as Add or Edit sent it, checked - [$after, $errors by field] */
function phoneSystemCheck(array $in, ?array $before): array
{
    $errors = [];
    $kind = $before ? (string) $before['phone_system_kind'] : (string) ($in['kind'] ?? '');
    if (!isset(phoneSystemKinds()[$kind])) {
        $errors['kind'] = 'Pick the kind of phone system.';
    }
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Give it a name.';
    }
    $web_url = rtrim(trim((string) ($in['web_url'] ?? '')), '/');
    if ($web_url !== '' && (!preg_match('#^https?://[^\s/]+#i', $web_url) || strlen($web_url) > 500)) {
        $errors['web_url'] = 'Its address starts with https:// - or leave it empty.';
    }
    return [[
        'phone_system_kind' => $kind,
        'phone_system_name' => mb_substr($name, 0, 100),
        'phone_system_web_url' => $web_url,
        'phone_system_active' => $before ? (intval($in['active'] ?? 0) === 1 ? 1 : 0) : 1,
        'phone_system_key' => $before ? (string) $before['phone_system_key'] : phoneSystemNewKey(),
    ], $errors];
}

/** Save a phone system's row - a new one when $phone_system_id is 0. Its id */
function phoneSystemWrite(array $after, int $phone_system_id = 0): int
{
    global $mysqli;
    $set = implode(', ', array_map(function ($column, $value) {
        return "`$column` = '" . escapeSql((string) $value) . "'";
    }, array_keys($after), $after));
    if ($phone_system_id) {
        mysqli_query($mysqli, "UPDATE phone_systems SET $set WHERE phone_system_id = $phone_system_id");
        return $phone_system_id;
    }
    mysqli_query($mysqli, "INSERT INTO phone_systems SET $set");
    return intval(mysqli_insert_id($mysqli));
}

/** What changed on a phone system, for the audit log - its key is never written down */
function phoneSystemChangeLines(array $before, array $after): array
{
    $labels = ['phone_system_name' => 'name', 'phone_system_web_url' => 'address', 'phone_system_active' => 'on'];
    $lines = [];
    foreach ($labels as $column => $label) {
        if ((string) ($before[$column] ?? '') !== (string) ($after[$column] ?? '')) {
            $lines[] = $column === 'phone_system_active'
                ? ($after[$column] ? 'turned it on' : 'turned it off')
                : "$label: " . ((string) ($before[$column] ?? '') === '' ? '(none)' : $before[$column]) . ' to ' . ((string) $after[$column] === '' ? '(none)' : $after[$column]);
        }
    }
    return $lines;
}

/* ------------------------------------------------------------------ Domains */

/** A domain as a PBX names it, tidied - lower case, no port - or '' when it can't be one */
function phoneDomainName($name): string
{
    $name = strtolower(trim((string) $name));
    $name = preg_replace('/:\d+$/', '', $name);
    return preg_match('/^[a-z0-9_]([a-z0-9._-]{0,253}[a-z0-9_])?$/', $name) ? $name : '';
}

/** A phone_domains row by id - with its system - or null */
function phoneDomainRow($phone_domain_id): ?array
{
    global $mysqli;
    $phone_domain_id = intval($phone_domain_id);
    if (!$phone_domain_id || !phoneTablesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT phone_domains.*, phone_systems.*, client_name FROM phone_domains
        JOIN phone_systems ON phone_system_id = phone_domain_system_id
        LEFT JOIN clients ON client_id = phone_domain_client_id AND phone_domain_client_id > 0
        WHERE phone_domain_id = $phone_domain_id")) ?: null;
}

/**
 * The phone_domains row a system's domain is - made the first time it is heard of, and said to be
 * heard from now. Null when $name can't be a domain.
 */
function phoneDomain(array $system, $name): ?array
{
    global $mysqli;
    $name = phoneDomainName($name);
    if ($name === '') {
        return null;
    }
    $system_id = intval($system['phone_system_id']);
    $name_sql = escapeSql($name);
    mysqli_query($mysqli, "INSERT INTO phone_domains SET phone_domain_system_id = $system_id, phone_domain_name = '$name_sql', phone_domain_seen_at = NOW()
        ON DUPLICATE KEY UPDATE phone_domain_seen_at = NOW()");
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM phone_domains WHERE phone_domain_system_id = $system_id AND phone_domain_name = '$name_sql'")) ?: null;
}

/**
 * The clients a system's domains not linked yet look like - [phone_domain_id => client_id]. A
 * domain is a client's when it is one of the client's own domains or under one
 * (voice.ravenwooddental.com), when its first part is the client's abbreviation (rdg.pbx.example.com),
 * or when the client's name starts with its first part (ravenwood.pbx.example.com) - and only
 * one client fits.
 */
function phoneDomainSuggestions(int $phone_system_id): array
{
    global $mysqli;
    $domains = [];
    $sql = mysqli_query($mysqli, "SELECT phone_domain_id, phone_domain_name FROM phone_domains WHERE phone_domain_system_id = $phone_system_id AND phone_domain_client_id = 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        $domains[intval($row['phone_domain_id'])] = $row['phone_domain_name'];
    }
    if (!$domains) {
        return [];
    }

    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL AND client_lead = 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = [
            'name' => preg_replace('/[^a-z0-9]/', '', strtolower((string) $row['client_name'])),
            'abbreviation' => strtolower(trim((string) $row['client_abbreviation'])),
            'domains' => [],
        ];
    }
    try {
        $sql = mysqli_query($mysqli, "SELECT domain_client_id, domain_name FROM domains WHERE domain_archived_at IS NULL AND domain_client_id > 0");
        while ($row = mysqli_fetch_assoc($sql)) {
            if (isset($clients[intval($row['domain_client_id'])])) {
                $clients[intval($row['domain_client_id'])]['domains'][] = strtolower(trim((string) $row['domain_name']));
            }
        }
    } catch (Throwable $e) {
        // Without the client's domains it goes by names
    }

    $suggestions = [];
    foreach ($domains as $domain_id => $domain) {
        $first = explode('.', $domain)[0];
        $fits = [];
        foreach ($clients as $client_id => $client) {
            foreach ($client['domains'] as $own) {
                if ($own !== '' && ($domain === $own || str_ends_with($domain, ".$own"))) {
                    $fits[$client_id] = 3;
                }
            }
            if (!isset($fits[$client_id]) && $client['abbreviation'] !== '' && $first === $client['abbreviation']) {
                $fits[$client_id] = 2;
            }
            if (!isset($fits[$client_id]) && strlen($first) >= 4 && str_starts_with($client['name'], preg_replace('/[^a-z0-9]/', '', $first))) {
                $fits[$client_id] = 1;
            }
        }
        if (!$fits) {
            continue;
        }
        // The best kind of fit, when only one client has it
        $best = max($fits);
        $top = array_keys(array_filter($fits, function ($fit) use ($best) {
            return $fit === $best;
        }));
        if (count($top) === 1) {
            $suggestions[$domain_id] = $top[0];
        }
    }
    return $suggestions;
}

/**
 * Link a domain to a client (0 unlinks it). Its phones become the client's assets (phoneDeviceAsset()), and its calls are
 * matched again by the new rule - on a client's domain they are the client's; on your own
 * company's, or unlinked, the caller's. A call already on a ticket keeps who it was with.
 */
function phoneDomainLink(int $phone_domain_id, int $client_id): void
{
    global $mysqli, $config_internal_client_id;
    mysqli_query($mysqli, "UPDATE phone_domains SET phone_domain_client_id = $client_id WHERE phone_domain_id = $phone_domain_id");
    mysqli_query($mysqli, "UPDATE phone_devices SET phone_device_client_id = $client_id WHERE phone_device_domain_id = $phone_domain_id");
    // Its phones become the client's assets
    $devices = mysqli_query($mysqli, "SELECT phone_device_id FROM phone_devices WHERE phone_device_domain_id = $phone_domain_id");
    while ($device = mysqli_fetch_row($devices)) {
        phoneDeviceAsset(intval($device[0]));
    }

    $own = $client_id && $client_id !== intval($config_internal_client_id ?? 0);
    $who = [];
    $sql = mysqli_query($mysqli, "SELECT phone_call_id, phone_call_number, phone_call_direction FROM phone_calls
        WHERE phone_call_domain_id = $phone_domain_id AND phone_call_ticket_id = 0
        ORDER BY phone_call_id DESC LIMIT 5000");
    while ($row = mysqli_fetch_assoc($sql)) {
        $number = (string) $row['phone_call_number'];
        if (!isset($who[$number])) {
            $who[$number] = $row['phone_call_direction'] !== 'local' ? phoneWho($number, $client_id) : ['contact_id' => 0, 'client_id' => 0];
        }
        $call_client = $own ? $client_id : ($who[$number]['client_id'] ?: $client_id);
        mysqli_query($mysqli, "UPDATE phone_calls SET phone_call_client_id = $call_client, phone_call_contact_id = " . intval($who[$number]['contact_id']) . "
            WHERE phone_call_id = " . intval($row['phone_call_id']));
    }
}

/* ------------------------------------------------------------------ Numbers */

/**
 * A number as a phone system gives it - "+14125550100", "4125550100", "sip:101@pbx.example.com" -
 * as digits: E.164 without the + for a phone number (a 10-digit North American number given its 1),
 * as it is for an extension. '' when there are no digits.
 */
function phoneNumber($raw): string
{
    $raw = trim(rawurldecode((string) $raw));
    if (preg_match('/^sips?:([^@;]+)/i', $raw, $m)) {
        $raw = $m[1];
    }
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
        return '';
    }
    if (phoneIsExtension($digits)) {
        return $digits;
    }
    return mb_substr(smsInboundNumber($digits) ?: $digits, 0, 32);
}

/** Whether digits are an extension - six or fewer - rather than a phone number */
function phoneIsExtension(string $digits): bool
{
    return strlen($digits) <= 6;
}

/** A number for reading - "+1 (412) 555-0100", "Ext 101" */
function phoneNumberText($number): string
{
    $number = (string) $number;
    if ($number === '') {
        return '';
    }
    return phoneIsExtension($number) ? "Ext $number" : smsNumberText($number);
}

/**
 * Who a number is - ['contact_id', 'client_id', 'contact_name', 'client_name']: a contact, else a
 * client whose location has the number, else nobody (all 0 and ''). On a client's domain only that
 * client's contacts and locations count; on your own company's domain, or one not linked
 * ($domain_client_id 0), every client's do. A number more than one client has is nobody's.
 */
function phoneWho($number, int $domain_client_id): array
{
    global $mysqli, $config_internal_client_id;
    $nobody = ['contact_id' => 0, 'client_id' => 0, 'contact_name' => '', 'client_name' => ''];
    $number = (string) $number;
    if ($number === '' || phoneIsExtension($number) || !smsNumberIsPhone($number)) {
        return $nobody;
    }
    $everyone = !$domain_client_id || $domain_client_id === intval($config_internal_client_id ?? 0);

    $contacts = array_values(array_filter(smsContactsFor($number), function ($contact) use ($everyone, $domain_client_id) {
        return intval($contact['contact_client_id']) > 0 && ($everyone || intval($contact['contact_client_id']) === $domain_client_id);
    }));
    if ($contacts) {
        // Someone's own mobile says more than an office number many share
        $mobiles = array_values(array_filter($contacts, function ($contact) use ($number) {
            return smsNumber($contact['contact_mobile'], $contact['contact_mobile_country_code']) === $number;
        }));
        foreach ([$mobiles, $contacts] as $matches) {
            if ($matches && count(array_unique(array_map('intval', array_column($matches, 'contact_client_id')))) === 1) {
                return [
                    'contact_id' => intval($matches[0]['contact_id']),
                    'client_id' => intval($matches[0]['contact_client_id']),
                    'contact_name' => (string) $matches[0]['contact_name'],
                    'client_name' => (string) $matches[0]['client_name'],
                ];
            }
        }
        return $nobody;
    }

    // A client's main number - one of its locations'
    $tail = escapeSql(substr($number, -7));
    $scope = $everyone ? '' : " AND location_client_id = $domain_client_id";
    $sql = mysqli_query($mysqli, "SELECT location_client_id, location_phone, location_phone_country_code, client_name FROM locations
        JOIN clients ON client_id = location_client_id
        WHERE location_archived_at IS NULL AND client_archived_at IS NULL AND REGEXP_REPLACE(location_phone, '[^0-9]', '') LIKE '%$tail'$scope
        LIMIT 20");
    $clients = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if (smsNumber($row['location_phone'], $row['location_phone_country_code']) === $number) {
            $clients[intval($row['location_client_id'])] = (string) $row['client_name'];
        }
    }
    if (count($clients) === 1) {
        return ['contact_id' => 0, 'client_id' => (int) array_key_first($clients), 'contact_name' => '', 'client_name' => reset($clients)];
    }
    return $nobody;
}

/** The name a phone shows for a caller - "Marla Whitfield - Ravenwood Dental", the client's name for its main number, '' for nobody */
function phoneCallerName($number, int $domain_client_id): string
{
    $who = phoneWho($number, $domain_client_id);
    if ($who['contact_name'] !== '') {
        // On a client's own domain the client goes without saying
        $name = $who['contact_name'] . ($who['client_name'] !== '' && $who['client_id'] !== $domain_client_id ? ' - ' . $who['client_name'] : '');
    } else {
        $name = $who['client_name'];
    }
    // One line, no characters a phone would choke on
    return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name)), 0, 60);
}

/* ------------------------------------------------------------------ Agents */

/** The agent whose desk phone an extension on a domain is (user_config_phone_line, extension@domain) - 0 for nobody */
function phoneUserForLine(string $extension, string $domain_name): int
{
    return phoneUsersForLine($extension, $domain_name)[0] ?? 0;
}

/** Every agent whose desk phone a line is - a phone at a desk two share is both of theirs */
function phoneUsersForLine(string $extension, string $domain_name): array
{
    global $mysqli;
    if ($extension === '' || $domain_name === '') {
        return [];
    }
    $line = escapeSql(strtolower("$extension@$domain_name"));
    $sql = mysqli_query($mysqli, "SELECT user_settings.user_id FROM user_settings
        JOIN users ON users.user_id = user_settings.user_id
        WHERE LOWER(user_config_phone_line) = '$line' AND user_archived_at IS NULL
        ORDER BY user_settings.user_id ASC");
    return array_map('intval', array_column(mysqli_fetch_all($sql), 0));
}

/** A desk phone line as an agent sets it - "101@pbx.example.com", lower case - or '' */
function phoneLineValue($raw): string
{
    $raw = strtolower(trim((string) $raw));
    if (!preg_match('/^([a-z0-9_.+*#-]{1,50})@(.+)$/', $raw, $m)) {
        return '';
    }
    $domain = phoneDomainName($m[2]);
    return $domain !== '' ? mb_substr("$m[1]@$domain", 0, 255) : '';
}

/** An agent's desk phone line - '' for none, or before database 4.1.4 */
function phoneLineForUser(int $user_id): string
{
    global $mysqli;
    static $lines = [];
    if (!$user_id || !phoneTablesReady()) {
        return '';
    }
    if (!isset($lines[$user_id])) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_config_phone_line FROM user_settings WHERE user_id = $user_id"));
        $lines[$user_id] = (string) ($row[0] ?? '');
    }
    return $lines[$user_id];
}

/**
 * The lines an agent can pick for their desk phone - [line => [label, description]]: those the
 * phones said they are, then extensions in calls these last 90 days. The description says whose
 * client it is, the phone, and the agents who have it already.
 */
function phoneLineChoices(): array
{
    global $mysqli;
    if (!phoneTablesReady()) {
        return [];
    }
    $choices = [];
    $add = function (string $line, string $domain, array $about) use (&$choices) {
        $value = phoneLineValue("$line@$domain");
        if ($value === '') {
            return;
        }
        if (!isset($choices[$value])) {
            $choices[$value] = ['label' => (phoneNumberText($line) ?: $line) . " on $domain", 'about' => []];
        }
        foreach (array_filter($about) as $each) {
            if (!in_array($each, $choices[$value]['about'], true)) {
                $choices[$value]['about'][] = $each;
            }
        }
    };
    $sql = mysqli_query($mysqli, "SELECT phone_device_line, phone_domain_name, phone_device_vendor, phone_device_model, client_name FROM phone_devices
        JOIN phone_domains ON phone_domain_id = phone_device_domain_id
        LEFT JOIN clients ON client_id = phone_domain_client_id AND phone_domain_client_id > 0
        WHERE phone_device_line <> '' ORDER BY phone_domain_name, phone_device_line");
    while ($row = mysqli_fetch_assoc($sql)) {
        $add((string) $row['phone_device_line'], (string) $row['phone_domain_name'], [(string) $row['client_name'], trim($row['phone_device_vendor'] . ' ' . $row['phone_device_model'])]);
    }
    $sql = mysqli_query($mysqli, "SELECT DISTINCT phone_call_extension, phone_domain_name, client_name FROM phone_calls
        JOIN phone_domains ON phone_domain_id = phone_call_domain_id
        LEFT JOIN clients ON client_id = phone_domain_client_id AND phone_domain_client_id > 0
        WHERE phone_call_extension <> '' AND phone_call_started_at >= NOW() - INTERVAL 90 DAY
        ORDER BY phone_domain_name, phone_call_extension LIMIT 2000");
    while ($row = mysqli_fetch_assoc($sql)) {
        $add((string) $row['phone_call_extension'], (string) $row['phone_domain_name'], [(string) $row['client_name']]);
    }
    $sql = mysqli_query($mysqli, "SELECT LOWER(user_config_phone_line) AS line, user_name FROM user_settings
        JOIN users ON users.user_id = user_settings.user_id
        WHERE user_config_phone_line IS NOT NULL AND user_config_phone_line <> '' AND user_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (isset($choices[$row['line']])) {
            $choices[$row['line']]['about'][] = $row['user_name'] . "'s";
        }
    }
    ksort($choices, SORT_NATURAL);
    return array_map(function ($choice) {
        return [$choice['label'], implode(' - ', $choice['about'])];
    }, $choices);
}

/* ------------------------------------------------------------------ Calls */

/** How a call ended - [label, icon, tone] by status */
function phoneCallStatuses(): array
{
    return [
        'answered' => ['Answered', 'fa-phone', 'success'],
        'missed' => ['Missed', 'fa-phone-slash', 'danger'],
        'voicemail' => ['Voicemail', 'fa-voicemail', 'warning'],
        'busy' => ['Busy', 'fa-phone-slash', 'secondary'],
        'no_answer' => ['No answer', 'fa-phone-slash', 'secondary'],
        'failed' => ['Didn\'t connect', 'fa-exclamation-circle', 'secondary'],
    ];
}

/** Which way a call went - [label, icon] by direction */
function phoneCallDirections(): array
{
    return [
        'inbound' => ['Inbound', 'fa-arrow-down'],
        'outbound' => ['Outbound', 'fa-arrow-up'],
        'local' => ['Internal', 'fa-exchange-alt'],
    ];
}

/** "4m 12s", "38s", "1h 2m" */
function phoneSecondsText($seconds): string
{
    $seconds = max(0, intval($seconds));
    if ($seconds < 60) {
        return "{$seconds}s";
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours ? "{$hours}h {$minutes}m" : "{$minutes}m" . ($seconds % 60 ? ' ' . ($seconds % 60) . 's' : '');
}

/** A call's talk time as time worked on a ticket - 'HH:MM:SS', rounded up to the minute */
function phoneTimeWorked($talk_seconds): string
{
    $minutes = (int) ceil(max(0, intval($talk_seconds)) / 60);
    return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
}

/**
 * The call record FreeSWITCH posted - mod_xml_cdr sends it as the cdr field, URL-encoded or in
 * base64 by its encode setting, or as the body itself. '' when there isn't one.
 */
function phoneCdrFromRequest(): string
{
    $cdr = (string) ($_POST['cdr'] ?? '');
    if ($cdr === '') {
        $body = (string) file_get_contents('php://input', false, null, 0, PHONE_CDR_MAX_BYTES + 1);
        if (str_starts_with(ltrim($body), '<')) {
            $cdr = $body;
        } else {
            parse_str($body, $fields);
            $cdr = (string) ($fields['cdr'] ?? '');
        }
    }
    $cdr = trim($cdr);
    if ($cdr !== '' && !str_starts_with($cdr, '<')) {
        $decoded = base64_decode($cdr, true);
        $cdr = $decoded !== false && str_starts_with(ltrim($decoded), '<') ? trim($decoded) : '';
    }
    return strlen($cdr) <= PHONE_CDR_MAX_BYTES ? $cdr : '';
}

/**
 * What a FreeSWITCH XML call record says, as plain values - null when it isn't one. Its variables
 * are URL-encoded in it; a value missing from them is taken from the call's first caller profile.
 */
function phoneCdrParse(string $xml): ?array
{
    if ($xml === '' || strlen($xml) > PHONE_CDR_MAX_BYTES) {
        return null;
    }
    $was = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($was);
    if (!$doc || $doc->getName() !== 'cdr') {
        return null;
    }

    $vars = [];
    if (isset($doc->variables)) {
        foreach ($doc->variables->children() as $name => $value) {
            $vars[(string) $name] = rawurldecode((string) $value);
        }
    }
    // The first caller profile is the last callflow - each transfer adds one in front
    $profile = [];
    $callflows = $doc->xpath('callflow');
    if ($callflows) {
        $first = end($callflows);
        if (isset($first->caller_profile)) {
            foreach ($first->caller_profile->children() as $name => $value) {
                if (!$value->count()) {
                    $profile[(string) $name] = (string) $value;
                }
            }
        }
    }
    $get = function (...$names) use ($vars, $profile) {
        foreach ($names as $name) {
            foreach ([$vars, $profile] as $from) {
                if (isset($from[$name]) && trim((string) $from[$name]) !== '' && $from[$name] !== '_undef_') {
                    return trim((string) $from[$name]);
                }
            }
        }
        return '';
    };

    $uuid = $get('uuid');
    if ($uuid === '') {
        return null;
    }
    $recording = '';
    if ($get('record_name') !== '') {
        $recording = ($get('record_path') !== '' ? rtrim($get('record_path'), '/') . '/' : '') . $get('record_name');
    } elseif ($get('recording_file') !== '') {
        $recording = $get('recording_file');
    }

    return [
        'uuid' => mb_substr($uuid, 0, 64),
        // A second leg of a call - its first has the call
        'b_leg' => $get('originating_leg_uuid') !== '' && $get('originating_leg_uuid') !== $uuid,
        'domain' => $get('domain_name', 'dialed_domain', 'sip_req_host'),
        'direction' => strtolower($get('call_direction')),
        'channel_direction' => strtolower((string) ($doc->channel_data->direction ?? '') ?: $get('direction')),
        'caller_number' => $get('caller_id_number'),
        'caller_name' => $get('caller_id_name'),
        'destination' => $get('destination_number'),
        'caller_destination' => $get('caller_destination'),
        'from_user' => $get('sip_from_user', 'username'),
        'answered_by' => $get('last_sent_callee_id_number', 'dialed_user', 'dialed_extension'),
        'start' => intval($get('start_epoch')),
        'answer' => intval($get('answer_epoch')),
        'end' => intval($get('end_epoch')),
        'duration' => intval($get('duration')),
        'billsec' => intval($get('billsec')),
        'hangup_cause' => strtoupper($get('hangup_cause')),
        'last_app' => strtolower($get('last_app')),
        'missed' => in_array(strtolower($get('missed_call')), ['true', '1', 'yes'], true),
        'voicemail' => strtolower($get('last_app')) === 'voicemail' || $get('voicemail_message_len') !== '' || $get('voicemail_message_seconds') !== '',
        'recording' => mb_substr($recording, 0, 500),
    ];
}

/**
 * A call record as a call - direction, the other party's number and name, the number dialled,
 * the extension on this side, how it ended and when. FusionPBX says which way a call went
 * (call_direction); without that, a call from an extension to a phone number is outbound, from a
 * phone number inbound, and between extensions internal.
 */
function phoneCallFromCdr(array $cdr): array
{
    $caller = phoneNumber($cdr['caller_number']);
    $destination = phoneNumber($cdr['destination']);
    $direction = $cdr['direction'];
    if (!in_array($direction, ['inbound', 'outbound', 'local'], true)) {
        $caller_internal = $caller === '' || phoneIsExtension($caller);
        $destination_internal = $destination === '' || phoneIsExtension($destination);
        $direction = $caller_internal && !$destination_internal ? 'outbound' : (!$caller_internal ? 'inbound' : 'local');
    }

    if ($direction === 'inbound') {
        $number = $caller;
        $name = $cdr['caller_name'];
        $dialed = phoneNumber($cdr['caller_destination'] ?: $cdr['destination']);
        $extension = phoneNumber($cdr['answered_by']);
        if ($extension === '' && phoneIsExtension($destination)) {
            $extension = $destination;
        }
    } elseif ($direction === 'outbound') {
        $number = $destination;
        $name = '';
        $dialed = $destination;
        $from = phoneNumber($cdr['from_user']);
        $extension = phoneIsExtension($from) ? $from : (phoneIsExtension($caller) ? $caller : '');
    } else {
        $number = $destination;
        $name = '';
        $dialed = $destination;
        $from = phoneNumber($cdr['from_user']);
        $extension = phoneIsExtension($from) && $from !== '' ? $from : $caller;
    }
    // A caller ID name that is only the number says nothing
    if ($name !== '' && phoneNumber($name) === $number && !preg_match('/[a-z]/i', $name)) {
        $name = '';
    }

    $talk = max(0, $cdr['billsec']);
    if ($direction === 'inbound' && $cdr['voicemail']) {
        $status = 'voicemail';
    } elseif ($talk > 0 && !($direction === 'inbound' && $cdr['missed'])) {
        $status = 'answered';
    } elseif ($direction === 'inbound') {
        $status = 'missed';
    } elseif ($cdr['hangup_cause'] === 'USER_BUSY') {
        $status = 'busy';
    } elseif (in_array($cdr['hangup_cause'], ['NO_ANSWER', 'ORIGINATOR_CANCEL', 'NO_USER_RESPONSE', 'NORMAL_CLEARING'], true)) {
        $status = 'no_answer';
    } else {
        $status = 'failed';
    }

    $start = $cdr['start'] ?: ($cdr['end'] ? $cdr['end'] - max(0, $cdr['duration']) : time());
    $seconds = $cdr['duration'] ?: ($cdr['end'] && $cdr['start'] ? max(0, $cdr['end'] - $cdr['start']) : 0);

    return [
        'direction' => $direction,
        'number' => mb_substr($number, 0, 32),
        'name' => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name)), 0, 100),
        'dialed' => mb_substr($dialed, 0, 32),
        'extension' => mb_substr($extension, 0, 32),
        'status' => $status,
        'started_at' => date('Y-m-d H:i:s', $start),
        'answered_at' => $cdr['answer'] ? date('Y-m-d H:i:s', $cdr['answer']) : null,
        'seconds' => max(0, $seconds),
        'talk_seconds' => $talk,
        'hangup_cause' => mb_substr($cdr['hangup_cause'], 0, 50),
        'recording' => $cdr['recording'],
    ];
}

/**
 * Keep a call from its record - the second time the same call comes (FreeSWITCH retrying a post)
 * it is put right, not doubled. Returns ['saved' => bool, 'why' => what happened].
 */
function phoneCallSave(array $system, array $cdr): array
{
    global $mysqli;

    if ($cdr['b_leg']) {
        return ['saved' => false, 'why' => 'second leg'];
    }
    $system_id = intval($system['phone_system_id']);
    $domain = $cdr['domain'] !== '' ? phoneDomain($system, $cdr['domain']) : null;
    $domain_id = intval($domain['phone_domain_id'] ?? 0);
    $domain_client_id = intval($domain['phone_domain_client_id'] ?? 0);
    $call = phoneCallFromCdr($cdr);

    // The other party - a contact, or a client - for calls with the outside world
    $who = $call['direction'] !== 'local' ? phoneWho($call['number'], $domain_client_id) : ['contact_id' => 0, 'client_id' => 0];
    $client_id = $domain_client_id && $domain_client_id !== intval($GLOBALS['config_internal_client_id'] ?? 0) ? $domain_client_id : ($who['client_id'] ?: $domain_client_id);
    $user_id = phoneUserForLine($call['extension'], (string) ($domain['phone_domain_name'] ?? ''));

    $set = "phone_call_domain_id = $domain_id, phone_call_client_id = $client_id, phone_call_contact_id = " . intval($who['contact_id']) . ",
        phone_call_direction = '" . escapeSql($call['direction']) . "', phone_call_number = '" . escapeSql($call['number']) . "',
        phone_call_name = '" . escapeSql($call['name']) . "', phone_call_dialed = '" . escapeSql($call['dialed']) . "',
        phone_call_extension = '" . escapeSql($call['extension']) . "', phone_call_user_id = $user_id,
        phone_call_status = '" . escapeSql($call['status']) . "', phone_call_started_at = '" . escapeSql($call['started_at']) . "',
        phone_call_answered_at = " . ($call['answered_at'] ? "'" . escapeSql($call['answered_at']) . "'" : 'NULL') . ",
        phone_call_seconds = " . intval($call['seconds']) . ", phone_call_talk_seconds = " . intval($call['talk_seconds']) . ",
        phone_call_hangup_cause = '" . escapeSql($call['hangup_cause']) . "',
        phone_call_recording = " . ($call['recording'] !== '' ? "'" . escapeSql($call['recording']) . "'" : 'NULL');

    mysqli_query($mysqli, "INSERT INTO phone_calls SET phone_call_system_id = $system_id, phone_call_uuid = '" . escapeSql($cdr['uuid']) . "', $set
        ON DUPLICATE KEY UPDATE $set");
    mysqli_query($mysqli, "UPDATE phone_systems SET phone_system_heard_at = NOW() WHERE phone_system_id = $system_id");

    return ['saved' => true, 'why' => $call['status']];
}

/** Inbound calls missed today that aren't on a ticket, of the clients this agent can reach - the Calls menu's count */
function phoneMissedToday(): int
{
    global $mysqli;
    if (!phoneTablesReady()) {
        return 0;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_calls
        WHERE phone_call_direction = 'inbound' AND phone_call_status IN ('missed', 'voicemail') AND phone_call_ticket_id = 0
        AND phone_call_started_at >= CURDATE()" . clientScopeSql('phone_call_client_id')))[0]);
}

/** Calls where $where (SQL, built from ints) - ['count', 'rows' (the latest $limit, with their ticket and agent)] - of the clients this agent can reach */
function phoneCallsFor(string $where, int $limit): array
{
    global $mysqli;
    $scope = clientScopeSql('phone_call_client_id');
    $count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_calls WHERE $where$scope"))[0]);
    $rows = [];
    if ($count) {
        $sql = mysqli_query($mysqli, "SELECT phone_calls.*, phone_system_kind, phone_system_name, phone_system_web_url, contact_name, ticket_prefix, ticket_number, ticket_subject, user_name
            FROM phone_calls
            LEFT JOIN phone_systems ON phone_system_id = phone_call_system_id
            LEFT JOIN contacts ON contact_id = phone_call_contact_id AND phone_call_contact_id > 0
            LEFT JOIN tickets ON ticket_id = phone_call_ticket_id AND phone_call_ticket_id > 0
            LEFT JOIN users ON users.user_id = phone_call_user_id AND phone_call_user_id > 0
            WHERE $where$scope
            ORDER BY phone_call_started_at DESC, phone_call_id DESC
            LIMIT " . intval($limit));
        while ($row = mysqli_fetch_assoc($sql)) {
            $rows[] = $row;
        }
    }
    return ['count' => $count, 'rows' => $rows];
}

/** A short table of calls (phoneCallsFor() rows) - for a contact's Calls tab */
function phoneCallsTableHtml(array $rows, bool $can_edit): string
{
    $statuses = phoneCallStatuses();
    $directions = phoneCallDirections();
    $html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><tbody>';
    foreach ($rows as $row) {
        [$status_label, $status_icon, $status_tone] = $statuses[$row['phone_call_status']] ?? [ucfirst((string) $row['phone_call_status']), 'fa-phone', 'secondary'];
        [$direction_label, $direction_icon] = $directions[$row['phone_call_direction']] ?? ['Call', 'fa-phone'];
        $answered = $row['phone_call_status'] === 'answered';
        $tone = $answered ? 'success' : $status_tone;
        $client_id = intval($row['phone_call_client_id']);
        $ticket_id = intval($row['phone_call_ticket_id']);
        $who = trim((string) ($row['user_name'] ?? '')) !== '' ? (string) $row['user_name'] : phoneNumberText($row['phone_call_extension']);
        $html .= '<tr data-phone-call-id="' . intval($row['phone_call_id']) . '">'
            . '<td class="ps-3" style="width: 2.5rem"><span class="itflow-phone-call-icon text-' . $tone . '-emphasis bg-' . $tone . '-subtle" title="' . escapeHtml("$direction_label - $status_label") . '"><i class="fas fa-fw ' . ($answered ? $direction_icon : $status_icon) . '"></i></span></td>'
            . '<td class="small w-100"><div>' . escapeHtml($direction_label) . ($answered ? ' &middot; ' . escapeHtml(phoneSecondsText($row['phone_call_talk_seconds'])) : ' &middot; <span class="text-' . $status_tone . '-emphasis">' . escapeHtml($status_label) . '</span>') . '</div>'
            . '<div class="text-body-secondary">' . escapeHtml(phoneNumberText($row['phone_call_number'])) . ($who !== '' ? ' &middot; ' . escapeHtml($who) : '') . '</div></td>'
            . '<td class="small text-nowrap"><span title="' . formatDateTime($row['phone_call_started_at']) . '">' . escapeHtml(timeAgo($row['phone_call_started_at'])) . '</span></td>'
            . '<td class="small">' . ($ticket_id && $row['ticket_number'] !== null ? '<a href="ticket.php?client_id=' . $client_id . '&amp;ticket_id=' . $ticket_id . '" title="' . escapeHtml((string) $row['ticket_subject']) . '">' . escapeHtml($row['ticket_prefix'] . $row['ticket_number']) . '</a>' : '') . '</td>'
            . '<td class="text-end pe-3">' . ($can_edit && $row['phone_call_direction'] !== 'local' ? '<button type="button" class="btn btn-sm btn-light ajax-modal" title="Put it on a ticket, with its talk time" data-modal-url="modals/phone/phone_call_ticket.php?id=' . intval($row['phone_call_id']) . '"><i class="fas fa-fw fa-life-ring"></i></button>' : '') . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** A call by id, with its contact, client, ticket and system - raw, or null */
function phoneCallRow($phone_call_id): ?array
{
    global $mysqli;
    $phone_call_id = intval($phone_call_id);
    if (!$phone_call_id || !phoneTablesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT phone_calls.*, phone_systems.*, phone_domain_name, contact_name, client_name,
            ticket_prefix, ticket_number, ticket_subject, user_name
        FROM phone_calls
        JOIN phone_systems ON phone_system_id = phone_call_system_id
        LEFT JOIN phone_domains ON phone_domain_id = phone_call_domain_id
        LEFT JOIN contacts ON contact_id = phone_call_contact_id AND phone_call_contact_id > 0
        LEFT JOIN clients ON client_id = phone_call_client_id AND phone_call_client_id > 0
        LEFT JOIN tickets ON ticket_id = phone_call_ticket_id AND phone_call_ticket_id > 0
        LEFT JOIN users ON users.user_id = phone_call_user_id AND phone_call_user_id > 0
        WHERE phone_call_id = $phone_call_id")) ?: null;
}

/**
 * The client a call belongs to for putting it on a ticket - 0 when it can go on any client's: a
 * caller nobody knows, on your own company's line or a domain not linked.
 */
function phoneCallClientFor(array $call): int
{
    global $config_internal_client_id;
    $client_id = intval($call['phone_call_client_id']);
    if (!intval($call['phone_call_contact_id']) && ($client_id === 0 || $client_id === intval($config_internal_client_id ?? 0))) {
        return 0;
    }
    return $client_id;
}

/** Who a call was with, for reading - the contact, the caller ID name, or the number */
function phoneCallWho(array $call): string
{
    if (trim((string) ($call['contact_name'] ?? '')) !== '') {
        return (string) $call['contact_name'];
    }
    if (trim((string) $call['phone_call_name']) !== '') {
        return (string) $call['phone_call_name'];
    }
    return phoneNumberText($call['phone_call_number']) ?: 'Unknown caller';
}

/** A call in a sentence, for a ticket note - "Inbound call from Marla Whitfield (+1 (412) 555-0100) on ext 101, 4m 12s." Raw */
function phoneCallSentence(array $call): string
{
    $direction = (string) $call['phone_call_direction'];
    $who = phoneCallWho($call);
    $number = phoneNumberText($call['phone_call_number']);
    $with = $who . ($number !== '' && $number !== $who ? " ($number)" : '');
    $label = $direction === 'outbound' ? "Outbound call to $with" : ($direction === 'local' ? "Internal call with $with" : "Inbound call from $with");
    if ((string) $call['phone_call_extension'] !== '') {
        $label .= ' on ext ' . $call['phone_call_extension'];
    }
    $status = (string) $call['phone_call_status'];
    if ($status === 'answered') {
        $label .= ', ' . phoneSecondsText($call['phone_call_talk_seconds']);
    } else {
        $label .= ' - ' . strtolower(phoneCallStatuses()[$status][0] ?? $status);
    }
    return $label . '.';
}

/**
 * Put a call on a ticket - an internal note saying what the call was, with its talk time as the
 * time worked (rounded up to the minute) when $count_time, and the call says which ticket it is on.
 * The note's id.
 */
function phoneCallToTicket(array $call, array $ticket, int $user_id, bool $count_time, string $note = ''): int
{
    global $mysqli;

    $ticket_id = intval($ticket['ticket_id']);
    // Time talking to someone - not a voicemail box
    $talk = $call['phone_call_status'] === 'answered' ? intval($call['phone_call_talk_seconds']) : 0;
    $time_worked = $count_time && $talk > 0 ? phoneTimeWorked($talk) : '00:00:00';
    $html = '<p>' . escapeHtml(phoneCallSentence($call)) . ' <small>(' . escapeHtml(formatDateTime($call['phone_call_started_at'])) . ')</small></p>';
    if (trim($note) !== '') {
        $html .= '<p>' . nl2br(escapeHtml(trim($note))) . '</p>';
    }
    $work_sql = '';
    if ($time_worked !== '00:00:00' && function_exists('ticketWorkTimesReady') && ticketWorkTimesReady() && $call['phone_call_answered_at']) {
        $end = date('Y-m-d H:i:s', strtotime($call['phone_call_answered_at']) + $talk);
        $work_sql = ", ticket_reply_work_start = '" . escapeSql($call['phone_call_answered_at']) . "', ticket_reply_work_end = '$end'";
    }

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . escapeSql($html) . "', ticket_reply_type = 'Internal',
        ticket_reply_time_worked = '$time_worked'$work_sql, ticket_reply_by = $user_id, ticket_reply_ticket_id = $ticket_id");
    $reply_id = intval(mysqli_insert_id($mysqli));
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE phone_calls SET phone_call_ticket_id = $ticket_id WHERE phone_call_id = " . intval($call['phone_call_id']));
    // A caller nobody knew is now the ticket's client - and its contact, when it has one
    if (!phoneCallClientFor($call)) {
        mysqli_query($mysqli, "UPDATE phone_calls SET phone_call_client_id = " . intval($ticket['ticket_client_id']) . ", phone_call_contact_id = " . intval($ticket['ticket_contact_id']) . "
            WHERE phone_call_id = " . intval($call['phone_call_id']));
    } elseif (!intval($call['phone_call_contact_id']) && intval($call['phone_call_client_id']) === intval($ticket['ticket_client_id'])) {
        mysqli_query($mysqli, "UPDATE phone_calls SET phone_call_contact_id = " . intval($ticket['ticket_contact_id']) . " WHERE phone_call_id = " . intval($call['phone_call_id']));
    }
    return $reply_id;
}

/*
 * Phones - desk phones that tell ITFlow about themselves (api/phone/hook.php): Yealink action URLs,
 * each a GET with e= the event, or Polycom telephony notifications, XML posted. A phone is kept by
 * its MAC address in phone_devices with what it last said - model, firmware, IP address, its line
 * (extension and domain) and whether it is registered - and becomes a Phone asset of its domain's
 * client once the domain is linked (phoneDeviceAsset()).
 */

/** A MAC address as 12 lower-case hex digits - '' when it isn't one */
function phoneMac($raw): string
{
    $mac = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', (string) $raw));
    return strlen($mac) === 12 && $mac !== '000000000000' && $mac !== 'ffffffffffff' ? $mac : '';
}

/** A MAC address for reading - "00:15:65:AB:CD:EF" */
function phoneMacText($mac): string
{
    $mac = (string) $mac;
    return strlen($mac) === 12 ? strtoupper(implode(':', str_split($mac, 2))) : '';
}

/**
 * Something a phone sent, cleaned up - '' for nothing, and for a variable the phone left as it was
 * ("$active_user"), cut to $max characters.
 */
function phoneHookValue($raw, int $max = 100): string
{
    $value = (string) $raw;
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
    $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $value));
    if ($value === '' || preg_match('/^\$\w+$|^\{\$[\w.]+\}$/', $value)) {
        return '';
    }
    return mb_substr($value, 0, $max);
}

/** An extension as a phone gives it - "101" - or '' */
function phoneHookLine($raw): string
{
    $line = phoneHookValue(rawurldecode((string) $raw), 50);
    return preg_match('/^[A-Za-z0-9_.+*#-]+$/', $line) ? $line : '';
}

/** [user, host] of a SIP address - "sip:101@pbx.example.com", "<sip:101@pbx.example.com>" - or ['', ''] */
function phoneSipParts($raw): array
{
    $raw = trim(rawurldecode((string) $raw));
    if (preg_match('/<([^>]*)>/', $raw, $m)) {
        $raw = $m[1];
    }
    if (preg_match('/^(?:sips?:)?([^@;:\s]+)@([^;>:\s\/]+)/i', $raw, $m)) {
        return [$m[1], $m[2]];
    }
    return ['', ''];
}

/** A model as phones give it, without the SIP- some put in front - "SIP-T46S" is T46S */
function phoneHookModel($raw): string
{
    return (string) preg_replace('/^SIP[-\s]+|-SIP$/i', '', phoneHookValue($raw));
}

/** [vendor, model, firmware] a phone's User-Agent says - '' for what it doesn't */
function phoneAgentFacts(string $agent): array
{
    // Yealink SIP-T46S 66.86.0.15 00:15:65:aa:bb:cc
    if (preg_match('/\bYealink\s+([A-Z0-9][\w-]*)\s+(\d[\d.]*)/i', $agent, $m)) {
        return ['Yealink', phoneHookModel($m[1]), $m[2]];
    }
    // PolycomVVX-VVX_410-UA/5.3.0.3844
    if (preg_match('/\bPolycom\w*-(\w+?)-UA\/(\d[\d.]*)/i', $agent, $m)) {
        return ['Polycom', str_replace('_', ' ', $m[1]), $m[2]];
    }
    if (preg_match('/\b(Grandstream|Fanvil|snom|Htek)\s*([A-Z0-9][\w-]*)?[\s\/]+(\d[\d.]*)/i', $agent, $m)) {
        $vendors = ['grandstream' => 'Grandstream', 'fanvil' => 'Fanvil', 'snom' => 'Snom', 'htek' => 'Htek'];
        return [$vendors[strtolower($m[1])], phoneHookModel($m[2] ?? ''), $m[3]];
    }
    return ['', '', ''];
}

/**
 * What a phone said, from its request - ['kind', 'mac', 'ip', 'vendor', 'model', 'firmware', 'line',
 * 'domain', 'number', 'name', 'call'], or null when it isn't a phone's. kind is setup, registered,
 * unregistered, failed, ringing, outgoing, answered, ended, missed, or heard (nothing more than that).
 * Yealink sends e= and the rest as the setup window's action URLs have them; Polycom posts XML, and
 * says its model and firmware in its User-Agent.
 */
function phoneHookEvent(array $get, string $body, string $agent): ?array
{
    [$vendor, $model, $firmware] = phoneAgentFacts($agent);
    $event = ['kind' => '', 'mac' => '', 'ip' => '', 'vendor' => $vendor, 'model' => $model, 'firmware' => $firmware,
        'line' => '', 'domain' => '', 'number' => '', 'name' => '', 'call' => ''];

    if (stripos($body, '<PolycomIPPhone') !== false) {
        return phoneHookPolycom($body, $event);
    }

    $kind = strtolower(phoneHookValue($get['e'] ?? '', 20));
    if (!in_array($kind, ['setup', 'registered', 'unregistered', 'failed', 'ringing', 'outgoing', 'answered', 'ended', 'missed'], true)) {
        return null;
    }
    $event['kind'] = $kind;
    $event['mac'] = phoneMac(phoneHookValue($get['mac'] ?? ''));
    $event['ip'] = (string) filter_var(phoneHookValue($get['ip'] ?? '', 64), FILTER_VALIDATE_IP);
    $event['model'] = phoneHookModel($get['model'] ?? '') ?: $event['model'];
    $event['firmware'] = phoneHookValue($get['fw'] ?? '') ?: $event['firmware'];
    // Yealink's own MAC address blocks, for a phone whose User-Agent didn't say
    if ($event['vendor'] === '' && $event['mac'] !== '' && in_array(substr($event['mac'], 0, 6), ['001565', '805ec0', '805e0c', '249ad8', '44dbd2'], true)) {
        $event['vendor'] = 'Yealink';
    }
    // The account the event is about - its extension and domain, or a whole SIP address in line
    [$user, $host] = phoneSipParts($get['line'] ?? '');
    $event['line'] = phoneHookLine($user !== '' ? $user : ($get['line'] ?? ''));
    $event['domain'] = phoneHookValue($host !== '' ? $host : ($get['domain'] ?? ''), 255);
    $event['number'] = phoneNumber(phoneHookValue($get['number'] ?? '', 255));
    $event['name'] = phoneHookValue(rawurldecode((string) ($get['name'] ?? '')));
    $event['call'] = phoneHookValue($get['call'] ?? '');
    return $event;
}

/** A Polycom telephony notification (phoneHookEvent()) - its event, or null */
function phoneHookPolycom(string $body, array $event): ?array
{
    if (strlen($body) > PHONE_HOOK_MAX_BYTES) {
        return null;
    }
    $was = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($was);
    if (!$doc || $doc->getName() !== 'PolycomIPPhone' || !$doc->children()->count()) {
        return null;
    }
    $node = $doc->children()[0];
    $type = $node->getName();

    // Every value in it, by name - the first of each, attributes too
    $values = [];
    $walk = function (SimpleXMLElement $element) use (&$walk, &$values) {
        foreach ($element->attributes() as $name => $value) {
            $values[(string) $name] ??= trim((string) $value);
        }
        foreach ($element->children() as $name => $child) {
            if ($child->count()) {
                $walk($child);
            } else {
                $values[(string) $name] ??= trim((string) $child);
            }
            foreach ($child->attributes() as $attribute => $value) {
                $values[(string) $attribute] ??= trim((string) $value);
            }
        }
    };
    $walk($node);
    $value = function (string $name) use ($values) {
        return phoneHookValue($values[$name] ?? '', 255);
    };

    $event['mac'] = phoneMac($value('MACAddress'));
    $event['ip'] = (string) filter_var($value('PhoneIP'), FILTER_VALIDATE_IP);
    if ($event['vendor'] === '') {
        $event['vendor'] = 'Polycom';
    }

    $ours = '';
    $theirs = ['', ''];
    switch ($type) {
        case 'IncomingCallEvent':
            $event['kind'] = 'ringing';
            $ours = $value('CalledPartyNumber');
            $theirs = [$value('CallingPartyNumber'), $value('CallingPartyName')];
            break;
        case 'OutgoingCallEvent':
            $event['kind'] = 'outgoing';
            $ours = $value('CallingPartyNumber');
            $theirs = [$value('CalledPartyNumber'), $value('CalledPartyName')];
            break;
        case 'LineRegistrationEvent':
        case 'LineUnregistrationEvent':
            $event['kind'] = $type === 'LineRegistrationEvent' ? 'registered' : 'unregistered';
            // The line's SIP address, whatever it is called in this firmware
            foreach ($values as $name => $each) {
                if (!in_array($name, ['MACAddress', 'PhoneIP', 'TimeStamp'], true) && phoneSipParts($each)[0] !== '') {
                    $ours = $each;
                    break;
                }
            }
            break;
        case 'CallStateChangeEvent':
            $state = strtolower($value('CallState'));
            $event['kind'] = $state === 'connected' ? 'answered' : (in_array($state, ['disconnected', 'free'], true) ? 'ended' : 'heard');
            $incoming = strtolower($value('CallType')) !== 'outgoing';
            $ours = $value('LineDirNum') ?: $value($incoming ? 'CalledPartyDirNum' : 'CallingPartyDirNum');
            $theirs = $incoming ? [$value('CallingPartyDirNum'), $value('CallingPartyName')] : [$value('CalledPartyDirNum'), $value('CalledPartyName')];
            $event['call'] = $value('CallReference');
            break;
        case 'UIInitializationEvent':
        case 'NetworkUpEvent':
            $event['kind'] = 'setup';
            break;
        default:
            $event['kind'] = 'heard';
    }
    [$user, $host] = phoneSipParts($ours);
    $event['line'] = phoneHookLine($user !== '' ? $user : $ours);
    $event['domain'] = phoneHookValue($host, 255);
    $event['number'] = phoneNumber($theirs[0]);
    $event['name'] = $theirs[1] !== '' && $theirs[1] !== $theirs[0] ? mb_substr($theirs[1], 0, 100) : '';
    return $event;
}

/** What a phone's status is - [label, icon, tone] by status ('' is a phone that hasn't said) */
function phoneDeviceStatuses(): array
{
    return [
        'registered' => ['Registered', 'fa-check-circle', 'success'],
        'unregistered' => ['Not registered', 'fa-minus-circle', 'secondary'],
        'failed' => ["Can't register", 'fa-exclamation-circle', 'danger'],
        '' => ['Heard from', 'fa-circle', 'secondary'],
    ];
}

/** A phone_devices row by id - with its system and domain - or null */
function phoneDeviceRow($phone_device_id): ?array
{
    global $mysqli;
    $phone_device_id = intval($phone_device_id);
    if (!$phone_device_id || !phoneTablesReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT phone_devices.*, phone_systems.*, phone_domain_name FROM phone_devices
        JOIN phone_systems ON phone_system_id = phone_device_system_id
        LEFT JOIN phone_domains ON phone_domain_id = phone_device_domain_id
        WHERE phone_device_id = $phone_device_id")) ?: null;
}

/** The phones an asset is - most recently heard from first */
function phoneDevicesForAsset(int $asset_id): array
{
    global $mysqli;
    if ($asset_id <= 0 || !phoneTablesReady()) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT phone_devices.*, phone_systems.*, phone_domain_name FROM phone_devices
        JOIN phone_systems ON phone_system_id = phone_device_system_id
        LEFT JOIN phone_domains ON phone_domain_id = phone_device_domain_id
        WHERE phone_device_asset_id = $asset_id ORDER BY phone_device_heard_at DESC");
    return mysqli_fetch_all($sql, MYSQLI_ASSOC);
}

/**
 * Keep what a phone said (phoneHookEvent()): its phone_devices row made or brought up to date - a
 * phone ringing or making a call is registered - and its asset (phoneDeviceAsset()). The row, or
 * null for a phone that didn't say its MAC address.
 */
function phoneDeviceSave(array $system, array $event): ?array
{
    global $mysqli;
    if ($event['mac'] === '') {
        return null;
    }
    $system_id = intval($system['phone_system_id']);
    $mac = escapeSql($event['mac']);
    $find = "SELECT * FROM phone_devices WHERE phone_device_system_id = $system_id AND phone_device_mac = '$mac'";
    $before = mysqli_fetch_assoc(mysqli_query($mysqli, $find));
    if (!$before) {
        mysqli_query($mysqli, "INSERT IGNORE INTO phone_devices SET phone_device_system_id = $system_id, phone_device_mac = '$mac'");
        $before = mysqli_fetch_assoc(mysqli_query($mysqli, $find));
    }
    if (!$before) {
        return null;
    }
    $phone_device_id = intval($before['phone_device_id']);

    $set = ['phone_device_heard_at = NOW()'];
    foreach (['vendor' => 30, 'model' => 100, 'firmware' => 100, 'ip' => 64] as $field => $max) {
        if ($event[$field] !== '') {
            $set[] = "phone_device_$field = '" . escapeSql(mb_substr($event[$field], 0, $max)) . "'";
        }
    }
    if ($event['line'] !== '') {
        $set[] = "phone_device_line = '" . escapeSql($event['line']) . "'";
        // An address rather than a name isn't a domain of the phone system's
        $domain = $event['domain'] !== '' && !filter_var(preg_replace('/:\d+$/', '', $event['domain']), FILTER_VALIDATE_IP) ? phoneDomain($system, $event['domain']) : null;
        if ($domain) {
            $set[] = 'phone_device_domain_id = ' . intval($domain['phone_domain_id']);
            $set[] = 'phone_device_client_id = ' . intval($domain['phone_domain_client_id']);
        }
    }
    $status = ['registered' => 'registered', 'unregistered' => 'unregistered', 'failed' => 'failed'][$event['kind']]
        ?? (in_array($event['kind'], ['ringing', 'outgoing', 'answered', 'ended', 'missed'], true) ? 'registered' : null);
    if ($status !== null && $status !== $before['phone_device_status']) {
        $set[] = "phone_device_status = '$status'";
        $set[] = 'phone_device_status_at = NOW()';
    }
    mysqli_query($mysqli, "UPDATE phone_devices SET " . implode(', ', $set) . " WHERE phone_device_id = $phone_device_id");
    mysqli_query($mysqli, "UPDATE phone_systems SET phone_system_heard_at = NOW() WHERE phone_system_id = $system_id");

    phoneDeviceAsset($phone_device_id, $before);
    return phoneDeviceRow($phone_device_id);
}

/** The name a phone's asset gets - PH-101, or PH- and the end of its MAC address until its extension is known */
function phoneDeviceAssetName(array $device): string
{
    $line = (string) $device['phone_device_line'];
    return 'PH-' . ($line !== '' ? $line : strtoupper(substr((string) $device['phone_device_mac'], -6)));
}

/**
 * A phone's asset - found, made, or kept up to date - its id (0 for none yet, -1 for one deleted in
 * ITFlow). Once its domain is linked a phone is an asset of the domain's client: the client's asset
 * with its MAC address, else a new Phone asset. Its name, make, model, firmware, IP address and
 * extension follow what the phone says, unless changed in ITFlow - a field is written when it is
 * empty or still holds what the phone said last time ($before, its row before this). A phone whose
 * asset was deleted isn't made one again; it takes an asset with its MAC address made by hand.
 * On a domain linked to another client its asset stays with the client it was at.
 */
function phoneDeviceAsset(int $phone_device_id, ?array $before = null): int
{
    global $mysqli;
    $device = phoneDeviceRow($phone_device_id);
    if (!$device) {
        return 0;
    }
    $client_id = intval($device['phone_device_client_id']);
    $asset_id = intval($device['phone_device_asset_id']);
    $who = phoneSystemName($device);
    $before = $before ?? $device;

    if ($asset_id > 0) {
        $asset = phoneDeviceAssetCurrent($asset_id);
        if (!$asset) {
            mysqli_query($mysqli, "UPDATE phone_devices SET phone_device_asset_id = -1 WHERE phone_device_id = $phone_device_id");
            $asset_id = -1;
        } elseif (!$client_id) {
            // Its domain isn't linked now - its asset is left as it is
            return $asset_id;
        } elseif (intval($asset['asset_client_id']) !== $client_id) {
            rmmAssetHistory($asset_id, $who, 'no longer keeps it up - its domain is linked to another client');
            mysqli_query($mysqli, "UPDATE phone_devices SET phone_device_asset_id = 0 WHERE phone_device_id = $phone_device_id");
            $asset_id = 0;
        } else {
            $changed = phoneDeviceAssetFollow($device, $before, $asset);
            if ($changed) {
                $last = array_pop($changed);
                rmmAssetHistory($asset_id, $who, 'changed its ' . ($changed ? implode(', ', $changed) . " and $last" : $last));
            }
            return $asset_id;
        }
    }
    if (!$client_id) {
        return $asset_id;
    }

    // The client's asset with its MAC address, that isn't another phone's
    $mac = escapeSql((string) $device['phone_device_mac']);
    $found = mysqli_fetch_row(mysqli_query($mysqli, "SELECT asset_id FROM assets JOIN asset_interfaces ON interface_asset_id = asset_id
        WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
        AND LOWER(REPLACE(REPLACE(REPLACE(interface_mac, ':', ''), '-', ''), '.', '')) = '$mac'
        AND asset_id NOT IN (SELECT phone_device_asset_id FROM phone_devices WHERE phone_device_id <> $phone_device_id AND phone_device_asset_id > 0)
        ORDER BY interface_primary DESC, asset_id ASC LIMIT 1"));
    if ($found) {
        $asset_id = intval($found[0]);
        mysqli_query($mysqli, "UPDATE phone_devices SET phone_device_asset_id = $asset_id WHERE phone_device_id = $phone_device_id");
        // An asset made by hand: only what it hasn't got is filled in
        $blank = array_merge($device, ['phone_device_vendor' => '', 'phone_device_model' => '', 'phone_device_firmware' => '', 'phone_device_ip' => '', 'phone_device_line' => '', 'phone_device_mac' => '']);
        phoneDeviceAssetFollow($device, $blank, phoneDeviceAssetCurrent($asset_id));
        rmmAssetHistory($asset_id, $who, 'found it by its MAC address, and keeps it up from now on');
        return $asset_id;
    }
    if ($asset_id === -1) {
        return -1;
    }

    $name = phoneDeviceAssetName($device);
    $name_sql = escapeSql($name);
    $make = escapeSql((string) $device['phone_device_vendor']);
    $model = escapeSql((string) $device['phone_device_model']);
    $firmware = escapeSql((string) $device['phone_device_firmware']);
    mysqli_query($mysqli, "INSERT INTO assets SET asset_name = '$name_sql', asset_type = 'Phone', asset_make = '$make', asset_model = '$model',
        asset_os = '$firmware', asset_status = 'Deployed', asset_client_id = $client_id");
    $asset_id = intval(mysqli_insert_id($mysqli));
    if (!$asset_id) {
        return 0;
    }
    $mac_text = escapeSql(phoneMacText((string) $device['phone_device_mac']));
    $ip = escapeSql((string) $device['phone_device_ip']);
    mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_mac = '$mac_text', interface_ip = '$ip', interface_primary = 1, interface_asset_id = $asset_id");
    phoneDeviceAssetExtension($asset_id, (string) $device['phone_device_line']);
    mysqli_query($mysqli, "UPDATE phone_devices SET phone_device_asset_id = $asset_id WHERE phone_device_id = $phone_device_id");
    rmmAssetHistory($asset_id, $who, 'added it - the phone told it about itself');
    logAudit('Asset', 'Create', escapeSql("$who added phone $name"), $client_id, $asset_id);
    return $asset_id;
}

/** What a phone's asset has now (phoneDeviceAsset()) - or null when it is gone */
function phoneDeviceAssetCurrent(int $asset_id): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_client_id, asset_name, asset_make, asset_model, asset_os,
        interface_id, interface_ip FROM assets
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_id = $asset_id"));
    if (!$row) {
        return null;
    }
    $row['extension'] = (string) (function_exists('assetSpecs') && assetSpecsReady() ? (assetSpecs($asset_id)['extension'] ?? '') : '');
    return $row;
}

/** A phone asset's Extension detail - set, when there is one */
function phoneDeviceAssetExtension(int $asset_id, string $line): void
{
    global $mysqli;
    if ($line === '' || !function_exists('assetSpecsReady') || !assetSpecsReady()) {
        return;
    }
    $line = escapeSql($line);
    mysqli_query($mysqli, "INSERT INTO asset_specs SET asset_spec_asset_id = $asset_id, asset_spec_key = 'extension', asset_spec_value = '$line'
        ON DUPLICATE KEY UPDATE asset_spec_value = VALUES(asset_spec_value)");
}

/** Bring a phone's asset up to what the phone says, by the follow rule (phoneDeviceAsset()) - what changed, for its history */
function phoneDeviceAssetFollow(array $device, array $before, ?array $asset): array
{
    global $mysqli;
    if (!$asset) {
        return [];
    }
    $asset_id = intval($asset['asset_id']);
    // field => [label, what it has, what the phone says, what the phone said]
    $fields = [
        'asset_name' => ['name', $asset['asset_name'], phoneDeviceAssetName($device), phoneDeviceAssetName($before)],
        'asset_make' => ['make', $asset['asset_make'], $device['phone_device_vendor'], $before['phone_device_vendor']],
        'asset_model' => ['model', $asset['asset_model'], $device['phone_device_model'], $before['phone_device_model']],
        'asset_os' => ['firmware', $asset['asset_os'], $device['phone_device_firmware'], $before['phone_device_firmware']],
        'ip' => ['IP address', $asset['interface_ip'], $device['phone_device_ip'], $before['phone_device_ip']],
        'extension' => ['extension', $asset['extension'], $device['phone_device_line'], $before['phone_device_line']],
    ];
    $changed = [];
    $sets = [];
    foreach ($fields as $field => [$label, $has, $says, $said]) {
        $has = (string) $has;
        $says = (string) $says;
        if ($says === '' || $has === $says || ($has !== '' && $has !== (string) $said)) {
            continue;
        }
        $changed[] = $label;
        if ($field === 'ip') {
            $ip = escapeSql($says);
            if ($asset['interface_id']) {
                mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_ip = '$ip' WHERE interface_id = " . intval($asset['interface_id']));
            } else {
                $mac_text = escapeSql(phoneMacText((string) $device['phone_device_mac']));
                mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_mac = '$mac_text', interface_ip = '$ip', interface_primary = 1, interface_asset_id = $asset_id");
            }
        } elseif ($field === 'extension') {
            phoneDeviceAssetExtension($asset_id, $says);
        } else {
            $sets[] = "$field = '" . escapeSql($says) . "'";
        }
    }
    if ($sets) {
        mysqli_query($mysqli, "UPDATE assets SET " . implode(', ', $sets) . " WHERE asset_id = $asset_id");
    }
    return $changed;
}

/*
 * Screen pops - a call ringing on an agent's desk phone pops up wherever they are in ITFlow
 * (js/phone_pop.js, asking agent/ajax.php?phone_pop every few seconds): who it is, their client and
 * open tickets, and New Ticket. Answered, it stays up while they talk; ended, it offers to put the
 * call on a ticket once its record is in. The phone says what happens (phoneHookEvent()), and
 * phoneEventSave() keeps it for each agent whose desk phone the line is.
 */

/** How long a call ringing, or being dialled, stays up without another word from the phone - seconds */
const PHONE_POP_RINGING_SECONDS = 90;
/** How long an ended call stays up, to put it on a ticket - seconds */
const PHONE_POP_ENDED_SECONDS = 600;

/**
 * Keep a phone's call event for the agents whose desk phone it is - only calls with the outside
 * world, and only lines someone has. How many it kept.
 */
function phoneEventSave(array $system, array $event, ?array $device): int
{
    global $mysqli;
    if (!in_array($event['kind'], ['ringing', 'outgoing', 'answered', 'ended', 'missed'], true)) {
        return 0;
    }
    $number = $event['number'];
    if ($number === '' || phoneIsExtension($number)) {
        return 0;
    }
    // The line - as the event says it, else as the phone last did
    $line = $event['line'];
    $domain_name = $event['domain'] !== '' && !filter_var(preg_replace('/:\d+$/', '', $event['domain']), FILTER_VALIDATE_IP) ? phoneDomainName($event['domain']) : '';
    if ($line === '' || $domain_name === '') {
        $line = $line !== '' ? $line : (string) ($device['phone_device_line'] ?? '');
        $domain_name = (string) ($device['phone_domain_name'] ?? '');
    }
    $users = phoneUsersForLine($line, $domain_name);
    if (!$users) {
        return 0;
    }
    $domain = phoneDomain($system, $domain_name);
    $domain_client_id = intval($domain['phone_domain_client_id'] ?? 0);
    $who = phoneWho($number, $domain_client_id);
    $client_id = $domain_client_id && $domain_client_id !== intval($GLOBALS['config_internal_client_id'] ?? 0) ? $domain_client_id : ($who['client_id'] ?: $domain_client_id);
    $set = "phone_event_system_id = " . intval($system['phone_system_id']) . ", phone_event_domain_id = " . intval($domain['phone_domain_id'] ?? 0) . ",
        phone_event_line = '" . escapeSql($line) . "', phone_event_kind = '" . escapeSql($event['kind']) . "',
        phone_event_call = '" . escapeSql($event['call']) . "', phone_event_number = '" . escapeSql($number) . "',
        phone_event_name = '" . escapeSql($event['name']) . "', phone_event_contact_id = " . intval($who['contact_id']) . ", phone_event_client_id = $client_id";
    foreach ($users as $user_id) {
        mysqli_query($mysqli, "INSERT INTO phone_events SET phone_event_user_id = $user_id, $set");
    }
    return count($users);
}

/**
 * An agent's calls to show (js/phone_pop.js) - newest first, at most two: ringing or being
 * dialled (for PHONE_POP_RINGING_SECONDS after the phone last said so), answered (until it ends),
 * or ended in the last PHONE_POP_ENDED_SECONDS. Events are one call by the phone's call id, or by
 * the number when it gave none. Each is what the page draws - who, their client and open tickets,
 * the ticket made for it from the pop ($made, [call key => ticket id]) and, ended, its record.
 */
function phonePopCalls(int $user_id, array $made = []): array
{
    global $mysqli;
    if (!$user_id || !phoneTablesReady()) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT phone_events.*, TIMESTAMPDIFF(SECOND, phone_event_at, NOW()) AS age FROM phone_events
        WHERE phone_event_user_id = $user_id AND phone_event_at >= NOW() - INTERVAL 4 HOUR
        ORDER BY phone_event_id ASC LIMIT 1000");
    $groups = [];
    $open = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $match = $row['phone_event_call'] !== '' ? 'c' . $row['phone_event_call'] : 'n' . $row['phone_event_number'] . '|' . $row['phone_event_line'];
        if (!isset($open[$match])) {
            $open[$match] = count($groups);
            $groups[] = ['key' => 'e' . $row['phone_event_id'], 'first' => $row, 'answered' => null, 'name' => '', 'contact_id' => 0, 'client_id' => 0];
        }
        $group = &$groups[$open[$match]];
        $group['last'] = $row;
        if ($row['phone_event_kind'] === 'answered' && !$group['answered']) {
            $group['answered'] = $row;
        }
        $group['name'] = $group['name'] !== '' ? $group['name'] : (string) $row['phone_event_name'];
        $group['contact_id'] = $group['contact_id'] ?: intval($row['phone_event_contact_id']);
        $group['client_id'] = $group['client_id'] ?: intval($row['phone_event_client_id']);
        unset($group);
        if (in_array($row['phone_event_kind'], ['ended', 'missed'], true)) {
            unset($open[$match]);
        }
    }

    $calls = [];
    foreach (array_reverse($groups) as $group) {
        $last = $group['last'];
        $kind = $last['phone_event_kind'];
        $state = in_array($kind, ['ended', 'missed'], true) ? 'ended' : $kind;
        $age = intval($last['age']);
        if (($state === 'ended' && $age > PHONE_POP_ENDED_SECONDS) || (in_array($state, ['ringing', 'outgoing'], true) && $age > PHONE_POP_RINGING_SECONDS)) {
            continue;
        }
        $calls[] = phonePopCall($user_id, $group, $state, intval($made[$group['key']] ?? 0));
        if (count($calls) === 2) {
            break;
        }
    }
    return $calls;
}

/** A pop's call key as a page sends it back - "e123" - or '' */
function phonePopKey($raw): string
{
    $raw = (string) $raw;
    return preg_match('/^e\d{1,11}$/', $raw) ? $raw : '';
}

/** Remember the ticket made from a pop for its call, for Add to Ticket when it ends - the last few, in the session */
function phonePopTicketMade(string $key, int $ticket_id): void
{
    if ($key === '' || !$ticket_id || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $made = (array) ($_SESSION['phone_pop_tickets'] ?? []);
    $made[$key] = $ticket_id;
    $_SESSION['phone_pop_tickets'] = array_slice($made, -10, null, true);
}

/** One call as the pop draws it (phonePopCalls()) - names and links only for clients the agent can reach */
function phonePopCall(int $user_id, array $group, string $state, int $made_ticket_id): array
{
    global $mysqli;
    $first = $group['first'];
    $number = (string) $first['phone_event_number'];
    $outbound = $first['phone_event_kind'] === 'outgoing';
    $client_id = $group['client_id'] && hasClientAccess($group['client_id']) ? $group['client_id'] : 0;
    $support = lookupUserPermission('module_support') >= 1;
    $call = [
        'key' => $group['key'],
        'state' => $state,
        'direction' => $outbound ? 'outbound' : 'inbound',
        'number' => $number,
        'number_text' => phoneNumberText($number),
        'name' => $group['name'],
        'seconds' => intval($first['age']),
        'talk_seconds' => null,
        'contact' => null,
        'client' => null,
        'tickets' => [],
        'ticket' => null,
        'record' => null,
        'new_ticket' => '',
        'add' => '',
    ];
    if ($group['answered']) {
        $call['talk_seconds'] = $state === 'ended' ? max(0, intval($group['answered']['age']) - intval($group['last']['age'])) : intval($group['answered']['age']);
    }
    if ($client_id) {
        $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
        if ($client) {
            $call['client'] = ['id' => $client_id, 'name' => (string) $client['client_name'], 'url' => "/agent/client_overview.php?client_id=$client_id"];
        }
        $contact = $group['contact_id'] ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title FROM contacts
            WHERE contact_id = " . intval($group['contact_id']) . " AND contact_client_id = $client_id")) : null;
        if ($contact) {
            $call['contact'] = ['id' => intval($contact['contact_id']), 'name' => (string) $contact['contact_name'], 'title' => (string) $contact['contact_title'],
                'url' => "/agent/contact.php?client_id=$client_id&contact_id=" . intval($contact['contact_id'])];
        }
        // Their open tickets - the contact's first
        if ($support) {
            $contact_id = intval($call['contact']['id'] ?? 0);
            $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id FROM tickets
                WHERE ticket_client_id = $client_id AND ticket_closed_at IS NULL AND ticket_resolved_at IS NULL
                ORDER BY ticket_contact_id = $contact_id DESC, COALESCE(ticket_updated_at, ticket_created_at) DESC LIMIT 3");
            while ($row = mysqli_fetch_assoc($sql)) {
                $call['tickets'][] = ['id' => intval($row['ticket_id']), 'ref' => ticketReference($row), 'subject' => (string) $row['ticket_subject'], 'url' => '/agent/' . ticketUrl($row['ticket_id'], $row['ticket_client_id'])];
            }
        }
    }
    if ($made_ticket_id && $support) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $made_ticket_id"));
        if ($row && (!intval($row['ticket_client_id']) || hasClientAccess($row['ticket_client_id']))) {
            $call['ticket'] = ['id' => $made_ticket_id, 'ref' => ticketReference($row), 'url' => '/agent/' . ticketUrl($row['ticket_id'], $row['ticket_client_id'])];
        }
    }
    // New Ticket - Quick Ticket, or New Ticket when that is off - with the client and contact in it
    $can_edit = lookupUserPermission('module_support') >= 2;
    if ($can_edit && !$call['ticket']) {
        $who = $call['contact']['name'] ?? ($call['name'] !== '' ? $call['name'] : $call['number_text']);
        $call['new_ticket'] = function_exists('quickTicketCan') && quickTicketCan()
            ? '/agent/modals/ticket/ticket_quick.php?' . http_build_query(array_filter(['client_id' => $client_id ?: null, 'contact_id' => $call['contact']['id'] ?? null, 'phone_call' => $group['key']]))
            : '/agent/modals/ticket/ticket_add.php?' . http_build_query(array_filter(['client_id' => $client_id ?: null, 'contact_id' => $call['contact']['id'] ?? null,
                'subject' => ($outbound ? 'Call to ' : 'Call from ') . $who, 'phone_pop_call' => $group['key']]));
    }

    // Ended: the call's record, once FreeSWITCH has sent it - the same number on this line, about then
    if ($state === 'ended') {
        $line = escapeSql((string) $first['phone_event_line']);
        $number_sql = escapeSql($number);
        $from = intval($first['age']) + 180;
        $to = max(0, intval($group['last']['age']) - 60);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT phone_call_id, phone_call_status, phone_call_talk_seconds, phone_call_ticket_id, phone_call_client_id, phone_call_contact_id FROM phone_calls
            WHERE phone_call_number = '$number_sql' AND (phone_call_user_id = $user_id OR phone_call_extension = '$line')
            AND phone_call_started_at BETWEEN NOW() - INTERVAL $from SECOND AND NOW() - INTERVAL $to SECOND
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, phone_call_started_at, NOW() - INTERVAL " . intval($first['age']) . " SECOND)) ASC LIMIT 1"));
        if ($row && (!phoneCallClientFor($row) || hasClientAccess($row['phone_call_client_id']))) {
            $call['record'] = ['id' => intval($row['phone_call_id']), 'answered' => $row['phone_call_status'] === 'answered',
                'talk' => phoneSecondsText($row['phone_call_talk_seconds']), 'on_ticket' => intval($row['phone_call_ticket_id']) > 0];
            if ($can_edit && !$call['record']['on_ticket']) {
                $call['add'] = '/agent/modals/phone/phone_call_ticket.php?id=' . intval($row['phone_call_id']) . ($call['ticket'] ? '&ticket_id=' . $call['ticket']['id'] : '');
            }
        }
    }
    return $call;
}

/** Each night (cron/nightly_tasks.php): calls older than PHONE_CALLS_KEEP_DAYS that aren't on a ticket go */
function phoneCleanup(): void
{
    global $mysqli;
    if (!phoneTablesReady()) {
        return;
    }
    mysqli_query($mysqli, "DELETE FROM phone_calls WHERE phone_call_ticket_id = 0 AND phone_call_started_at < NOW() - INTERVAL " . PHONE_CALLS_KEEP_DAYS . " DAY");
    mysqli_query($mysqli, "DELETE FROM phone_events WHERE phone_event_at < NOW() - INTERVAL 2 DAY");
}
