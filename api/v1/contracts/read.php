<?php
/*
 * API - Contracts - Read
 * GET /api/v1/contracts/read.php
 *
 * Clients' contracts - Draft, Active, Ended or Cancelled. One, by contract_id,
 * also has its prepaid hours (hours: bought, used, left, lapsed, next_expiry, and
 * each block), and where its signing stands (signature: its state, who it was
 * sent to, when).
 *
 *   contract_id    optional - Just this contract, with its hours and signing
 *   client_id      optional - Just this client's
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (isset($_GET['contract_id'])) {
    $id = intval($_GET['contract_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $id " . apiClientScopeSql('contract_client_id'));
    if ($sql && ($row = mysqli_fetch_assoc($sql))) {
        if (contractHoursReady()) {
            $blocks = contractBlocks($id);
            $row['hours'] = array_merge(contractHoursSummary($blocks), ['blocks' => array_map(function ($block) {
                return ['contract_block_id' => intval($block['contract_block_id']), 'hours' => floatval($block['contract_block_hours']), 'left' => floatval($block['left']),
                    'bought' => $block['contract_block_bought'], 'expires' => $block['contract_block_expires'], 'expired' => (bool) $block['expired'],
                    'note' => $block['contract_block_note'], 'invoice_id' => intval($block['contract_block_invoice_id'])];
            }, array_values($blocks))]);
        }
        $row['signature'] = null;
        if (contractSignaturesReady() && ($round = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contract_signature_id FROM contract_signatures WHERE contract_signature_contract_id = $id ORDER BY contract_signature_id DESC LIMIT 1")))) {
            $round = contractSignatureRow($round['contract_signature_id']);
            $state = contractSignatureState($round);
            $row['signature'] = ['contract_signature_id' => intval($round['contract_signature_id']), 'state' => $state['key'] ?? null,
                'sent_to_name' => $round['contract_signature_sent_to_name'], 'sent_to_email' => $round['contract_signature_sent_to_email'], 'sent_at' => $round['contract_signature_sent_at'] ?? null];
        }
        echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
        exit();
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE 1=1 " . apiClientScopeSql('contract_client_id') . " ORDER BY contract_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
