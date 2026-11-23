<?php

/*
 * UniFi - the first network platform (Admin > Network Platforms), kinds of the RMM providers'
 * engine (functions/rmm.php) in the network family: a platform's sites are linked to clients,
 * and its gateways, switches and access points become assets kept up to date, each known by
 * its MAC address - so a device keeps its asset when its controller moves.
 *
 * Three ways in, one kind each:
 *
 *   unifi        A controller, signed in to with a local admin's user name and password - the
 *                classic, self-hosted UniFi Network application, which can't make API keys
 *                (POST /api/login, then /api/s/<site>/...). A UniFi OS console or UniFi OS
 *                Server is signed in to the same way when it is one (/api/auth/login, and the
 *                same API under /proxy/network). This API isn't documented by Ubiquiti, but
 *                it is what UniFi's own pages use.
 *   unifi_os     A UniFi OS console, or UniFi OS Server, with an API key made under Settings >
 *                Control Plane > Integrations - the official Network API, at
 *                /proxy/network/integration/v1 (or /integration/v1 on a controller that has it).
 *   unifi_cloud  Every console a UI account owns - or every one of an organization's, with an
 *                organization key - through the Site Manager API at api.ui.com and its
 *                connector to each console's official Network API (console firmware 5.0.3 or
 *                later). Nothing needs to reach the consoles.
 *
 * A site's id there: the site's short name (default) on a controller, its id from the Network
 * API on a console, and - through Site Manager - its console's and its own, as one.
 */

// Where the Site Manager API is
defined('UNIFI_SITE_MANAGER_URL') || define('UNIFI_SITE_MANAGER_URL', 'https://api.ui.com');

/** The UniFi kinds - one for each way in. */
function rmmUnifiKinds(): array
{
    return [
        'unifi' => rmmUnifiKind('unifi'),
        'unifi_os' => rmmUnifiKind('unifi_os'),
        'unifi_cloud' => rmmUnifiKind('unifi_cloud'),
    ];
}

/** One UniFi kind, as rmmProviderKinds() describes a kind. */
function rmmUnifiKind(string $key): array
{
    $insecure = [
        'label' => 'Accept the certificate it made for itself', 'icon' => 'fa-certificate', 'type' => 'switch', 'required' => false, 'placeholder' => '',
        'help' => 'A self-hosted controller makes its own certificate, which nothing trusts - turn this on for one you run yourself. Leave it off for an address with a real certificate.',
        'needed' => '', 'on' => 'its own certificate accepted', 'off' => 'its certificate checked',
    ];
    $kind = [
        'family' => 'network',
        'short' => 'UniFi',
        'icon' => 'fas fa-wifi',
        'seal' => true,
        'device_kinds' => ['Firewall/Router' => 'Gateways', 'Switch' => 'Switches', 'Access Point' => 'Access points', 'Other' => 'Other UniFi devices'],
        'attention' => ['patches_pending' => 'Firmware update available'],
        'links' => ['open' => 'Open in UniFi'],
        'clients' => 'rmmUnifiSites',
        'devices' => 'rmmUnifiDevices',
        'networks' => 'rmmUnifiNetworks',
        'dashboard' => 'rmmUnifiDashboard',
        'docs_url' => 'https://help.ui.com/hc/en-us/articles/30076656117655',
    ];
    if ($key === 'unifi') {
        return array_merge($kind, [
            'name' => 'UniFi controller (sign-in)',
            'default_name' => 'UniFi Controller',
            'about' => 'A self-hosted UniFi Network controller, signed in to with a local admin - its sites, and their gateways, switches and access points as assets.',
            'docs_url' => '',
            'fields' => [
                'api_url' => [
                    'label' => 'Controller address', 'icon' => 'fa-server', 'type' => 'url', 'required' => true, 'placeholder' => 'https://unifi.example.com:8443',
                    'help' => 'As you open it in a browser, with its port - 8443 on a classic controller.',
                    'needed' => 'The controller\'s address is needed - as you open it in a browser, like https://unifi.example.com:8443.',
                ],
                'api_key' => [
                    'label' => 'User name', 'icon' => 'fa-user', 'type' => 'text', 'required' => true, 'placeholder' => 'itflow',
                    'help' => 'A local admin of the controller, just for ITFlow - one with view-only access is enough. Not a UI account with two-step sign-in.',
                    'needed' => 'The user name ITFlow signs in with is needed.',
                ],
                'api_secret' => [
                    'label' => 'Password', 'icon' => 'fa-lock', 'type' => 'secret', 'required' => true, 'placeholder' => '', 'spaces' => true,
                    'help' => 'Kept encrypted with this ITFlow\'s own key.',
                    'needed' => 'The password is needed.',
                ],
                'insecure' => $insecure,
            ],
            'hints' => [
                'auth' => 'check the user name and password - a local admin of the controller, not a UI account.',
                'not_found' => 'is that the controller\'s address, with its port?',
                'not_json' => 'is that the controller\'s address, with its port?',
                'certificate' => ' - a controller with a certificate it made for itself needs "Accept the certificate it made for itself" on.',
            ],
        ]);
    }
    if ($key === 'unifi_os') {
        return array_merge($kind, [
            'name' => 'UniFi console (API key)',
            'default_name' => 'UniFi Console',
            'about' => 'A UniFi OS console or UniFi OS Server, with an API key - the official Network API.',
            'fields' => [
                'api_url' => [
                    'label' => 'Console address', 'icon' => 'fa-server', 'type' => 'url', 'required' => true, 'placeholder' => 'https://192.168.1.1',
                    'help' => 'As you open it in a browser - with its port on UniFi OS Server, usually 11443. ITFlow needs to be able to reach it.',
                    'needed' => 'The console\'s address is needed - as you open it in a browser.',
                ],
                'api_key' => [
                    'label' => 'API key', 'icon' => 'fa-key', 'type' => 'secret', 'required' => true, 'placeholder' => '',
                    'help' => 'In UniFi Network: Settings > Control Plane > Integrations. Kept encrypted with this ITFlow\'s own key.',
                    'needed' => 'The API key is needed - make one in UniFi Network under Settings > Control Plane > Integrations.',
                ],
                'insecure' => $insecure,
            ],
            'hints' => [
                'auth' => 'check the key, made in UniFi Network under Settings > Control Plane > Integrations.',
                'not_found' => 'is that the console\'s address? A classic controller has no API keys - add it as a UniFi controller (sign-in).',
                'not_json' => 'is that the console\'s address?',
                'certificate' => ' - a console with a certificate it made for itself needs "Accept the certificate it made for itself" on.',
            ],
        ]);
    }
    return array_merge($kind, [
        'name' => 'UniFi Site Manager (cloud)',
        'default_name' => 'UniFi Site Manager',
        'about' => 'Every UniFi console your UI account or organization has, through api.ui.com - nothing needs to reach them.',
        'fields' => [
            'api_key' => [
                'label' => 'Site Manager API key', 'icon' => 'fa-key', 'type' => 'secret', 'required' => true, 'placeholder' => '',
                'help' => 'At unifi.ui.com, under API. An organization\'s key reaches every console in it; anyone else\'s reaches the consoles they own. Consoles need firmware 5.0.3 or later. Kept encrypted with this ITFlow\'s own key.',
                'needed' => 'The Site Manager API key is needed - make one at unifi.ui.com, under API.',
            ],
        ],
        'hints' => [
            'auth' => 'check the key, made at unifi.ui.com under API - and, for a console someone else owns, that it is an organization key.',
            'not_found' => 'that console may need firmware 5.0.3 or later, or have no Network application.',
            'not_json' => '',
        ],
    ]);
}

