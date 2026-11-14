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
 *
 * Its device actions, each needing its own permission in the key's role: POST
 * /agents/<id>/reboot/ (Shutdown / Reboot Agents), POST /winupdate/<id>/install/ (Manage Windows
 * Updates - it installs what the agent's patch policy approves, and reboots after if that policy
 * says to), PUT /agents/<id>/ with maintenance_mode (Edit Agents), and POST
 * /agents/<id>/runscript/ (Run Script) with a script from GET /scripts/ (List Scripts). Most
 * answer with text in JSON - "ok", or what went wrong with a 400; a script run with output
 * "wait" answers with its output, or "timeout" when the agent didn't finish in time.
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
        'actions' => [
            'reboot' => ['label' => 'Reboot now', 'icon' => 'fa-redo-alt', 'needs' => 'Shutdown / Reboot Agents',
                'about' => 'It restarts straight away.'],
            'patches' => ['label' => 'Install patches now', 'icon' => 'fa-download', 'needs' => 'Manage Windows Updates', 'platforms' => ['windows'],
                'about' => 'Tactical installs the ones its patch policy approves, and restarts it afterwards if that policy says to.'],
            'maintenance' => ['label' => 'Maintenance mode', 'icon' => 'fa-tools', 'needs' => 'Edit Agents',
                'about' => 'While it is on, Tactical raises no alerts for it - so none opens a ticket.'],
            'script' => ['label' => 'Run a script', 'icon' => 'fa-scroll', 'needs' => 'List Scripts and Run Script',
                'about' => 'One of the scripts in Tactical\'s library, with its output back here.'],
        ],
        'act' => 'rmmTacticalAct',
        'scripts' => 'rmmTacticalScripts',
        'script' => 'rmmTacticalScript',
        'actions_help' => 'Tactical does each as the API key\'s user, so its role needs Shutdown / Reboot Agents, Manage Windows Updates, Edit Agents (for maintenance mode), and List Scripts and Run Script - give it only the ones you want done from ITFlow.',
    ];
}

/**
 * Ask Tactical's API. $path is Tactical's, with its trailing slash (/agents/) - it matters there.
 * $text_ok for the answers that are only text.
 */
function rmmTacticalRequest(array $provider, string $method, string $path, array $query = [], ?array $body = null, int $timeout = 30, bool $text_ok = false): array
{
    $base = rmmUrlClean($provider['rmm_provider_api_url'] ?? '');
    $key = trim((string) ($provider['rmm_provider_api_key'] ?? ''));
    if ($base === '' || $key === '') {
        return ['ok' => false, 'code' => 0, 'json' => null, 'error' => 'Its API address and key aren\'t set.'];
    }
    $url = $base . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query, '', '&') : '');
    return rmmHttp($provider, $method, $url, ['X-API-KEY: ' . $key], $body, $timeout, $text_ok);
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

/**
 * Do an action to one of Tactical's agents - ['ok', 'output', 'timed_out', 'error'] (functions/rmm.php,
 * Device actions). A script waited for has its output; timed_out when it didn't finish in time.
 */
