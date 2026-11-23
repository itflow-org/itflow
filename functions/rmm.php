<?php

/*
 * RMM Providers - devices from an RMM as ITFlow assets (Admin > Integrations > RMM Providers).
 * Network platforms - UniFi's switches, access points and gateways (Admin > Network Platforms,
 * functions/rmm_unifi.php) - are kinds of the same thing in another family: what is said here
 * of an RMM's clients is said of a platform's sites, and the pages say so in its own words
 * (rmmFamilyWords()).
 *
 * Each provider (rmm_providers) is one RMM ITFlow reads from: its kind - which RMM it is - the
 * name it goes by here, its addresses and its key. Every kind looks the same to the rest of
 * ITFlow. Its own file says what the kind is and turns the RMM's answers into the shapes
 * below (functions/rmm_tactical.php for Tactical RMM); this file does the rest. Adding an RMM
 * is a file like that and a line in rmmProviderKinds().
 *
 * A provider's clients (rmm_clients) are linked to ITFlow clients by hand on its Clients page -
 * a client with the same name is suggested. Only a linked client's devices come in: every 15
 * minutes (cron/rmm_sync.php, or Sync now) each device (rmm_devices) becomes an asset, or is
 * matched to the asset it already is - same serial number, or the same name when only one
 * asset has it - and its asset is kept up to date.
 *
 * What a device is - its name, type, make and model, serial, OS, IP, CPU, storage, and its
 * location from its site in the RMM - follows the RMM, field by field, until someone changes
 * that field in ITFlow: rmm_devices.rmm_device_synced holds what the sync last wrote, and a
 * field that no longer holds it was changed by hand and is left alone from then on. A blank
 * from the RMM never blanks a field. Matching an asset that was already there only fills in
 * what it hasn't got.
 *
 * What a device is doing - online, offline or overdue, last seen, who is signed in, a reboot
 * or patches waiting, failing checks - lives on its rmm_devices row and shows on the asset.
 * A device the RMM no longer has keeps its asset, marked as gone from it. An asset deleted in
 * ITFlow isn't brought back by the next sync - its row stays, with no asset, until Bring back
 * on the Clients page lets the sync make it again.
 */

/*
 * ------------------------------------------------------------------ The kinds
 *
 * A kind is an array:
 *   name, short      - "Tactical RMM", and "Tactical" for "Gone from Tactical"
 *   default_name     - what a new one is called, when not its name
 *   icon, about      - its icon, and a line on what it brings in
 *   docs_url         - where its API keys are explained
 *   family           - rmm (the default) or network - which Admin page it is on (rmmFamilyWords())
 *   fields           - the connection's fields it uses, of api_url, web_url, api_key, api_secret
 *                      and insecure (the rmm_provider_ columns): each with label, icon, type (url,
 *                      text, secret or switch), required, placeholder, help and needed (said when
 *                      it's empty)
 *   seal             - true to keep its secrets encrypted with this install's key (rmmSecret())
 *   device_kinds     - the kinds of device it has, guessed type => what they are called, each
 *                      coming in as the asset type its Edit picks (rmmDeviceKinds())
 *   attention        - its own words for what rmmDeviceAttention() says: patches_pending
 *   hints            - added to what rmmHttp() says went wrong: auth (401/403), not_found
 *                      (404) and not_json (an answer that isn't the API's)
 *   links            - a device's pages in the RMM that rmmDeviceUrls() gives, key => label:
 *                      open, and control (Take Control) and background where it has them
 *   clients          - fn(provider): ['ok', 'clients' => [[id, name, sites, devices]], 'error']
 *   devices          - fn(provider, client's id there): ['ok', 'devices' => [device], 'error'],
 *                      each device as rmmDeviceSave() takes it
 *   dashboard        - fn(provider): its web address, '' when it has none
 *   device_urls      - fn(provider, device's id there): key of links => address
 *   alerts, alert    - optional, for alerts opening tickets: fn(provider): ['ok', 'alerts' =>
 *                      the open ones, 'error'], and fn(provider, alert's id there): ['ok',
 *                      'alert' => it, or null when the RMM no longer has it, 'error'] - each
 *                      alert as described under Alerts below
 */

/*
 * Most of a client's devices gone from its RMM at once - at least RMM_GONE_AT_ONCE of them, and at
 * least half - is far more often the RMM, or its API key's access, than the devices. They are kept
 * as they were until they have been missing from it for RMM_GONE_HOLD_HOURS, and the sync says so.
 */
const RMM_GONE_AT_ONCE = 3;
const RMM_GONE_HOLD_HOURS = 24;

/** Alerts cleared this long ago are deleted at night - nothing reads one once it has cleared. */
const RMM_ALERTS_KEEP_DAYS = 180;

/** The RMMs ITFlow can bring devices in from - kind => what it is. */
function rmmProviderKinds(?string $family = null): array
{
    $kinds = [
        'tactical' => rmmTacticalKind(),
    ];
    // Network platforms keep columns of their own - once the 4.1.5 database update is in
    if (rmmNetworkReady()) {
        $kinds += rmmUnifiKinds();
    }
    if ($family === null) {
        return $kinds;
    }
    return array_filter($kinds, function ($kind) use ($family) {
        return ($kind['family'] ?? 'rmm') === $family;
    });
}

/** Whether the 4.1.5 database update is in - network platforms, and devices' MAC addresses. */
function rmmNetworkReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.1.5', '>=');
    }
    return $ready;
}

/** Which family a provider, or a kind, is in - rmm or network. One of a kind ITFlow doesn't know is an RMM's. */
function rmmFamily($provider): string
{
    $kind = is_array($provider) && isset($provider['fields']) ? $provider : rmmKind($provider);
    return (string) ($kind['family'] ?? 'rmm');
}

/**
 * How a family's pages speak - Admin > RMM Providers and its clients, or Admin > Network
 * Platforms and their sites. $family is rmm or network.
 */
function rmmFamilyWords(string $family): array
{
    if ($family === 'network') {
        return [
            'title' => 'Network Platforms', 'page' => 'network_platforms.php', 'icon' => 'fas fa-network-wired',
            'one' => 'platform', 'One' => 'Platform', 'full' => 'network platform', 'Full' => 'Network platform', 'kind' => 'Platform',
            'audit' => 'Network Platform', 'clients' => 'sites', 'Clients' => 'Sites', 'client' => 'site', 'Client' => 'Site',
        ];
    }
    return [
        'title' => 'RMM Providers', 'page' => 'rmm_providers.php', 'icon' => 'fas fa-laptop-medical',
        'one' => 'provider', 'One' => 'Provider', 'full' => 'RMM provider', 'Full' => 'RMM provider', 'kind' => 'RMM',
        'audit' => 'RMM Provider', 'clients' => 'clients', 'Clients' => 'Clients', 'client' => 'client', 'Client' => 'Client',
    ];
}

/** A provider's kind - null when ITFlow doesn't know it. */
function rmmKind($provider): ?array
{
    $kind = is_array($provider) ? (string) ($provider['rmm_provider_kind'] ?? '') : (string) $provider;
    return rmmProviderKinds()[$kind] ?? null;
}

/** A provider's name, for the places it is spoken of - the kind's when it hasn't one. */
function rmmProviderName(array $provider): string
{
    $name = trim((string) ($provider['rmm_provider_name'] ?? ''));
    return $name !== '' ? $name : (rmmKind($provider)['name'] ?? 'RMM');
}

/** Whether the RMM tables are there - they arrive with the 3.6.7 database update. */
function rmmTablesReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        try {
            mysqli_query($mysqli, "SELECT 1 FROM rmm_devices LIMIT 1");
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** One provider by id - raw, or null. */
function rmmProviderRow($provider_id): ?array
{
    global $mysqli;
    $provider_id = intval($provider_id);
    if (!$provider_id || !rmmTablesReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_providers WHERE rmm_provider_id = $provider_id LIMIT 1"));
    return $row ?: null;
}

/** Every provider set up, on or off - by name. Read once a request. */
function rmmProviders(bool $fresh = false): array
{
    global $mysqli;
    static $providers = null;
    if ($providers === null || $fresh) {
        $providers = [];
        if (rmmTablesReady()) {
            $sql = mysqli_query($mysqli, "SELECT * FROM rmm_providers ORDER BY rmm_provider_name ASC, rmm_provider_id ASC");
            while ($row = mysqli_fetch_assoc($sql)) {
                $providers[] = $row;
            }
        }
    }
    return $providers;
}

/** The providers of one family (rmmFamily()) - rmm for the RMMs, network for the network platforms. */
function rmmFamilyProviders(string $family): array
{
    return array_values(array_filter(rmmProviders(), function ($provider) use ($family) {
        return rmmFamily($provider) === $family;
    }));
}

/** Whether a provider can be asked - on, of a kind ITFlow knows, with what its kind needs. */
function rmmProviderUsable(?array $provider): bool
{
    $kind = $provider ? rmmKind($provider) : null;
    if (!$kind || intval($provider['rmm_provider_active']) !== 1) {
        return false;
    }
    foreach ($kind['fields'] as $field => $about) {
        if (!empty($about['required']) && $about['type'] !== 'switch' && trim((string) ($provider["rmm_provider_$field"] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

/**
 * A provider's secret, as typed - its api_key or api_secret. A kind that seals them (seal) keeps
 * them encrypted with this install's key (backupEncryptionKey(), in config.php); one saved before
 * it did is read as it is. '' when it can't be read - config.php's key was changed.
 */
function rmmSecret(array $provider, string $field): string
{
    $stored = (string) ($provider["rmm_provider_$field"] ?? '');
    if (!str_starts_with($stored, 'enc1:')) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 5), true);
    $key = rmmSealKey();
    if ($raw === false || strlen($raw) < 29 || $key === '') {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

/** A secret encrypted for keeping (rmmSecret() reads it) - '' when this install has no key to do it with. */
function rmmSecretSeal(string $plain): string
{
    $key = rmmSealKey();
    if ($key === '' || $plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    return $cipher === false ? '' : 'enc1:' . base64_encode($iv . $tag . $cipher);
}

/** The key secrets are sealed with - from this install's key, so a backup restored with it reads them. */
function rmmSealKey(): string
{
    $install = function_exists('backupEncryptionKey') ? backupEncryptionKey() : '';
    return $install === '' ? '' : hash('sha256', "itflow-rmm-secrets|$install", true);
}

/** An address typed in, tidied - https:// in front when there is no scheme, no trailing slash. '' when it isn't a web address. */
function rmmUrlClean($url): string
{
    $url = trim((string) $url);
    if ($url === '' || preg_match('/\s/', $url)) {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) && !preg_match('#^https?://#i', $url)) {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    return rtrim($url, '/');
}

/** A provider's dashboard - where Open in its RMM goes. '' when it hasn't one. */
function rmmDashboardUrl(array $provider): string
{
    $kind = rmmKind($provider);
    if ($kind && isset($kind['dashboard'])) {
        return (string) call_user_func($kind['dashboard'], $provider);
    }
    return rmmUrlClean($provider['rmm_provider_web_url'] ?? '');
}

/** A device's pages in its RMM - links key => address. $row is its rmm_devices row with its provider's columns. */
function rmmDeviceUrls(array $row): array
{
    $kind = rmmKind($row);
    $remote_id = (string) ($row['rmm_device_remote_id'] ?? '');
    if (!$kind || $remote_id === '' || !isset($kind['device_urls'])) {
        return [];
    }
    return array_filter((array) call_user_func($kind['device_urls'], $row, $remote_id), 'strlen');
}

/**
 * Ask an RMM's API - ['ok', 'code', 'json', 'error', 'headers']: error is what to tell a person
 * when it didn't work, with the kind's hints. The kind's file says where and how to sign in.
 */
function rmmHttp(array $provider, string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30, array $opts = []): array
{
    $name = rmmProviderName($provider);
    $hints = rmmKind($provider)['hints'] ?? [];
    $hint = function ($key) use ($hints) {
        return isset($hints[$key]) ? ' - ' . $hints[$key] : '';
    };

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'ITFlow',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    // $opts: insecure - accept a certificate the server made for itself; cookie - the Cookie header to send
    if (!empty($opts['insecure'])) {
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    if (!empty($opts['cookie'])) {
        $options[CURLOPT_COOKIE] = (string) $opts['cookie'];
    }
    // The answer's headers - name (lower case) => its last value, and cookies => each Set-Cookie's name=value
    $answer_headers = ['cookies' => []];
    $options[CURLOPT_HEADERFUNCTION] = function ($curl, $line) use (&$answer_headers) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $header = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($header === 'set-cookie') {
                $answer_headers['cookies'][] = trim(explode(';', $value, 2)[0]);
            } else {
                $answer_headers[$header] = $value;
            }
        }
        return strlen($line);
    };
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $transport = curl_error($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    $host = (string) parse_url($url, PHP_URL_HOST);
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($raw === false) {
        $ssl = stripos($transport, 'SSL') !== false && stripos($url, 'https://') === 0 ? ($hint('certificate') ?: ' - if it isn\'t on https, put http:// in front of the address') : '';
        return ['ok' => false, 'code' => 0, 'json' => null, 'error' => "Couldn't reach $host - $transport$ssl", 'headers' => []];
    }
    $json = json_decode((string) $raw, true);
    if ($code === 401 || $code === 403) {
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "$name turned the key away (HTTP $code)" . ($hint('auth') ?: ' - check the key'), 'headers' => $answer_headers];
    }
    if ($code >= 300 && $code < 400) {
        return ['ok' => false, 'code' => $code, 'json' => null, 'error' => "$host sent ITFlow somewhere else (HTTP $code) - use the API's own address, with https if it has it.", 'headers' => $answer_headers];
    }
    if ($code === 404) {
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "Nothing at $path on $host (HTTP 404)" . $hint('not_found'), 'headers' => $answer_headers];
    }
    if ($code < 200 || $code > 299) {
        $detail = is_array($json) ? rmmText($json['detail'] ?? $json['error'] ?? $json['message'] ?? '', 300) : '';
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "$name answered HTTP $code" . ($detail !== '' ? " - $detail" : ''), 'headers' => $answer_headers];
    }
    if (!is_array($json)) {
        return ['ok' => false, 'code' => $code, 'json' => null, 'error' => "$host answered, but not as $name's API" . $hint('not_json'), 'headers' => $answer_headers];
    }
    return ['ok' => true, 'code' => $code, 'json' => $json, 'error' => '', 'headers' => $answer_headers];
}

/**
 * Is what an RMM answered a list - [{...}, {...}] - and not an object? An object where a list
 * belongs is an error page or an API that has changed, never "it has none": read as none, every
 * device or client would be marked gone.
 */
function rmmIsList($value): bool
{
    return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
}

/** A provider's clients, from its RMM - ['ok', 'clients' => [id, name, sites, devices], 'error']. */
function rmmRemoteClients(array $provider): array
{
    $kind = rmmKind($provider);
    if (!$kind) {
        return ['ok' => false, 'clients' => [], 'error' => 'ITFlow doesn\'t know this kind of RMM.'];
    }
    return call_user_func($kind['clients'], $provider);
}

/** One client's devices, from its RMM - ['ok', 'devices', 'error']. */
function rmmRemoteDevices(array $provider, string $client_remote_id): array
{
    $kind = rmmKind($provider);
    if (!$kind) {
        return ['ok' => false, 'devices' => [], 'error' => 'ITFlow doesn\'t know this kind of RMM.'];
    }
    return call_user_func($kind['devices'], $provider, $client_remote_id);
}

/** Try a provider - ['ok', 'text']: how many clients and devices it sees, or what went wrong. */
function rmmTest(array $provider): array
{
    $answer = rmmRemoteClients($provider);
    if (!$answer['ok']) {
        return ['ok' => false, 'text' => $answer['error']];
    }
    $devices = array_sum(array_column($answer['clients'], 'devices'));
    $clients = count($answer['clients']);
    $words = rmmFamilyWords(rmmFamily($provider));
    // A kind can say more - which API it reached
    $more = isset($answer['about']) && $answer['about'] !== '' ? ' ' . $answer['about'] : '';
    return ['ok' => true, 'text' => 'Connected - ' . rmmProviderName($provider) . ' has ' . number_format($clients) . ' ' . $words['client'] . ($clients === 1 ? '' : 's')
        . ' and ' . number_format($devices) . ' device' . ($devices === 1 ? '' : 's') . ' ' . (rmmFamily($provider) === 'network' ? 'it can see' : 'this key can see') . '.' . $more];
}

/*
 * ------------------------------------------------------------------ Reading what an RMM says
 */

/** An RMM's words for "it couldn't tell" - not worth keeping. */
function rmmIsPlaceholder($value): bool
{
    $value = strtolower(trim((string) $value));
    if ($value === '' || $value === '-' || $value === 'none' || $value === 'null' || $value === 'n/a') {
        return true;
    }
    foreach (['unknown ', 'error getting', 'to be filled', 'default string', 'system serial number', 'not specified', 'not applicable', 'chassis serial', 'requires agent'] as $words) {
        if (str_contains($value, $words)) {
            return true;
        }
    }
    return preg_match('/^(0+|1234567890|123456789|x+|\.+|o\.e\.m\.?)$/', $value) === 1;
}

/**
 * Text from an RMM as ITFlow keeps it - what escapeSql() would store (no tags), trimmed and
 * cut to the column. Kept this way before it is compared with what is stored, so a value is
 * the same on both sides and isn't written again every sync.
 */
function rmmText($value, int $max = 255): string
{
    $value = (string) (is_scalar($value) ? $value : '');
    if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
    return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($value))), 0, $max);
}

/** A serial number worth matching on - '' when it is blank or a placeholder. */
function rmmSerial($serial): string
{
    $serial = rmmText($serial, 200);
    return rmmIsPlaceholder($serial) ? '' : $serial;
}

/** The first address worth showing from a list - not a link-local or loopback one. */
function rmmFirstIp($local_ips): string
{
    foreach (preg_split('/[\s,]+/', (string) $local_ips) as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '169.254.') && !str_starts_with($ip, '127.')) {
            return $ip;
        }
    }
    return '';
}

