<?php
/*
 * Client Portal
 * An invoice or a quote as a PDF, downloaded - pdf.php?invoice=ID or
 * pdf.php?quote=ID. The same PDF the agents send (functions/billing_pdf.php).
 *
 * SCOPING: the document must be the session's client's, and not a draft.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

portalBillingGate();

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