/*
 * ------------------------------------------------------------------ Asking UniFi
 */

/** The options every request of a platform takes - its own certificate accepted, when it says so. */
function rmmUnifiOpts(array $provider): array
{
    return ['insecure' => intval($provider['rmm_provider_insecure'] ?? 0) === 1];
}

/**
 * Signed in to a controller (unifi) - ['ok', 'cookie', 'prefix', 'csrf', 'error']. A classic
 * controller signs in at /api/login; a UniFi OS one answers that with 404 and signs in at
 * /api/auth/login, its Network API under /proxy/network. Kept for the request; $again signs in
 * afresh, for a session that ended.
 */
function rmmUnifiSession(array $provider, bool $again = false): array
{
    static $sessions = [];
    $id = intval($provider['rmm_provider_id'] ?? 0);
    if (!$again && isset($sessions[$id])) {
        return $sessions[$id];
    }
    $base = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    $user = trim((string) ($provider['rmm_provider_api_key'] ?? ''));
    $password = rmmSecret($provider, 'api_secret');
    $name = rmmProviderName($provider);
    if ($base === '' || $user === '' || $password === '') {
        return ['ok' => false, 'cookie' => '', 'prefix' => '', 'csrf' => '', 'error' => 'Its address, user name and password aren\'t set' . ($password === '' && (string) ($provider['rmm_provider_api_secret'] ?? '') !== '' ? ' - its password can\'t be read, so type it again' : '') . '.'];
    }
    $body = ['username' => $user, 'password' => $password, 'remember' => false];
    $prefix = '';
    $answer = rmmHttp($provider, 'POST', "$base/api/login", [], $body, 30, rmmUnifiOpts($provider));
    if ($answer['code'] === 404) {
        $prefix = '/proxy/network';
        $answer = rmmHttp($provider, 'POST', "$base/api/auth/login", [], $body, 30, rmmUnifiOpts($provider));
    }
    if (in_array($answer['code'], [400, 401, 403], true)) {
        $said = is_array($answer['json']) ? (string) ($answer['json']['meta']['msg'] ?? $answer['json']['message'] ?? '') : '';
        $why = str_contains(strtolower($said), 'ubic_2fa') || str_contains(strtolower($said), '2fa') ? ' - that account has two-step sign-in, so use a local admin without it' : ' - check the user name and password: a local admin of the controller, not a UI account';
        return $sessions[$id] = ['ok' => false, 'cookie' => '', 'prefix' => $prefix, 'csrf' => '', 'error' => "$name turned the sign-in away (HTTP {$answer['code']})$why."];
    }
    if (!$answer['ok']) {
        return $sessions[$id] = ['ok' => false, 'cookie' => '', 'prefix' => $prefix, 'csrf' => '', 'error' => $answer['error']];
    }
    $cookies = array_filter($answer['headers']['cookies'] ?? [], function ($cookie) {
        return preg_match('/^(unifises|TOKEN|csrf_token)=/', $cookie) === 1;
    });
    if (!$cookies) {
        return $sessions[$id] = ['ok' => false, 'cookie' => '', 'prefix' => $prefix, 'csrf' => '', 'error' => "$name answered the sign-in, but didn't sign ITFlow in - is that a UniFi controller's address?"];
    }
    return $sessions[$id] = ['ok' => true, 'cookie' => implode('; ', $cookies), 'prefix' => $prefix, 'csrf' => (string) ($answer['headers']['x-csrf-token'] ?? ''), 'error' => ''];
}

