<?php

/*
 * Start Contract, from the clients list's Action menu: a contract from a
 * template for each client ticked, active from the day picked -
 * bulk_add_client_contract (agent/post/client.php).
 */

require_once '../../../includes/modal_header.php';

$client_ids = array_map('intval', $_GET['client_ids'] ?? []);

$count = count($client_ids);

$templates = contractClientPickReady() ? contractTemplates() : [];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract me-2"></i>Start a contract for <strong><?= $count ?></strong> client<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($client_ids as $client_id) { ?><input type="hidden" name="client_ids[]" value="<?= $client_id ?>"><?php } ?>

    <div class="modal-body">

        <?php if ($templates) { ?>
            <div class="mb-3">
                <label class="form-label">Template <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-contract"></i></span>
                    <select class="form-select" name="contract_template_id" required data-bulk-contract-template>
                        <option value="">- Pick a template -</option>
                        <?php foreach ($templates as $template_id => $template) { ?>
                            <option value="<?= intval($template_id) ?>"><?= escapeHtml($template['contract_template_name']) ?> - <?= escapeHtml(contractTypeLabel($template['contract_template_type'])) ?>, <?= escapeHtml(strtolower(contractTermLabel($template['contract_template_term_months']))) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">From</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                    <input type="date" class="form-control" name="contract_start" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <p class="small text-secondary mb-0">
                Each client gets its own contract from the template, active from that day, with its primary contact - and you to look after it.
                Its rates, net terms and SLAs are the client's while it is in force, ahead of a contract in force now for what it sets.
            </p>
        <?php } else { ?>
            <p class="text-secondary mb-0">There are no contract templates to start one from - make one in Admin &gt; Contract Templates.</p>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <?php if ($templates) { ?>
            <button type="submit" name="bulk_add_client_contract" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Start</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
