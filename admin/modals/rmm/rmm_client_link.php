<?php

/*
 * Admin > RMM Providers > a provider's clients - link one of them to an ITFlow client, or to
 * another one. The client of the same name is picked to start with. Saved by link_rmm_client
 * (admin/post/rmm.php), which brings its devices in straight away.
 */

require_once '../../includes/modal_header.php';

$row = rmmClientRow($_GET['id'] ?? 0);
if (!$row) {
    echo json_encode(['error' => 'That RMM client is gone - sync again.']);
    exit;
}
$name = rmmProviderName($row);
$rmm_client_id = intval($row['rmm_client_id']);
$current = intval($row['rmm_client_client_id']);
$picked = $current ?: (rmmClientSuggestions(intval($row['rmm_client_provider_id']))[$rmm_client_id] ?? 0);

// Every other client of this provider linked to each ITFlow client, so the picker can say so
$linked_to = [];
$sql = mysqli_query($mysqli, "SELECT rmm_client_client_id, rmm_client_name FROM rmm_clients WHERE rmm_client_provider_id = " . intval($row['rmm_client_provider_id']) . "
    AND rmm_client_client_id > 0 AND rmm_client_id <> $rmm_client_id AND rmm_client_removed_at IS NULL");
while ($linked = mysqli_fetch_assoc($sql)) {
    $linked_to[intval($linked['rmm_client_client_id'])][] = $linked['rmm_client_name'];
}

$clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL OR client_id = $current ORDER BY client_name ASC");
$devices = intval($row['rmm_client_devices']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-link me-2"></i>Link <strong><?= escapeHtml($row['rmm_client_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="rmm_client_id" value="<?= $rmm_client_id ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">Its <?= number_format($devices) ?> device<?= $devices === 1 ? '' : 's' ?> in <?= escapeHtml($name) ?> come in as this client's assets - matched to the ones it has by serial number or name, made where there are none.
            <?php if ($current) { ?>Linked to another client, they come in there - the assets already brought in for <?= escapeHtml((string) $row['client_name']) ?> stay, no longer kept up.<?php } ?></p>

        <label class="form-label">ITFlow client <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id" required>
                <option value="">- Select Client -</option>
                <?php while ($client = mysqli_fetch_assoc($clients)) {
                    $client_id = intval($client['client_id']);
                    $others = $linked_to[$client_id] ?? []; ?>
                    <option value="<?= $client_id ?>" <?= $client_id === $picked ? 'selected' : '' ?><?= selectOptionClient($client_id, $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?><?= $others ? ' (has ' . escapeHtml(implode(', ', $others)) . ' too)' : '' ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="link_rmm_client" class="btn btn-primary text-bold"><i class="fa fa-link me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php
require_once '../../../includes/modal_footer.php';