/**
 * Ask a controller (unifi) - GET <prefix>/api/<path>: ['ok', 'data', 'error', 'code']. Its answers are
 * {meta: {rc}, data: [...]}; a session that ended is signed in again, once.
 */
function rmmUnifiController(array $provider, string $path, int $timeout = 60): array
{
    $base = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    $session = rmmUnifiSession($provider);
    for ($try = 0; $try < 2; $try++) {
        if (!$session['ok']) {
            return ['ok' => false, 'data' => [], 'error' => $session['error'], 'code' => 0];
        }
        $headers = $session['csrf'] !== '' ? ['X-CSRF-Token: ' . $session['csrf']] : [];
        $answer = rmmHttp($provider, 'GET', $base . $session['prefix'] . '/api/' . ltrim($path, '/'), $headers, null, $timeout, rmmUnifiOpts($provider) + ['cookie' => $session['cookie']]);
        if ($answer['code'] === 401 && $try === 0) {
            $session = rmmUnifiSession($provider, true);
            continue;
        }
        break;
    }
    if (!$answer['ok']) {
        return ['ok' => false, 'data' => [], 'error' => $answer['error'], 'code' => $answer['code']];
    }
    $rc = (string) ($answer['json']['meta']['rc'] ?? '');
    if ($rc !== 'ok' || !rmmIsList($answer['json']['data'] ?? null)) {
        $said = rmmText($answer['json']['meta']['msg'] ?? '', 200);
        return ['ok' => false, 'data' => [], 'error' => rmmProviderName($provider) . ' answered, but not with what was asked for' . ($said !== '' ? " ($said)" : '') . ' - nothing was changed', 'code' => $answer['code']];
    }
    return ['ok' => true, 'data' => $answer['json']['data'], 'error' => '', 'code' => $answer['code']];
}

/**
 * Where a console's official Network API is (unifi_os) - /proxy/network/integration on UniFi OS,
 * /integration on a controller that has it. ['ok', 'base', 'version', 'error'], found once a request.
 */
function rmmUnifiNetworkApi(array $provider): array
{
    static $found = [];
    $id = intval($provider['rmm_provider_id'] ?? 0);
    if (isset($found[$id])) {
        return $found[$id];
    }
    $base = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    $key = rmmSecret($provider, 'api_key');
    if ($base === '' || $key === '') {
        return ['ok' => false, 'base' => '', 'version' => '', 'error' => 'Its address and API key aren\'t set' . ($key === '' && (string) ($provider['rmm_provider_api_key'] ?? '') !== '' ? ' - its key can\'t be read, so paste it again' : '') . '.'];
    }
    $first = null;
    foreach (['/proxy/network/integration', '/integration'] as $path) {
        $answer = rmmHttp($provider, 'GET', "$base$path/v1/info", ['X-API-KEY: ' . $key], null, 30, rmmUnifiOpts($provider));
        if ($answer['ok']) {
            return $found[$id] = ['ok' => true, 'base' => "$base$path", 'version' => rmmText($answer['json']['applicationVersion'] ?? '', 50), 'error' => ''];
        }
        $first = $first ?? $answer;
        if ($answer['code'] !== 404) {
            break;
        }
    }
    return $found[$id] = ['ok' => false, 'base' => '', 'version' => '', 'error' => $first['error']];
}

