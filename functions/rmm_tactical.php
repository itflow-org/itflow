<?php

/*
 * Tactical RMM - an RMM Providers kind (functions/rmm.php).
 *
 * Its API sits at the api. address its install set up and takes an API key, made in Tactical
 * under Settings > Global Settings > API Keys, sent as X-API-KEY. The key acts as the Tactical
 * user it was made for, so that user's role decides what ITFlow can see. /clients/ lists its
 * clients, /agents/?client= a client's devices. Its dashboard - where a device opens, and
 * Take Control - is at the rmm. address beside it.
 *
 * Its alerts: PATCH /alerts/ with resolvedFilter false lists the open ones its dashboard shows
 * (a check's or agent's dashboard alert setting decides which those are), and GET /alerts/<id>/
 * says whether one has cleared. The key's role needs to be able to list alerts.
 */

/** What Tactical RMM is, as rmmProviderKinds() describes a kind. */
function rmmTacticalKind(): array
{
    return [
        'name' => 'Tactical RMM',
        'short' => 'Tactical',
        'icon' => 'fas fa-laptop-medical',
        'about' => 'Its devices become assets on the clients you link, with whether each is online, who is signed in and what needs attention.',
        'docs_url' => 'https://docs.tacticalrmm.com/functions/api/',
        'fields' => [
            'api_url' => [
                'label' => 'API address', 'icon' => 'fa-server', 'type' => 'url', 'required' => true, 'placeholder' => 'https://api.example.com',
                'help' => 'The address of Tactical\'s API - the api. one its install set up, not the dashboard\'s.',
                'needed' => 'Tactical RMM\'s API address is needed - usually https://api. and your domain.',
            ],
            'web_url' => [
                'label' => 'Dashboard address', 'icon' => 'fa-desktop', 'type' => 'url', 'required' => false, 'placeholder' => 'https://rmm.example.com',
                'help' => 'Where Open in Tactical RMM and Take Control go. Left empty, it is the API\'s address with rmm. for api.',
                'needed' => '',
            ],
            'api_key' => [
                'label' => 'API key', 'icon' => 'fa-key', 'type' => 'secret', 'required' => true, 'placeholder' => '',
                'help' => 'In Tactical RMM: Settings > Global Settings > API Keys. The key can do what its user can, so make it for a user whose role can see clients and agents - and no more than you want ITFlow to do.',
                'needed' => 'The API key is needed - make one in Tactical RMM under Settings > Global Settings > API Keys.',
            ],
        ],
        'hints' => [
            'auth' => 'check the key, and that its user\'s role can see clients and agents - and list alerts, if alerts open tickets.',
            'not_found' => 'is that Tactical\'s API address, usually api. and your domain?',
            'not_json' => 'is that the dashboard\'s address rather than the API\'s?',
        ],
        'links' => ['open' => 'Open in Tactical RMM', 'control' => 'Take Control', 'background' => 'Remote Background'],
        'clients' => 'rmmTacticalClients',
        'devices' => 'rmmTacticalDevices',
        'dashboard' => 'rmmTacticalDashboard',
        'device_urls' => 'rmmTacticalDeviceUrls',
        'alerts' => 'rmmTacticalAlerts',
        'alert' => 'rmmTacticalAlert',
        'alerts_help' => 'These are the alerts Tactical\'s dashboard shows - a check\'s or agent\'s Dashboard alert setting, or its alert template, decides which those are. The API key\'s user needs to be able to list alerts.',
    ];
}

/** Ask Tactical's API. $path is Tactical's, with its trailing slash (/agents/) - it matters there. */
function rmmTacticalRequest(array $provider, string $method, string $path, array $query = [], ?array $body = null, int $timeout = 30): array
{
    $base = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    $key = trim((string) ($provider['rmm_provider_api_key'] ?? ''));
    if ($base === '' || $key === '') {
        return ['ok' => false, 'code' => 0, 'json' => null, 'error' => 'Its API address and key aren\'t set.'];
    }
    $url = $base . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query, '', '&') : '');
    return rmmHttp($provider, $method, $url, ['X-API-KEY: ' . $key], $body, $timeout);
}

