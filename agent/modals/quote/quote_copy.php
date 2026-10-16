<?php

/*
 * Copy a quote - a new draft with the same lines, scope, category, discount
 * and notes, for this client or another, on the dates given. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc_type = 'quote';
$doc = billingRow($doc_type, intval($_GET['id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That quote no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();
$doc_number = billingNumber($doc_type, $doc);
$lines = count(billingItems('quote', intval($doc['quote_id'])));

$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy me-2"></i>Copy Quote <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= intval($doc['quote_id']) ?>">

    <div class="modal-body">
        <p class="text-body-secondary small">A new draft with the same <?= $lines ?> line<?= $lines === 1 ? '' : 's' ?>, scope, discount and notes - <?= escapeHtml(billingMoney($doc['quote_amount'], $doc['quote_currency_code'])) ?>. Quote the same work for another client by picking them here.</p>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">For <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <?php foreach ($clients as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>" <?= intval($client_option['client_id']) === $client_id ? 'selected' : '' ?><?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Quote date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Valid until <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                    <input type="date" class="form-control" name="expire" min="<?= date('Y-m-d') ?>" max="2999-12-31" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_copy" class="btn btn-primary text-bold"><i class="fas fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
