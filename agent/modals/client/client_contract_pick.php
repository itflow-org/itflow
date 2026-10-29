<?php
/*
 * The client form's Billing tab: a contract to start from a template, for an
 * agent who can add contracts (contractClientPickReady()) - made by
 * add_client and edit_client through contractFromClientForm(). Posts
 * contract_template_id (0 for none) and contract_start. $contract_pick_edit:
 * true on Edit, where it adds another contract to the client's.
 */

$contract_pick_edit = !empty($contract_pick_edit);
$contract_pick_templates = contractClientPickReady() ? contractTemplates() : [];
?>
<?php if ($contract_pick_templates) { ?>
    <div class="row g-2 mb-3" data-client-contract-pick>
        <div class="col-12">
            <label class="form-label"><?= $contract_pick_edit ? 'New contract' : 'Contract' ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-file-contract"></i></span>
                <select class="form-select" name="contract_template_id" data-client-contract-template>
                    <option value="0"><?= $contract_pick_edit ? '- None - keep its contracts as they are -' : '- None - bill on the defaults -' ?></option>
                    <?php foreach ($contract_pick_templates as $contract_pick_id => $contract_pick_template) { ?>
                        <option value="<?= intval($contract_pick_id) ?>"><?= escapeHtml($contract_pick_template['contract_template_name']) ?> - <?= escapeHtml(contractTypeLabel($contract_pick_template['contract_template_type'])) ?>, <?= escapeHtml(strtolower(contractTermLabel($contract_pick_template['contract_template_term_months']))) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label">From</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" name="contract_start" value="<?= date('Y-m-d') ?>" data-client-contract-start>
            </div>
        </div>
        <div class="col-12 form-text">
            <?php if ($contract_pick_edit) { ?>
                Starts another contract from the template, active from that day. For what it sets it comes first, ahead of a contract in force now - end that one from its page if this replaces it.
            <?php } else { ?>
                Starts a contract from the template, active from that day - its rates, net terms and SLAs are the client's while it is in force. Change it afterwards on the client's Contracts.
            <?php } ?>
        </div>
    </div>
<?php } ?>
