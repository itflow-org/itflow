<?php

/*
 * ITFlow - Admin > Payment Providers > Saved Cards (admin/saved_payment_methods.php):
 * removing a client's saved card - at the provider and in ITFlow, the
 * autopay on it with it (paymentCardRemove(), functions/payment_provider.php).
 * A provider that can't be told is said, so the card can be taken off there
 * by hand.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['delete_saved_payment'])) {

    validateCSRFToken();

    $removed = paymentCardRemove($_GET['delete_saved_payment'], stripslashes($session_name), $error);
    if (!$removed) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = $removed['row'];
    $client_id = intval($row['saved_payment_client_id']);
    $client_name = (string) ($row['client_name'] ?? "client #$client_id");
    $provider_name = (string) ($row['payment_provider_name'] ?: 'its provider');
    $stopped = array_column($removed['stopped'], 'reference');

    logAudit("Payment Provider", "Delete", escapeSql(stripslashes($session_name) . " removed $client_name's saved card " . $removed['label'] . " (" . $row['saved_payment_provider_method'] . ")"
        . ($stopped ? ' - autopay stopped on ' . implode(', ', $stopped) : '')
        . ($removed['told'] ? '' : " - $provider_name couldn't be told: " . $removed['provider_error'])), $client_id, intval($row['saved_payment_id']));

    $said = '<strong>' . escapeHtml($removed['label']) . '</strong> removed from ' . escapeHtml($client_name)
        . ($stopped ? ' - autopay stopped on ' . escapeHtml(implode(', ', $stopped)) : '');
    if (!$removed['told']) {
        flashAlert($said . '. ' . escapeHtml($provider_name) . " couldn't be told, so it is still saved there - remove it in " . escapeHtml($provider_name) . ' too', 'warning');
    } else {
        flashAlert($said, 'error');
    }

    redirect();

}
