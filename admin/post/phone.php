<?php

/*
 * Admin > Phone Systems (functions/phone.php) - Add and Edit, turning one off and on, a new key
 * for its addresses, Delete, and on its Domains page linking its domains to clients - one at a
 * time, the suggested ones, and unlinking. What changed is in the audit log - never the key.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['save_phone_system'])) {

    validateCSRFToken();

    $phone_system_id = intval($_POST['phone_system_id'] ?? 0);
    $before = $phone_system_id ? phoneSystemRow($phone_system_id) : null;
    if ($phone_system_id && !$before) {
        flashAlert('That phone system no longer exists.', 'error');
        redirect('phone_systems.php');
    }

    [$after, $errors] = phoneSystemCheck($_POST, $before);
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['phone_system_name'];
    if ($before) {
        $lines = phoneSystemChangeLines($before, $after);
        if (!$lines) {
            flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
            redirect();
        }
        phoneSystemWrite($after, $phone_system_id);
        logAudit("Phone System", "Edit", "$session_name changed phone system " . escapeSql(phoneSystemName($before) . ': ' . implode('; ', $lines)), 0, $phone_system_id);
        flashAlert('<strong>' . escapeHtml($name) . '</strong> saved');
        redirect();
    }

    $phone_system_id = phoneSystemWrite($after);
    $kind = phoneSystemKinds()[$after['phone_system_kind']];
    logAudit("Phone System", "Create", "$session_name added phone system " . escapeSql($name . ($name !== $kind['name'] ? " ({$kind['name']})" : '')), 0, $phone_system_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> created - now set it up to send ITFlow its calls');
    redirect("phone_domains.php?phone_system_id=$phone_system_id&setup=1");

}

// Turn it off - what it sends is turned away - or on again
if (isset($_GET['turn_phone_system_off']) || isset($_GET['turn_phone_system_on'])) {

    validateCSRFToken();

    $on = isset($_GET['turn_phone_system_on']);
    $system = phoneSystemRow($on ? $_GET['turn_phone_system_on'] : $_GET['turn_phone_system_off']);
    if (!$system) {
        flashAlert('That phone system no longer exists.', 'error');
        redirect('phone_systems.php');
    }
    $phone_system_id = intval($system['phone_system_id']);
    mysqli_query($mysqli, "UPDATE phone_systems SET phone_system_active = " . ($on ? 1 : 0) . " WHERE phone_system_id = $phone_system_id");
    logAudit("Phone System", "Edit", "$session_name turned phone system " . escapeSql(phoneSystemName($system)) . ($on ? ' on' : ' off'), 0, $phone_system_id);
    flashAlert('<strong>' . escapeHtml(phoneSystemName($system)) . '</strong> turned ' . ($on ? 'on' : 'off - what it sends is turned away'), $on ? 'success' : 'error');
    redirect();

}

// A new key - its addresses change, and the old ones stop working
if (isset($_GET['new_phone_system_key'])) {

    validateCSRFToken();

    $system = phoneSystemRow($_GET['new_phone_system_key']);
    if (!$system) {
        flashAlert('That phone system no longer exists.', 'error');
        redirect('phone_systems.php');
    }
    $phone_system_id = intval($system['phone_system_id']);
    mysqli_query($mysqli, "UPDATE phone_systems SET phone_system_key = '" . escapeSql(phoneSystemNewKey()) . "' WHERE phone_system_id = $phone_system_id");
    logAudit("Phone System", "Edit", "$session_name made a new key for phone system " . escapeSql(phoneSystemName($system)), 0, $phone_system_id);
    flashAlert('<strong>' . escapeHtml(phoneSystemName($system)) . '</strong> has a new key - put its new addresses in FusionPBX; the old ones no longer work', 'warning');
    redirect("phone_domains.php?phone_system_id=$phone_system_id&setup=1");

}

if (isset($_GET['delete_phone_system'])) {

    validateCSRFToken();

    $system = phoneSystemRow($_GET['delete_phone_system']);
    if (!$system) {
        flashAlert('That phone system no longer exists.', 'error');
        redirect('phone_systems.php');
    }
    $phone_system_id = intval($system['phone_system_id']);
    $calls = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM phone_calls WHERE phone_call_system_id = $phone_system_id"))[0]);
    foreach (['phone_calls' => 'phone_call_system_id', 'phone_devices' => 'phone_device_system_id', 'phone_events' => 'phone_event_system_id', 'phone_domains' => 'phone_domain_system_id'] as $table => $column) {
        mysqli_query($mysqli, "DELETE FROM $table WHERE $column = $phone_system_id");
    }
    mysqli_query($mysqli, "DELETE FROM phone_systems WHERE phone_system_id = $phone_system_id");
    logAudit("Phone System", "Delete", "$session_name deleted phone system " . escapeSql(phoneSystemName($system)) . " and its $calls call" . ($calls === 1 ? '' : 's'), 0, $phone_system_id);
    flashAlert('<strong>' . escapeHtml(phoneSystemName($system)) . '</strong> deleted', 'error');
    redirect('phone_systems.php');

}

// A domain linked to a client - from its window
if (isset($_POST['link_phone_domain'])) {

    validateCSRFToken();

    $domain = phoneDomainRow($_POST['phone_domain_id'] ?? 0);
    $client_id = intval($_POST['client_id'] ?? 0);
    $client_name = $client_id ? (string) getFieldById('clients', $client_id, 'client_name') : '';
    if (!$domain || $client_name === '') {
        flashAlert(!$domain ? 'That domain is gone.' : 'Pick a client.', 'error');
        redirect();
    }
    phoneDomainLink(intval($domain['phone_domain_id']), $client_id);
    logAudit("Phone System", "Edit", "$session_name linked " . escapeSql(phoneSystemName($domain)) . " domain " . escapeSql($domain['phone_domain_name']) . " to client " . escapeSql($client_name), $client_id, intval($domain['phone_system_id']));
    flashAlert('<strong>' . escapeHtml($domain['phone_domain_name']) . '</strong> linked to <strong>' . escapeHtml($client_name) . '</strong>');
    redirect();

}

// The suggested link for one domain, or for all of them
if (isset($_GET['link_phone_suggested'])) {

    validateCSRFToken();

    $system = phoneSystemRow($_GET['phone_system_id'] ?? 0);
    if (!$system) {
        flashAlert('That phone system no longer exists.', 'error');
        redirect('phone_systems.php');
    }
    $suggestions = phoneDomainSuggestions(intval($system['phone_system_id']));
    $which = (string) $_GET['link_phone_suggested'];
    if ($which !== 'all') {
        $suggestions = array_intersect_key($suggestions, [intval($which) => true]);
    }
    foreach ($suggestions as $domain_id => $client_id) {
        $domain = phoneDomainRow($domain_id);
        phoneDomainLink($domain_id, $client_id);
        logAudit("Phone System", "Edit", "$session_name linked " . escapeSql(phoneSystemName($system)) . " domain " . escapeSql($domain['phone_domain_name']) . " to client " . escapeSql((string) getFieldById('clients', $client_id, 'client_name')), $client_id, intval($system['phone_system_id']));
    }
    $count = count($suggestions);
    flashAlert($count ? "$count domain" . ($count === 1 ? '' : 's') . " linked" : 'Nothing to link', $count ? 'success' : 'info');
    redirect();

}

if (isset($_GET['unlink_phone_domain'])) {

    validateCSRFToken();

    $domain = phoneDomainRow($_GET['unlink_phone_domain']);
    if (!$domain) {
        flashAlert('That domain is gone.', 'error');
        redirect();
    }
    $client_id = intval($domain['phone_domain_client_id']);
    phoneDomainLink(intval($domain['phone_domain_id']), 0);
    logAudit("Phone System", "Edit", "$session_name unlinked " . escapeSql(phoneSystemName($domain)) . " domain " . escapeSql($domain['phone_domain_name']) . " from client " . escapeSql((string) $domain['client_name']), $client_id, intval($domain['phone_system_id']));
    flashAlert('<strong>' . escapeHtml($domain['phone_domain_name']) . '</strong> unlinked', 'error');
    redirect();

}
