<?php

/*
 * The payers Import Deposits remembers - what a deposit starting with these
 * words was last time: a payment from a client, other income in a category
 * and from a client, or a transfer from one of our accounts, and how often.
 * Forgetting one has the next statement match it afresh.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$rules = [];
$sql = mysqli_query($mysqli, "SELECT import_rule_id, import_rule_match, import_rule_action, import_rule_used_count, client_name, category_name, account_name
    FROM income_import_rules
    LEFT JOIN clients ON client_id = import_rule_client_id
    LEFT JOIN categories ON category_id = import_rule_category_id
    LEFT JOIN accounts ON account_id = import_rule_account_id
    ORDER BY import_rule_used_count DESC, import_rule_match ASC LIMIT 500");
while ($row = mysqli_fetch_assoc($sql)) {
    $rules[] = $row;
}
$actions = incomeImportActions();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-magic me-2"></i>Remembered Payers <span class="fw-normal text-white-50 ms-1"><?= count($rules) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body p-0">
    <div class="px-3 py-2 small text-body-secondary border-bottom">A deposit whose words start like these is matched to what was picked for it last time. Each import updates them.</div>
    <?php if ($rules) { ?>
        <div class="table-responsive" style="max-height: 28rem;">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light text-nowrap">
                <tr>
                    <th class="ps-3">Deposit starts</th>
                    <th>It is</th>
                    <th>From</th>
                    <th class="text-end">Used</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rules as $rule) {
                    $action = (string) $rule['import_rule_action'];
                    $from = $action === 'transfer' ? ($rule['account_name'] ?: 'An account since deleted') : ($rule['client_name'] ?: ($action === 'payment' ? 'A client since deleted' : '-')); ?>
                    <tr>
                        <td class="ps-3 font-monospace"><?= escapeHtml($rule['import_rule_match']) ?></td>
                        <td><?= escapeHtml($actions[$action] ?? $action) ?><?= $action === 'income' && $rule['category_name'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($rule['category_name']) . '</span>' : '' ?></td>
                        <td><?= escapeHtml($from) ?></td>
                        <td class="text-end"><?= intval($rule['import_rule_used_count']) ?></td>
                        <td class="text-end pe-3">
                            <a class="btn btn-sm btn-link text-danger p-0 confirm-link" title="Forget" href="post.php?income_import_rule_delete=<?= intval($rule['import_rule_id']) ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                data-confirm-text="Deposits starting &quot;<?= escapeHtml($rule['import_rule_match']) ?>&quot; are matched afresh next time."><i class="fas fa-fw fa-times"></i></a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-magic fa-2x mb-2 opacity-50"></i>
            <div>Nothing remembered yet - each statement you import teaches it.</div>
        </div>
    <?php } ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>
<?php
require_once '../../../includes/modal_footer.php';
