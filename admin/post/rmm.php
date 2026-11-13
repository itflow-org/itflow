<?php

/*
 * Admin > RMM Providers - Add and Edit (asked first by check_rmm_provider, which answers in
 * JSON, so what is wrong shows beside each field), Test the connection, Sync now, turning a
 * provider off and on, Delete, and on a provider's Clients page linking its clients to
 * ITFlow's - one at a time, the suggested ones, unlinking, and bringing back devices whose
 * assets were deleted (functions/rmm.php). What changed is in the audit log - keys and
 * secrets only as changed.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['check_rmm_provider']) || isset($_POST['add_rmm_provider']) || isset($_POST['edit_rmm_provider'])) {

    validateCSRFToken();

    $checking = isset($_POST['check_rmm_provider']);
    $provider_id = intval($_POST['provider_id'] ?? 0);
    $before = $provider_id ? rmmProviderRow($provider_id) : null;

    $refuse = function ($text) use ($checking) {
        if ($checking) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'errors' => ['name' => $text]]);
            exit;
        }
        flashAlert(escapeHtml($text), 'error');
        redirect('rmm_providers.php');
    };
    if ($provider_id && !$before) {
        $refuse('That RMM provider no longer exists - close this and look again.');
    }
    if ($before && !rmmKind($before)) {
        $refuse('This ITFlow doesn\'t know that provider\'s kind of RMM.');
    }

    [$after, $errors] = rmmProviderCheck($_POST, $before);

    if ($checking) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => !$errors, 'errors' => $errors]);
        exit;
    }

    // Only reached without the window's script - say the first thing wrong
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['rmm_provider_name'];
    $kind = rmmKind($after['rmm_provider_kind']);
    if ($before) {
        $lines = rmmProviderChangeLines($before, $after);
        if (!$lines) {
            flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
            redirect();
        }
        rmmProviderWrite($after, $provider_id);
        logAudit("RMM Provider", "Edit", "$session_name changed RMM provider " . escapeSql(rmmProviderName($before) . ': ' . implode('; ', $lines)), 0, $provider_id);
    } else {
        $provider_id = rmmProviderWrite($after);
        logAudit("RMM Provider", "Create", "$session_name added RMM provider " . escapeSql($name . ($name !== $kind['name'] ? " ({$kind['name']})" : '') . ($after['rmm_provider_api_url'] !== '' ? ' at ' . $after['rmm_provider_api_url'] : '')), 0, $provider_id);
    }

    // Try it - and, connected, bring in its clients so they can be linked
    $provider = rmmProviderRow($provider_id);
    $back = $before ? null : "rmm_clients.php?provider_id=$provider_id";
    if (!intval($provider['rmm_provider_active'])) {
        flashAlert('<strong>' . escapeHtml($name) . '</strong> saved - it is off, so nothing is brought in');
        redirect($back);
    }
    $test = rmmTest($provider);
    if (!$test['ok']) {
        flashAlert('<strong>' . escapeHtml($name) . '</strong> saved, but it didn\'t answer: ' . escapeHtml($test['text']), 'warning');
        redirect($back);
    }
    rmmSync($provider, 0, 60);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> saved. ' . escapeHtml($test['text']) . ($before ? '' : ' Now link its clients to yours.'));
    redirect($back);

}

// Try the connection
if (isset($_GET['test_rmm_provider'])) {

    validateCSRFToken();

    $provider = rmmProviderRow($_GET['test_rmm_provider']);
    if (!$provider) {
        flashAlert('That RMM provider no longer exists.', 'error');
        redirect('rmm_providers.php');
    }
    $test = rmmTest($provider);
    flashAlert('<strong>' . escapeHtml(rmmProviderName($provider)) . '</strong> - ' . escapeHtml($test['text']), $test['ok'] ? 'success' : 'error');
    redirect();

}

// Sync now - its clients, and the linked ones' devices
if (isset($_GET['sync_rmm'])) {

    validateCSRFToken();

    $provider = rmmProviderRow($_GET['sync_rmm']);
    if (!rmmProviderUsable($provider)) {
        flashAlert('That RMM provider isn\'t set up, or is turned off.', 'error');
        redirect();
    }
    set_time_limit(180);
    $sync = rmmSync($provider, 0, 150);
    flashAlert('<strong>' . escapeHtml(rmmProviderName($provider)) . '</strong> ' . ($sync['ok'] ? 'synced - ' : 'didn\'t sync - ') . escapeHtml($sync['note']), $sync['ok'] ? 'success' : 'error');
    redirect();

}

// Turn it off - nothing is brought in - or on again
if (isset($_GET['turn_rmm_provider_off']) || isset($_GET['turn_rmm_provider_on'])) {

    validateCSRFToken();

    $on = isset($_GET['turn_rmm_provider_on']);
    $provider = rmmProviderRow($on ? $_GET['turn_rmm_provider_on'] : $_GET['turn_rmm_provider_off']);
    if (!$provider) {
        flashAlert('That RMM provider no longer exists.', 'error');
        redirect('rmm_providers.php');
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $name = rmmProviderName($provider);
    mysqli_query($mysqli, "UPDATE rmm_providers SET rmm_provider_active = " . ($on ? 1 : 0) . " WHERE rmm_provider_id = $provider_id");
    logAudit("RMM Provider", "Edit", "$session_name turned RMM provider " . escapeSql($name) . ($on ? ' on' : ' off'), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> is ' . ($on ? 'on - the linked clients\' devices come in again with the next sync' : 'off - nothing is brought in'));
    redirect();

}

// Delete - the provider, its client links and what it said about each device go; the assets stay
if (isset($_GET['delete_rmm_provider'])) {

    validateCSRFToken();

    $provider = rmmProviderRow($_GET['delete_rmm_provider']);
    if (!$provider) {
        flashAlert('That RMM provider no longer exists.', 'error');
        redirect('rmm_providers.php');
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $name = rmmProviderName($provider);
    $devices = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_asset_id > 0"))[0]);
    mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_provider_id = $provider_id");
    mysqli_query($mysqli, "DELETE FROM rmm_clients WHERE rmm_client_provider_id = $provider_id");
    mysqli_query($mysqli, "DELETE FROM rmm_providers WHERE rmm_provider_id = $provider_id");
    logAudit("RMM Provider", "Delete", "$session_name deleted RMM provider " . escapeSql($name) . " - the $devices assets it brought in stay, no longer kept up", 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> deleted - the ' . number_format($devices) . ' asset' . ($devices === 1 ? '' : 's') . ' it brought in stay, as they are', 'error');
    redirect('rmm_providers.php');

}

/*
 * Link one of a provider's clients to an ITFlow client - and bring its devices in now. Linked
 * to another client before, what the sync knew about its devices is forgotten, so they come in
 * afresh at the new one; the old client's assets stay, no longer kept up.
 */