/** Tactical's clients - ['ok', 'clients' => [id, name, sites, devices], 'error']. */
function rmmTacticalClients(array $provider): array
{
    $answer = rmmTacticalRequest($provider, 'GET', '/clients/');
    if (!$answer['ok']) {
        return ['ok' => false, 'clients' => [], 'error' => $answer['error']];
    }
    $clients = [];
    foreach ($answer['json'] as $client) {
        if (!is_array($client) || !isset($client['id']) || !isset($client['name'])) {
            continue;
        }
        $name = rmmText($client['name']);
        $clients[] = [
            'id' => (string) $client['id'],
            'name' => $name !== '' ? $name : 'Client ' . $client['id'],
            'sites' => is_array($client['sites'] ?? null) ? count($client['sites']) : 0,
            'devices' => intval($client['agent_count'] ?? 0),
        ];
    }
    return ['ok' => true, 'clients' => $clients, 'error' => ''];
}

/** A Tactical client's devices - ['ok', 'devices', 'error'], each device as rmmDeviceSave() takes it. */
function rmmTacticalDevices(array $provider, string $client_remote_id): array
{
    $answer = rmmTacticalRequest($provider, 'GET', '/agents/', ['client' => $client_remote_id], null, 60);
    if (!$answer['ok']) {
        return ['ok' => false, 'devices' => [], 'error' => $answer['error']];
    }
    $devices = [];
    foreach ($answer['json'] as $agent) {
        if (is_array($agent)) {
            $devices[] = rmmTacticalDevice($agent);
        }
    }
    return ['ok' => true, 'devices' => $devices, 'error' => ''];
}

/** One of Tactical's agents as a device. */
function rmmTacticalDevice(array $agent): array
{
    $checks = is_array($agent['checks'] ?? null) ? $agent['checks'] : [];
    $user = rmmText($agent['logged_username'] ?? '');
    $local_ips = rmmText($agent['local_ips'] ?? '', 500);
    [$make, $model] = rmmTacticalMakeModel($agent['make_model'] ?? '');
    return [
        'remote_id' => (string) ($agent['agent_id'] ?? ''),
        'hostname' => $agent['hostname'] ?? '',
        'site' => $agent['site_name'] ?? '',
        'role' => (string) ($agent['monitoring_type'] ?? ''),
        'platform' => (string) ($agent['plat'] ?? ''),
        'os' => $agent['operating_system'] ?? '',
        'status' => (string) ($agent['status'] ?? ''),
        'last_seen_at' => rmmTime($agent['last_seen'] ?? null),
        'boot_at' => rmmTime($agent['boot_time'] ?? null),
        'logged_user' => $user === '-' || $user === 'None' ? '' : $user,
        'public_ip' => $agent['public_ip'] ?? '',
        'local_ips' => rmmIsPlaceholder($local_ips) ? '' : $local_ips,
        'needs_reboot' => !empty($agent['needs_reboot']),
        'patches_pending' => !empty($agent['has_patches_pending']),
        'checks_total' => intval($checks['total'] ?? 0),
        'checks_failing' => intval($checks['failing'] ?? 0),
        'maintenance' => !empty($agent['maintenance_mode']),
        'agent_version' => $agent['version'] ?? '',
        'asset' => [
            'make' => $make,
            'model' => $model,
            'serial' => $agent['serial_number'] ?? '',
            'os' => rmmTacticalOsText($agent['operating_system'] ?? ''),
            'ip' => $local_ips,
            'cpu' => rmmTacticalCpuText($agent['cpu_model'] ?? []),
            'storage' => rmmTacticalStorageText($agent['physical_disks'] ?? []),
        ],
    ];
}

/** Tactical's open alerts - the ones its dashboard shows - each as an alert (functions/rmm.php, Alerts). */
function rmmTacticalAlerts(array $provider): array
{
    $answer = rmmTacticalRequest($provider, 'PATCH', '/alerts/', [], ['resolvedFilter' => false], 60);
    if (!$answer['ok']) {
        return ['ok' => false, 'alerts' => [], 'error' => $answer['error']];
    }
    $alerts = [];
    foreach ($answer['json'] as $alert) {
        if (is_array($alert) && isset($alert['id'])) {
            $alerts[] = rmmTacticalAlertShape($alert);
        }
    }
    return ['ok' => true, 'alerts' => $alerts, 'error' => ''];
}

