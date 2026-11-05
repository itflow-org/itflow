<?php

/*
 * ITFlow - managed mode: an instance connecting to this manager with a code from Admin >
 * Managed Instances. See managerEnroll() in functions/managed.php.
 */

require_once __DIR__ . '/bootstrap.php';

[$managed_status, $managed_answer] = managerEnroll($managed_api_body, getIP());

$managed_api_reply($managed_status, $managed_answer);
