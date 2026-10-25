<?php

/*
 * Countersign a contract the client has signed - by name, with a signature
 * drawn or typed (includes/contract_signature_pad.php). Then the client
 * gets the PDF signed by both, and a draft becomes active.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$round = contractSignatureRow(intval($_GET['id'] ?? 0));
if ($round) {
    $client_id = intval($round['contract_client_id']);
    enforceClientAccess();
}
$state = contractSignatureState($round);

ob_start();

if (!$round || $state['key'] !== 'countersign') { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $round ? "That contract isn't waiting for a countersignature." : 'That signing request is gone.' ?></div>
<?php } else {
    $me = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($session_user_id)))[0] ?? '';
    // The title used last time, to start with
    $title = mysqli_fetch_row(mysqli_query($mysqli, "SELECT contract_signature_agent_title FROM contract_signatures
        WHERE contract_signature_agent_user_id = " . intval($session_user_id) . " AND contract_signature_agent_title IS NOT NULL ORDER BY contract_signature_id DESC LIMIT 1"))[0] ?? '';
    $company = billingCompany();
    $signature_pad = ['id' => 'countersign' . intval($round['contract_signature_id']), 'name' => $me, 'title' => $title,
        'agree' => 'I agree to sign this electronically for ' . ($company['company_name'] ?? 'us')];
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-pen-nib me-2"></i>Countersign <strong><?= escapeHtml($round['contract_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_signature_id" value="<?= intval($round['contract_signature_id']) ?>">
    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="itflow-asset-icon bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-signature"></i></span>
            <div>
                <div class="fw-semibold"><?= escapeHtml($round['contract_signature_client_name']) ?> signed it for <?= escapeHtml($round['client_name']) ?></div>
                <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([(string) $round['contract_signature_client_title'], date('M j, Y g:i A', strtotime($round['contract_signature_client_signed_at']))]))) ?></div>
            </div>
        </div>
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_signature_pad.php'; ?>
        <small class="text-secondary d-block mt-3">
            <?= escapeHtml($round['contract_signature_sent_to_name']) ?> gets the PDF signed by both<?= $round['contract_status'] === 'Draft' ? ', and the contract becomes active from ' . escapeHtml(!empty($round['contract_start_date']) ? date('M j, Y', strtotime($round['contract_start_date'])) : 'today') : '' ?>.
        </small>
    </div>
    <div class="modal-footer">
        <a class="btn btn-link me-auto" href="contract_document.php?client_id=<?= $client_id ?>&contract_id=<?= intval($round['contract_id']) ?>&round=<?= intval($round['contract_signature_id']) ?>" target="_blank"><i class="fas fa-eye me-2"></i>Read what they signed</a>
        <button type="submit" name="countersign_contract" class="btn btn-success text-bold"><i class="fas fa-pen-nib me-2"></i>Countersign</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>
<script src="/js/signature_pad.js"></script>

<?php }
require_once '../../../includes/modal_footer.php';
