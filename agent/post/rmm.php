<?php

/*
 * RMM device actions (functions/rmm.php, Device actions) - from a device's menu on its asset's
 * card, or beside it on a ticket: reboot it, install its patches, and turn its maintenance mode
 * on or off; and from the Run a script window (modals/rmm/rmm_script.php), run one of the RMM's
 * scripts on it, answered in JSON so the window shows the output. Each needs Modify on Support
 * and the asset's client, and goes in the asset's history and the audit log - and, done from a
 * ticket, on it as an internal note.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['rmm_reboot']) || isset($_POST['rmm_install_patches']) || isset($_POST['rmm_maintenance_on']) || isset($_POST['rmm_maintenance_off'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    // Which it is - the action, what it is given, and how it is spoken of after and when it fails
    $asked = [
        'rmm_reboot' => ['reboot', [], 'rebooted %1$s %2$s', 'couldn\'t reboot'],
        'rmm_install_patches' => ['patches', [], 'started installing the patches on %1$s %2$s', 'couldn\'t install the patches on'],
        'rmm_maintenance_on' => ['maintenance', ['on' => true], 'turned maintenance mode on for %1$s %2$s', 'couldn\'t turn maintenance mode on for'],
        'rmm_maintenance_off' => ['maintenance', ['on' => false], 'turned maintenance mode off for %1$s %2$s', 'couldn\'t turn maintenance mode off for'],
    ];
    $key = (string) array_key_first(array_intersect_key($asked, $_POST));
    [$action, $options, $did, $failed] = $asked[$key];

    [$row, $ticket, $error] = rmmActionTarget(intval($_POST[$key]), intval($_POST['ticket_id'] ?? 0), $action);
    if ($row) {
        $client_id = intval($row['asset_client_id']);
        enforceClientAccess();
    }
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $name = rmmProviderName($row);
    $host = rmmActionHost($row);
    $answer = rmmDeviceAct($row, $action, $options);
    if (!$answer['ok']) {
        flashAlert(escapeHtml("$name $failed $host. {$answer['error']}"), 'error');
        redirect();
    }

    // Maintenance mode shows straight away - the next sync says the same
    if ($action === 'maintenance') {
        mysqli_query($mysqli, "UPDATE rmm_devices SET rmm_device_maintenance = " . (empty($options['on']) ? 0 : 1) . " WHERE rmm_device_id = " . intval($row['rmm_device_id']));
    }
    rmmActionRecord($row, $ticket, $did);

    $done = [
        'reboot' => "$host is rebooting.",
        'patches' => "$name is installing the patches $host's patch policy approves.",
        'maintenance' => empty($options['on']) ? "Maintenance mode is off for $host." : "Maintenance mode is on for $host - $name raises no alerts for it until it's off.",
    ][$action];
    flashAlert(escapeHtml($done) . ($ticket ? ' Noted on <strong>' . escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) . '</strong>.' : ''));

    redirect();

}

if (isset($_POST['rmm_run_script'])) {

    validateCSRFToken();

    // The window asks in JSON and shows what comes back; anything else gets it as a flash
    $wants_json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $answer_with = function (array $answer) use ($wants_json) {
        if ($wants_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($answer);
            exit;
        }
        flashAlert(escapeHtml($answer['ok'] ? $answer['text'] : $answer['error']), $answer['ok'] ? 'success' : 'error');
        redirect();
    };

    $support = intval(lookupUserPermission('module_support'));
    if ($support < 2) {
        $answer_with(['ok' => false, 'error' => permissionRefusedText('module_support', 2, $support)]);
    }
    [$row, $ticket, $error] = rmmActionTarget(intval($_POST['rmm_run_script']), intval($_POST['ticket_id'] ?? 0), 'script');
    if ($row && !hasClientAccess($row['asset_client_id'])) {
        $answer_with(['ok' => false, 'error' => 'You don\'t have access to this client.']);
    }
    if ($error !== '') {
        $answer_with(['ok' => false, 'error' => $error]);
    }

    $name = rmmProviderName($row);
    $host = rmmActionHost($row);
    $found = rmmDeviceScript($row, (string) intval($_POST['script_id'] ?? 0));
    if (!$found['ok']) {
        $answer_with(['ok' => false, 'error' => $found['error']]);
    }
    $script = $found['script'];

    // Its arguments, one a line, as typed - and how long to wait for it
    $args = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['args'] ?? ''))), 'strlen'));
    $args = array_map(function ($arg) {
        return mb_substr($arg, 0, 500);
    }, array_slice($args, 0, 50));
    $timeout = max(5, min(600, intval($_POST['timeout'] ?? 0) ?: $script['timeout']));
    $wait = !empty($_POST['wait']);

    // Waiting is up to the timeout, and a little more for the RMM to answer - without holding up the person's other tabs
    set_time_limit($timeout + 60);
    if ($wants_json) {
        session_write_close();
    }

    $result = rmmDeviceAct($row, 'script', ['script' => $script, 'args' => $args, 'timeout' => $timeout, 'wait' => $wait]);
    if (!$result['ok']) {
        $answer_with(['ok' => false, 'error' => "$name couldn't run {$script['name']} on $host. {$result['error']}"]);
    }

    // How it is kept - what was run, then its output
    $ran = str_replace('%', '%%', $script['name']);
    $with = $args ? ', with ' . str_replace('%', '%%', implode(' ', $args)) : '';
    $output = null;
    if (!$wait) {
        $what = "started the script $ran on %1\$s %2\$s$with";
        $more = '<p>' . escapeHtml("Its output is in the device's history in $name.") . '</p>';
        $text = "$name is running {$script['name']} on $host - its output will be in the device's history there.";
    } elseif ($result['timed_out']) {
        $what = "ran the script $ran on %1\$s %2\$s$with - it didn't finish within $timeout seconds";
        $more = '<p>' . escapeHtml("If it finishes, its output is in the device's history in $name.") . '</p>';
        $text = "{$script['name']} didn't finish on $host within $timeout seconds. If it finishes, its output is in the device's history in $name.";
    } else {
        $output = (string) $result['output'];
        // A long one keeps its end, where the errors are
        if (mb_strlen($output) > 60000) {
            $output = '... (the first ' . number_format(mb_strlen($output) - 60000) . " characters left out)\n" . mb_substr($output, -60000);
        }
        $what = "ran the script $ran on %1\$s %2\$s$with";
        $more = '<pre>' . escapeHtml(trim($output) !== '' ? $output : '(no output)') . '</pre>';
        $text = "Ran {$script['name']} on $host.";
    }
    rmmActionRecord($row, $ticket, $what, $more);

    $answer_with([
        'ok' => true,
        'text' => $text . ($ticket ? ' Noted on ' . $ticket['ticket_prefix'] . $ticket['ticket_number'] . '.' : ''),
        'output' => $output,
        'timed_out' => $wait && $result['timed_out'],
    ]);

}