/** One of Tactical's alerts by its id - null when Tactical no longer has it. */
function rmmTacticalAlert(array $provider, string $remote_id): array
{
    $answer = rmmTacticalRequest($provider, 'GET', '/alerts/' . rawurlencode($remote_id) . '/');
    if (!$answer['ok']) {
        return $answer['code'] === 404 ? ['ok' => true, 'alert' => null, 'error' => ''] : ['ok' => false, 'alert' => null, 'error' => $answer['error']];
    }
    return ['ok' => true, 'alert' => rmmTacticalAlertShape($answer['json']), 'error' => ''];
}

/** One of Tactical's alerts as an alert. What it is about: its check or task, the agent not checking in, or its message. */
function rmmTacticalAlertShape(array $alert): array
{
    $type = preg_replace('/[^a-z]/', '', strtolower((string) ($alert['alert_type'] ?? '')));
    $message = rmmText($alert['message'] ?? '', 2000);
    if ($type === 'check' && !empty($alert['assigned_check'])) {
        $about = 'check:' . intval($alert['assigned_check']);
    } elseif ($type === 'task' && !empty($alert['assigned_task'])) {
        $about = 'task:' . intval($alert['assigned_task']);
    } elseif ($type === 'availability') {
        $about = 'availability';
    } else {
        $about = $type . ':' . md5($message);
    }
    $cleared = !empty($alert['resolved']);
    return [
        'remote_id' => (string) ($alert['id'] ?? ''),
        'device_remote_id' => (string) ($alert['agent_id'] ?? ''),
        'type' => $type,
        'about' => $about,
        'severity' => strtolower((string) ($alert['severity'] ?? '')),
        'message' => $message,
        'raised_at' => rmmTime($alert['alert_time'] ?? null),
        'snoozed' => !empty($alert['snoozed']),
        'cleared_at' => $cleared ? (rmmTime($alert['resolved_on'] ?? null) ?? date('Y-m-d H:i:s')) : null,
    ];
}

/**
 * Tactical's dashboard address - the one set, or one worked out from the API's: Tactical's
 * own install puts them side by side as api.example.com and rmm.example.com.
 */
function rmmTacticalDashboard(array $provider): string
{
    $web = rmmUrlClean($provider['rmm_provider_web_url'] ?? '');
    if ($web !== '') {
        return $web;
    }
    $api = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    return $api === '' ? '' : preg_replace('#^(https?://)api\.#i', '$1rmm.', $api);
}

/** A device's pages in Tactical's dashboard - the agent, Take Control and its background tools. */
function rmmTacticalDeviceUrls(array $provider, string $remote_id): array
{
    $web = rmmTacticalDashboard($provider);
    if ($web === '') {
        return [];
    }
    $id = rawurlencode($remote_id);
    return [
        'open' => "$web/agents/$id",
        'control' => "$web/takecontrol/$id",
        'background' => "$web/remotebackground/$id",
    ];
}