/**
 * Ask the official Network API - of a console (unifi_os) by its address, or of one of the account's
 * consoles through Site Manager's connector (unifi_cloud, $console its id). A list is read page by
 * page: ['ok', 'data' (every item, or the one object), 'total', 'error', 'code'].
 */
function rmmUnifiNetwork(array $provider, string $path, array $query = [], string $console = '', bool $list = true): array
{
    if ($provider['rmm_provider_kind'] === 'unifi_cloud') {
        $key = rmmSecret($provider, 'api_key');
        $base = rtrim(UNIFI_SITE_MANAGER_URL, '/') . '/v1/connector/consoles/' . rawurlencode($console) . '/proxy/network/integration';
    } else {
        $api = rmmUnifiNetworkApi($provider);
        if (!$api['ok']) {
            return ['ok' => false, 'data' => [], 'total' => 0, 'error' => $api['error'], 'code' => 0];
        }
        $key = rmmSecret($provider, 'api_key');
        $base = $api['base'];
    }
    $items = [];
    $offset = 0;
    for ($page = 0; $page < 50; $page++) {
        $ask = $list ? $query + ['offset' => $offset, 'limit' => $query['limit'] ?? 200] : $query;
        $answer = rmmHttp($provider, 'GET', $base . '/v1/' . ltrim($path, '/') . ($ask ? '?' . http_build_query($ask, '', '&') : ''), ['X-API-KEY: ' . $key], null, 30, rmmUnifiOpts($provider));
        if (!$answer['ok']) {
            return ['ok' => false, 'data' => [], 'total' => 0, 'error' => $answer['error'], 'code' => $answer['code']];
        }
        if (!$list) {
            return ['ok' => true, 'data' => $answer['json'], 'total' => 1, 'error' => '', 'code' => $answer['code']];
        }
        if (!rmmIsList($answer['json']['data'] ?? null)) {
            return ['ok' => false, 'data' => [], 'total' => 0, 'error' => rmmProviderName($provider) . ' answered, but not with a list - nothing was changed', 'code' => $answer['code']];
        }
        $items = array_merge($items, $answer['json']['data']);
        $total = intval($answer['json']['totalCount'] ?? count($items));
        $offset += count($answer['json']['data']);
        if (!$answer['json']['data'] || $offset >= $total || !empty($query['limit'])) {
            return ['ok' => true, 'data' => $items, 'total' => $total, 'error' => '', 'code' => $answer['code']];
        }
    }
    return ['ok' => true, 'data' => $items, 'total' => count($items), 'error' => '', 'code' => 200];
}

/** The consoles a Site Manager key reaches (unifi_cloud) - ['ok', 'consoles' => [id => name], 'skipped', 'error']. */
function rmmUnifiConsoles(array $provider): array
{
    $key = rmmSecret($provider, 'api_key');
    if ($key === '') {
        return ['ok' => false, 'consoles' => [], 'skipped' => 0, 'error' => 'Its Site Manager API key isn\'t set' . ((string) ($provider['rmm_provider_api_key'] ?? '') !== '' ? ' - it can\'t be read, so paste it again' : '') . '.'];
    }
    $consoles = [];
    $skipped = 0;
    $next = '';
    for ($page = 0; $page < 50; $page++) {
        $query = ['pageSize' => 200] + ($next !== '' ? ['nextToken' => $next] : []);
        $answer = rmmHttp($provider, 'GET', rtrim(UNIFI_SITE_MANAGER_URL, '/') . '/v1/hosts?' . http_build_query($query, '', '&'), ['X-API-KEY: ' . $key]);
        if (!$answer['ok']) {
            return ['ok' => false, 'consoles' => [], 'skipped' => 0, 'error' => $answer['error']];
        }
        if (!rmmIsList($answer['json']['data'] ?? null)) {
            return ['ok' => false, 'consoles' => [], 'skipped' => 0, 'error' => 'Site Manager answered, but not with a list of consoles - nothing was changed'];
        }
        foreach ($answer['json']['data'] as $host) {
            if (!is_array($host) || !isset($host['id'])) {
                continue;
            }
            // Only UniFi OS consoles have the connector - a self-hosted Network server doesn't
            if (strtolower((string) ($host['type'] ?? '')) !== 'console') {
                $skipped++;
                continue;
            }
            $state = is_array($host['reportedState'] ?? null) ? $host['reportedState'] : [];
            $name = rmmText($state['name'] ?? $state['hostname'] ?? '', 150);
            $consoles[(string) $host['id']] = $name !== '' ? $name : 'Console ' . substr((string) $host['id'], 0, 8);
        }
        $next = (string) ($answer['json']['nextToken'] ?? '');
        if ($next === '') {
            break;
        }
    }
    return ['ok' => true, 'consoles' => $consoles, 'skipped' => $skipped, 'error' => ''];
}

/*
 * ------------------------------------------------------------------ Its sites and devices
 */

