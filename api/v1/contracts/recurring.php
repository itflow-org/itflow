<?php
/*
 * API - Contracts - Recurring
 * POST /api/v1/contracts/recurring.php
 *
 * Give a contract the recurring invoice that bills it - one of the client's that
 * isn't another contract's (recurring_invoice_id), or a new one made from the
 * fields below - as Bill it does. It runs while the contract is in force.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients),
 *   recurring_invoice_id  Link this one - or make one:
 *   item_name, amount     required to make one - its line
 *   frequency             optional - month (the default), quarter or year
 *   start                 optional - Its first invoice, Y-m-d; as Bill it offers if left out
 *   description, tax_id, category, auto_send   optional
 *
 * Answer: recurring_invoice_id, reference and state (started, stopped or unchanged)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    if (!contractBillingReady()) {
        apiFormRefuse('Linking a recurring invoice needs the database update - run it from Maintenance > Update first');
    }
    if (intval($config_module_enable_accounting) !== 1) {
        apiFormRefuse('Recurring invoices need accounting turned on, in Admin > Settings > Modules');
    }
    if (!contractRecurringShouldRun($contract) && $contract['contract_status'] !== 'Draft') {
        apiFormRefuse("An ended contract isn't billed - renew it first");
    }
    if (contractRecurringRow($contract)) {
        apiFormRefuse('It already has a recurring invoice - unlink that one first');
    }

    $by = 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')';
    if (!empty($_POST['recurring_invoice_id'])) {
        $recurring_id = intval($_POST['recurring_invoice_id']);
        $choices = contractRecurringChoices($client_id, $contract_id);
        if (!isset($choices[$recurring_id])) {
            apiFormRefuse("recurring_invoice_id is one of the client's recurring invoices that isn't another contract's");
        }
        $number = billingNumber('recurring', $choices[$recurring_id]);
        $state = contractRecurringLink($contract, $recurring_id, $by);
        $what = "Linked recurring invoice " . escapeSql($number) . " to";
    } else {
        $in = [];
        foreach (['item_name', 'amount', 'frequency', 'start', 'description', 'tax_id', 'category', 'auto_send'] as $key) {
            if (array_key_exists($key, $_POST)) {
                $in[$key] = is_scalar($_POST[$key]) ? (string) $_POST[$key] : '';
            }
        }
        $in += ['frequency' => 'month', 'auto_send' => '1', 'start' => contractRecurringFirstDate($contract)];
        [$f, $error] = contractRecurringFromInput($in);
        if ($error !== '') {
            apiFormRefuse($error);
        }
        [$recurring_id, $number, $state] = contractRecurringCreate($contract, $f, $by);
        $what = "Made recurring invoice " . escapeSql($number) . " for";
    }

    $update_count = 1;
    $return_arr['recurring_invoice_id'] = intval($recurring_id);
    $return_arr['reference'] = $number;
    $return_arr['state'] = $state;
    logAudit("Contract", "Edit", "$what contract $contract_label via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "$what contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