function rmmTacticalAct(array $provider, string $remote_id, string $action, array $options = []): array
{
    $id = rawurlencode($remote_id);
    $timeout = 30;
    if ($action === 'reboot') {
        $answer = rmmTacticalRequest($provider, 'POST', "/agents/$id/reboot/", [], [], 30, true);
    } elseif ($action === 'patches') {
        $answer = rmmTacticalRequest($provider, 'POST', "/winupdate/$id/install/", [], [], 30, true);
    } elseif ($action === 'maintenance') {
        $answer = rmmTacticalRequest($provider, 'PUT', "/agents/$id/", [], ['maintenance_mode' => !empty($options['on'])], 30, true);
    } elseif ($action === 'script') {
        $timeout = max(5, min(600, intval($options['timeout'] ?? 90)));
        $wait = !empty($options['wait']);
        // Every key its run_script view reads, with the script's own environment variables as Tactical's Run Script window sends them
        $answer = rmmTacticalRequest($provider, 'POST', "/agents/$id/runscript/", [], [
            'script' => intval($options['script']['id'] ?? 0),
            'output' => $wait ? 'wait' : 'forget',
            'args' => array_values(array_map('strval', (array) ($options['args'] ?? []))),
            'env_vars' => array_values(array_map('strval', (array) ($options['script']['env_vars'] ?? []))),
            'timeout' => $timeout,
            'run_as_user' => false,
            'emails' => [],
            'emailMode' => 'default',
            'custom_field' => null,
            'save_all_output' => false,
        ], $timeout + 30, true);
        if ($answer['ok'] && $wait) {
            $output = is_string($answer['json']) ? $answer['json'] : json_encode($answer['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (trim($output) === 'timeout') {
                return ['ok' => true, 'output' => null, 'timed_out' => true, 'error' => ''];
            }
            return ['ok' => true, 'output' => $output, 'timed_out' => false, 'error' => ''];
        }
    } else {
        return ['ok' => false, 'output' => null, 'timed_out' => false, 'error' => rmmProviderName($provider) . ' can\'t do that.'];
    }
    if (!$answer['ok']) {
        return ['ok' => false, 'output' => null, 'timed_out' => false, 'error' => rmmTacticalActError($provider, $answer, $action)];
    }
    return ['ok' => true, 'output' => null, 'timed_out' => false, 'error' => ''];
}

/** What went wrong with an action, in words - what the key's role needs for it, or Tactical's own reason. */
function rmmTacticalActError(array $provider, array $answer, string $action): string
{
    $name = rmmProviderName($provider);
    if ($answer['code'] === 401 || $answer['code'] === 403) {
        $needs = rmmTacticalKind()['actions'][$action]['needs'] ?? '';
        return "$name didn't let the API key do that" . ($needs !== '' ? " - its user's role needs $needs" : '') . '.';
    }
    if ($answer['code'] === 404) {
        return "$name no longer has that " . ($action === 'script' ? 'device or script' : 'device') . '.';
    }
    if ($answer['code'] === 400 && is_string($answer['json']) && trim($answer['json']) !== '') {
        $reason = rmmText($answer['json'], 300);
        return "$name said: $reason" . (stripos($reason, 'unable to contact') !== false ? ' - is it online?' : '');
    }
    return $answer['error'];
}

/** Tactical's scripts that run on $platform - its favorites first, then by category and name. */
function rmmTacticalScripts(array $provider, string $platform): array
{
    $answer = rmmTacticalRequest($provider, 'GET', '/scripts/');
    if (!$answer['ok']) {
        if ($answer['code'] === 401 || $answer['code'] === 403) {
            $answer['error'] = rmmProviderName($provider) . ' didn\'t let the API key see its scripts - its user\'s role needs List Scripts.';
        }
        return ['ok' => false, 'scripts' => [], 'error' => $answer['error']];
    }
    $scripts = [];
    foreach ($answer['json'] as $script) {
        if (!is_array($script) || !isset($script['id'], $script['name']) || !empty($script['hidden'])) {
            continue;
        }
        $platforms = array_map('strval', (array) ($script['supported_platforms'] ?? []));
        if ($platforms && !in_array($platform, $platforms, true)) {
            continue;
        }
        $scripts[] = rmmTacticalScriptShape($script);
    }
    usort($scripts, function ($a, $b) {
        if ($a['favorite'] !== $b['favorite']) {
            return $a['favorite'] ? -1 : 1;
        }
        if (($a['category'] === '') !== ($b['category'] === '')) {
            return $a['category'] === '' ? 1 : -1;
        }
        return strcasecmp($a['category'], $b['category']) ?: strcasecmp($a['name'], $b['name']);
    });
    return ['ok' => true, 'scripts' => $scripts, 'error' => ''];
}

/** One of Tactical's scripts by its id - null when Tactical no longer has it. */
function rmmTacticalScript(array $provider, string $script_id): array
{
    $answer = rmmTacticalRequest($provider, 'GET', '/scripts/' . intval($script_id) . '/');
    if (!$answer['ok']) {
        if ($answer['code'] === 404) {
            return ['ok' => true, 'script' => null, 'error' => ''];
        }
        return ['ok' => false, 'script' => null, 'error' => rmmTacticalActError($provider, $answer, 'script')];
    }
    return ['ok' => true, 'script' => rmmTacticalScriptShape($answer['json']), 'error' => ''];
}

/** One of Tactical's scripts as a script (functions/rmm.php, Device actions). */
function rmmTacticalScriptShape(array $script): array
{
    $list = function ($values) {
        return array_values(array_filter(array_map(function ($value) {
            return is_scalar($value) ? mb_substr((string) $value, 0, 500) : '';
        }, (array) $values), 'strlen'));
    };
    return [
        'id' => (string) intval($script['id'] ?? 0),
        'name' => rmmText($script['name'] ?? '', 255) ?: 'Script ' . intval($script['id'] ?? 0),
        'category' => rmmText($script['category'] ?? '', 100),
        'description' => rmmText($script['description'] ?? '', 500),
        'args' => $list($script['args'] ?? []),
        'env_vars' => $list($script['env_vars'] ?? []),
        'platforms' => array_map('strval', (array) ($script['supported_platforms'] ?? [])),
        'timeout' => max(5, min(600, intval($script['default_timeout'] ?? 90) ?: 90)),
        'favorite' => !empty($script['favorite']),
        'hidden' => !empty($script['hidden']),
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
