<?php

/*
 * A contract as the client sees it - the document as it is today, with
 * empty lines to sign on, or (?round=) the copy sent in one round of
 * signing, with its signatures. Print and Download PDF from the top.
 * includes/contract_paper.php draws it.
 */

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else {
    require_once "includes/inc_client_overview_all.php";
}

enforceUserPermission('module_sales');

$contract = contractRow(intval($_GET['contract_id'] ?? 0));
$round = null;
if ($contract && !empty($_GET['round'])) {
    $round = contractSignatureRow(intval($_GET['round']));
    if ($round && intval($round['contract_id']) !== intval($contract['contract_id'])) {
        $round = null;
    }
}

if (!$contract || (isset($_GET['client_id']) && intval($contract['contract_client_id']) !== intval($client_id)) || (!empty($_GET['round']) && !$round)) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
    $contract_id = intval($contract['contract_id']);
    $page_title = $contract['contract_name'];
    $state = contractSignatureState($round);
    $contract_paper = [
        'row' => $contract,
        'round' => $round,
        'document' => $round ? (string) $round['contract_signature_document'] : contractDocumentHtml($contract),
        'logo_url' => billingCompany()['logo_url'],
    ];
    $unfilled = $round ? [] : contractUnfilled($contract_paper['document']);
    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($contract['client_name']) ?></a></li>
        <li class="breadcrumb-item"><a href="contracts.php?client_id=<?= $client_id ?>">Contracts</a></li>
        <li class="breadcrumb-item"><a href="contract.php?client_id=<?= $client_id ?>&contract_id=<?= $contract_id ?>"><?= escapeHtml($contract['contract_name']) ?></a></li>
        <li class="breadcrumb-item active"><?= $round ? 'Copy sent ' . escapeHtml(date('M j, Y', strtotime($round['contract_signature_sent_at'] ?? $round['contract_signature_created_at']))) : 'Document' ?></li>
    </ol>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
        <div class="me-auto small text-body-secondary">
            <?php if ($round) { ?>
                <span class="badge rounded-pill text-bg-<?= $state['tone'] ?> me-1"><i class="fas fa-fw <?= $state['icon'] ?> me-1"></i><?= escapeHtml($state['label']) ?></span>
                The copy sent to <?= escapeHtml($round['contract_signature_sent_to_name'] ?: 'the client') ?> - what was signed, as it was.
            <?php } else { ?>
                As the client would see it today. The placeholders in its terms are filled in.
            <?php } ?>
        </div>
        <a class="btn btn-outline-secondary" href="post.php?export_contract_pdf=<?= $contract_id ?><?= $round ? '&round=' . intval($round['contract_signature_id']) : '' ?>"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
    </div>

    <?php if ($unfilled) { ?>
        <div class="alert alert-warning small d-print-none" data-contract-unfilled><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Nothing fills in <?= escapeHtml(implode(', ', array_map(function ($word) { return "[$word]"; }, $unfilled))) ?> - it shows as written.</div>
    <?php } ?>

    <div class="row">
        <div class="col-xl-9 col-lg-10 itflow-paper-col">
            <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_paper.php'; ?>
        </div>
    </div>

<?php }

require_once "../includes/footer.php";