/** What a Site Manager site's id stands for - remote id => [console, site] - from the last listing this request. */
function rmmUnifiCloudSites(?array $set = null): array
{
    static $sites = [];
    if ($set !== null) {
        $sites = $set + $sites;
    }
    return $sites;
}

/** A platform's sites - ['ok', 'clients' => [id, name, sites, devices], 'error', 'about'], as a kind's clients function gives them. */
/** A controller or console with only its Default site goes by the platform's name instead. */
function rmmUnifiLoneDefault(array $provider, array $sites): array
{
    if (count($sites) === 1 && strtolower($sites[0]['name']) === 'default') {
        $sites[0]['name'] = rmmText($provider['rmm_provider_name'] ?? '', 200) ?: $sites[0]['name'];
    }
    return $sites;
}

function rmmUnifiSites(array $provider): array
{
    $sites = [];
    $kind = (string) $provider['rmm_provider_kind'];

    if ($kind === 'unifi') {
        // stat/sites has the device counts; an admin limited to some sites may only have self/sites
        $answer = rmmUnifiController($provider, 'stat/sites');
        if (!$answer['ok'] && in_array($answer['code'], [401, 403], true)) {
            $answer = rmmUnifiController($provider, 'self/sites');
        }
        if (!$answer['ok']) {
            return ['ok' => false, 'clients' => [], 'error' => $answer['error']];
        }
        foreach ($answer['data'] as $site) {
            if (!is_array($site) || !preg_match('/^[A-Za-z0-9_.-]{1,100}$/', (string) ($site['name'] ?? ''))) {
                continue;
            }
            $devices = intval($site['device_count'] ?? 0);
            if (!$devices && is_array($site['health'] ?? null)) {
                foreach ($site['health'] as $health) {
                    if (is_array($health) && in_array($health['subsystem'] ?? '', ['wlan', 'lan', 'wan'], true)) {
                        $devices += intval($health['num_adopted'] ?? 0);
                    }
                }
            }
            $label = rmmText($site['desc'] ?? '', 200);
            $sites[] = ['id' => (string) $site['name'], 'name' => $label !== '' ? $label : (string) $site['name'], 'sites' => 1, 'devices' => $devices];
        }
        $sites = rmmUnifiLoneDefault($provider, $sites);
        $session = rmmUnifiSession($provider);
        return ['ok' => true, 'clients' => $sites, 'error' => '', 'about' => $session['prefix'] !== '' ? 'It is a UniFi OS console, signed in to with a user name.' : 'It is a classic controller, signed in to with a user name.'];
    }

    if ($kind === 'unifi_os') {
        $answer = rmmUnifiNetwork($provider, 'sites');
        if (!$answer['ok']) {
            return ['ok' => false, 'clients' => [], 'error' => $answer['error']];
        }
        foreach ($answer['data'] as $site) {
            if (!is_array($site) || !preg_match('/^[A-Za-z0-9-]{1,100}$/', (string) ($site['id'] ?? ''))) {
                continue;
            }
            $count = rmmUnifiNetwork($provider, 'sites/' . $site['id'] . '/devices', ['limit' => 1]);
            $label = rmmText($site['name'] ?? '', 200);
            $sites[] = ['id' => (string) $site['id'], 'name' => $label !== '' ? $label : (string) ($site['internalReference'] ?? $site['id']), 'sites' => 1, 'devices' => $count['ok'] ? $count['total'] : 0];
        }
        $sites = rmmUnifiLoneDefault($provider, $sites);
        $api = rmmUnifiNetworkApi($provider);
        return ['ok' => true, 'clients' => $sites, 'error' => '', 'about' => 'Its Network API' . ($api['version'] !== '' ? ' is version ' . $api['version'] : ' answered') . '.'];
    }

    // Through Site Manager - each console's sites, named with the console when it has more than one
    $consoles = rmmUnifiConsoles($provider);
    if (!$consoles['ok']) {
        return ['ok' => false, 'clients' => [], 'error' => $consoles['error']];
    }
    $map = [];
    $unreached = [];
    foreach ($consoles['consoles'] as $console => $console_name) {
        $answer = rmmUnifiNetwork($provider, 'sites', [], $console);
        if (!$answer['ok']) {
            $unreached[] = $console_name;
            continue;
        }
        $many = count($answer['data']) > 1;
        foreach ($answer['data'] as $site) {
            if (!is_array($site) || !preg_match('/^[A-Za-z0-9-]{1,60}$/', (string) ($site['id'] ?? ''))) {
                continue;
            }
            $id = substr(md5($console), 0, 16) . '.' . $site['id'];
            $map[$id] = [$console, (string) $site['id']];
            $count = rmmUnifiNetwork($provider, 'sites/' . $site['id'] . '/devices', ['limit' => 1], $console);
            // A console's one Default site is the console; the rest go by their names, with the console's when it has more
            $label = rmmText($site['name'] ?? '', 100);
            $plain = $label === '' || strtolower($label) === 'default';
            $sites[] = ['id' => $id, 'name' => $plain ? $console_name . ($many ? ' (Default)' : '') : ($many ? "$label ($console_name)" : $label),
                'sites' => 1, 'devices' => $count['ok'] ? $count['total'] : 0];
        }
    }
    rmmUnifiCloudSites($map);
    if ($unreached && !$sites) {
        return ['ok' => false, 'clients' => [], 'error' => 'Site Manager answered, but none of its consoles did through its connector (' . implode(', ', array_slice($unreached, 0, 5)) . ') - consoles need firmware 5.0.3 or later and the Network application'];
    }
    $about = count($consoles['consoles']) . ' console' . (count($consoles['consoles']) === 1 ? '' : 's') . ' through Site Manager';
    if ($unreached) {
        $about .= ' - ' . count($unreached) . ' didn\'t answer through its connector: ' . implode(', ', array_slice($unreached, 0, 5));
    }
    if ($consoles['skipped']) {
        $about .= '; ' . $consoles['skipped'] . ' self-hosted controller' . ($consoles['skipped'] === 1 ? '' : 's') . ' left out - add ' . ($consoles['skipped'] === 1 ? 'it' : 'them') . ' as a UniFi controller (sign-in)';
    }
    return ['ok' => true, 'clients' => $sites, 'error' => '', 'about' => "It reaches $about."];
}

