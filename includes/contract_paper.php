<?php

/*
 * A contract as the client sees it - on the agent pages, the guest signing
 * page and the client portal alike, and as it prints. Set $contract_paper
 * before including:
 *
 *   row        contractRow()
 *   round      the round of signing shown (contract_signatures), or null
 *              for the document as it is today
 *   document   the document's HTML - the round's copy, or
 *              contractDocumentHtml() - made safe already
 *   logo_url   the company logo, relative to the page
 *   image_url  a function giving a signature picture its address, when the
 *              page can't show data: pictures (the client portal)
 *
 * The paper's head - the logo, who it is from, and where it stands - is
 * drawn here; the document is the contract itself.
 */

isset($contract_paper) || die("Direct file access is not allowed");

$contract_paper_row = $contract_paper['row'];
$contract_paper_round = $contract_paper['round'] ?? null;
$contract_paper_company = billingCompany();
$contract_paper_state = contractSignatureState($contract_paper_round);
$contract_paper_lines = array_filter([
    formatAddress($contract_paper_company['company_address'] ?? '', $contract_paper_company['company_city'] ?? '', $contract_paper_company['company_state'] ?? '', $contract_paper_company['company_zip'] ?? '', $contract_paper_company['company_country'] ?? '', "\n"),
    implode(' · ', array_filter([$contract_paper_company['company_email'] ?? '', $contract_paper_company['phone_display'] ?? ''])),
    $contract_paper_company['company_website'] ?? '',
]);
$contract_paper_dates = [];
if ($contract_paper_round && !empty($contract_paper_round['contract_signature_sent_at'])) {
    $contract_paper_dates['Sent'] = date('M j, Y', strtotime($contract_paper_round['contract_signature_sent_at']));
}
if ($contract_paper_state && $contract_paper_state['key'] === 'signed') {
    $contract_paper_dates['Signed'] = date('M j, Y', strtotime(max($contract_paper_round['contract_signature_client_signed_at'], $contract_paper_round['contract_signature_agent_signed_at'])));
}
if (!$contract_paper_dates) {
    $contract_paper_dates['As of'] = date('M j, Y');
}

?>
<div class="itflow-paper itflow-contract-paper" data-contract-paper>

    <?php if ($contract_paper_state && $contract_paper_state['key'] === 'signed') { ?>
        <div class="itflow-paper-stamp text-success" aria-hidden="true">Signed</div>
    <?php } elseif ($contract_paper_state && $contract_paper_state['key'] === 'withdrawn') { ?>
        <div class="itflow-paper-stamp text-secondary" aria-hidden="true">Withdrawn</div>
    <?php } ?>

    <div class="itflow-paper-head">
        <div class="itflow-paper-from">
            <?php if (!empty($contract_paper['logo_url']) && !empty($contract_paper_company['logo_url'])) { ?>
                <img class="itflow-paper-logo" src="<?= escapeHtml($contract_paper['logo_url']) ?>" alt="<?= escapeHtml($contract_paper_company['company_name'] ?? '') ?>">
            <?php } ?>
            <div class="fw-semibold fs-5"><?= escapeHtml($contract_paper_company['company_name'] ?? '') ?></div>
            <div class="small text-body-secondary"><?= nl2br(escapeHtml(implode("\n", $contract_paper_lines))) ?></div>
        </div>
        <div class="itflow-paper-what text-md-end">
            <div class="itflow-paper-title">Contract</div>
            <div class="fs-6 fw-semibold"><?= escapeHtml(contractTypeLabel($contract_paper_row['contract_type'])) ?></div>
            <dl class="itflow-paper-dates">
                <?php foreach ($contract_paper_dates as $contract_paper_label => $contract_paper_value) { ?>
                    <dt><?= $contract_paper_label ?></dt><dd><?= escapeHtml($contract_paper_value) ?></dd>
                <?php } ?>
            </dl>
        </div>
    </div>

    <?= $contract_paper['document'] ?>

    <?= contractSignatureBlockHtml($contract_paper_row, $contract_paper_round, $contract_paper['image_url'] ?? null) ?>

</div>
