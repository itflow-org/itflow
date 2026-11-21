<?php

/*
 * Copy a recurring invoice to another client - the same package for the
 * next client: its lines, scope, category, discount, notes, how often and
 * how it sends, starting on the day picked. Seat lines count the new
 * client's users and devices; a line counting one of this client's
 * licenses keeps its quantity instead. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc = billingRow('recurring', intval($_GET['id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That recurring invoice no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();

$recurring_invoice_id = intval($doc['recurring_invoice_id']);
$lines = count(billingItems('recurring', $recurring_invoice_id));
$license_lines = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id AND item_seat_source = 'license'"))[0]);
$start = $doc['recurring_invoice_next_date'] >= date('Y-m-d') ? $doc['recurring_invoice_next_date'] : date('Y-m-01', strtotime('first day of next month'));

// The clients it can go to - not leads, not this one
$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL AND client_id != $client_id"
    . clientNotLeadSql() . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy me-2"></i>Copy to Another Client <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('recurring', $doc)) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_invoice_id ?>">

    <div class="modal-body">
        <p class="text-body-secondary small">
            A new recurring invoice with the same <?= $lines ?> line<?= $lines === 1 ? '' : 's' ?>, scope, category, discount and notes,
            billed <?= escapeHtml(strtolower(recurringInvoiceFrequencies()[$doc['recurring_invoice_frequency']] ?? $doc['recurring_invoice_frequency'])) ?> and sent the same way.
            Lines billed per user or device count the new client's.<?= $license_lines ? ' The line' . ($license_lines === 1 ? '' : 's') . ' counting this client\'s license seats keep' . ($license_lines === 1 ? 's its' : ' their') . ' quantity instead.' : '' ?>
            Autopay isn't copied.
        </p>
        <div class="mb-3">
            <label class="form-label">Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                <select class="form-select select2" name="client_id" required>
                    <option value="">- Select a client -</option>
                    <?php foreach ($clients as $client) { ?>
                        <option value="<?= intval($client['client_id']) ?>"<?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <label class="form-label">First invoice <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="start_date" max="2999-12-31" value="<?= escapeHtml($start) ?>" required>
        </div>
        <div class="form-text">The day its first invoice is made - the runs after follow on from it.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="copy_recurring_invoice" class="btn btn-primary fw-bold"><i class="fas fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
