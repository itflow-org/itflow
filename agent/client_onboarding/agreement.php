<?php

/*
 * Onboarding - the agreement: the client's contracts so far, and one to
 * start from a template - from a day, looked after by an agent - which can
 * go to one of their people to sign (client_onboarding_agreement). Sent to
 * sign, it is a draft until it is countersigned, and active from then; not
 * sent, it is active from that day.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_templates = contractTemplates();
$co_contracts = [];
$co_sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_client_id = $client_id AND contract_archived_at IS NULL ORDER BY contract_id DESC");
while ($co_contract = mysqli_fetch_assoc($co_sql)) {
    $co_contracts[] = $co_contract;
}
$co_signers = [];
$co_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_primary FROM contacts
    WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email LIKE '%@%' ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC");
while ($co_signer = mysqli_fetch_assoc($co_sql)) {
    $co_signers[intval($co_signer['contact_id'])] = $co_signer['contact_name'] . ' - ' . $co_signer['contact_email'];
}
$co_agents = [];
$co_sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($co_agent = mysqli_fetch_assoc($co_sql)) {
    $co_agents[intval($co_agent['user_id'])] = $co_agent['user_name'];
}
$co_sign_ready = contractSignaturesReady();
// The template a client's agreement is most likely on - one of a managed type, else the first
$co_template_pick = '';
foreach ($co_templates as $co_template_id => $co_template) {
    if ($co_template_pick === '' || ($co_template['contract_template_type'] === 'managed' && $co_templates[$co_template_pick]['contract_template_type'] !== 'managed')) {
        $co_template_pick = (string) $co_template_id;
    }
}

?>
<?php if ($co_contracts) { ?>
    <ul class="list-unstyled itflow-gs-people mb-4" data-co-contracts="<?= count($co_contracts) ?>">
        <?php foreach ($co_contracts as $co_contract) { ?>
            <li data-co-contract="<?= intval($co_contract['contract_id']) ?>">
                <span class="itflow-gs-person-initials" aria-hidden="true"><i class="fas fa-file-contract"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <a href="/agent/contract.php?contract_id=<?= intval($co_contract['contract_id']) ?>" class="fw-semibold text-truncate d-block" target="_blank"><?= escapeHtml($co_contract['contract_name']) ?></a>
                    <div class="small text-body-secondary text-truncate"><?= escapeHtml(contractTypeLabel($co_contract['contract_type'])) ?>, <?= escapeHtml(strtolower(contractTermLabel($co_contract['contract_term_months']))) ?>
                        <?= !empty($co_contract['contract_start_date']) ? ' - from ' . escapeHtml(date('M j, Y', strtotime($co_contract['contract_start_date']))) : '' ?>
                        <?php if ($co_sign_ready && contractSignaturePendingFor(intval($co_contract['contract_id']))) { ?> - out to sign<?php } ?>
                    </div>
                </div>
                <?= contractStateHtml($co_contract) ?>
            </li>
        <?php } ?>
    </ul>
    <h2 class="h6 fw-semibold">Start another</h2>
<?php } ?>

<?php if (!$co_templates) { ?>
    <div class="alert alert-info small mb-0" data-co-no-templates>
        <i class="fas fa-info-circle me-1"></i>There are no contract templates yet. Make one in <a href="/admin/contract_templates.php">Admin &gt; Contract Templates</a>, or write the contract on the client's <a href="/agent/contracts.php?client_id=<?= $client_id ?>">Contracts</a> and come back - this step then ticks itself off.
    </div>
<?php } else { ?>
    <form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="agreement">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <?php $co_template_choices = [];
                foreach ($co_templates as $co_template_id => $co_template) {
                    $co_template_choices[$co_template_id] = $co_template['contract_template_name'] . ' - ' . contractTypeLabel($co_template['contract_template_type']) . ', ' . strtolower(contractTermLabel($co_template['contract_template_term_months']));
                } ?>
                <?= settingsSelect('template_id', $co_template_pick, $co_template_choices, ['id' => 'coTemplate', 'icon' => 'fa-file-contract', 'label' => 'Template', 'required' => true,
                    'help' => 'Its rates, net terms, SLAs, support hours and terms - change them on the contract afterwards if this client\'s differ.']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('start', date('Y-m-d'), ['id' => 'coContractStart', 'icon' => 'fa-calendar-day', 'type' => 'date', 'label' => 'From', 'required' => true, 'max' => '2999-12-31']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsSelect('owner_id', (string) intval($session_user_id), ['0' => '- Nobody -'] + $co_agents, ['id' => 'coContractOwner', 'icon' => 'fa-user-tie', 'label' => 'Looked after by']) ?>
            </div>
            <?php if ($co_sign_ready) { ?>
                <div class="col-12">
                    <div class="itflow-gs-optional">
                        <?= settingsSwitch('send', $co_signers ? '1' : '', ['id' => 'coContractSend', 'label' => 'Send it to them to sign', 'disabled' => !$co_signers, 'data' => ['gs-reveal' => 'coContractSendTo'],
                            'help' => $co_signers ? 'A draft until you have countersigned it, then active from the day above.' : 'Nobody here has an email address yet - add them on People, or send it from the contract later.']) ?>
                        <?php if ($co_signers) { ?>
                            <div id="coContractSendTo" class="row g-3 mt-0">
                                <div class="col-12">
                                    <?= settingsSelect('signer_id', (string) array_key_first($co_signers), $co_signers, ['id' => 'coContractSigner', 'icon' => 'fa-signature', 'label' => 'To']) ?>
                                </div>
                                <div class="col-12">
                                    <?= settingsTextarea('note', '', ['id' => 'coContractNote', 'label' => 'A note with it', 'rows' => 2, 'maxlength' => 2000, 'placeholder' => 'Looking forward to working with you.']) ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
        <button type="submit" name="client_onboarding_agreement" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-file-signature me-2"></i>Start the contract</button>
    </form>
<?php } ?>
