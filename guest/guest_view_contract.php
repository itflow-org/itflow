<?php

/*
 * A contract as the client opens it from the email asking them to sign -
 * the document as it was sent, on the left; on the right (under it, on a
 * phone) where it stands and, while it waits, the signing form: full name, job title, a signature
 * drawn or typed, and the box to tick. Then: signed, waiting for us to
 * countersign; signed by both, with the PDF; or withdrawn. Opening it the
 * first time tells the agents.
 *
 * The round's id and key are the only proof (contractSignatureByKey()),
 * matched exactly. guest_post.php takes the signature and the PDF.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/inc_all_guest.php';

$round = contractSignaturesReady() ? contractSignatureByKey(intval($_GET['id'] ?? 0), $_GET['key'] ?? '') : null;

if (!$round) {
    echo '<div class="itflow-asset-empty mt-5"><i class="fas fa-file-signature fa-3x mb-3 opacity-50"></i><h1 class="h4">This contract link isn\'t working</h1><p>It may have been mistyped, or the contract deleted. Ask whoever sent it for a new link.</p></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$contract = contractRow(intval($round['contract_id']));
$state = contractSignatureState($round);
$company = billingCompany();
$query = 'id=' . intval($round['contract_signature_id']) . '&key=' . rawurlencode((string) $round['contract_signature_url_key']);
$waiting = in_array($state['key'], ['sent', 'viewed'], true);

contractSignatureViewed($round);

$contract_paper = [
    'row' => $contract,
    'round' => $round,
    'document' => (string) $round['contract_signature_document'],
    'logo_url' => $company['logo_url'],
];
$signature_pad = [
    'id' => 'sign',
    'name' => (string) $round['contract_signature_sent_to_name'],
    'title' => intval($round['contract_signature_contact_id']) ? (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_title FROM contacts WHERE contact_id = " . intval($round['contract_signature_contact_id'])))[0] ?? '') : '',
    'agree' => 'I have read this contract and agree to it for ' . $round['client_name'] . ', and to signing it electronically',
];

?>

<?php if ($waiting) { ?>
    <a class="btn btn-success w-100 mt-3 d-lg-none d-print-none" href="#sign"><i class="fas fa-file-signature me-2"></i>Read it through - you sign at the end</a>
<?php } ?>

<div class="row g-3 my-1">

    <div class="col-lg-8 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_paper.php'; ?>
    </div>

    <div class="col-lg-4 d-print-none" id="sign">
        <div class="card mb-3" data-contract-sign-panel>
            <div class="card-body">
                <div class="itflow-billing-stat-label">Contract</div>
                <div class="fs-5 fw-semibold mb-1"><?= escapeHtml($round['contract_name']) ?></div>
                <div class="small text-body-secondary mb-3">For <?= escapeHtml($round['client_name']) ?> · from <?= escapeHtml($company['company_name'] ?? '') ?></div>

                <?php if ($waiting) { ?>
                    <p class="small mb-3">Read it through, then sign below - it takes a minute. We countersign after, and you get a copy signed by both of us.</p>
                    <form action="guest_post.php" method="post" autocomplete="off" data-contract-sign-form>
                        <input type="hidden" name="contract_signature_id" value="<?= intval($round['contract_signature_id']) ?>">
                        <input type="hidden" name="key" value="<?= escapeHtml($round['contract_signature_url_key']) ?>">
                        <input type="hidden" name="document_hash" value="<?= escapeHtml($round['contract_signature_document_hash']) ?>">
                        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_signature_pad.php'; ?>
                        <button type="submit" name="sign_contract" class="btn btn-success btn-lg w-100 mt-3"><i class="fas fa-file-signature me-2"></i>Sign</button>
                    </form>
                <?php } elseif ($state['key'] === 'countersign') { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold">Signed - thank you</div>
                            <div class="small text-body-secondary"><?= escapeHtml($round['contract_signature_client_name']) ?> signed it <?= escapeHtml(date('M j, Y', strtotime($round['contract_signature_client_signed_at']))) ?>. We'll countersign it and email you a copy signed by both of us.</div>
                        </div>
                    </div>
                <?php } elseif ($state['key'] === 'signed') { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                        <div>
                            <div class="fw-semibold">Signed by both</div>
                            <div class="small text-body-secondary"><?= escapeHtml($state['when']) ?>. Keep the PDF for your records.</div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="fas fa-undo"></i></span>
                        <div>
                            <div class="fw-semibold">This request was withdrawn</div>
                            <div class="small text-body-secondary">
                                <?= $round['contract_signature_withdrawn_reason'] === 'Replaced by a new request' ? 'A newer version was sent - sign from the latest email.' : 'It can no longer be signed. If you were expecting to sign it, ask us for a new link.' ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <div class="d-flex gap-2 mt-3">
                    <a class="btn btn-outline-primary flex-fill" href="guest_post.php?export_contract_pdf=1&<?= escapeHtml($query) ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
                </div>
            </div>
        </div>

        <?php if (!empty($company['company_email']) || !empty($company['phone_display'])) { ?>
            <div class="small text-body-secondary px-1">
                Questions about this contract? Contact <?= escapeHtml($company['company_name']) ?>
                <?php if (!empty($company['company_email'])) { ?> at <a href="mailto:<?= escapeHtml($company['company_email']) ?>?subject=<?= rawurlencode('Contract: ' . $round['contract_name']) ?>"><?= escapeHtml($company['company_email']) ?></a><?php } ?>
                <?php if (!empty($company['phone_display'])) { ?> or <?= escapeHtml($company['phone_display']) ?><?php } ?>.
            </div>
        <?php } ?>
    </div>

</div>

<script src="/js/signature_pad.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