/** One site's devices - ['ok', 'devices', 'error'], each as rmmDeviceSave() takes it. */
function rmmUnifiDevices(array $provider, string $site): array
{
    $kind = (string) $provider['rmm_provider_kind'];
    $devices = [];
    if ($kind === 'unifi') {
        $answer = rmmUnifiController($provider, 's/' . rawurlencode($site) . '/stat/device');
        if (!$answer['ok']) {
            return ['ok' => false, 'devices' => [], 'error' => $answer['error']];
        }
        foreach ($answer['data'] as $device) {
            if (is_array($device) && ($device['adopted'] ?? true) !== false && rmmMac($device['mac'] ?? '') !== '') {
                $devices[] = rmmUnifiControllerDevice($device);
            }
        }
        return ['ok' => true, 'devices' => $devices, 'error' => ''];
    }

    $where = rmmUnifiSiteWhere($provider, $site);
    if (!$where) {
        return ['ok' => false, 'devices' => [], 'error' => 'Site Manager no longer lists that site'];
    }
    [$console, $site_id] = $where;
    $answer = rmmUnifiNetwork($provider, 'sites/' . rawurlencode($site_id) . '/devices', [], $console);
    if (!$answer['ok']) {
        return ['ok' => false, 'devices' => [], 'error' => $answer['error']];
    }
    foreach ($answer['data'] as $device) {
        if (is_array($device) && rmmMac($device['macAddress'] ?? '') !== '') {
            $devices[] = rmmUnifiNetworkDevice($device);
        }
    }
    return ['ok' => true, 'devices' => $devices, 'error' => ''];
}

/** Where a site is - for Site Manager, the console it is on and its id there: [console, site id], or null when it no longer lists it. */
function rmmUnifiSiteWhere(array $provider, string $site): ?array
{
    if ($provider['rmm_provider_kind'] !== 'unifi_cloud') {
        return ['', $site];
    }
    $map = rmmUnifiCloudSites();
    if (!isset($map[$site])) {
        rmmUnifiSites($provider);
        $map = rmmUnifiCloudSites();
    }
    return $map[$site] ?? null;
}

/**
 * One site's networks and VLANs - ['ok', 'networks', 'error', 'unoffered'], each as rmmNetworkSave() takes
 * it (functions/rmm_network.php). Only the ones the gateway or a switch routes, with a subnet, and on:
 * a WAN, a site-to-site VPN or a VLAN with no subnet isn't one of the client's networks.
 */
