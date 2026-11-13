<?php

/*
 * RMM Providers - devices from an RMM as ITFlow assets (Admin > Integrations > RMM Providers).
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
 *   icon, about      - its icon, and a line on what it brings in
 *   docs_url         - where its API keys are explained
 *   fields           - the connection's fields it uses, of api_url, web_url, api_key and
 *                      api_secret (the rmm_provider_ columns): each with label, icon, type (url
 *                      or secret), required, placeholder, help and needed (said when it's empty)
 *   hints            - added to what rmmHttp() says went wrong: auth (401/403), not_found
 *                      (404) and not_json (an answer that isn't the API's)
 *   links            - a device's pages in the RMM that rmmDeviceUrls() gives, key => label:
 *                      open, and control (Take Control) and background where it has them
 *   clients          - fn(provider): ['ok', 'clients' => [[id, name, sites, devices]], 'error']
 *   devices          - fn(provider, client's id there): ['ok', 'devices' => [device], 'error'],
 *                      each device as rmmDeviceSave() takes it
 *   dashboard        - fn(provider): its web address, '' when it has none
 *   device_urls      - fn(provider, device's id there): key of links => address
 */

/** The RMMs ITFlow can bring devices in from - kind => what it is. */
function rmmProviderKinds(): array
{
    return [
        'tactical' => rmmTacticalKind(),
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

/** Whether a provider can be asked - on, of a kind ITFlow knows, with what its kind needs. */
function rmmProviderUsable(?array $provider): bool
{
    $kind = $provider ? rmmKind($provider) : null;
    if (!$kind || intval($provider['rmm_provider_active']) !== 1) {
        return false;
    }
    foreach ($kind['fields'] as $field => $about) {
        if (!empty($about['required']) && trim((string) ($provider["rmm_provider_$field"] ?? '')) === '') {
            return false;
        }
    }
    return true;
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
 * Ask an RMM's API - ['ok', 'code', 'json', 'error']: error is what to tell a person when it
 * didn't work, with the kind's hints. The kind's file says where and how to sign in.
 */
function rmmHttp(array $provider, string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): array
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
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $transport = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $host = (string) parse_url($url, PHP_URL_HOST);
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($raw === false) {
        $ssl = stripos($transport, 'SSL') !== false && stripos($url, 'https://') === 0 ? ' - if it isn\'t on https, put http:// in front of the address' : '';
        return ['ok' => false, 'code' => 0, 'json' => null, 'error' => "Couldn't reach $host - $transport$ssl"];
    }
    $json = json_decode((string) $raw, true);
    if ($code === 401 || $code === 403) {
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "$name turned the key away (HTTP $code)" . ($hint('auth') ?: ' - check the key')];
    }
    if ($code >= 300 && $code < 400) {
        return ['ok' => false, 'code' => $code, 'json' => null, 'error' => "$host sent ITFlow somewhere else (HTTP $code) - use the API's own address, with https if it has it."];
    }
    if ($code === 404) {
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "Nothing at $path on $host (HTTP 404)" . $hint('not_found')];
    }
    if ($code < 200 || $code > 299) {
        $detail = is_array($json) ? rmmText($json['detail'] ?? $json['error'] ?? $json['message'] ?? '', 300) : '';
        return ['ok' => false, 'code' => $code, 'json' => $json, 'error' => "$name answered HTTP $code" . ($detail !== '' ? " - $detail" : '')];
    }
    if (!is_array($json)) {
        return ['ok' => false, 'code' => $code, 'json' => null, 'error' => "$host answered, but not as $name's API" . $hint('not_json')];
    }
    return ['ok' => true, 'code' => $code, 'json' => $json, 'error' => ''];
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
    return ['ok' => true, 'text' => 'Connected - ' . rmmProviderName($provider) . ' has ' . number_format($clients) . ' client' . ($clients === 1 ? '' : 's')
        . ' and ' . number_format($devices) . ' device' . ($devices === 1 ? '' : 's') . ' this key can see.'];
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
 *   asset                            - what its asset is: make, model, serial, os, ip, cpu,
 *                                      storage - '' where the RMM doesn't say - and type
 *                                      when the kind knows better than rmmDeviceType()
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
    ];
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
        'cpu' => $value('cpu', 500),
        'storage' => $value('storage', 500),
        'location_id' => rmmSiteLocationId($client_id, $facts['site']),
    ];
}