/** A time from an RMM (ISO 8601, or seconds since 1970) as one in ITFlow's timezone - null when there isn't one. */
function rmmTime($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    try {
        if (is_numeric($value)) {
            $time = new DateTime('@' . intval($value));
        } else {
            $time = new DateTime((string) $value);
        }
        $time->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $time->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/** What kind of asset a device is - from whether the RMM watches it as a server, and its make and model. */
function rmmDeviceType(string $role, string $make, string $model): string
{
    $both = strtolower("$make $model");
    foreach (['vmware', 'virtual machine', 'virtualbox', 'qemu', 'kvm', 'xen', 'bochs', 'parallels', 'proxmox', 'hvm domu', 'standard pc (', 'google compute', 'amazon', 'nutanix', 'openstack', 'cloud server'] as $word) {
        if (str_contains($both, $word)) {
            return 'Virtual Machine';
        }
    }
    if ($role === 'server') {
        return 'Server';
    }
    foreach (['latitude', 'thinkpad', 'thinkbook', 'ideapad', 'yoga', 'elitebook', 'probook', 'zbook', 'xps 1', 'xps 9', 'surface laptop', 'surface pro', 'surface book', 'surface go',
        'macbook', 'zenbook', 'vivobook', 'expertbook', 'travelmate', 'aspire 3', 'aspire 5', 'aspire 7', 'swift', 'spin ', 'chromebook', 'dynabook', 'tecra', 'portege', 'satellite',
        'toughbook', 'getac', 'galaxy book', 'spectre', 'envy x360', 'pavilion x360', 'dragonfly', 'legion 5', 'legion 7', 'precision 35', 'precision 55', 'precision 75',
        'vostro 14', 'vostro 15', 'vostro 35', 'inspiron 13', 'inspiron 14', 'inspiron 15', 'inspiron 16', 'notebook', 'laptop'] as $word) {
        if (str_contains($both, $word)) {
            return 'Laptop';
        }
    }
    return 'Desktop';
}

/*
 * ------------------------------------------------------------------ A device and its asset
 *
 * A device, as a kind's devices function gives it:
 *   remote_id                        - its id in the RMM (letters, digits, _ . : -)
 *   hostname, site, os, logged_user, public_ip, local_ips, agent_version - text, as rmmText() keeps it
 *   role                             - server or workstation, '' when the RMM doesn't say
 *   platform                         - windows, linux, darwin...
 *   status                           - online, offline or overdue, '' when it doesn't say
 *   last_seen_at, boot_at            - from rmmTime(), or null
 *   needs_reboot, patches_pending, maintenance - 1 or 0
 *   checks_total, checks_failing     - numbers
 *   mac                              - its MAC address, any separators - '' when the kind doesn't say
 *   asset                            - what its asset is: make, model, serial, os, ip, cpu,
 *                                      storage, mac - '' where the RMM doesn't say - and type
 *                                      when the kind knows better than rmmDeviceType(). One with
 *                                      a MAC address is matched to the asset that has it first
 */

/** The rmm_devices columns a device's facts go into, as they are to be saved. */
function rmmDeviceFacts(array $device): array
{
    $text = function ($key, $max = 255) use ($device) {
        return rmmText($device[$key] ?? '', $max);
    };
    $status = strtolower((string) ($device['status'] ?? ''));
    $role = strtolower((string) ($device['role'] ?? ''));
    return [
        'hostname' => $text('hostname'),
        'site' => $text('site'),
        'role' => in_array($role, ['server', 'workstation'], true) ? $role : '',
        'platform' => mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($device['platform'] ?? ''))), 0, 20),
        'os' => $text('os'),
        'status' => in_array($status, ['online', 'offline', 'overdue'], true) ? $status : '',
        'last_seen_at' => $device['last_seen_at'] ?? null,
        'boot_at' => $device['boot_at'] ?? null,
        'logged_user' => $text('logged_user'),
        'public_ip' => $text('public_ip', 100),
        'local_ips' => $text('local_ips', 500),
        'needs_reboot' => !empty($device['needs_reboot']) ? 1 : 0,
        'patches_pending' => !empty($device['patches_pending']) ? 1 : 0,
        'checks_total' => intval($device['checks_total'] ?? 0),
        'checks_failing' => intval($device['checks_failing'] ?? 0),
        'maintenance' => !empty($device['maintenance']) ? 1 : 0,
        'agent_version' => $text('agent_version', 50),
    ] + (rmmNetworkReady() ? ['mac' => rmmMac($device['mac'] ?? '')] : []);
}

/** A MAC address as 12 lower-case hex digits - '' when it isn't one. */
function rmmMac($raw): string
{
    $mac = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', (string) $raw));
    return strlen($mac) === 12 && $mac !== '000000000000' && $mac !== 'ffffffffffff' ? $mac : '';
}

/** A MAC address the way ITFlow's interfaces keep it - "F4:E2:C6:12:34:56" - '' when it isn't one. */
function rmmMacText($raw): string
{
    $mac = rmmMac($raw);
    return $mac === '' ? '' : strtoupper(implode(':', str_split($mac, 2)));
}

/** The location of an ITFlow client a site in the RMM is - the one with its name - or 0. */
function rmmSiteLocationId(int $client_id, string $site): int
{
    global $mysqli;
    static $locations = [];
    if ($site === '') {
        return 0;
    }
    if (!isset($locations[$client_id])) {
        $locations[$client_id] = [];
        $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL");
        while ($row = mysqli_fetch_assoc($sql)) {
            $locations[$client_id][mb_strtolower(trim($row['location_name']))][] = intval($row['location_id']);
        }
    }
    $found = $locations[$client_id][mb_strtolower(trim($site))] ?? [];
    return count($found) === 1 ? $found[0] : 0;
}

/** What a device's asset is, by its RMM - field => value. '' (0 for the location) where the RMM doesn't say. */
function rmmDeviceAssetValues(array $device, array $facts, int $client_id): array
{
    $asset = is_array($device['asset'] ?? null) ? $device['asset'] : [];
    $value = function ($key, $max = 200) use ($asset) {
        $text = rmmText($asset[$key] ?? '', $max);
        return rmmIsPlaceholder($text) ? '' : $text;
    };
    $make = $value('make');
    $model = $value('model');
    $type = $value('type', 100);
    return [
        'name' => mb_substr($facts['hostname'], 0, 200),
        'type' => $type !== '' ? $type : rmmDeviceType($facts['role'], $make, $model),
        'make' => $make,
        'model' => $model,
        'serial' => rmmSerial($asset['serial'] ?? ''),
        'os' => $value('os'),
        'ip' => rmmFirstIp($asset['ip'] ?? ''),
        // Details are 255 characters (asset_specs) - a server's eight disks are more
        'cpu' => $value('cpu', 255),
        'storage' => $value('storage', 255),
        'mac' => rmmMacText($asset['mac'] ?? ''),
        'location_id' => rmmSiteLocationId($client_id, $facts['site']),
    ];
}

/** The asset fields the sync keeps up, as people read them. */
function rmmAssetFieldLabels(): array
{
    return ['name' => 'name', 'type' => 'type', 'make' => 'make', 'model' => 'model', 'serial' => 'serial number', 'os' => 'OS', 'ip' => 'IP',
        'mac' => 'MAC address', 'cpu' => 'CPU', 'storage' => 'storage', 'location_id' => 'location'];
}

/** An asset's present values, for the fields the sync keeps up - null when the asset is gone. */
function rmmAssetCurrent(int $asset_id): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_os, asset_location_id,
        asset_status, asset_client_id, asset_archived_at, interface_id, interface_ip, interface_mac FROM assets
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_id = $asset_id LIMIT 1"));
    if (!$row) {
        return null;
    }
    $specs = function_exists('assetSpecsReady') && assetSpecsReady() ? assetSpecs($asset_id) : [];
    return [
        'name' => (string) $row['asset_name'],
        'type' => (string) $row['asset_type'],
        'make' => (string) $row['asset_make'],
        'model' => (string) $row['asset_model'],
        'serial' => (string) $row['asset_serial'],
        'os' => (string) $row['asset_os'],
        'ip' => (string) $row['interface_ip'],
        'mac' => (string) $row['interface_mac'],
        'cpu' => (string) ($specs['cpu'] ?? ''),
        'storage' => (string) ($specs['storage'] ?? ''),
        'location_id' => intval($row['asset_location_id']),
        '_interface_id' => intval($row['interface_id']),
        '_status' => (string) $row['asset_status'],
        '_client_id' => intval($row['asset_client_id']),
    ];
}