function rmmUnifiNetworks(array $provider, string $site): array
{
    $networks = [];
    if ($provider['rmm_provider_kind'] === 'unifi') {
        $answer = rmmUnifiController($provider, 's/' . rawurlencode($site) . '/rest/networkconf');
        if (!$answer['ok']) {
            return ['ok' => false, 'networks' => [], 'error' => $answer['error'], 'unoffered' => false];
        }
        $purposes = ['corporate' => 'lan', 'guest' => 'guest', 'remote-user-vpn' => 'vpn'];
        foreach ($answer['data'] as $net) {
            if (!is_array($net) || !isset($purposes[$net['purpose'] ?? '']) || ($net['enabled'] ?? true) === false || trim((string) ($net['ip_subnet'] ?? '')) === '') {
                continue;
            }
            $dhcp = !empty($net['dhcpd_enabled']);
            $dns = [];
            if ($dhcp && !empty($net['dhcpd_dns_enabled'])) {
                foreach (['dhcpd_dns_1', 'dhcpd_dns_2', 'dhcpd_dns_3', 'dhcpd_dns_4'] as $field) {
                    if (trim((string) ($net[$field] ?? '')) !== '') {
                        $dns[] = (string) $net[$field];
                    }
                }
            }
            $networks[] = [
                'remote_id' => (string) ($net['_id'] ?? ''),
                'name' => (string) ($net['name'] ?? ''),
                'purpose' => $purposes[$net['purpose']],
                'vlan' => !empty($net['vlan_enabled']) ? intval($net['vlan'] ?? 0) : 0,
                'subnet' => (string) $net['ip_subnet'],
                'dhcp_start' => $dhcp ? (string) ($net['dhcpd_start'] ?? '') : '',
                'dhcp_stop' => $dhcp ? (string) ($net['dhcpd_stop'] ?? '') : '',
                'dns' => $dns,
            ];
        }
        return ['ok' => true, 'networks' => $networks, 'error' => '', 'unoffered' => false];
    }

    // The official Network API - the list, then each network's details for its subnet and DHCP
    $where = rmmUnifiSiteWhere($provider, $site);
    if (!$where) {
        return ['ok' => false, 'networks' => [], 'error' => 'Site Manager no longer lists that site', 'unoffered' => false];
    }
    [$console, $site_id] = $where;
    $answer = rmmUnifiNetwork($provider, 'sites/' . rawurlencode($site_id) . '/networks', [], $console);
    if (!$answer['ok']) {
        // A Network version older than its networks endpoint - nothing to bring in, and nothing wrong
        return $answer['code'] === 404 ? ['ok' => true, 'networks' => [], 'error' => '', 'unoffered' => true] : ['ok' => false, 'networks' => [], 'error' => $answer['error'], 'unoffered' => false];
    }
    foreach ($answer['data'] as $item) {
        $id = is_array($item) ? (string) ($item['id'] ?? '') : '';
        if ($id === '' || !preg_match('/^[A-Za-z0-9-]{1,100}$/', $id) || ($item['enabled'] ?? true) === false || !in_array($item['management'] ?? '', ['GATEWAY', 'SWITCH'], true)) {
            continue;
        }
        $details = rmmUnifiNetwork($provider, 'sites/' . rawurlencode($site_id) . '/networks/' . $id, [], $console, false);
        if (!$details['ok']) {
            return ['ok' => false, 'networks' => [], 'error' => $details['error'], 'unoffered' => false];
        }
        $ipv4 = is_array($details['data']['ipv4Configuration'] ?? null) ? $details['data']['ipv4Configuration'] : [];
        if (trim((string) ($ipv4['hostIpAddress'] ?? '')) === '' || !isset($ipv4['prefixLength'])) {
            continue;
        }
        $dhcp = is_array($ipv4['dhcpConfiguration'] ?? null) && ($ipv4['dhcpConfiguration']['mode'] ?? '') === 'SERVER' ? $ipv4['dhcpConfiguration'] : [];
        $networks[] = [
            'remote_id' => $id,
            'name' => (string) ($details['data']['name'] ?? $item['name'] ?? ''),
            'purpose' => 'lan',
            'vlan' => intval($details['data']['vlanId'] ?? $item['vlanId'] ?? 0),
            'subnet' => $ipv4['hostIpAddress'] . '/' . intval($ipv4['prefixLength']),
            'dhcp_start' => (string) ($dhcp['ipAddressRange']['start'] ?? ''),
            'dhcp_stop' => (string) ($dhcp['ipAddressRange']['stop'] ?? ''),
            'dns' => array_map('strval', (array) ($dhcp['dnsServerIpAddressesOverride'] ?? [])),
        ];
    }
    return ['ok' => true, 'networks' => $networks, 'error' => '', 'unoffered' => false];
}

/** A controller's device (stat/device) as a device. */
function rmmUnifiControllerDevice(array $device): array
{
    $mac = rmmMac($device['mac'] ?? '');
    $type = strtolower((string) ($device['type'] ?? ''));
    $model = rmmUnifiModelName((string) ($device['model'] ?? ''));
    $firmware = rmmText($device['version'] ?? '', 100);
    // 1 connected, 4 upgrading and 5 provisioning are up; 0 disconnected, 6 heartbeat missed, 10 and 11 isolated are down
    $state = intval($device['state'] ?? -1);
    $status = in_array($state, [1, 4, 5], true) ? 'online' : (in_array($state, [0, 6, 10, 11], true) ? 'offline' : '');
    $uptime = intval($device['uptime'] ?? 0);
    $gateway = in_array($type, ['ugw', 'udm', 'uxg'], true);
    $name = rmmText($device['name'] ?? '', 200);
    $ip = rmmText($device['ip'] ?? '', 100);
    return [
        'remote_id' => $mac,
        'mac' => $mac,
        'hostname' => $name !== '' ? $name : trim($model . ' ' . strtoupper(substr($mac, -6))),
        'site' => '',
        'role' => '',
        'platform' => 'unifi',
        'os' => $firmware,
        'status' => $status,
        'last_seen_at' => rmmTime($device['last_seen'] ?? null),
        'boot_at' => $status === 'online' && $uptime > 0 ? date('Y-m-d H:i:s', time() - $uptime) : null,
        'public_ip' => $gateway ? (string) ($device['wan1']['ip'] ?? '') : '',
        'local_ips' => $ip,
        'patches_pending' => !empty($device['upgradable']),
        'agent_version' => '',
        'asset' => [
            'type' => $gateway ? 'Firewall/Router' : ($type === 'usw' ? 'Switch' : (in_array($type, ['uap', 'ubb'], true) ? 'Access Point' : 'Other')),
            'make' => 'Ubiquiti',
            'model' => $model,
            'serial' => (string) ($device['serial'] ?? ''),
            'os' => $firmware,
            'ip' => $ip,
            'mac' => $mac,
        ],
    ];
}