/** The asset fields the sync keeps up, as people read them. */
function rmmAssetFieldLabels(): array
{
    return ['name' => 'name', 'type' => 'type', 'make' => 'make', 'model' => 'model', 'serial' => 'serial number', 'os' => 'OS', 'ip' => 'IP',
        'cpu' => 'CPU', 'storage' => 'storage', 'location_id' => 'location'];
}

/** An asset's present values, for the fields the sync keeps up - null when the asset is gone. */
function rmmAssetCurrent(int $asset_id): ?array
{
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_os, asset_location_id,
        asset_status, asset_client_id, asset_archived_at, interface_id, interface_ip FROM assets
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

    if (array_key_exists('ip', $set)) {
        $ip = escapeSql($set['ip']);
        if ($current['_interface_id']) {
            mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_ip = '$ip' WHERE interface_id = " . intval($current['_interface_id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_ip = '$ip', interface_primary = 1, interface_asset_id = $asset_id");
        }
    }

    // CPU and storage are details of the asset's type (functions/asset_type.php) - only for a type that has them
    $type = $set['type'] ?? $current['type'];
    $allowed = function_exists('assetTypeSpecs') ? array_keys(assetTypeSpecs($type)) : [];
    foreach (['cpu', 'storage'] as $key) {
        if (array_key_exists($key, $set) && in_array($key, $allowed, true) && function_exists('assetSpecsReady') && assetSpecsReady()) {
            $value = escapeSql($set[$key]);
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
 * An asset already in ITFlow that is this device - same serial number, or the only one with
 * its name - and not another of this provider's devices already. 0 when there is none.
 */
function rmmAssetMatch(int $provider_id, int $client_id, array $values): int
{
    global $mysqli;
    $taken = "asset_id NOT IN (SELECT rmm_device_asset_id FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id > 0)";
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
 * and what it is doing saved. What happened goes into $counts. Its id in the RMM, or '' when
 * it hasn't got one ITFlow can keep.
 */
function rmmDeviceSave(array $provider, array $rmm_client, int $client_id, array $device, array &$counts): string
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
    $row = rmmDeviceRow($provider_id, $remote_id);
    $synced_before = [];
    $asset_id = 0;
    $is_new_row = $row === null;

    if ($row) {
        $synced_before = json_decode((string) $row['rmm_device_synced'], true) ?: [];
        $asset_id = intval($row['rmm_device_asset_id']);
        if ($asset_id && !rmmAssetCurrent($asset_id)) {
            // Deleted in ITFlow - not made again until Bring back
            $asset_id = 0;
        }
    }

    $made = false;
    $matched = false;
    if ($is_new_row) {
        $asset_id = rmmAssetMatch($provider_id, $client_id, $values);
        if ($asset_id) {
            $matched = true;
            $counts['linked']++;
        } else {
            $asset_id = rmmAssetCreate($client_id, $values);
            $made = true;
            $counts['created']++;
        }
    }

    $changed = [];
    if ($asset_id) {
        [$changed, $synced] = rmmAssetFollow($asset_id, $values, $made ? array_fill_keys(array_keys(rmmAssetFieldLabels()), '') : $synced_before);
        if ($made) {
            rmmAssetHistory($asset_id, $who, 'brought it in from ' . ($facts['site'] !== '' ? 'site ' . $facts['site'] . ' of ' : '') . $rmm_client['rmm_client_name']);
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

    if ($row && $row['rmm_device_removed_at'] !== null) {
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

    $counts = ['clients' => 0, 'devices' => 0, 'created' => 0, 'linked' => 0, 'updated' => 0, 'removed' => 0, 'returned' => 0, 'skipped' => 0];
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
    $complete = [];   // rmm_clients id => the ids of its devices, for each whose list came back whole - the rest of its devices are gone
    $stopped = false;

    $in_list = function (array $ids) {
        return $ids ? "'" . implode("', '", array_map('escapeSql', $ids)) . "'" : "''";
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
            $gone = mysqli_query($mysqli, "SELECT rmm_client_id FROM rmm_clients WHERE rmm_client_provider_id = $provider_id AND rmm_client_removed_at IS NULL
                AND rmm_client_remote_id NOT IN ($listed)");
            while ($gone_row = mysqli_fetch_row($gone)) {
                $complete[intval($gone_row[0])] = [];
                mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_removed_at = NOW() WHERE rmm_client_id = " . intval($gone_row[0]));
            }
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
            $seen = [];
            foreach ($devices['devices'] as $device) {
                if (is_array($device)) {
                    $seen[] = rmmDeviceSave($provider, $rmm_client, intval($rmm_client['rmm_client_client_id']), $device, $counts);
                }
            }
            $complete[intval($rmm_client['rmm_client_id'])] = array_values(array_filter($seen, 'strlen'));
            mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_synced_at = NOW() WHERE rmm_client_id = " . intval($rmm_client['rmm_client_id']));
        }

        // Devices of the clients brought in whole that the RMM didn't list - it no longer has them
        foreach ($complete as $rmm_client_id => $seen) {
            $sql = mysqli_query($mysqli, "SELECT rmm_device_id, rmm_device_asset_id FROM rmm_devices WHERE rmm_device_provider_id = $provider_id
                AND rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_removed_at IS NULL AND rmm_device_remote_id NOT IN (" . $in_list($seen) . ")");
            while ($row = mysqli_fetch_assoc($sql)) {
                mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_removed_at = NOW() WHERE rmm_device_id = " . intval($row['rmm_device_id']));
                if (intval($row['rmm_device_asset_id']) && rmmAssetCurrent(intval($row['rmm_device_asset_id']))) {
                    rmmAssetHistory(intval($row['rmm_device_asset_id']), $name, 'no longer has it');
                }
                $counts['removed']++;
            }
        }

        $note = number_format($counts['devices']) . ' device' . ($counts['devices'] === 1 ? '' : 's') . ' brought in';
        $done = [];
        foreach (['created' => 'new', 'linked' => 'matched to assets already here', 'updated' => 'updated', 'removed' => "no longer in $short", 'returned' => "back in $short"] as $key => $words) {
            if ($counts[$key]) {
                $done[] = number_format($counts[$key]) . " $words";
            }
        }
        $note .= $done ? ' - ' . implode(', ', $done) : '';
        if ($stopped) {
            $note .= '. Stopped for time - the rest come in next sync';
        }
        if ($errors) {
            $note .= '. Not brought in: ' . implode('; ', $errors);
            logApp('RMM', 'warning', "$name: sync - " . implode('; ', $errors));
        }
        $ok = !$errors;
    } catch (Throwable $e) {
        $ok = false;
        $note = $e->getMessage();
        logApp('RMM', 'warning', "$name: sync failed - $note");
    }

    $note_sql = escapeSql(mb_substr($note, 0, 500));
    mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_sync_at = NOW(), rmm_provider_sync_ok = " . ($ok ? 1 : 0) . ", rmm_provider_sync_note = '$note_sql' WHERE rmm_provider_id = $provider_id");

    if ($counts['updated'] || $counts['removed'] || $counts['returned']) {
        logAudit('RMM Provider', 'Sync', escapeSql("$name synced devices: $note"), 0, $provider_id);
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
        $left = $max_seconds - (int) (microtime(true) - $clock);
        if ($left < 5) {
            $results[rmmProviderName($provider)] = ['ok' => true, 'note' => 'Left for the next run - out of time', 'counts' => []];
            continue;
        }
        $results[rmmProviderName($provider)] = rmmSync($provider, 0, $left);
    }
    return $results;
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
        $items[] = ['icon' => 'fa-download', 'text' => 'Patches waiting to install', 'tone' => 'warning'];
    }
    $failing = intval($row['rmm_device_checks_failing']);
    if ($failing) {
        $total = intval($row['rmm_device_checks_total']);
        $items[] = ['icon' => 'fa-exclamation-triangle', 'text' => "$failing of $total check" . ($total === 1 ? '' : 's') . ' failing', 'tone' => 'danger'];
    }
    return $items;
}

/** How the assets list speaks of the RMMs - the one provider by name, or "an RMM" when there are more. */
function rmmListLabel(): string
{
    $providers = rmmProviders();
    return count($providers) === 1 ? rmmProviderName($providers[0]) : 'an RMM';
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
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $by_key[rmmNameKey($row['client_name'])][] = intval($row['client_id']);
    }
    $suggestions = [];
    $sql = mysqli_query($mysqli, "SELECT rmm_client_id, rmm_client_name FROM rmm_clients WHERE rmm_client_provider_id = $provider_id AND rmm_client_client_id = 0 AND rmm_client_removed_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $key = rmmNameKey($row['rmm_client_name']);
        if ($key !== '' && count($by_key[$key] ?? []) === 1) {
            $suggestions[intval($row['rmm_client_id'])] = $by_key[$key][0];
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
        return [[], ['kind' => 'Pick which RMM it is.']];
    }

    $name = rmmText($in['name'] ?? '', 100);
    if ($name === '') {
        $name = $kind['name'];
    }
    $id = intval($before['rmm_provider_id'] ?? 0);
    $name_sql = escapeSql($name);
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM rmm_providers WHERE rmm_provider_name = '$name_sql' AND rmm_provider_id <> $id LIMIT 1"))) {
        $errors['name'] = 'Another provider is called that - give this one a name of its own, like ' . $kind['name'] . ' (second office).';
    }

    $after = [
        'rmm_provider_kind' => $kind_key,
        'rmm_provider_name' => $name,
        'rmm_provider_active' => $before ? (!empty($in['active']) ? 1 : 0) : 1,
    ];
    foreach (['api_url', 'web_url', 'api_key', 'api_secret'] as $field) {
        $column = "rmm_provider_$field";
        $had = (string) ($before[$column] ?? '');
        $about = $kind['fields'][$field] ?? null;
        if (!$about) {
            $after[$column] = $had;
            continue;
        }
        $typed = trim((string) ($in[$field] ?? ''));
        if ($about['type'] === 'url') {
            $clean = rmmUrlClean($typed);
            if ($typed === '' && !empty($about['required'])) {
                $errors[$field] = $about['needed'];
            } elseif ($typed !== '' && ($clean === '' || mb_strlen($clean) > 500)) {
                $errors[$field] = 'That isn\'t a web address ITFlow can use - just the address, like ' . $about['placeholder'] . '.';
            }
            $after[$column] = $clean;
        } else {
            if ($typed === '' && $had === '' && !empty($about['required'])) {
                $errors[$field] = $about['needed'];
            } elseif ($typed !== '' && (mb_strlen($typed) > 500 || preg_match('/\s/', $typed))) {
                $errors[$field] = 'That doesn\'t look like ' . $kind['name'] . '\'s ' . rmmFieldWords($about['label']) . ' - paste just the ' . rmmFieldWords($about['label']) . '.';
            }
            $after[$column] = $typed !== '' ? $typed : $had;
        }
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
        if ((string) $before[$column] === (string) $after[$column]) {
            continue;
        }
        $label = rmmFieldWords($about['label']);
        if ($about['type'] === 'secret') {
            $lines[] = "$label changed";
        } else {
            $lines[] = $after[$column] === '' ? "$label cleared" : "$label " . $after[$column];
        }
    }
    if (intval($before['rmm_provider_active']) !== intval($after['rmm_provider_active'])) {
        $lines[] = intval($after['rmm_provider_active']) ? 'turned on' : 'turned off';
    }
    return $lines;
}
