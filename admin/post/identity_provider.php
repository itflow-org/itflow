<?php

/*
 * Admin > Settings > Single sign-on (admin/identity_providers.php):
 * edit_identity_provider saves the Microsoft Entra app the client portal
 * signs in with. A secret left empty stays as saved; an empty app ID turns
 * it off and forgets the secret. An ID that isn't one, or an app with no
 * secret, comes back to the page saying so - nothing is saved.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_identity_provider'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_azure_client_id', 'config_azure_client_secret']);

    $client_id = trim((string) ($_POST['config_azure_client_id'] ?? $before['config_azure_client_id']));
    $secret = trim((string) ($_POST['config_azure_client_secret'] ?? ''));
    $after = ['config_azure_client_id' => $client_id, 'config_azure_client_secret' => $client_id === '' ? '' : ($secret !== '' ? $secret : (string) $before['config_azure_client_secret'])];

    $errors = [];
    if ($client_id !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $client_id)) {
        $errors['config_azure_client_id'] = 'The Application (client) ID - 32 letters and numbers in five groups, as e721e3b6-01d6-50e8-7f22-c84d951a52e7.';
    } elseif ($client_id !== '' && $after['config_azure_client_secret'] === '') {
        $errors['config_azure_client_secret'] = 'And a secret for it, from Certificates & secrets.';
    }
    if ($errors) {
        settingsRefuse('identity', $errors);
    }

    settingsSave('identity', $before, $after);

    redirect();

}