$rmm_link_client = function (array $rmm_client, int $client_id) use ($mysqli, &$session_name) {
    $rmm_client_id = intval($rmm_client['rmm_client_id']);
    $before = intval($rmm_client['rmm_client_client_id']);
    $name = rmmProviderName($rmm_client);
    $left = 0;
    if ($before && $before !== $client_id) {
        $sql = mysqli_query($mysqli, "SELECT rmm_device_asset_id FROM rmm_devices WHERE rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_asset_id > 0");
        while ($device = mysqli_fetch_row($sql)) {
            if (rmmAssetCurrent(intval($device[0]))) {
                rmmAssetHistory(intval($device[0]), $name, 'no longer keeps it up - its client there was linked to another one');
                $left++;
            }
        }
        mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_rmm_client_id = $rmm_client_id");
    }
    mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_client_id = $client_id, rmm_client_synced_at = NULL WHERE rmm_client_id = $rmm_client_id");
    $client_name = (string) getFieldById('clients', $client_id, 'client_name');
    logAudit("RMM Provider", "Link", "$session_name linked " . escapeSql("$name client {$rmm_client['rmm_client_name']} to $client_name"), $client_id, intval($rmm_client['rmm_client_provider_id']));
    return ['client_name' => $client_name, 'left' => $left, 'before_name' => $before ? (string) getFieldById('clients', $before, 'client_name') : ''];
};

if (isset($_POST['link_rmm_client'])) {

    validateCSRFToken();

    $rmm_client = rmmClientRow($_POST['rmm_client_id'] ?? 0);
    $client_id = intval($_POST['client_id'] ?? 0);
    $client = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_id, client_archived_at FROM clients WHERE client_id = $client_id")) : null;
    if (!$rmm_client || $rmm_client['rmm_client_removed_at'] !== null) {
        flashAlert('Its RMM no longer has that client - sync again.', 'error');
        redirect();
    }
    $back = 'rmm_clients.php?provider_id=' . intval($rmm_client['rmm_client_provider_id']);
    if (!$client || $client['client_archived_at'] !== null) {
        flashAlert('Pick a client to link it to.', 'error');
        redirect($back);
    }
    if (intval($rmm_client['rmm_client_client_id']) === $client_id) {
        flashAlert('<strong>' . escapeHtml($rmm_client['rmm_client_name']) . '</strong> is linked to that client already', 'info');
        redirect($back);
    }

    $linked = $rmm_link_client($rmm_client, $client_id);
    set_time_limit(180);
    $sync = rmmSync(rmmProviderRow($rmm_client['rmm_client_provider_id']), intval($rmm_client['rmm_client_id']), 150);
    $left = $linked['left'] ? '. The ' . ($linked['left'] === 1 ? 'asset' : $linked['left'] . ' assets') . ' brought in for <strong>' . escapeHtml($linked['before_name']) . '</strong> ' . ($linked['left'] === 1 ? 'stays there - archive it if it was' : 'stay there - archive them if they were') . ' never theirs.' : '';
    flashAlert('<strong>' . escapeHtml($rmm_client['rmm_client_name']) . '</strong> linked to <strong>' . escapeHtml($linked['client_name']) . '</strong> - ' . escapeHtml($sync['note']) . $left, $sync['ok'] ? 'success' : 'warning');
    redirect($back);

}

