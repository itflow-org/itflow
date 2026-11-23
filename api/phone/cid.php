<?php

/*
 * ITFlow - caller ID names from a phone system's contacts. FreeSWITCH's mod_cidlookup asks here
 * as a call comes in (cidlookup.conf.xml's url, as the system's setup window shows it) and the
 * phone shows what it gets back: the contact and their client, the client for its main number,
 * nothing for a number nobody has (phoneCallerName(), functions/phone.php). With the call's
 * domain, a client's own domain only matches that client's contacts.
 */

require_once __DIR__ . '/phone_system.php';

$phone_number = phoneNumber($_GET['number'] ?? '');
$phone_domain = phoneDomainName($_GET['domain'] ?? '');
$phone_domain_client_id = 0;
if ($phone_domain !== '') {
    $phone_domain_row = phoneDomain($phone_system, $phone_domain);
    $phone_domain_client_id = intval($phone_domain_row['phone_domain_client_id'] ?? 0);
}

$phone_reply(200, $phone_number !== '' ? phoneCallerName($phone_number, $phone_domain_client_id) : '');
