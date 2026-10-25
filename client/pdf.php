<?php
/*
 * Client Portal
 * An invoice, a quote or a contract as a PDF, downloaded - pdf.php?invoice=ID,
 * pdf.php?quote=ID or pdf.php?contract=ID. The same PDF the agents send
 * (functions/billing_pdf.php, functions/contract_pdf.php).
 *
 * SCOPING: the document must be the session's client's, and not a draft -
 * a draft contract only once it is sent to sign.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

portalBillingGate();

// A contract - the copy sent to sign or signed (portalContractRound()), else as it is today
if (isset($_GET['contract']) && contractsReady()) {
    $contract_id = intval($_GET['contract']);
    if ($contract_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_id = $contract_id AND " . portalContractScopeSql()))) {
        billingPdfSend(contractPdf(contractRow($contract_id), contractSignaturesReady() ? portalContractRound($contract_id) : null), true);
    }
}

foreach (['invoice', 'quote'] as $type) {
    if (!isset($_GET[$type])) {
        continue;
    }
    $row = billingRow($type, intval($_GET[$type]), false);
    if (!$row || intval($row['client_id']) !== $session_client_id || $row["{$type}_status"] === 'Draft') {
        break;
    }
    billingPdfSend(billingPdf($type, $row), true);
}

http_response_code(404);
exit('That document is not available.');
