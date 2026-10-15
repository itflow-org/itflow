<?php

/*
 * Add one vendor template to several clients at once - a standard vendor
 * rolled out everywhere. Opened from the vendors lists and from Admin >
 * Vendor Templates, so it posts to the agent handler by its full path. A client that already has a vendor made from the
 * template is shown as having it and is left alone.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$templates = [];
$sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name, vendor_template_description FROM vendor_templates WHERE vendor_template_archived_at IS NULL ORDER BY vendor_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[] = $row;
}
if (!$templates) {
    echo json_encode(['error' => 'There are no vendor templates yet - an admin adds them under Admin > Vendor Templates.']);
    exit;
}

$template_id = intval($_GET['template_id'] ?? 0);
$template_ids = array_map(function ($t) { return intval($t['vendor_template_id']); }, $templates);
if (!in_array($template_id, $template_ids, true)) {
    $template_id = $template_ids[0];
}

// Which clients already have a vendor from each template
$has = [];
$sql = mysqli_query($mysqli, "SELECT DISTINCT vendor_template_id, vendor_client_id FROM vendors WHERE vendor_template_id > 0 AND vendor_client_id > 0");
while ($row = mysqli_fetch_row($sql)) {
    $has[intval($row[0])][intval($row[1])] = true;
}

$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-puzzle-piece me-2"></i>Add a Vendor to Clients</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off" data-vendor-template-clients>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Template</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                <select class="form-select select2" name="vendor_template_id" data-template-pick>
                    <?php foreach ($templates as $template) {
                        $option_id = intval($template['vendor_template_id']); ?>
                        <option value="<?= $option_id ?>" <?= $option_id === $template_id ? 'selected' : '' ?><?= selectOptionVendor($template['vendor_template_name'], $template['vendor_template_description']) ?>><?= escapeHtml($template['vendor_template_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php foreach ($templates as $template) {
            $option_id = intval($template['vendor_template_id']);
            $already = count(array_filter($clients, function ($client) use ($has, $option_id) {
                return isset($has[$option_id][intval($client['client_id'])]);
            })); ?>
            <div data-template-clients="<?= $option_id ?>" <?= $option_id === $template_id ? '' : 'hidden' ?>>
                <label class="form-label">Clients</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <select class="form-select select2" name="client_ids[]" multiple data-placeholder="Pick the clients that use them" <?= $option_id === $template_id ? '' : 'disabled' ?>>
                        <?php foreach ($clients as $client) {
                            $client_option_id = intval($client['client_id']);
                            $client_has = isset($has[$option_id][$client_option_id]); ?>
                            <option value="<?= $client_option_id ?>" <?= $client_has ? 'disabled' : '' ?><?= selectOptionClient($client_option_id, $client['client_name'], $client['client_abbreviation'] ?? '') ?><?= $client_has ? selectOptionData(['description' => 'Already has it']) : '' ?>><?= escapeHtml($client['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text"><?= $already ? "$already client" . ($already === 1 ? ' has' : 's have') . ' it already. ' : '' ?>Each gets its own copy - add their account number and PIN after.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_vendor_template_to_clients" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Add to clients</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    itflowReady(function () {
        var form = document.querySelector('form[data-vendor-template-clients]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var pick = form.querySelector('[data-template-pick]');
        // Only the chosen template's client list posts
        pick.addEventListener('change', function () {
            form.querySelectorAll('[data-template-clients]').forEach(function (pane) {
                var on = pane.dataset.templateClients === pick.value;
                pane.hidden = !on;
                pane.querySelector('select').disabled = !on;
            });
        });
    });
</script>

<?php
require_once '../../../includes/modal_footer.php';
