<?php

/*
 * ITFlow - call records from a phone system. FreeSWITCH's mod_xml_cdr posts each call's record
 * here as it hangs up - xml_cdr.conf.xml's url, as the system's setup window in Admin > Phone
 * Systems shows it, with log-http-and-disk so FusionPBX still imports its own. Each call is kept
 * once (phoneCallSave(), functions/phone.php): a record sent again puts it right. A second leg of a
 * call is taken and set aside - its first leg is the call. Anything that isn't a call record is
 * turned down (400), so FreeSWITCH keeps it in its error folder.
 */

require_once __DIR__ . '/phone_system.php';

$phone_cdr = phoneCdrParse(phoneCdrFromRequest());
if (!$phone_cdr) {
    $phone_reply(400, 'No call record');
}

// mod_xml_cdr names a leg with a_ or b_ in uuid when prefix-a-leg is on
$phone_leg = (string) ($_REQUEST['uuid'] ?? '');
if (str_starts_with($phone_leg, 'b_')) {
    $phone_cdr['b_leg'] = true;
}

$phone_result = phoneCallSave($phone_system, $phone_cdr);

$phone_reply(200, $phone_result['saved'] ? 'OK' : 'OK - ' . $phone_result['why']);
