<?php
/*
 * API - Contract Hours - Create
 * POST /api/v1/contract_hours/create.php
 *
 * Add a block of prepaid hours to a contract, as Add hours does - on a draft or
 * active contract, or a cancelled one until its last day - and, with invoice 1,
 * a draft invoice for them.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   contract_id  required - The contract
 *   client_id    its client - needed when the key's user is limited to certain clients
 *   hours        required - How many
 *   bought       required - The day they were bought, Y-m-d
 *   expires      optional - The day they run out; never if left out
 *   price        optional - What they cost
 *   note         optional
 *   invoice      optional - 1 makes a draft invoice for them (needs a price)
 *   category     optional - The invoice's income category
 *
 * Answer: insert_id, and invoice_id and invoice_reference when one was made.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once '../contracts/contract_find.php';

$insert_id = false;

if ($contract) {
    if (!contractHoursReady()) {
        apiFormRefuse('Prepaid hours need the database update - run it from Maintenance > Update first');
    }
    if (!($contract['contract_status'] === 'Draft' || contractHoursLive($contract))) {
        apiFormRefuse('Hours are added to a draft or active contract, or a cancelled one until its last day - renew it first');
    }
    $in = [];
    foreach (['hours', 'bought', 'expires', 'price', 'note', 'invoice', 'category'] as $key) {
        if (array_key_exists($key, $_POST)) {
            $in[$key] = is_scalar($_POST[$key]) ? (string) $_POST[$key] : '';
        }
    }
    [$f, $error] = contractHoursFromInput($in);
    if ($error === '' && $f['invoice'] && intval($config_module_enable_accounting) !== 1) {
        $error = 'Invoicing needs accounting turned on, in Admin > Settings > Modules.';
    }
    if ($error !== '') {
        apiFormRefuse($error);
    }
    [$block_id, $invoice_id, $number] = contractHoursAdd($contract, $f, $session_user_id, 'The API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')');
    $insert_id = intval($block_id) ?: false;
    if ($invoice_id) {
        $return_arr['invoice_id'] = intval($invoice_id);
        $return_arr['invoice_reference'] = $number;
    }
    $hours = escapeSql(contractHoursWords($f['hours']));
    logAudit("Contract", "Edit", "Added $hours to contract $contract_label" . ($invoice_id ? ", on invoice " . escapeSql($number) : '') . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Added $hours to contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