// Link the suggested ones - one, or all of a provider's - to the client of the same name
if (isset($_GET['link_rmm_suggested'])) {

    validateCSRFToken();

    $provider = rmmProviderRow($_GET['provider_id'] ?? 0);
    if (!$provider) {
        flashAlert('That RMM provider no longer exists.', 'error');
        redirect('rmm_providers.php');
    }
    $provider_id = intval($provider['rmm_provider_id']);
    $back = "rmm_clients.php?provider_id=$provider_id";
    $suggestions = rmmClientSuggestions($provider_id);
    if ($_GET['link_rmm_suggested'] !== 'all') {
        $only = intval($_GET['link_rmm_suggested']);
        $suggestions = isset($suggestions[$only]) ? [$only => $suggestions[$only]] : [];
    }
    if (!$suggestions) {
        flashAlert('Nothing to link - there is no client of the same name', 'info');
        redirect($back);
    }
    foreach ($suggestions as $rmm_client_id => $client_id) {
        $rmm_link_client(rmmClientRow($rmm_client_id), $client_id);
    }
    set_time_limit(180);
    $sync = rmmSync($provider, count($suggestions) === 1 ? intval(array_key_first($suggestions)) : 0, 150);
    flashAlert(count($suggestions) . ' client' . (count($suggestions) === 1 ? '' : 's') . ' linked - ' . escapeHtml($sync['note']), $sync['ok'] ? 'success' : 'warning');
    redirect($back);

}

// Unlink - its devices stop coming in; their assets stay
if (isset($_GET['unlink_rmm_client'])) {

    validateCSRFToken();

    $rmm_client = rmmClientRow($_GET['unlink_rmm_client']);
    if (!$rmm_client || !intval($rmm_client['rmm_client_client_id'])) {
        flashAlert('That client isn\'t linked.', 'info');
        redirect();
    }
    $rmm_client_id = intval($rmm_client['rmm_client_id']);
    mysqli_query($mysqli, "UPDATE rmm_clients SET rmm_client_client_id = 0, rmm_client_synced_at = NULL WHERE rmm_client_id = $rmm_client_id");
    logAudit("RMM Provider", "Unlink", "$session_name unlinked " . escapeSql(rmmProviderName($rmm_client) . " client {$rmm_client['rmm_client_name']} from " . $rmm_client['client_name']), intval($rmm_client['rmm_client_client_id']), intval($rmm_client['rmm_client_provider_id']));
    flashAlert('<strong>' . escapeHtml($rmm_client['rmm_client_name']) . '</strong> unlinked - its devices no longer come in. Their assets stay.');
    redirect('rmm_clients.php?provider_id=' . intval($rmm_client['rmm_client_provider_id']));

}

// Bring back - devices whose assets were deleted in ITFlow are matched or made again, now
if (isset($_GET['rmm_bring_back'])) {

    validateCSRFToken();

    $rmm_client = rmmClientRow($_GET['rmm_bring_back']);
    if (!$rmm_client || !intval($rmm_client['rmm_client_client_id'])) {
        flashAlert('That client isn\'t linked.', 'error');
        redirect();
    }
    $rmm_client_id = intval($rmm_client['rmm_client_id']);
    mysqli_query($mysqli, "DELETE FROM rmm_devices WHERE rmm_device_rmm_client_id = $rmm_client_id AND rmm_device_asset_id = 0");
    $brought = mysqli_affected_rows($mysqli);
    set_time_limit(180);
    $sync = rmmSync(rmmProviderRow($rmm_client['rmm_client_provider_id']), $rmm_client_id, 150);
    logAudit("RMM Provider", "Edit", "$session_name brought back $brought deleted devices of " . escapeSql(rmmProviderName($rmm_client) . " client {$rmm_client['rmm_client_name']}"), intval($rmm_client['rmm_client_client_id']), intval($rmm_client['rmm_client_provider_id']));
    flashAlert('<strong>' . escapeHtml($rmm_client['rmm_client_name']) . '</strong> - ' . escapeHtml($sync['note']), $sync['ok'] ? 'success' : 'warning');
    redirect('rmm_clients.php?provider_id=' . intval($rmm_client['rmm_client_provider_id']));

}
