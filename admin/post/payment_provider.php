<?php

/*
 * ITFlow - Admin > Payment Providers (admin/payment_providers.php): adding
 * one, changing it, turning it off and on, and deleting it.
 *
 * Saving checks what was sent (paymentProviderCheck(),
 * functions/payment_provider.php) and, when the keys are new or changed, asks
 * the provider whether it takes them - nothing is saved until both are right.
 * The Add and Edit window asks first (check_payment_provider, which answers
 * in JSON) and says what is wrong beside each field, so nothing typed is
 * lost; a key the window has just had taken isn't asked about twice. What
 * changed is in the audit log, from what to what - the keys only as changed.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['check_payment_provider']) || isset($_POST['add_payment_provider']) || isset($_POST['edit_payment_provider'])) {

    validateCSRFToken();

    $checking = isset($_POST['check_payment_provider']);
    $provider_id = intval($_POST['provider_id'] ?? 0);
    $before = $provider_id ? paymentProviderRow($provider_id) : null;

    if ($provider_id && !$before) {
        if ($checking) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'errors' => ['provider' => 'That payment provider no longer exists - close this and look again.'], 'account' => []]);
            exit;
        }
        flashAlert('That payment provider no longer exists.', 'error');
        redirect('payment_providers.php');
    }

    [$after, $errors] = paymentProviderCheck($_POST, $before);

    // New or changed keys - does the provider take them? Once taken, not asked again for ten minutes
    $account = [];
    if (!array_intersect_key($errors, ['provider' => 1, 'public_key' => 1, 'private_key' => 1]) && paymentProviderKeysChanged($after, $before)) {
        $asked_key = hash('sha256', $after['payment_provider_name'] . '|' . $after['payment_provider_private_key']);
        $asked_at = $_SESSION['payment_provider_asked'][$asked_key] ?? 0;
        if ($checking || time() - $asked_at > 600) {
            $asked = paymentProviderAsk($after['payment_provider_name'], $after['payment_provider_private_key']);
            if ($asked['ok']) {
                $_SESSION['payment_provider_asked'] = [$asked_key => time()];
                $account = $asked['account'];
            } else {
                $errors['private_key'] = $asked['error'];
            }
        }
    }

    if ($checking) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => !$errors, 'errors' => $errors, 'account' => $account]);
        exit;
    }

    // Only reached without the window's script - say the first thing wrong
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['payment_provider_name'];
    $mode = paymentProviderMode($after['payment_provider_private_key']);

    if (!$before) {
        $provider_id = paymentProviderWrite($after);
        $account_name = (string) (paymentProviderRow($provider_id)['account_name'] ?? '');
        logAudit("Payment Provider", "Create", "$session_name added payment provider " . escapeSql("$name ($mode mode), payments into $account_name, card limit " . paymentProviderLimitText($after['payment_provider_threshold'])
            . ', fees ' . ($after['payment_provider_expense_vendor'] ? 'recorded' : 'not recorded')), 0, $provider_id);
        flashAlert('<strong>' . escapeHtml($name) . '</strong> added - clients can pay invoices by card' . ($mode === 'test' ? '. Its keys are test keys, so no real money moves until they are live ones' : ''));
        redirect();
    }

    $lines = paymentProviderChangeLines($before, $after);
    if (!$lines) {
        flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
        redirect();
    }
    paymentProviderWrite($after, $provider_id);
    logAudit("Payment Provider", "Edit", "$session_name changed payment provider " . escapeSql("$name: " . implode('; ', $lines)), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes'));
    redirect();

}

// Turn a provider off - clients can't pay through it, and autopay with its cards is skipped - or on again
if (isset($_GET['turn_payment_provider_off']) || isset($_GET['turn_payment_provider_on'])) {

    validateCSRFToken();

    $on = isset($_GET['turn_payment_provider_on']);
    $row = paymentProviderRow($on ? $_GET['turn_payment_provider_on'] : $_GET['turn_payment_provider_off']);
    if (!$row) {
        flashAlert('That payment provider no longer exists.', 'error');
        redirect('payment_providers.php');
    }
    $name = $row['payment_provider_name'];
    if (intval($row['payment_provider_active']) === ($on ? 1 : 0)) {
        flashAlert('<strong>' . escapeHtml($name) . '</strong> is ' . ($on ? 'on' : 'off') . ' already', 'info');
        redirect();
    }
    if ($on && (!$row['account_name'] || $row['account_archived_at'])) {
        flashAlert('Its payments would go into an account that is ' . ($row['account_name'] ? 'archived' : 'gone') . ' - pick another in Edit, then turn it on', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE payment_providers SET payment_provider_active = " . ($on ? 1 : 0) . " WHERE payment_provider_id = " . intval($row['payment_provider_id']));
    logAudit("Payment Provider", "Edit", "$session_name turned payment provider " . escapeSql($name) . ($on ? ' on' : ' off'), 0, intval($row['payment_provider_id']));
    flashAlert($on
        ? '<strong>' . escapeHtml($name) . '</strong> turned on - clients can pay by card again'
        : '<strong>' . escapeHtml($name) . '</strong> turned off - clients can\'t pay by card, and autopay with its cards is skipped until it is on again');
    redirect();

}

// Delete a provider - its saved cards, the autopay on them and its links to clients go with it
if (isset($_GET['delete_payment_provider'])) {

    validateCSRFToken();

    $row = paymentProviderRow($_GET['delete_payment_provider']);
    if (!$row) {
        flashAlert('That payment provider no longer exists.', 'error');
        redirect('payment_providers.php');
    }
    $provider_id = intval($row['payment_provider_id']);
    $name = $row['payment_provider_name'];
    $ties = paymentProviderTiesText(paymentProviderTies($provider_id));

    // The cards, autopay and client links cascade with it
    mysqli_query($mysqli, "DELETE FROM payment_providers WHERE payment_provider_id = $provider_id");

    logAudit("Payment Provider", "Delete", "$session_name deleted payment provider " . escapeSql($name . ($ties !== '' ? " - and with it $ties" : '')), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> deleted' . ($ties !== '' ? ', with ' . escapeHtml($ties) : ''), 'error');
    redirect('payment_providers.php');

}

// Check connection - is the provider still taking the secret key, and whose account is it?
if (isset($_GET['check_payment_provider_connection'])) {

    validateCSRFToken();

    $row = paymentProviderRow($_GET['check_payment_provider_connection']);
    if (!$row) {
        flashAlert('That payment provider no longer exists.', 'error');
        redirect('payment_providers.php');
    }
    $name = $row['payment_provider_name'];
    $asked = paymentProviderAsk($name, $row['payment_provider_private_key']);
    if (!$asked['ok']) {
        logApp($name, 'warning', "Check connection - $name turned the saved key away: " . $asked['error']);
        flashAlert('<strong>' . escapeHtml($name) . '</strong> isn\'t connected - ' . escapeHtml($asked['error']), 'error');
        redirect();
    }
    $account = $asked['account'];
    $who = trim(($account['name'] ?? '') . (($account['email'] ?? '') !== '' ? ' (' . $account['email'] . ')' : ''));
    flashAlert('<strong>' . escapeHtml($name) . '</strong> is connected' . ($who !== '' ? ' to ' . escapeHtml($who) : '') . ', with '
        . (paymentProviderMode($row['payment_provider_private_key']) === 'live' ? 'live keys' : 'test keys - no real money moves'));
    redirect();

}

// Record fees now - what the nightly run does, for the payments whose fee is still to record
if (isset($_GET['record_payment_provider_fees'])) {

    validateCSRFToken();

    $row = paymentProviderRow($_GET['record_payment_provider_fees']);
    if (!$row) {
        flashAlert('That payment provider no longer exists.', 'error');
        redirect('payment_providers.php');
    }
    $name = $row['payment_provider_name'];
    if (!intval($row['payment_provider_expense_vendor']) || !intval($row['payment_provider_expense_category'])) {
        flashAlert('<strong>' . escapeHtml($name) . '</strong>\'s fees aren\'t recorded - switch Record its fees as expenses on in Edit first', 'error');
        redirect();
    }

    $done = paymentProviderRecordFees($row);
    $said = [];
    if ($done['recorded']) {
        $said[] = 'Recorded its fee on ' . $done['recorded'] . ' payment' . ($done['recorded'] === 1 ? '' : 's') . ' - ' . paymentProviderMoneyText($done['amount']);
        logAudit("Payment Provider", "Edit", escapeSql(stripslashes($session_name) . " recorded $name's fees on " . $done['recorded'] . ' card payment' . ($done['recorded'] === 1 ? '' : 's') . ' - ' . paymentProviderMoneyText($done['amount'])), 0, intval($row['payment_provider_id']));
    }
    if ($done['waiting']) {
        $said[] = $done['waiting'] . ' ' . ($done['waiting'] === 1 ? 'isn\'t' : 'aren\'t') . ' settled yet - ' . ($done['waiting'] === 1 ? 'it is' : 'they are') . ' recorded once ' . ($done['waiting'] === 1 ? 'it has' : 'they have');
    }
    if ($done['failed']) {
        $said[] = $name . ' wouldn\'t say for ' . $done['failed'] . ' - ' . $done['error'];
    }
    if (!$said) {
        flashAlert('There are no fees to record - every card payment of the last 30 days has its fee', 'info');
        redirect();
    }
    flashAlert(escapeHtml(implode('. ', $said)), $done['failed'] ? 'warning' : ($done['recorded'] ? 'success' : 'info'));
    redirect();

}