/** Tactical's one "make model" string, as ITFlow's make and model. */
function rmmTacticalMakeModel($make_model): array
{
    $text = rmmText($make_model, 400);
    if (rmmIsPlaceholder($text)) {
        return ['', ''];
    }
    // How makers name themselves in the BIOS, and what everyone calls them
    $makers = [
        'Dell Inc.' => 'Dell', 'Dell' => 'Dell', 'LENOVO' => 'Lenovo', 'Lenovo' => 'Lenovo', 'HP' => 'HP', 'Hewlett-Packard' => 'HP',
        'Hewlett Packard Enterprise' => 'HPE', 'Microsoft Corporation' => 'Microsoft', 'VMware, Inc.' => 'VMware', 'VMware' => 'VMware',
        'ASUSTeK COMPUTER INC.' => 'ASUS', 'ASUSTeK Computer Inc.' => 'ASUS', 'ASUS' => 'ASUS', 'Acer' => 'Acer', 'Apple Inc.' => 'Apple', 'Apple' => 'Apple',
        'Intel Corporation' => 'Intel', 'Intel(R) Client Systems' => 'Intel', 'Gigabyte Technology Co., Ltd.' => 'Gigabyte',
        'Micro-Star International Co., Ltd.' => 'MSI', 'Supermicro' => 'Supermicro', 'Fujitsu' => 'Fujitsu', 'FUJITSU' => 'Fujitsu',
        'TOSHIBA' => 'Toshiba', 'Dynabook Inc.' => 'Dynabook', 'Samsung Electronics Co., Ltd.' => 'Samsung', 'Panasonic Corporation' => 'Panasonic',
        'QEMU' => 'QEMU', 'innotek GmbH' => 'VirtualBox', 'Xen' => 'Xen', 'Parallels Software International Inc.' => 'Parallels',
        'Proxmox' => 'Proxmox', 'Google' => 'Google', 'Amazon EC2' => 'Amazon', 'Nutanix' => 'Nutanix',
    ];
    uksort($makers, function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    foreach ($makers as $prefix => $make) {
        if (strncasecmp($text, $prefix . ' ', strlen($prefix) + 1) === 0) {
            $model = trim(substr($text, strlen($prefix)));
            // HP says its name twice - "HP HP EliteBook 840 G8"
            if (strncasecmp($model, $make . ' ', strlen($make) + 1) === 0) {
                $model = trim(substr($model, strlen($make)));
            }
            return [$make, mb_substr($model, 0, 200)];
        }
        if (strcasecmp($text, $prefix) === 0) {
            return [$make, ''];
        }
    }
    // Otherwise the first word is the maker
    $words = explode(' ', $text, 2);
    return [mb_substr($words[0], 0, 200), mb_substr($words[1] ?? '', 0, 200)];
}

/**
 * Tactical's OS as ITFlow writes it - "Windows 11 Pro, 64 bit v24H2 (build 26100.2605)" is
 * "Windows 11 Pro 24H2"; anything else as it comes.
 */
function rmmTacticalOsText($os): string
{
    $os = rmmText($os, 200);
    if (stripos($os, 'windows') === 0) {
        $os = preg_replace('/,\s*(32|64)[ -]?bit/i', '', $os);
        $os = preg_replace('/\s*\(build [^)]*\)/i', '', $os);
        $os = preg_replace('/\bv(\d{2}H\d|\d{4})\b/i', '$1', $os);
        $os = trim(preg_replace('/\s+/', ' ', $os));
    }
    return rmmIsPlaceholder($os) ? '' : $os;
}

/** Tactical's CPUs, as one line - "Intel Core i7-8700 CPU @ 3.20GHz, 6C/12T". */
function rmmTacticalCpuText($cpus): string
{
    $lines = [];
    foreach ((array) $cpus as $cpu) {
        $cpu = rmmText(str_replace(['(R)', '(TM)', '(tm)', '(r)'], '', (string) (is_array($cpu) ? implode(' ', array_filter($cpu, 'is_scalar')) : $cpu)), 500);
        if (!rmmIsPlaceholder($cpu)) {
            $lines[] = $cpu;
        }
    }
    $counts = array_count_values($lines);
    $text = implode(', ', array_map(function ($cpu, $count) {
        return $count > 1 ? "{$count} x $cpu" : $cpu;
    }, array_keys($counts), $counts));
    return mb_substr($text, 0, 500);
}

/** Tactical's disks, as one line - "Samsung SSD 980 PRO 1TB 932GB SCSI". */
function rmmTacticalStorageText($disks): string
{
    $lines = [];
    foreach ((array) $disks as $disk) {
        if (is_array($disk)) {
            $words = [];
            foreach (['model', 'name', 'size', 'interface'] as $key) {
                if (isset($disk[$key]) && is_scalar($disk[$key]) && trim((string) $disk[$key]) !== '') {
                    $words[] = trim((string) $disk[$key]);
                }
            }
            $disk = implode(' ', $words);
        }
        $disk = rmmText($disk, 500);
        if (!rmmIsPlaceholder($disk)) {
            $lines[] = $disk;
        }
    }
    return mb_substr(implode(', ', $lines), 0, 500);
}