/** The official Network API's device (sites/{id}/devices) as a device. */
function rmmUnifiNetworkDevice(array $device): array
{
    $mac = rmmMac($device['macAddress'] ?? '');
    $features = array_map('strval', (array) ($device['features'] ?? []));
    $state = strtoupper((string) ($device['state'] ?? ''));
    $status = $state === 'ONLINE' || $state === 'UPDATING' ? 'online' : (in_array($state, ['OFFLINE', 'CONNECTION_INTERRUPTED', 'ISOLATED'], true) ? 'offline' : '');
    $model = rmmUnifiModelName((string) ($device['model'] ?? ''));
    $firmware = rmmText($device['firmwareVersion'] ?? '', 100);
    $name = rmmText($device['name'] ?? '', 200);
    $ip = rmmText($device['ipAddress'] ?? '', 100);
    return [
        'remote_id' => $mac,
        'mac' => $mac,
        'hostname' => $name !== '' ? $name : trim($model . ' ' . strtoupper(substr($mac, -6))),
        'site' => '',
        'role' => '',
        'platform' => 'unifi',
        'os' => $firmware,
        'status' => $status,
        'local_ips' => $ip,
        'patches_pending' => !empty($device['firmwareUpdatable']),
        'agent_version' => '',
        'asset' => [
            'type' => in_array('gateway', $features, true) ? 'Firewall/Router' : (in_array('switching', $features, true) ? 'Switch' : (in_array('accessPoint', $features, true) ? 'Access Point' : 'Other')),
            'make' => 'Ubiquiti',
            'model' => $model,
            'serial' => '',
            'os' => $firmware,
            'ip' => $ip,
            'mac' => $mac,
        ],
    ];
}

/**
 * A model code as people know it - U7PG2 is the UAP-AC-Pro. The code as it is for one not here,
 * and a name - the official Network API often says the name itself - as it is.
 */
function rmmUnifiModelName(string $code): string
{
    $names = [
        'U7PG2' => 'UAP-AC-Pro', 'U7LT' => 'UAP-AC-Lite', 'U7LR' => 'UAP-AC-LR', 'U7MSH' => 'UAP-AC-Mesh', 'U7NHD' => 'UAP-nanoHD',
        'U7HD' => 'UAP-AC-HD', 'U7SHD' => 'UAP-AC-SHD', 'UFLHD' => 'UAP-FlexHD', 'U7IW' => 'UAP-AC-IW',
        'UAL6' => 'U6-Lite', 'UALR6' => 'U6-LR', 'UAP6MP' => 'U6-Pro', 'UAE6' => 'U6-Enterprise',
        'UGW3' => 'USG', 'UGW4' => 'USG-Pro-4', 'UGWXG' => 'USG-XG-8', 'UDM' => 'UDM', 'UDMPRO' => 'UDM-Pro', 'UDMPROSE' => 'UDM-SE', 'UDR' => 'UDR',
        'US8' => 'US-8', 'US8P60' => 'US-8-60W', 'US8P150' => 'US-8-150W', 'US16P150' => 'US-16-150W', 'US24' => 'US-24', 'US24P250' => 'US-24-250W',
        'US24P500' => 'US-24-500W', 'US48' => 'US-48', 'US48P500' => 'US-48-500W', 'US48P750' => 'US-48-750W',
        'USMINI' => 'USW-Flex-Mini', 'USL8LP' => 'USW-Lite-8-PoE', 'USL16LP' => 'USW-Lite-16-PoE',
    ];
    $code = rmmText($code, 100);
    return $names[strtoupper($code)] ?? $code;
}

/** Where Open in UniFi goes - the controller or console, or unifi.ui.com through Site Manager. */
function rmmUnifiDashboard(array $provider): string
{
    if ($provider['rmm_provider_kind'] === 'unifi_cloud') {
        return 'https://unifi.ui.com';
    }
    return rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
}