/** Write fields to an asset - field => value, for the fields rmmAssetFieldLabels() names. */
function rmmAssetWrite(int $asset_id, array $set, array $current): void
{
    global $mysqli;
    $columns = ['name' => 'asset_name', 'type' => 'asset_type', 'make' => 'asset_make', 'model' => 'asset_model', 'serial' => 'asset_serial', 'os' => 'asset_os'];
    $sets = [];
    foreach ($columns as $field => $column) {
        if (array_key_exists($field, $set)) {
            $sets[] = "$column = '" . escapeSql($set[$field]) . "'";
        }
    }
    if (array_key_exists('location_id', $set)) {
        $sets[] = 'asset_location_id = ' . intval($set['location_id']);
    }
    if ($sets) {
        mysqli_query($mysqli, "UPDATE assets SET " . implode(', ', $sets) . " WHERE asset_id = $asset_id");
    }

    // Its IP and MAC address are its primary interface's
    $interface = [];
    foreach (['ip' => 'interface_ip', 'mac' => 'interface_mac'] as $field => $column) {
        if (array_key_exists($field, $set)) {
            $interface[] = "$column = '" . escapeSql($set[$field]) . "'";
        }
    }
    if ($interface) {
        if ($current['_interface_id']) {
            mysqli_query($mysqli, "UPDATE asset_interfaces SET " . implode(', ', $interface) . " WHERE interface_id = " . intval($current['_interface_id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', " . implode(', ', $interface) . ", interface_primary = 1, interface_asset_id = $asset_id");
        }
    }

    // CPU and storage are details of the asset's type (functions/asset_type.php) - only for a type that has them
    $type = $set['type'] ?? $current['type'];
    $allowed = function_exists('assetTypeSpecs') ? array_keys(assetTypeSpecs($type)) : [];
    foreach (['cpu', 'storage'] as $key) {
        if (array_key_exists($key, $set) && in_array($key, $allowed, true) && function_exists('assetSpecsReady') && assetSpecsReady()) {
            $value = escapeSql(mb_substr((string) $set[$key], 0, 255));
            mysqli_query($mysqli, "INSERT INTO asset_specs SET asset_spec_asset_id = $asset_id, asset_spec_key = '$key', asset_spec_value = '$value'
                ON DUPLICATE KEY UPDATE asset_spec_value = VALUES(asset_spec_value)");
        }
    }
}

/**
 * Bring an asset up to what its RMM says - the follow rule: a field is written when it is
 * empty, or still holds what the sync last wrote; one changed in ITFlow is left. A blank from
 * the RMM writes nothing. [$changed labels, $synced] - $synced is what to keep as last written.
 */
function rmmAssetFollow(int $asset_id, array $values, array $synced_before): array
{
    $current = rmmAssetCurrent($asset_id);
    if (!$current) {
        return [[], $synced_before];
    }
    $labels = rmmAssetFieldLabels();
    $set = [];
    $synced = [];
    foreach ($values as $field => $new) {
        if (!isset($labels[$field])) {
            continue;
        }
        // CPU and storage are details only some types have - none for a VM's CPU, or a type that has neither
        if (in_array($field, ['cpu', 'storage'], true) && !in_array($field, array_keys(function_exists('assetTypeSpecs') ? assetTypeSpecs($set['type'] ?? $current['type']) : []), true)) {
            continue;
        }
        $new = $field === 'location_id' ? intval($new) : (string) $new;
        $had = $field === 'location_id' ? intval($current[$field]) : (string) $current[$field];
        $was_ours = array_key_exists($field, $synced_before) && (string) $synced_before[$field] === (string) $had;
        if ($new === '' || $new === 0) {
            // The RMM doesn't say - nothing to write; what was ours stays ours
            if ($was_ours) {
                $synced[$field] = $synced_before[$field];
            }
            continue;
        }
        if ((string) $had === (string) $new) {
            $synced[$field] = $new;
            continue;
        }
        if ($had === '' || $had === 0 || $was_ours) {
            $set[$field] = $new;
            $synced[$field] = $new;
            continue;
        }
        // Changed in ITFlow - left as it is, and still not ours next time
        if (array_key_exists($field, $synced_before)) {
            $synced[$field] = $synced_before[$field];
        }
    }
    if ($set) {
        rmmAssetWrite($asset_id, $set, $current);
    }
    return [array_values(array_intersect_key($labels, $set)), $synced];
}

/** A line in an asset's history, as the provider that wrote it - "Tactical RMM changed its OS". */
function rmmAssetHistory(int $asset_id, string $who, string $what): void
{
    global $mysqli;
    $status = escapeSql((string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT asset_status FROM assets WHERE asset_id = $asset_id"))[0] ?? ''));
    $what = escapeSql(mb_substr("$who $what", 0, 255));
    mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = '$what', asset_history_asset_id = $asset_id");
}

/** A new asset for a device - its id. */
function rmmAssetCreate(int $client_id, array $values): int
{
    global $mysqli;
    $name = escapeSql($values['name'] !== '' ? $values['name'] : 'Unnamed device');
    $type = escapeSql($values['type']);
    $location_id = intval($values['location_id']);
    mysqli_query($mysqli, "INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '', asset_status = 'Deployed',
        asset_location_id = $location_id, asset_client_id = $client_id");
    return intval(mysqli_insert_id($mysqli));
}

/**
 * An asset already in ITFlow that is this device - same MAC address, same serial number, or the
 * only one with its name - and not another of this provider's devices already. One it no longer has doesn't
 * keep its asset: an agent reinstalled is a new device there. 0 when there is none.
 */
function rmmAssetMatch(int $provider_id, int $client_id, array $values, array $free = []): int
{
    global $mysqli;
    // $free - devices it didn't list this time, kept for now (RMM_GONE_AT_ONCE): they don't hold their assets either
    $free_sql = $free ? ' AND rmm_device_id NOT IN (' . implode(', ', array_map('intval', $free)) . ')' : '';
    $taken = "asset_id NOT IN (SELECT rmm_device_asset_id FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id > 0 AND rmm_device_removed_at IS NULL$free_sql)";
    $mac = rmmMac($values['mac'] ?? '');
    if ($mac !== '') {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT asset_id FROM assets JOIN asset_interfaces ON interface_asset_id = asset_id
            WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND $taken
            AND LOWER(REPLACE(REPLACE(REPLACE(interface_mac, ':', ''), '-', ''), '.', '')) = '$mac'
            ORDER BY interface_primary DESC, asset_id ASC LIMIT 1"));
        if ($row) {
            return intval($row[0]);
        }
    }
    if ($values['serial'] !== '') {
        $serial = escapeSql($values['serial']);
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
            AND asset_serial = '$serial' AND $taken ORDER BY asset_id ASC LIMIT 1"));
        if ($row) {
            return intval($row[0]);
        }
    }
    if ($values['name'] !== '') {
        $name = escapeSql($values['name']);
        $sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
            AND asset_name = '$name' AND $taken LIMIT 2");
        if (mysqli_num_rows($sql) === 1) {
            return intval(mysqli_fetch_row($sql)[0]);
        }
    }
    return 0;
}

/** A device's rmm_devices row by its id in the RMM - raw, or null. */
function rmmDeviceRow(int $provider_id, string $remote_id): ?array
{
    global $mysqli;
    $remote = escapeSql($remote_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_remote_id = '$remote' LIMIT 1"));
    return $row ?: null;
}

/**
 * One device from an RMM, brought into ITFlow - its asset made, matched or kept up to date,
 * and what it is doing saved. What happened goes into $counts. $free are the client's devices
 * the RMM didn't list this time but that are kept for now - a reinstalled agent can have the
 * asset of one. Its id in the RMM, or '' when it hasn't got one ITFlow can keep.
 */
function rmmDeviceSave(array $provider, array $rmm_client, int $client_id, array $device, array &$counts, array $free = []): string
{
    global $mysqli, $session_name;

    $provider_id = intval($provider['rmm_provider_id']);
    $who = rmmProviderName($provider);
    $remote_id = (string) ($device['remote_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $remote_id)) {
        $counts['skipped']++;
        return '';
    }
    $facts = rmmDeviceFacts($device);
    $values = rmmDeviceAssetValues($device, $facts, $client_id);
    // The asset type the provider's Edit has this kind of device come in as
    $values['type'] = rmmProviderAssetTypes($provider)[$values['type']] ?? $values['type'];
    $row = rmmDeviceRow($provider_id, $remote_id);
    $synced_before = [];
    $asset_id = 0;
    $is_new_row = $row === null;
    $find = $is_new_row;
    $moved = false;

    if ($row) {
        $synced_before = json_decode((string) $row['rmm_device_synced'], true) ?: [];
        $asset_id = intval($row['rmm_device_asset_id']);
        $current = $asset_id ? rmmAssetCurrent($asset_id) : null;
        if ($asset_id && !$current) {
            // Deleted in ITFlow - not made again until Bring back
            $asset_id = 0;
        } elseif ($current && $current['_client_id'] !== $client_id) {
            // Moved to another client in the RMM - its asset stays with the client it was at, and it is matched or made at this one
            rmmAssetHistory($asset_id, $who, 'no longer keeps it up - it moved to ' . $rmm_client['rmm_client_name'] . ' there');
            $asset_id = 0;
            $synced_before = [];
            $find = true;
            $moved = true;
        }
    }

    $made = false;
    $matched = false;
    if ($find) {
        $asset_id = rmmAssetMatch($provider_id, $client_id, $values, $free);
        if ($asset_id) {
            $matched = true;
            $counts['linked']++;
            // The device it was before - an agent since reinstalled - is gone there; this one has its asset now
            $free_sql = $free ? ' OR rmm_device_id IN (' . implode(', ', array_map('intval', $free)) . ')' : '';
            mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id = $asset_id
                AND (rmm_device_removed_at IS NOT NULL$free_sql) AND rmm_device_remote_id != '" . escapeSql($remote_id) . "'");
        } else {
            $asset_id = rmmAssetCreate($client_id, $values);
            $made = true;
            $counts['created']++;
        }
    }

    $changed = [];
    if ($asset_id) {
        [$changed, $synced] = rmmAssetFollow($asset_id, $values, $made ? array_fill_keys(array_keys(rmmAssetFieldLabels()), '') : $synced_before);
        // A network platform's devices have firmware, not an OS, and come from one of its sites
        $network = rmmFamily($provider) === 'network';
        if ($network) {
            $changed = array_map(function ($label) {
                return $label === 'OS' ? 'firmware' : $label;
            }, $changed);
        }
        if ($made) {
            rmmAssetHistory($asset_id, $who, 'brought it in from ' . ($network ? 'its site ' : ($facts['site'] !== '' ? 'site ' . $facts['site'] . ' of ' : '')) . $rmm_client['rmm_client_name']);
            logAudit('Asset', 'Create', "$session_name brought in asset " . escapeSql($values['name']) . ' from ' . escapeSql($rmm_client['rmm_client_name']), $client_id, $asset_id);
        } elseif ($matched) {
            rmmAssetHistory($asset_id, $who, 'linked it to its device there, ' . $facts['hostname'] . ($changed ? ', and filled in its ' . implode(', ', $changed) : ''));
        } elseif ($changed) {
            $counts['updated']++;
            rmmAssetHistory($asset_id, $who, 'changed its ' . implode(', ', $changed));
        }
    } else {
        $synced = $synced_before;
    }

    if ($row && $row['rmm_device_removed_at'] !== null && !$moved) {
        $counts['returned']++;
        if ($asset_id) {
            rmmAssetHistory($asset_id, $who, 'has it again');
        }
    }

    $sql_facts = [];
    foreach ($facts as $field => $value) {
        $sql_facts[] = "rmm_device_$field = " . ($value === null ? 'NULL' : (is_int($value) ? $value : "'" . escapeSql($value) . "'"));
    }
    $sql_facts = implode(', ', $sql_facts);
    $synced_sql = escapeSql(json_encode($synced, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
    $rmm_client_row_id = intval($rmm_client['rmm_client_id']);

    if ($is_new_row) {
        $remote = escapeSql($remote_id);
        mysqli_query($mysqli, "INSERT INTO rmm_devices SET rmm_device_provider_id = $provider_id, rmm_device_remote_id = '$remote', rmm_device_asset_id = $asset_id,
            rmm_device_client_id = $client_id, rmm_device_rmm_client_id = $rmm_client_row_id, $sql_facts, rmm_device_synced = '$synced_sql', rmm_device_synced_at = NOW()");
    } else {
        mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_asset_id = $asset_id, rmm_device_client_id = $client_id, rmm_device_rmm_client_id = $rmm_client_row_id,
            $sql_facts, rmm_device_synced = '$synced_sql', rmm_device_synced_at = NOW(), rmm_device_removed_at = NULL WHERE rmm_device_id = " . intval($row['rmm_device_id']));
    }
    $counts['devices']++;
    return $remote_id;
}

/**
 * Forget what the sync knew about one of an RMM's clients' devices - it was unlinked, or linked to
 * another client. Their assets stay as they are, no longer in the RMM, and their history says why;
 * linked again, its devices come in afresh and are matched to them. Returns how many assets stay.
 */
function rmmForgetClientDevices(int $rmm_client_id, string $name, string $why): int
{
    global $mysqli;
    $left = 0;
    $sql = mysqli_query($mysqli, "SELECT rmm_device_asset_id FROM rmm_devices WHERE rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_asset_id > 0");
    while ($device = mysqli_fetch_row($sql)) {
        if (rmmAssetCurrent(intval($device[0]))) {
            rmmAssetHistory(intval($device[0]), $name, "no longer keeps it up - $why");
            $left++;
        }
    }
    mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_rmm_client_id = $rmm_client_id");
    rmmNetworksForget($rmm_client_id);
    return $left;
}

/*
 * ------------------------------------------------------------------ Syncing
 */

/** A provider's rmm_clients rows that are linked to a client ITFlow still has, and that the RMM still has - oldest brought in first. */
function rmmLinkedClients(int $provider_id, int $only = 0): array
{
    global $mysqli;
    $only_sql = $only ? "AND rmm_client_id = $only" : '';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT rmm_clients.*, client_name, client_archived_at FROM rmm_clients
        JOIN clients ON client_id = rmm_client_client_id
        WHERE rmm_client_provider_id = $provider_id AND rmm_client_client_id > 0 AND rmm_client_removed_at IS NULL $only_sql
        ORDER BY rmm_client_synced_at IS NOT NULL, rmm_client_synced_at ASC, rmm_client_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Sync one provider - its clients, then the devices of each linked one. One at a time (a
 * second sync of it started while one runs says so and stops). $only is an rmm_clients id,
 * for bringing in one client's devices straight after it is linked. Stops after $max_seconds
 * and picks up where it left off next time - the clients brought in longest ago go first.
 *
 * Returns ['ok', 'note', 'counts'] - note is what to show a person.
 */
function rmmSync(array $provider, int $only = 0, int $max_seconds = 120): array
{
    global $mysqli, $session_name, $session_user_id, $session_ip, $session_user_agent;

    $counts = ['clients' => 0, 'devices' => 0, 'created' => 0, 'linked' => 0, 'updated' => 0, 'removed' => 0, 'returned' => 0, 'skipped' => 0,
        'networks' => 0, 'networks_created' => 0, 'networks_linked' => 0, 'networks_updated' => 0, 'networks_removed' => 0, 'networks_returned' => 0, 'seen' => 0];
    $name = rmmProviderName($provider);
    if (!rmmProviderUsable($provider)) {
        return ['ok' => false, 'note' => "$name isn't set up, or is turned off.", 'counts' => $counts];
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $short = rmmKind($provider)['short'];

    $lock = "itflow_rmm_sync_$provider_id";
    if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 0)"))[0] ?? 0) !== 1) {
        return ['ok' => false, 'note' => "A sync of $name is running already - give it a minute.", 'counts' => $counts];
    }

    // The audit log and asset history say the provider did it - and, for a Sync now, who asked (log_user_id)
    $signed_in_name = $session_name ?? null;
    $session_name = escapeSql($name);
    $session_user_id = intval($session_user_id ?? 0);
    $session_ip = $session_ip ?? '';
    $session_user_agent = $session_user_agent ?? 'ITFlow cron';

    $clock = microtime(true);
    $errors = [];
    $held = [];       // the clients whose devices went at once, kept as they were: "client: n of m"
    $complete = [];   // rmm_clients id => its name, for each client the RMM no longer has - all its devices are gone
    $stopped = false;

    $in_list = function (array $ids) {
        return $ids ? "'" . implode("', '", array_map('escapeSql', $ids)) . "'" : "''";
    };

    /*
     * A client's devices the RMM didn't list, when its list came back whole - it no longer has them.
     * Most of them at once are kept as they were for now (RMM_GONE_AT_ONCE). The ids of the ones
     * kept - an agent reinstalled there can still be matched to the asset of one of them.
     */
    $remove_unlisted = function (int $rmm_client_id, array $listed) use ($mysqli, $provider_id, $name, $in_list, &$counts): array {
        $rows = [];
        $sql = mysqli_query($mysqli, "SELECT rmm_device_id, rmm_device_asset_id, rmm_device_synced_at FROM rmm_devices WHERE rmm_device_provider_id = $provider_id
            AND rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_removed_at IS NULL AND rmm_device_remote_id NOT IN (" . $in_list($listed) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $rows[] = $row;
        }
        if (!$rows) {
            return [];
        }
        $live = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = $provider_id
            AND rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_removed_at IS NULL"))[0]);
        $at_once = count($rows) >= RMM_GONE_AT_ONCE && count($rows) * 2 >= $live;
        $kept = [];
        foreach ($rows as $row) {
            if ($at_once && $row['rmm_device_synced_at'] !== null && strtotime($row['rmm_device_synced_at']) > time() - RMM_GONE_HOLD_HOURS * 3600) {
                $kept[] = intval($row['rmm_device_id']);
                continue;
            }
            mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_removed_at = NOW() WHERE rmm_device_id = " . intval($row['rmm_device_id']));
            if (intval($row['rmm_device_asset_id']) && rmmAssetCurrent(intval($row['rmm_device_asset_id']))) {
                rmmAssetHistory(intval($row['rmm_device_asset_id']), $name, 'no longer has it');
            }
            $counts['removed']++;
        }
        return $kept;
    };

    // The ones kept that are still there - not taken over by a reinstalled agent - for the note
    $note_kept = function (int $rmm_client_id, string $client_name, array $kept) use ($mysqli, $provider_id, &$held) {
        if (!$kept) {
            return;
        }
        $still = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_id IN (" . implode(', ', $kept) . ")"))[0]);
        if ($still) {
            $live = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = $provider_id
                AND rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_removed_at IS NULL"))[0]);
            $held[] = "$client_name, $still of $live";
        }
    };

    try {
        $answer = rmmRemoteClients($provider);
        if (!$answer['ok']) {
            throw new RuntimeException($answer['error']);
        }

        // Its clients - new ones added, names and counts kept up, the ones it no longer has marked
        foreach ($answer['clients'] as $client) {
            $remote = escapeSql($client['id']);
            $client_name = escapeSql($client['name']);
            mysqli_query($mysqli, "INSERT INTO rmm_clients SET rmm_client_provider_id = $provider_id, rmm_client_remote_id = '$remote', rmm_client_name = '$client_name',
                rmm_client_sites = " . intval($client['sites']) . ", rmm_client_devices = " . intval($client['devices']) . ", rmm_client_seen_at = NOW()
                ON DUPLICATE KEY UPDATE rmm_client_name = VALUES(rmm_client_name), rmm_client_sites = VALUES(rmm_client_sites),
                rmm_client_devices = VALUES(rmm_client_devices), rmm_client_seen_at = NOW(), rmm_client_removed_at = NULL");
        }
        $counts['clients'] = count($answer['clients']);
        if (!$only) {
            $listed = $in_list(array_column($answer['clients'], 'id'));
            // And the ones marked before whose devices were kept, until they are marked too
            $gone = mysqli_query($mysqli, "SELECT rmm_client_id, rmm_client_name, rmm_client_removed_at FROM rmm_clients WHERE rmm_client_provider_id = $provider_id
                AND rmm_client_remote_id NOT IN ($listed) AND (rmm_client_removed_at IS NULL OR rmm_client_id IN (SELECT rmm_device_rmm_client_id FROM rmm_devices
                WHERE rmm_device_provider_id = $provider_id AND rmm_device_removed_at IS NULL))");
            while ($gone_row = mysqli_fetch_assoc($gone)) {
                $complete[intval($gone_row['rmm_client_id'])] = (string) $gone_row['rmm_client_name'];
                if ($gone_row['rmm_client_removed_at'] === null) {
                    mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_removed_at = NOW() WHERE rmm_client_id = " . intval($gone_row['rmm_client_id']));
                }
            }
        }

        // Devices still kept for a client that was unlinked - before 26.12.1 unlinking left them
        $unlinked = mysqli_query($mysqli, "SELECT DISTINCT rmm_device_rmm_client_id FROM rmm_devices
            JOIN rmm_clients ON rmm_clients.rmm_client_id = rmm_device_rmm_client_id AND rmm_client_client_id = 0
            WHERE rmm_device_provider_id = $provider_id");
        while ($unlinked_row = mysqli_fetch_row($unlinked)) {
            rmmForgetClientDevices(intval($unlinked_row[0]), $name, 'its client there was unlinked');
        }

        foreach (rmmLinkedClients($provider_id, $only) as $rmm_client) {
            if (microtime(true) - $clock > $max_seconds) {
                $stopped = true;
                break;
            }
            if ($rmm_client['client_archived_at'] !== null) {
                continue;
            }
            $devices = rmmRemoteDevices($provider, (string) $rmm_client['rmm_client_remote_id']);
            if (!$devices['ok']) {
                $errors[] = $rmm_client['rmm_client_name'] . ': ' . $devices['error'];
                continue;
            }
            // The ones it no longer lists are marked first, so an agent reinstalled there - a new device with the
            // same serial number - is matched to the asset its old one had
            $listed = [];
            foreach ($devices['devices'] as $device) {
                if (is_array($device)) {
                    $listed[] = (string) ($device['remote_id'] ?? '');
                }
            }
            $kept = $remove_unlisted(intval($rmm_client['rmm_client_id']), $listed);
            foreach ($devices['devices'] as $device) {
                if (!is_array($device)) {
                    continue;
                }
                // One device that can't be saved doesn't stop the rest - the note says which
                try {
                    rmmDeviceSave($provider, $rmm_client, intval($rmm_client['rmm_client_client_id']), $device, $counts, $kept);
                } catch (Throwable $e) {
                    $errors[] = $rmm_client['rmm_client_name'] . ': ' . rmmText($device['hostname'] ?? '', 100) . ' - ' . $e->getMessage();
                    $counts['skipped']++;
                }
            }
            $note_kept(intval($rmm_client['rmm_client_id']), (string) $rmm_client['rmm_client_name'], $kept);
            // A network platform's networks and VLANs, as the client's networks (functions/rmm_network.php)
            try {
                $network_error = rmmNetworksSync($provider, $rmm_client, $counts);
            } catch (Throwable $e) {
                $network_error = $e->getMessage();
            }
            if ($network_error !== '') {
                $errors[] = $rmm_client['rmm_client_name'] . ': its networks - ' . $network_error;
            }
            // And where the client's assets plug in
            try {
                $seen_error = rmmSeenSync($provider, $rmm_client, $counts);
            } catch (Throwable $e) {
                $seen_error = $e->getMessage();
            }
            if ($seen_error !== '') {
                $errors[] = $rmm_client['rmm_client_name'] . ': where its devices plug in - ' . $seen_error;
            }
            mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_synced_at = NOW() WHERE rmm_client_id = " . intval($rmm_client['rmm_client_id']));
        }

        // Devices of the clients it no longer has
        foreach ($complete as $rmm_client_id => $client_name) {
            $note_kept($rmm_client_id, $client_name, $remove_unlisted($rmm_client_id, []));
        }

        $note = number_format($counts['devices']) . ' device' . ($counts['devices'] === 1 ? '' : 's') . ' brought in';
        $done = [];
        foreach (['created' => 'new', 'linked' => 'matched to assets already here', 'updated' => 'updated', 'removed' => "no longer in $short", 'returned' => "back in $short"] as $key => $words) {
            if ($counts[$key]) {
                $done[] = number_format($counts[$key]) . " $words";
            }
        }
        $note .= $done ? ' - ' . implode(', ', $done) : '';
        $networks_note = rmmNetworksNote($counts, $short);
        $note .= $networks_note !== '' ? ". $networks_note" : '';
        if ($stopped) {
            $note .= '. Stopped for time - the rest come in next sync';
        }
        if ($held) {
            $note .= ". Not marked gone yet - $short stopped listing most of a client's devices at once, which is more often $short or its API key's access than the devices ("
                . implode('; ', $held) . '). If they are still missing after ' . RMM_GONE_HOLD_HOURS . ' hours, they are marked gone';
        }
        if ($errors) {
            $more = count($errors) > 5 ? ' - and ' . (count($errors) - 5) . ' more' : '';
            $note .= '. Not brought in: ' . implode('; ', array_slice($errors, 0, 5)) . $more;
        }
        if ($held || $errors) {
            logApp('RMM', 'warning', "$name: sync - " . implode('; ', array_merge($held ? ['devices kept as they were: ' . implode('; ', $held)] : [], array_slice($errors, 0, 20))));
        }
        $ok = !$errors && !$held;
    } catch (Throwable $e) {
        $ok = false;
        $note = $e->getMessage();
        logApp('RMM', 'warning', "$name: sync failed - $note");
    }

    $note_sql = escapeSql(mb_substr($note, 0, 500));
    mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_sync_at = NOW(), rmm_provider_sync_ok = " . ($ok ? 1 : 0) . ", rmm_provider_sync_note = '$note_sql' WHERE rmm_provider_id = $provider_id");

    if ($counts['updated'] || $counts['removed'] || $counts['returned']) {
        logAudit(rmmFamilyWords(rmmFamily($provider))['audit'], 'Sync', escapeSql("$name synced devices: $note"), 0, $provider_id);
    }

    mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
    $session_name = $signed_in_name ?? $session_name;

    return ['ok' => $ok, 'note' => $note, 'counts' => $counts];
}

/**
 * Sync every provider that is on - for the RMM Sync job. They share $max_seconds, each picking
 * up next run where it stopped. [provider name => rmmSync()'s answer].
 */
function rmmSyncAll(int $max_seconds = 120): array
{
    $results = [];
    $clock = microtime(true);
    foreach (rmmProviders(true) as $provider) {
        if (!rmmProviderUsable($provider)) {
            continue;
        }
        $left = $max_seconds - intval(microtime(true) - $clock);
        if ($left < 5) {
            $results[rmmProviderName($provider)] = ['ok' => true, 'note' => 'Left for the next run - out of time', 'counts' => []];
            continue;
        }
        $results[rmmProviderName($provider)] = rmmSync($provider, 0, $left);
    }
    return $results;
}

/*
 * ------------------------------------------------------------------ Alerts
 *
 * A provider with alerts turned on (rmm_provider_alert_level) is looked at every minute
 * (cron/rmm_alerts.php). An alert at or above its level, on a device ITFlow has, opens a ticket
 * on the device's client and asset - or, when the same thing on the same device already has a
 * ticket that isn't closed, goes on that one, reopening it if it was resolved. When the RMM no
 * longer lists an alert as open it is asked about that one: once it is cleared, or gone, its
 * ticket gets a note and is resolved - unless someone has written on it, it is in a status
 * someone moved it to, or another of its alerts is still open.
 *
 * The first look takes the alerts already open as known, without tickets - turning alerts on
 * doesn't open one for everything that has been alerting for weeks - and so does an alert
 * raised before its device came into ITFlow.
 *
 * An alert, as a kind's alerts and alert functions give it:
 *   remote_id         - its id in the RMM (letters, digits, _ . : -)
 *   device_remote_id  - its device's id there, as rmm_devices.rmm_device_remote_id has it
 *   type              - availability, check, task...
 *   about             - what on the device it is about, so a repeat finds its ticket - check:12
 *   severity          - error, warning or info
 *   message           - what it says, as rmmText() keeps it
 *   raised_at         - from rmmTime()
 *   snoozed           - true while it is snoozed there: no ticket until it isn't
 *   cleared_at        - from rmmTime() once it is cleared, else null
 */

/** Which alerts open tickets - level => what it is called. */
function rmmAlertLevels(): array
{
    return ['' => 'Off', 'error' => 'Errors', 'warning' => 'Errors and warnings', 'info' => 'Every alert'];
}

/** Whether an alert of $severity is one $level opens a ticket for. */
function rmmAlertSeverityPasses(string $severity, string $level): bool
{
    $rank = ['info' => 1, 'warning' => 2, 'error' => 3];
    return isset($rank[$severity], $rank[$level]) && $rank[$severity] >= $rank[$level];
}

/** The priority an alert's ticket gets. */
function rmmAlertPriority(string $severity): string
{
    return ['error' => 'High', 'warning' => 'Medium', 'info' => 'Low'][$severity] ?? 'Medium';
}

/** Whether the alerts table is there - it arrives with the 3.6.8 database update. */
function rmmAlertsReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        try {
            mysqli_query($mysqli, "SELECT 1 FROM rmm_alerts LIMIT 1");
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** Whether a provider's alerts open tickets - on, its kind able to, a level set. */
function rmmAlertsOn(?array $provider): bool
{
    $kind = $provider ? rmmKind($provider) : null;
    $level = (string) ($provider['rmm_provider_alert_level'] ?? '');
    return $kind && isset($kind['alerts'], $kind['alert']) && rmmProviderUsable($provider) && $level !== '' && isset(rmmAlertLevels()[$level]) && rmmAlertsReady();
}

/** One alert's row by its id in the RMM - raw, or null. */
function rmmAlertRow(int $provider_id, string $remote_id): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_alerts WHERE rmm_alert_provider_id = $provider_id AND rmm_alert_remote_id = '" . escapeSql($remote_id) . "' LIMIT 1"));
    return $row ?: null;
}

/** Keep an alert ITFlow has seen, on $ticket_id (0: none). */
function rmmAlertInsert(int $provider_id, array $device, array $alert, int $ticket_id): void
{
    global $mysqli;
    $raised = $alert['raised_at'] ? "'" . escapeSql($alert['raised_at']) . "'" : 'NULL';
    mysqli_query($mysqli, "INSERT IGNORE INTO rmm_alerts SET rmm_alert_provider_id = $provider_id, rmm_alert_remote_id = '" . escapeSql($alert['remote_id']) . "',
        rmm_alert_device_id = " . intval($device['rmm_device_id']) . ", rmm_alert_asset_id = " . intval($device['rmm_device_asset_id']) . ",
        rmm_alert_client_id = " . intval($device['rmm_device_client_id']) . ", rmm_alert_about = '" . escapeSql(mb_substr((string) $alert['about'], 0, 150)) . "',
        rmm_alert_type = '" . escapeSql(mb_substr((string) $alert['type'], 0, 20)) . "', rmm_alert_severity = '" . escapeSql(mb_substr((string) $alert['severity'], 0, 20)) . "',
        rmm_alert_message = '" . escapeSql((string) $alert['message']) . "', rmm_alert_raised_at = $raised, rmm_alert_ticket_id = $ticket_id");
}

/** An internal note on a ticket, written by ITFlow - $text is plain. */
function rmmTicketNote(int $ticket_id, string $text): void
{
    global $mysqli;
    // HTML, escaped as itself - escapeSql() would take the tags out
    $html = mysqli_real_escape_string($mysqli, '<p>' . nl2br(escapeHtml($text)) . '</p>');
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$html', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");
}

/** Whether someone has written on a ticket - a reply or note that isn't ITFlow's own. */
function rmmTicketWorked(int $ticket_id): bool
{
    global $mysqli;
    return (bool) mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_by > 0 AND ticket_reply_archived_at IS NULL LIMIT 1"));
}

/** What a ticket for an alert says - HTML. */
function rmmAlertDetailsHtml(array $provider, array $device, array $alert): string
{
    $name = rmmProviderName($provider);
    $severities = ['error' => 'Error', 'warning' => 'Warning', 'info' => 'Informational'];
    $types = ['availability' => 'Not checking in', 'check' => 'Check', 'task' => 'Task', 'custom' => 'Custom', 'offline' => 'Offline'];
    $facts = [
        'Severity' => $severities[$alert['severity']] ?? ucfirst((string) $alert['severity']),
        'Kind' => $types[$alert['type']] ?? ucfirst((string) $alert['type']),
        'Raised' => $alert['raised_at'] ? date('M j, Y g:i A', strtotime($alert['raised_at'])) : '',
        'Device' => trim($device['rmm_device_hostname'] . ($device['rmm_device_site'] ? ' (' . $device['rmm_device_site'] . ')' : '')),
    ];
    $html = '<p><strong>' . escapeHtml($alert['message'] !== '' ? $alert['message'] : 'An alert') . '</strong></p><p>';
    foreach (array_filter($facts, 'strlen') as $label => $value) {
        $html .= escapeHtml($label) . ': ' . escapeHtml($value) . '<br>';
    }
    $html .= 'From: ' . escapeHtml($name) . '</p>';
    $urls = rmmDeviceUrls(array_merge($provider, $device));
    if (isset($urls['open'])) {
        $html .= '<p><a href="' . escapeHtml($urls['open']) . '">' . escapeHtml(rmmKind($provider)['links']['open'] ?? "Open in $name") . '</a></p>';
    }
    return $html;
}

/** A ticket for an alert on a device - its id. */
function rmmAlertTicketOpen(array $provider, array $device, array $alert): int
{
    global $mysqli, $session_name;

    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_prefix, config_ticket_default_billable FROM settings WHERE company_id = 1"));
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
    $ticket_number = intval(mysqli_insert_id($mysqli));

    $client_id = intval($device['rmm_device_client_id']);
    // Its asset, when it is still the client's
    $asset_id = intval($device['rmm_device_asset_id']);
    if ($asset_id && intval(getFieldById('assets', $asset_id, 'asset_client_id')) !== $client_id) {
        $asset_id = 0;
    }
    $subject = mb_strimwidth($alert['message'] !== '' ? $alert['message'] : 'Alert on ' . $device['rmm_device_hostname'], 0, 200, '...');
    $prefix = escapeSql((string) $settings['config_ticket_prefix']);
    $billable = intval($settings['config_ticket_default_billable']) ? 1 : 0;
    $category = function_exists('ticketCategoryId') ? ticketCategoryId($provider['rmm_provider_alert_category_id'] ?? 0) : 0;
    $priority = escapeSql(rmmAlertPriority((string) $alert['severity']));
    $details = mysqli_real_escape_string($mysqli, rmmAlertDetailsHtml($provider, $device, $alert));

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = $ticket_number, ticket_source = 'RMM', ticket_category = $category,
        ticket_subject = '" . escapeSql($subject) . "', ticket_details = '$details', ticket_priority = '$priority', ticket_status = " . TICKET_STATUS_NEW . ",
        ticket_billable = $billable, ticket_created_by = 0, ticket_contact_id = 0, ticket_asset_id = $asset_id, ticket_url_key = '" . randomString(32) . "', ticket_client_id = $client_id");
    $ticket_id = intval(mysqli_insert_id($mysqli));
    applyTicketSla($ticket_id);

    $reference = $settings['config_ticket_prefix'] . $ticket_number;
    $host = (string) $device['rmm_device_hostname'];
    logAudit('Ticket', 'Create', "$session_name opened ticket " . escapeSql("$reference for an alert on $host - $subject"), $client_id, $ticket_id);
    logTicketHistory($ticket_id, "Opened by $session_name for an alert on " . escapeSql($host));
    ticketMailNewTicketAddress($ticket_id, 'rmm');
    triggerCustomAction('ticket_create', $ticket_id);
    return $ticket_id;
}

/**
 * A new alert on a device - on the ticket the same thing on it already has, if that isn't
 * closed (reopened if it was resolved), or a new one. Which it was goes into $counts.
 */
function rmmAlertTicket(array $provider, array $device, array $alert, array &$counts): void
{
    global $mysqli;
    $provider_id = intval($provider['rmm_provider_id']);
    $name = rmmProviderName($provider);
    $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rmm_alert_ticket_id FROM rmm_alerts
        JOIN tickets ON ticket_id = rmm_alert_ticket_id
        WHERE rmm_alert_provider_id = $provider_id AND rmm_alert_device_id = " . intval($device['rmm_device_id']) . "
        AND rmm_alert_about = '" . escapeSql(mb_substr((string) $alert['about'], 0, 150)) . "' AND rmm_alert_ticket_id > 0
        AND ticket_status <> " . TICKET_STATUS_CLOSED . " AND ticket_archived_at IS NULL
        ORDER BY rmm_alert_id DESC LIMIT 1"));

    if ($existing) {
        $ticket_id = intval($existing['rmm_alert_ticket_id']);
        rmmAlertInsert($provider_id, $device, $alert, $ticket_id);
        rmmTicketNote($ticket_id, "$name: alerting again - " . ($alert['message'] !== '' ? $alert['message'] : 'the same alert'));
        $ticket = ticketRow($ticket_id);
        if ($ticket && intval($ticket['ticket_status']) === TICKET_STATUS_RESOLVED) {
            ticketReopen($ticket, TICKET_STATUS_OPEN, ' - the alert came back');
        } else {
            logTicketHistory($ticket_id, escapeSql("$name: alerting again"));
        }
        $counts['again']++;
        return;
    }

    $ticket_id = rmmAlertTicketOpen($provider, $device, $alert);
    rmmAlertInsert($provider_id, $device, $alert, $ticket_id);
    $counts['opened']++;
}

/**
 * An alert of a ticket that has cleared - $alert as the RMM has it now, null when it no longer
 * has it. The ticket gets a note, and is resolved when nothing else keeps it open.
 */
function rmmAlertCleared(array $provider, array $row, ?array $alert, array &$counts): void
{
    global $mysqli;
    $name = rmmProviderName($provider);
    $cleared_at = $alert && $alert['cleared_at'] ? "'" . escapeSql($alert['cleared_at']) . "'" : 'NOW()';
    mysqli_query($mysqli, "UPDATE rmm_alerts SET rmm_alert_cleared_at = $cleared_at WHERE rmm_alert_id = " . intval($row['rmm_alert_id']));
    $counts['cleared']++;

    $ticket_id = intval($row['rmm_alert_ticket_id']);
    $ticket = $ticket_id ? ticketRow($ticket_id) : null;
    if (!$ticket || intval($ticket['ticket_status']) === TICKET_STATUS_CLOSED || $ticket['ticket_archived_at'] !== null) {
        return;
    }
    $what = trim((string) $row['rmm_alert_message']);
    rmmTicketNote($ticket_id, "$name: " . ($alert ? 'the alert cleared' : 'it no longer has the alert') . ($what !== '' ? " - $what" : ''));

    $still = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_alerts WHERE rmm_alert_ticket_id = $ticket_id AND rmm_alert_cleared_at IS NULL"))[0]);
    $status = intval($ticket['ticket_status']);
    $left = $still ? "$still more still open" : (!in_array($status, [TICKET_STATUS_NEW, TICKET_STATUS_OPEN], true) ? 'left ' . getTicketStatusName($status)
        : (rmmTicketWorked($ticket_id) ? 'left open, as someone has written on it' : ''));
    if ($left !== '') {
        logTicketHistory($ticket_id, escapeSql("$name: the alert cleared - $left"));
        return;
    }
    ticketResolve($ticket, ' - the alert cleared');
    $counts['resolved']++;
}

/**
 * Look at one provider's alerts - new ones open tickets, cleared ones resolve them. One at a
 * time per provider. Returns ['ok', 'note', 'counts'] - note is what to show a person.
 */
function rmmAlertsPoll(array $provider, int $max_seconds = 50): array
{
    global $mysqli, $session_name, $session_user_id, $session_ip, $session_user_agent;

    $counts = ['opened' => 0, 'again' => 0, 'known' => 0, 'cleared' => 0, 'resolved' => 0];
    $name = rmmProviderName($provider);
    if (!rmmAlertsOn($provider)) {
        return ['ok' => false, 'note' => "$name's alerts don't open tickets.", 'counts' => $counts];
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $kind = rmmKind($provider);
    $level = (string) $provider['rmm_provider_alert_level'];

    $lock = "itflow_rmm_alerts_$provider_id";
    if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 0)"))[0] ?? 0) !== 1) {
        return ['ok' => true, 'note' => 'Still looking from the last run', 'counts' => $counts];
    }

    // Tickets, notes and the audit log say the provider did it
    $signed_in_name = $session_name ?? null;
    $session_name = escapeSql($name);
    $session_user_id = intval($session_user_id ?? 0);
    $session_ip = $session_ip ?? '';
    $session_user_agent = $session_user_agent ?? 'ITFlow cron';

    $clock = microtime(true);
    $first = $provider['rmm_provider_alerts_since'] === null;
    $errors = [];

    try {
        $answer = call_user_func($kind['alerts'], $provider);
        if (!$answer['ok']) {
            throw new RuntimeException($answer['error']);
        }

        $open = [];
        foreach ($answer['alerts'] as $alert) {
            if (!is_array($alert) || !preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', (string) ($alert['remote_id'] ?? ''))) {
                continue;
            }
            $open[] = (string) $alert['remote_id'];
            if (!rmmAlertSeverityPasses((string) $alert['severity'], $level) || rmmAlertRow($provider_id, (string) $alert['remote_id'])) {
                continue;
            }
            // On a device ITFlow has, of a client that is still linked and not archived
            $device = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rmm_devices.* FROM rmm_devices
                JOIN rmm_clients ON rmm_clients.rmm_client_id = rmm_device_rmm_client_id AND rmm_client_client_id = rmm_device_client_id
                JOIN clients ON client_id = rmm_device_client_id AND client_archived_at IS NULL
                WHERE rmm_device_provider_id = $provider_id AND rmm_device_remote_id = '" . escapeSql((string) $alert['device_remote_id']) . "'
                AND rmm_device_removed_at IS NULL AND rmm_device_client_id > 0 LIMIT 1"));
            if (!$device) {
                continue;
            }
            // Open at the first look, or raised before its device came into ITFlow (its client linked since) - known, with no ticket.
            // Anything else it hasn't seen is new: the first look took in all that was open, so no clocks are compared but this one
            $raised = $alert['raised_at'] ? strtotime($alert['raised_at']) : time();
            if ($first || $raised < strtotime((string) $device['rmm_device_created_at'])) {
                rmmAlertInsert($provider_id, $device, $alert, 0);
                $counts['known']++;
                continue;
            }
            if (!empty($alert['snoozed'])) {
                continue;
            }
            rmmAlertTicket($provider, $device, $alert, $counts);
        }
        if ($first) {
            mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_alerts_since = NOW() WHERE rmm_provider_id = $provider_id");
        }

        // The ones still open here that it no longer lists as open - asked about one by one, a few each run
        $in_list = $open ? "'" . implode("', '", array_map('escapeSql', $open)) . "'" : "''";
        $sql = mysqli_query($mysqli, "SELECT * FROM rmm_alerts WHERE rmm_alert_provider_id = $provider_id AND rmm_alert_cleared_at IS NULL
            AND rmm_alert_remote_id NOT IN ($in_list) ORDER BY rmm_alert_id ASC LIMIT 25");
        while ($row = mysqli_fetch_assoc($sql)) {
            if (microtime(true) - $clock > $max_seconds) {
                break;
            }
            $one = call_user_func($kind['alert'], $provider, (string) $row['rmm_alert_remote_id']);
            if (!$one['ok']) {
                $errors[] = $one['error'];
                break;
            }
            if ($one['alert'] === null || $one['alert']['cleared_at'] !== null) {
                rmmAlertCleared($provider, $row, $one['alert'], $counts);
            }
        }

        $done = [];
        foreach (['opened' => 'ticket opened', 'again' => 'added to its ticket', 'cleared' => 'cleared', 'resolved' => 'ticket resolved'] as $key => $words) {
            if ($counts[$key]) {
                $done[] = $counts[$key] . ' ' . ($counts[$key] === 1 ? $words : str_replace(['ticket opened', 'ticket resolved'], ['tickets opened', 'tickets resolved'], $words));
            }
        }
        $note = $first ? number_format($counts['known']) . ' open alert' . ($counts['known'] === 1 ? '' : 's') . ' taken as known - new ones open tickets from now'
            : ($done ? implode(', ', $done) : 'Nothing new');
        if ($errors) {
            throw new RuntimeException(reset($errors));
        }
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
        $note = $e->getMessage();
    }

    // What went wrong is logged once, when it starts going wrong - not every minute it stays so
    $note_before = (string) ($provider['rmm_provider_alerts_note'] ?? '');
    if (!$ok && $note !== $note_before) {
        logApp('RMM', 'warning', "$name: alerts - $note");
    }
    $note_sql = $ok ? 'NULL' : "'" . escapeSql(mb_substr($note, 0, 500)) . "'";
    mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_alerts_at = NOW(), rmm_provider_alerts_note = $note_sql WHERE rmm_provider_id = $provider_id");

    mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
    $session_name = $signed_in_name ?? $session_name;

    return ['ok' => $ok, 'note' => $note, 'counts' => $counts];
}

/** Look at the alerts of every provider whose alerts open tickets - for the RMM Alerts job. [provider name => rmmAlertsPoll()'s answer]. */
function rmmAlertsPollAll(int $max_seconds = 50): array
{
    $results = [];
    $clock = microtime(true);
    foreach (rmmProviders(true) as $provider) {
        if (!rmmAlertsOn($provider)) {
            continue;
        }
        $left = $max_seconds - intval(microtime(true) - $clock);
        if ($left < 5) {
            break;
        }
        $results[rmmProviderName($provider)] = rmmAlertsPoll($provider, $left);
    }
    return $results;
}

/** Alerts cleared more than RMM_ALERTS_KEEP_DAYS ago go - for the nightly tasks. The RMM no longer lists them, and nothing here reads one once it has cleared. */
function rmmAlertsCleanup(): void
{
    global $mysqli;
    if (rmmAlertsReady()) {
        mysqli_query($mysqli, "DELETE FROM rmm_alerts WHERE rmm_alert_cleared_at IS NOT NULL AND rmm_alert_cleared_at < NOW() - INTERVAL " . RMM_ALERTS_KEEP_DAYS . " DAY");
    }
}

/** The alerts still open on some devices that have tickets - rmm_device_id => rows with the ticket's reference and status. */
function rmmOpenAlertsForDevices(array $device_ids): array
{
    global $mysqli;
    $device_ids = array_values(array_filter(array_map('intval', $device_ids)));
    if (!$device_ids || !rmmAlertsReady()) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT rmm_alert_device_id, rmm_alert_severity, rmm_alert_message, rmm_alert_raised_at, ticket_id, ticket_prefix, ticket_number, ticket_client_id
        FROM rmm_alerts JOIN tickets ON ticket_id = rmm_alert_ticket_id
        WHERE rmm_alert_device_id IN (" . implode(', ', $device_ids) . ") AND rmm_alert_cleared_at IS NULL AND rmm_alert_ticket_id > 0
        ORDER BY rmm_alert_raised_at DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['rmm_alert_device_id'])][] = $row;
    }
    return $rows;
}

/*
 * ------------------------------------------------------------------ Showing it
 */

/**
 * Devices from an RMM for some assets - asset_id => their rmm_devices rows with their
 * provider's columns, the ones still in their RMM first, then the one seen last first.
 */
function rmmDevicesForAssets(array $asset_ids): array
{
    global $mysqli;
    $asset_ids = array_values(array_filter(array_map('intval', $asset_ids)));
    if (!$asset_ids || !rmmTablesReady()) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT rmm_devices.*, rmm_providers.*, rmm_client_name, rmm_client_client_id FROM rmm_devices
        JOIN rmm_providers ON rmm_provider_id = rmm_device_provider_id
        LEFT JOIN rmm_clients ON rmm_clients.rmm_client_id = rmm_device_rmm_client_id
        WHERE rmm_device_asset_id IN (" . implode(', ', $asset_ids) . ")
        ORDER BY rmm_device_removed_at IS NOT NULL, rmm_device_last_seen_at IS NULL, rmm_device_last_seen_at DESC, rmm_device_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['rmm_device_asset_id'])][] = $row;
    }
    return $rows;
}

/** The devices from an RMM one asset is - most likely to matter first. */
function rmmDevicesForAsset(int $asset_id): array
{
    return rmmDevicesForAssets([$asset_id])[$asset_id] ?? [];
}

/**
 * How a device is, in a word - ['key', 'label', 'tone', 'title']: tone is a Bootstrap colour.
 * Gone from its RMM says so; a sync that hasn't run for an hour says what it last knew.
 */
function rmmDeviceState(array $row): array
{
    $name = rmmProviderName($row);
    $seen = $row['rmm_device_last_seen_at'] ? 'Last seen ' . timeAgo($row['rmm_device_last_seen_at']) : 'Never seen';
    if ($row['rmm_device_removed_at'] !== null) {
        return ['key' => 'removed', 'label' => "No longer in $name", 'tone' => 'secondary', 'title' => "$name stopped listing it " . timeAgo($row['rmm_device_removed_at']) . ". $seen."];
    }
    $as_of = $row['rmm_device_synced_at'] && strtotime($row['rmm_device_synced_at']) < time() - 3600 ? ' (as of ' . timeAgo($row['rmm_device_synced_at']) . ')' : '';
    $states = [
        'online' => ['Online', 'success'],
        'offline' => ['Offline', 'secondary'],
        'overdue' => ['Overdue', 'danger'],
    ];
    [$label, $tone] = $states[$row['rmm_device_status']] ?? ['Unknown', 'secondary'];
    if (intval($row['rmm_device_maintenance'])) {
        $label .= ', maintenance';
    }
    return ['key' => $row['rmm_device_status'] ?: 'unknown', 'label' => $label, 'tone' => $tone, 'title' => "$name: $seen$as_of"];
}

/** A device's state as a small pill - for the asset page and its quick look. */
function rmmDeviceStatePill(array $row): string
{
    $state = rmmDeviceState($row);
    $icon = rmmKind($row)['icon'] ?? 'fas fa-laptop-medical';
    return '<span class="badge rounded-pill text-bg-' . $state['tone'] . ' fw-normal" data-rmm-state="' . escapeHtml($state['key']) . '" title="' . escapeHtml($state['title']) . '">'
        . '<i class="' . escapeHtml($icon) . ' fa-fw me-1"></i>' . escapeHtml($state['label']) . '</span>';
}

/** An asset's state in its RMM as a dot - for the assets list. Its first device's state; the title says each. */
function rmmDeviceStateDot(array $rows): string
{
    if (!$rows) {
        return '';
    }
    $state = rmmDeviceState($rows[0]);
    $titles = [];
    foreach ($rows as $row) {
        $each = rmmDeviceState($row);
        $titles[] = rmmProviderName($row) . ': ' . $each['label'] . ($each['key'] === 'removed' ? '' : ' - ' . preg_replace('/^[^:]*: /', '', $each['title']));
    }
    return '<span class="itflow-rmm-dot itflow-above-stretch bg-' . $state['tone'] . ($state['key'] === 'removed' ? ' is-removed' : '') . '" data-rmm-state="' . escapeHtml($state['key']) . '"'
        . ' title="' . escapeHtml(implode("\n", $titles)) . '"></span>';
}

/** What needs looking at on a device, in words - a reboot, patches, failing checks. */
function rmmDeviceAttention(array $row): array
{
    $items = [];
    if (intval($row['rmm_device_needs_reboot'])) {
        $items[] = ['icon' => 'fa-redo-alt', 'text' => 'Needs a reboot', 'tone' => 'warning'];
    }
    if (intval($row['rmm_device_patches_pending'])) {
        $items[] = ['icon' => 'fa-download', 'text' => rmmKind($row)['attention']['patches_pending'] ?? 'Patches waiting to install', 'tone' => 'warning'];
    }
    $failing = intval($row['rmm_device_checks_failing']);
    if ($failing) {
        $total = intval($row['rmm_device_checks_total']);
        $items[] = ['icon' => 'fa-exclamation-triangle', 'text' => "$failing of $total check" . ($total === 1 ? '' : 's') . ' failing', 'tone' => 'danger'];
    }
    return $items;
}

/**
 * How the assets list speaks of the RMMs - the one provider by name, "an RMM" when there are more,
 * and "monitoring" once a network platform is among them. $family speaks of that family's only.
 */
function rmmListLabel(?string $family = null): string
{
    $providers = array_values(array_filter(rmmProviders(), function ($provider) use ($family) {
        return $family === null || rmmFamily($provider) === $family;
    }));
    if (count($providers) === 1) {
        return rmmProviderName($providers[0]);
    }
    foreach ($providers as $provider) {
        if (rmmFamily($provider) === 'network') {
            return 'monitoring';
        }
    }
    return 'an RMM';
}

/*
 * ------------------------------------------------------------------ Linking clients
 */

/** A company name with the words that don't tell companies apart taken out - for suggesting a link. */
function rmmNameKey($name): string
{
    $name = mb_strtolower(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $name = str_replace('&', ' and ', $name);
    $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
    $words = array_filter(explode(' ', $name), function ($word) {
        return $word !== '' && !in_array($word, ['the', 'llc', 'inc', 'incorporated', 'ltd', 'limited', 'co', 'corp', 'corporation', 'company', 'pllc', 'pc', 'llp', 'lp', 'plc', 'gmbh'], true);
    });
    return implode(' ', $words);
}

/** The ITFlow client each of a provider's unlinked clients looks like - rmm_client_id => client_id, where exactly one client has its name. */
function rmmClientSuggestions(int $provider_id): array
{
    global $mysqli;
    $by_key = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL" . clientNotLeadSql());
    while ($row = mysqli_fetch_assoc($sql)) {
        $by_key[rmmNameKey($row['client_name'])][] = intval($row['client_id']);
    }
    $suggestions = [];
    $sql = mysqli_query($mysqli, "SELECT rmm_client_id, rmm_client_name FROM rmm_clients WHERE rmm_client_provider_id = $provider_id AND rmm_client_client_id = 0 AND rmm_client_removed_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        // "Harbor Freight Logistics (MSP Gateway)" - a network platform's site with its console's name - is its name too
        foreach (array_unique([rmmNameKey($row['rmm_client_name']), rmmNameKey(preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $row['rmm_client_name']))]) as $key) {
            if ($key !== '' && count($by_key[$key] ?? []) === 1) {
                $suggestions[intval($row['rmm_client_id'])] = $by_key[$key][0];
                break;
            }
        }
    }
    return $suggestions;
}

/** One rmm_clients row with its ITFlow client's name and its provider's columns - raw, or null. */
function rmmClientRow($rmm_client_id): ?array
{
    global $mysqli;
    $rmm_client_id = intval($rmm_client_id);
    if (!$rmm_client_id || !rmmTablesReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rmm_clients.*, rmm_providers.*, client_name, client_archived_at FROM rmm_clients
        JOIN rmm_providers ON rmm_provider_id = rmm_client_provider_id
        LEFT JOIN clients ON client_id = rmm_client_client_id AND rmm_client_client_id > 0
        WHERE rmm_client_id = $rmm_client_id LIMIT 1"));
    return $row ?: null;
}

/*
 * ------------------------------------------------------------------ Asset types, linking by hand, contacts
 */

/** Can a provider pick the asset types its devices come in as yet (database update 4.1.2)? */
function rmmAssetTypesReady(): bool
{
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.1.2', '>=');
    }
    return $ready;
}

/** The kinds of device the sync tells apart - the type it guesses (rmmDeviceType()) => what the provider window calls them. */
function rmmDeviceKinds(?array $kind = null): array
{
    return $kind['device_kinds'] ?? ['Desktop' => 'Desktops', 'Laptop' => 'Laptops', 'Server' => 'Servers', 'Virtual Machine' => 'Virtual machines'];
}

/** The asset type each kind of a provider's devices comes in as - [guessed type => type]. A type deleted since is the guessed one again. */
function rmmProviderAssetTypes(?array $provider, ?array $kind = null): array
{
    $picked = $provider && rmmAssetTypesReady() ? json_decode((string) ($provider['rmm_provider_asset_types'] ?? ''), true) : null;
    $kind = $kind ?? ($provider ? rmmKind($provider) : null);
    $types = [];
    foreach (array_keys(rmmDeviceKinds($kind)) as $guessed) {
        $type = is_array($picked) && is_string($picked[$guessed] ?? null) ? assetTypeName($picked[$guessed]) : '';
        $types[$guessed] = $type !== '' ? $type : $guessed;
    }
    return $types;
}

/** One of your asset types renamed, or deleted with its assets moved to another - the providers bringing devices in as it follow. */
function rmmProviderAssetTypesRename(string $old, string $new): void
{
    global $mysqli;
    if (!rmmTablesReady() || !rmmAssetTypesReady() || $old === $new) {
        return;
    }
    $sql = mysqli_query($mysqli, "SELECT rmm_provider_id, rmm_provider_asset_types FROM rmm_providers WHERE rmm_provider_asset_types IS NOT NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $picked = json_decode((string) $row['rmm_provider_asset_types'], true);
        if (!is_array($picked)) {
            continue;
        }
        $changed = false;
        foreach ($picked as $guessed => $type) {
            if (is_string($type) && strcasecmp($type, $old) === 0) {
                $picked[$guessed] = $new;
                $changed = true;
            }
        }
        if ($changed) {
            mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_asset_types = '" . escapeSql(json_encode($picked)) . "' WHERE rmm_provider_id = " . intval($row['rmm_provider_id']));
        }
    }
}

/** One device with its provider's columns and its RMM client's name - raw, or null. */
function rmmDeviceById(int $device_id): ?array
{
    global $mysqli;
    if (!$device_id || !rmmTablesReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rmm_devices.*, rmm_providers.*, rmm_client_name FROM rmm_devices
        JOIN rmm_providers ON rmm_provider_id = rmm_device_provider_id
        LEFT JOIN rmm_clients ON rmm_clients.rmm_client_id = rmm_device_rmm_client_id
        WHERE rmm_device_id = $device_id LIMIT 1"));
    return $row ?: null;
}

/** The assets a device can be linked to by hand - its client's, not archived, but the one it has: [asset_id => row, with 'taken' - the hostname of another device of its provider that has it]. */
function rmmDeviceLinkTargets(array $device): array
{
    global $mysqli;
    $client_id = intval($device['rmm_device_client_id']);
    $provider_id = intval($device['rmm_device_provider_id']);
    $device_id = intval($device['rmm_device_id']);
    $targets = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial,
            (SELECT rmm_device_hostname FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id = asset_id
                AND rmm_device_removed_at IS NULL AND rmm_device_id <> $device_id LIMIT 1) AS taken
        FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_id <> " . intval($device['rmm_device_asset_id']) . "
        ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $targets[intval($row['asset_id'])] = $row;
    }
    return $targets;
}

/**
 * Link a device to another of its client's assets, by hand - it was matched to the wrong one,
 * or brought in as a new one beside the asset you already had. The asset it had stays, no
 * longer kept up. The one picked fills in only what it is missing, as a matched asset does.
 * '' when done, or what is wrong.
 */
function rmmDeviceLinkAsset(array $device, int $asset_id): string
{
    global $mysqli, $session_name;
    $device_id = intval($device['rmm_device_id']);
    $provider_id = intval($device['rmm_device_provider_id']);
    $client_id = intval($device['rmm_device_client_id']);
    $name = rmmProviderName($device);
    $host = (string) $device['rmm_device_hostname'];
    $had = intval($device['rmm_device_asset_id']);
    if ($device['rmm_device_removed_at'] !== null) {
        return "$name no longer has it.";
    }
    $target = rmmAssetCurrent($asset_id);
    if (!$target || $target['_client_id'] !== $client_id) {
        return 'Pick one of this client\'s assets.';
    }
    if ($asset_id === $had) {
        return 'It is linked to that asset already.';
    }
    $taken = rmmDeviceLinkTargets($device)[$asset_id]['taken'] ?? null;
    if ($taken !== null) {
        return "That asset is $taken in $name already.";
    }

    // A device of this provider that had the asset picked and is gone there no longer holds it
    mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id = $asset_id
        AND rmm_device_removed_at IS NOT NULL AND rmm_device_id <> $device_id");
    mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_asset_id = $asset_id, rmm_device_synced = '[]' WHERE rmm_device_id = $device_id");

    $who = stripslashes((string) $session_name);
    $had_row = $had ? rmmAssetCurrent($had) : null;
    if ($had_row) {
        rmmAssetHistory($had, $name, "no longer keeps it up - $who linked its device $host to " . $target['name']);
    }
    rmmAssetHistory($asset_id, $who, "linked it to its device in $name, $host" . ($had_row ? ', which was ' . $had_row['name'] : ''));
    logAudit('Asset', 'Edit', "$session_name linked " . escapeSql("$name device $host to asset {$target['name']}" . ($had_row ? " - it was {$had_row['name']}" : '')), $client_id, $asset_id);
    return '';
}

/** A username as it is compared with contacts - DOMAIN\ and @domain taken off, then letters and digits only, in lower case. */
function rmmUserKey($user): string
{
    $user = preg_replace('/^.*\\\\/u', '', (string) $user);
    $user = preg_replace('/@.*$/u', '', $user);
    return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($user));
}

/**
 * The contact a device's signed-in user is - the only one of the client's contacts whose email
 * starts with that username, or whose name it is: Marla Whitfield for mwhitfield, marlaw,
 * marla.whitfield or AzureAD\MarlaWhitfield. Null when none is, or when more than one could be.
 */
function rmmUserContact(int $client_id, string $logged_user): ?array
{
    global $mysqli;
    static $contacts = [];
    $key = rmmUserKey($logged_user);
    if (mb_strlen($key) < 3 || in_array($key, ['administrator', 'admin', 'localadmin', 'user', 'guest', 'owner', 'system', 'defaultuser0', 'kiosk'], true)) {
        return null;
    }
    if (!isset($contacts[$client_id])) {
        $contacts[$client_id] = [];
        $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL");
        while ($row = mysqli_fetch_assoc($sql)) {
            $keys = [];
            $email = (string) $row['contact_email'];
            if (str_contains($email, '@')) {
                $keys[] = rmmUserKey($email);
            }
            $words = preg_split('/\s+/u', trim((string) $row['contact_name']), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) >= 2) {
                $first = rmmUserKey($words[0]);
                $last = rmmUserKey(end($words));
                $keys[] = rmmUserKey(implode('', $words));
                $keys[] = mb_substr($first, 0, 1) . $last;
                $keys[] = $first . mb_substr($last, 0, 1);
            }
            $row['keys'] = array_values(array_unique(array_filter($keys, function ($k) {
                return mb_strlen($k) >= 3;
            })));
            $contacts[$client_id][] = $row;
        }
    }
    $found = [];
    foreach ($contacts[$client_id] as $row) {
        if (in_array($key, $row['keys'], true)) {
            $found[intval($row['contact_id'])] = $row;
        }
    }
    return count($found) === 1 ? reset($found) : null;
}

/**
 * Some assets' contacts from who is signed in to their devices - only assets with nobody
 * assigned. ['assigned', 'had', 'none'] counts. The caller checks the agent may edit them.
 */
function rmmAssignUserContacts(array $asset_ids): array
{
    global $mysqli;
    $counts = ['assigned' => 0, 'had' => 0, 'none' => 0];
    $asset_ids = array_values(array_filter(array_map('intval', $asset_ids)));
    $devices = rmmDevicesForAssets($asset_ids);
    foreach ($asset_ids as $asset_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_client_id, asset_contact_id FROM assets WHERE asset_id = $asset_id"));
        if (!$row) {
            continue;
        }
        if (intval($row['asset_contact_id'])) {
            $counts['had']++;
            continue;
        }
        $contact = null;
        foreach ($devices[$asset_id] ?? [] as $device) {
            if ($device['rmm_device_removed_at'] === null && (string) $device['rmm_device_logged_user'] !== '') {
                $contact = rmmUserContact(intval($row['asset_client_id']), (string) $device['rmm_device_logged_user']);
                break;
            }
        }
        if (!$contact) {
            $counts['none']++;
            continue;
        }
        [$ok] = assetQuickSet($asset_id, 'asset_contact', (string) intval($contact['contact_id']));
        $counts[$ok ? 'assigned' : 'none']++;
    }
    return $counts;
}

/** Assets deleted - their devices keep no asset, so Bring back on their client's row has them at once. How many were in an RMM. */
function rmmAssetsDeleted(array $asset_ids): int
{
    global $mysqli;
    $asset_ids = array_values(array_filter(array_map('intval', $asset_ids)));
    if (!$asset_ids || !rmmTablesReady()) {
        return 0;
    }
    $in = implode(', ', $asset_ids);
    $live = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT rmm_device_asset_id) FROM rmm_devices WHERE rmm_device_asset_id IN ($in) AND rmm_device_removed_at IS NULL"))[0]);
    mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_asset_id = 0 WHERE rmm_device_asset_id IN ($in)");
    return $live;
}

/**
 * How a client's devices in its RMMs are - how many of its assets are in one, and how many are
 * online, offline, overdue, need a reboot, have patches waiting or checks failing, with when it
 * was last brought in. Null when none of its assets are in an RMM.
 */
function rmmClientSummary(int $client_id): ?array
{
    global $mysqli;
    static $summaries = [];
    if (array_key_exists($client_id, $summaries)) {
        return $summaries[$client_id];
    }
    // The RMMs' devices - a network platform's switches and access points aren't what this counts
    $ids = array_map(function ($provider) {
        return intval($provider['rmm_provider_id']);
    }, rmmFamilyProviders('rmm'));
    if (!rmmTablesReady() || !$ids) {
        return $summaries[$client_id] = null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(DISTINCT asset_id) AS total,
            COUNT(DISTINCT IF(rmm_device_status = 'online', asset_id, NULL)) AS online,
            COUNT(DISTINCT IF(rmm_device_status = 'offline', asset_id, NULL)) AS offline,
            COUNT(DISTINCT IF(rmm_device_status = 'overdue', asset_id, NULL)) AS overdue,
            COUNT(DISTINCT IF(rmm_device_needs_reboot = 1, asset_id, NULL)) AS reboot,
            COUNT(DISTINCT IF(rmm_device_patches_pending = 1, asset_id, NULL)) AS patches,
            COUNT(DISTINCT IF(rmm_device_checks_failing > 0, asset_id, NULL)) AS checks,
            MAX(rmm_device_synced_at) AS synced_at
        FROM rmm_devices JOIN assets ON asset_id = rmm_device_asset_id AND asset_client_id = $client_id AND asset_archived_at IS NULL
        WHERE rmm_device_asset_id > 0 AND rmm_device_removed_at IS NULL AND rmm_device_provider_id IN (" . implode(', ', $ids) . ")"));
    if (!$row || !intval($row['total'])) {
        return $summaries[$client_id] = null;
    }
    $summary = ['synced_at' => $row['synced_at']];
    foreach (['total', 'online', 'offline', 'overdue', 'reboot', 'patches', 'checks'] as $key) {
        $summary[$key] = intval($row[$key]);
    }
    return $summaries[$client_id] = $summary;
}

/** Each asset's devices in its RMMs, for the API - as asset_rmm, [] for an asset in none. */
function rmmApiAttach(array $rows): array
{
    $devices = rmmTablesReady() ? rmmDevicesForAssets(array_map(function ($row) {
        return intval($row['asset_id'] ?? 0);
    }, $rows)) : [];
    foreach ($rows as &$row) {
        $row['asset_rmm'] = array_map(function ($device) {
            return [
                'provider_id' => intval($device['rmm_device_provider_id']),
                'provider' => rmmProviderName($device),
                'device_id' => (string) $device['rmm_device_remote_id'],
                'hostname' => (string) $device['rmm_device_hostname'],
                'site' => (string) $device['rmm_device_site'],
                'status' => $device['rmm_device_removed_at'] !== null ? 'removed' : (string) $device['rmm_device_status'],
                'last_seen_at' => $device['rmm_device_last_seen_at'],
                'boot_at' => $device['rmm_device_boot_at'],
                'logged_user' => (string) $device['rmm_device_logged_user'],
                'public_ip' => (string) $device['rmm_device_public_ip'],
                'local_ips' => (string) $device['rmm_device_local_ips'],
                'needs_reboot' => (bool) intval($device['rmm_device_needs_reboot']),
                'patches_pending' => (bool) intval($device['rmm_device_patches_pending']),
                'checks_total' => intval($device['rmm_device_checks_total']),
                'checks_failing' => intval($device['rmm_device_checks_failing']),
                'maintenance' => (bool) intval($device['rmm_device_maintenance']),
                'agent_version' => (string) $device['rmm_device_agent_version'],
                'synced_at' => $device['rmm_device_synced_at'],
                'removed_at' => $device['rmm_device_removed_at'],
            ];
        }, $devices[intval($row['asset_id'] ?? 0)] ?? []);
    }
    unset($row);
    return $rows;
}

/*
 * ------------------------------------------------------------------ The provider window
 */

/**
 * What the provider window sent, checked - [$after, $errors]: the row to save, and what is
 * wrong by field. A secret left empty keeps the saved one; a field its kind doesn't use keeps
 * what it had. The kind is picked when it is added, and stays.
 */
function rmmProviderCheck(array $in, ?array $before): array
{
    global $mysqli;
    $errors = [];
    $kind_key = $before ? (string) $before['rmm_provider_kind'] : (string) ($in['kind'] ?? '');
    $kind = rmmKind($kind_key);
    if (!$kind) {
        return [[], ['kind' => 'Pick which ' . (($in['family'] ?? '') === 'network' ? 'platform' : 'RMM') . ' it is.']];
    }
    $words = rmmFamilyWords(rmmFamily($kind));

    $name = rmmText($in['name'] ?? '', 100);
    if ($name === '') {
        $name = $kind['name'];
    }
    $id = intval($before['rmm_provider_id'] ?? 0);
    $name_sql = escapeSql($name);
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM rmm_providers WHERE rmm_provider_name = '$name_sql' AND rmm_provider_id <> $id LIMIT 1"))) {
        $errors['name'] = 'Another ' . $words['one'] . ' is called that - give this one a name of its own, like ' . $kind['name'] . ' (second office).';
    }

    $after = [
        'rmm_provider_kind' => $kind_key,
        'rmm_provider_name' => $name,
        'rmm_provider_active' => $before ? (!empty($in['active']) ? 1 : 0) : 1,
    ];
    foreach (array_merge(['api_url', 'web_url', 'api_key', 'api_secret'], rmmNetworkReady() ? ['insecure'] : []) as $field) {
        $column = "rmm_provider_$field";
        $had = (string) ($before[$column] ?? ($field === 'insecure' ? '0' : ''));
        $about = $kind['fields'][$field] ?? null;
        if (!$about) {
            $after[$column] = $had;
            continue;
        }
        if ($about['type'] === 'switch') {
            $after[$column] = !empty($in[$field]) ? 1 : 0;
            continue;
        }
        $typed = trim((string) ($in[$field] ?? ''));
        if ($about['type'] === 'text') {
            // Shown in the window, so what is there is what it is - a user name
            if ($typed === '' && !empty($about['required'])) {
                $errors[$field] = $about['needed'];
            } elseif (mb_strlen($typed) > 255 || preg_match('/[\x00-\x1F]/', $typed)) {
                $errors[$field] = 'That doesn\'t look like a ' . rmmFieldWords($about['label']) . ' - just the ' . rmmFieldWords($about['label']) . '.';
            }
            $after[$column] = $typed;
        } elseif ($about['type'] === 'url') {
            $clean = rmmUrlClean($typed);
            if ($typed === '' && !empty($about['required'])) {
                $errors[$field] = $about['needed'];
            } elseif ($typed !== '' && ($clean === '' || mb_strlen($clean) > 500)) {
                $errors[$field] = 'That isn\'t a web address ITFlow can use - just the address, like ' . $about['placeholder'] . '.';
            }
            $after[$column] = $clean;
        } else {
            // A password can hold spaces; a key can't
            $spaces = !empty($about['spaces']) ? '/[\x00-\x1F]/' : '/\s/';
            if ($typed === '' && $had === '' && !empty($about['required'])) {
                $errors[$field] = $about['needed'];
            } elseif ($typed !== '' && (mb_strlen($typed) > 300 || preg_match($spaces, $typed))) {
                $errors[$field] = 'That doesn\'t look like ' . $kind['name'] . '\'s ' . rmmFieldWords($about['label']) . ' - paste just the ' . rmmFieldWords($about['label']) . '.';
            }
            if ($typed !== '' && !empty($kind['seal'])) {
                $sealed = rmmSecretSeal($typed);
                if ($sealed === '') {
                    $errors[$field] = 'ITFlow keeps this encrypted with its own key, and couldn\'t make one - config.php needs to be writable by the web server once. See Maintenance > Backup.';
                }
                $typed = $sealed;
            }
            $after[$column] = $typed !== '' ? $typed : $had;
        }
    }

    // Alerts opening tickets - kept as they were when the window didn't send them (ticketing off)
    if (rmmAlertsReady()) {
        $level = (string) ($in['alert_level'] ?? ($before['rmm_provider_alert_level'] ?? ''));
        if (!isset(rmmAlertLevels()[$level]) || ($level !== '' && !isset($kind['alerts'], $kind['alert']))) {
            $errors['alert_level'] = 'Pick which alerts open tickets.';
            $level = '';
        }
        $after['rmm_provider_alert_level'] = $level;
        $after['rmm_provider_alert_category_id'] = ticketCategoryId($in['alert_category_id'] ?? ($before['rmm_provider_alert_category_id'] ?? 0));
        // Turned on, at another level, or the provider back on - the alerts open then are taken as known, as at first
        $after['rmm_provider_alerts_restart'] = $level !== '' && ($level !== (string) ($before['rmm_provider_alert_level'] ?? '')
            || ($before && !intval($before['rmm_provider_active']) && $after['rmm_provider_active']));
    }

    // The asset type each kind of device comes in as - kept as they were for a kind the window didn't send
    if (rmmAssetTypesReady()) {
        $now = rmmProviderAssetTypes($before, $kind);
        $sent = is_array($in['asset_types'] ?? null) ? $in['asset_types'] : [];
        $picked = [];
        foreach (array_keys(rmmDeviceKinds($kind)) as $guessed) {
            $type = isset($sent[$guessed]) && is_string($sent[$guessed]) ? assetTypeName($sent[$guessed]) : $now[$guessed];
            if ($type === '') {
                $errors['asset_types'] = 'Pick an asset type for each kind of device.';
                $type = $now[$guessed];
            }
            if ($type !== $guessed) {
                $picked[$guessed] = $type;
            }
        }
        $after['rmm_provider_asset_types'] = $picked ? json_encode($picked) : null;
    }
    return [$after, $errors];
}

/** A field's label inside a sentence - "dashboard address", but "API key" stays as it is. */
function rmmFieldWords(string $label): string
{
    return preg_match('/^[A-Z]{2}/', $label) ? $label : lcfirst($label);
}

/** Save a provider - a new row when $provider_id is 0. Its id. */
function rmmProviderWrite(array $after, int $provider_id = 0): int
{
    global $mysqli;
    $set = [];
    foreach (['rmm_provider_name', 'rmm_provider_api_url', 'rmm_provider_web_url', 'rmm_provider_api_key', 'rmm_provider_api_secret'] as $column) {
        $set[] = "$column = '" . escapeSql($after[$column]) . "'";
    }
    $set[] = 'rmm_provider_active = ' . intval($after['rmm_provider_active']);
    if (array_key_exists('rmm_provider_insecure', $after)) {
        $set[] = 'rmm_provider_insecure = ' . (intval($after['rmm_provider_insecure']) ? 1 : 0);
    }
    if (array_key_exists('rmm_provider_alert_level', $after)) {
        $set[] = "rmm_provider_alert_level = '" . escapeSql($after['rmm_provider_alert_level']) . "'";
        $set[] = 'rmm_provider_alert_category_id = ' . intval($after['rmm_provider_alert_category_id']);
        if (!empty($after['rmm_provider_alerts_restart'])) {
            $set[] = 'rmm_provider_alerts_since = NULL, rmm_provider_alerts_note = NULL';
        }
    }
    if (array_key_exists('rmm_provider_asset_types', $after)) {
        $set[] = 'rmm_provider_asset_types = ' . ($after['rmm_provider_asset_types'] === null ? 'NULL' : "'" . escapeSql($after['rmm_provider_asset_types']) . "'");
    }
    if ($provider_id) {
        mysqli_query($mysqli, "UPDATE rmm_providers SET " . implode(', ', $set) . " WHERE rmm_provider_id = $provider_id");
        return $provider_id;
    }
    $set[] = "rmm_provider_kind = '" . escapeSql($after['rmm_provider_kind']) . "'";
    mysqli_query($mysqli, "INSERT INTO rmm_providers SET " . implode(', ', $set));
    return intval(mysqli_insert_id($mysqli));
}

/** What changed on a provider, for the audit log - secrets only as changed. */
function rmmProviderChangeLines(array $before, array $after): array
{
    $kind = rmmKind($before);
    $lines = [];
    if ($before['rmm_provider_name'] !== $after['rmm_provider_name']) {
        $lines[] = 'renamed ' . $after['rmm_provider_name'];
    }
    foreach ($kind['fields'] ?? [] as $field => $about) {
        $column = "rmm_provider_$field";
        if (!array_key_exists($column, $after) || (string) ($before[$column] ?? '') === (string) $after[$column]) {
            continue;
        }
        $label = rmmFieldWords($about['label']);
        if ($about['type'] === 'secret') {
            $lines[] = "$label changed";
        } elseif ($about['type'] === 'switch') {
            $lines[] = intval($after[$column]) ? ($about['on'] ?? "$label on") : ($about['off'] ?? "$label off");
        } else {
            $lines[] = $after[$column] === '' ? "$label cleared" : "$label " . $after[$column];
        }
    }
    if (intval($before['rmm_provider_active']) !== intval($after['rmm_provider_active'])) {
        $lines[] = intval($after['rmm_provider_active']) ? 'turned on' : 'turned off';
    }
    if (array_key_exists('rmm_provider_alert_level', $after) && (string) ($before['rmm_provider_alert_level'] ?? '') !== $after['rmm_provider_alert_level']) {
        $lines[] = $after['rmm_provider_alert_level'] === '' ? 'alerts no longer open tickets' : 'alerts open tickets: ' . mb_strtolower(rmmAlertLevels()[$after['rmm_provider_alert_level']]);
    }
    if (array_key_exists('rmm_provider_alert_category_id', $after) && intval($before['rmm_provider_alert_category_id'] ?? 0) !== intval($after['rmm_provider_alert_category_id'])) {
        $category = intval($after['rmm_provider_alert_category_id']) ? (string) getFieldById('categories', intval($after['rmm_provider_alert_category_id']), 'category_name') : '';
        $lines[] = $category === '' ? 'alert tickets not categorized' : "alert tickets in $category";
    }
    if (array_key_exists('rmm_provider_asset_types', $after)) {
        $had = rmmProviderAssetTypes($before, $kind);
        $now = rmmProviderAssetTypes(['rmm_provider_asset_types' => $after['rmm_provider_asset_types']], $kind);
        foreach (rmmDeviceKinds($kind) as $guessed => $label) {
            if ($had[$guessed] !== $now[$guessed]) {
                $lines[] = mb_strtolower($label) . ' come in as ' . $now[$guessed];
            }
        }
    }
    return $lines;
}
