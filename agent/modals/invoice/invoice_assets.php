<?php

/*
 * Add to Assets - the hardware sold on an invoice goes on the client's
 * assets. ?invoice_id=
 *
 * One row for each unit not on their assets yet, under its line: a name, the
 * serial and who has it - or, when the client already has it on file, the
 * asset to link instead. The make, model and warranty come from the product
 * (the warranty counted from the invoice date) and can be changed per line;
 * the status and location are for all of them. A row taken out is left for
 * later. Posts invoice_assets_save.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');
enforceUserPermission('module_support', 2);

$invoice = billingRow('invoice', intval($_GET['invoice_id'] ?? 0));
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$invoice_id = intval($invoice['invoice_id']);
$client_id = intval($invoice['client_id']);
enforceClientAccess();
$number = billingNumber('invoice', $invoice);
$invoice_date = substr((string) $invoice['invoice_date'], 0, 10);

if ($invoice['invoice_status'] === 'Cancelled') {
    exit(json_encode(['error' => escapeHtml($number) . ' is cancelled - nothing on it was sold.']));
}
$lines = array_values(array_filter(productInvoiceHardware($invoice_id), function ($line) {
    return $line['to_add'] > 0;
}));
if (!$lines) {
    exit(json_encode(['error' => 'Every unit of hardware on ' . escapeHtml($number) . ' is on ' . escapeHtml($invoice['client_name']) . "'s assets."]));
}

$statuses = [];
foreach (categoryPicker('asset_status') as $status_name => $status_row) {
    $statuses[$status_name] = assetStatusColor($status_name, $status_row['category_color'] ?? '');
}
$status_default = isset($statuses['Deployed']) ? 'Deployed' : (isset($statuses['Ready to Deploy']) ? 'Ready to Deploy' : (string) array_key_first($statuses));

$locations = [];
$location_default = 0;
$sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_primary FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $locations[intval($row['location_id'])] = $row['location_name'];
    if (intval($row['location_primary']) && !$location_default) {
        $location_default = intval($row['location_id']);
    }
}
$contacts = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $contacts[intval($row['contact_id'])] = $row['contact_name'];
}

$form_id = "invoiceAssets$invoice_id";
$total = array_sum(array_column($lines, 'to_add'));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-laptop me-2"></i>Add to Assets <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($number) ?> &middot; <?= escapeHtml($invoice['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="<?= $form_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body">
        <p class="small text-body-secondary mb-3">
            Each one goes on <?= escapeHtml($invoice['client_name']) ?>'s assets, bought <?= escapeHtml($invoice_date) ?> on <?= escapeHtml($number) ?>.
            Take a row out to leave it for later.
        </p>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label">Status</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-circle fa-fw" data-assets-status-icon style="color: <?= escapeHtml($statuses[$status_default] ?? 'var(--bs-secondary-color)') ?>"></i></span>
                    <select class="form-select" name="status" data-assets-status>
                        <option value="" data-color="var(--bs-secondary-color)">- No status -</option>
                        <?php foreach ($statuses as $status_name => $status_color) { ?>
                            <option value="<?= escapeHtml($status_name) ?>" data-color="<?= escapeHtml($status_color) ?>" <?= $status_name === $status_default ? 'selected' : '' ?>><?= escapeHtml($status_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Location</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                    <select class="form-select" name="location">
                        <option value="0">- No location -</option>
                        <?php foreach ($locations as $location_id => $location_name) { ?>
                            <option value="<?= $location_id ?>" <?= $location_id === $location_default ? 'selected' : '' ?>><?= escapeHtml($location_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <?php foreach ($lines as $line) {
            $item_id = intval($line['item_id']);
            $asset_type = (string) $line['product_asset_type'];
            $recorded = count($line['assets']);
            $base_name = trim((string) $line['product_model']) !== '' ? trim((string) $line['product_model']) : (string) $line['product_name'];
            $warranty = productWarrantyUntil($invoice_date, $line['product_warranty_months']);
            $candidates = productAssetCandidates($client_id, $asset_type);
            $same_type = array_filter($candidates, function ($candidate) use ($asset_type) {
                return $candidate['asset_type'] === $asset_type;
            });
            $other_type = array_filter($candidates, function ($candidate) use ($asset_type) {
                return $candidate['asset_type'] !== $asset_type;
            });
            ?>
            <section class="itflow-hw-line mb-3" data-hw-line>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <?= productAssetIcon($asset_type, 'sm') ?>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-semibold text-truncate"><?= escapeHtml($line['item_name']) ?></div>
                        <div class="small text-body-secondary"><?= escapeHtml($asset_type) ?> &middot; <?= escapeHtml(billingQuantity($line['item_quantity'])) ?> sold<?= $recorded ? ", $recorded on their assets already" : '' ?></div>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" title="Make"><i class="fas fa-fw fa-industry"></i></span>
                            <input type="text" class="form-control" name="lines[<?= $item_id ?>][make]" maxlength="200" value="<?= escapeHtml($line['product_make']) ?>" placeholder="Make" aria-label="Make">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" title="Model"><i class="fas fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="lines[<?= $item_id ?>][model]" maxlength="200" value="<?= escapeHtml($line['product_model']) ?>" placeholder="Model" aria-label="Model">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" title="Warranty until"><i class="fas fa-fw fa-shield-alt"></i></span>
                            <input type="date" class="form-control" name="lines[<?= $item_id ?>][warranty]" max="2999-12-31" value="<?= escapeHtml((string) $warranty) ?>" aria-label="Warranty until" title="Warranty until">
                        </div>
                    </div>
                </div>

                <div class="itflow-hw-units" data-hw-units>
                    <?php for ($i = 0; $i < $line['to_add']; $i++) {
                        $unit_name = $line['units'] > 1 || $recorded ? $base_name . ' ' . ($recorded + $i + 1) : $base_name;
                        $field = "units[$item_id][$i]"; ?>
                        <div class="row g-2 align-items-center itflow-hw-unit" data-hw-unit>
                            <?php if ($candidates) { ?>
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" title="New, or one already on file"><i class="fas fa-fw fa-link"></i></span>
                                        <select class="form-select" name="<?= $field ?>[existing]" aria-label="New asset, or one they have" data-hw-existing>
                                            <option value="0">New asset</option>
                                            <?php foreach ([$asset_type => $same_type, 'Their other assets' => $other_type] as $group_label => $group) {
                                                if (!$group) {
                                                    continue;
                                                } ?>
                                                <optgroup label="<?= escapeHtml($group_label) ?>">
                                                    <?php foreach ($group as $candidate) { ?>
                                                        <option value="<?= intval($candidate['asset_id']) ?>"><?= escapeHtml($candidate['asset_name'] . ($candidate['asset_serial'] ? ' - ' . $candidate['asset_serial'] : '')) ?></option>
                                                    <?php } ?>
                                                </optgroup>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="<?= $candidates ? 'col-md-3' : 'col-md-4' ?>">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" title="Name"><i class="fas fa-fw fa-tag"></i></span>
                                    <input type="text" class="form-control" name="<?= $field ?>[name]" maxlength="200" value="<?= escapeHtml($unit_name) ?>" placeholder="Name" aria-label="Name" required data-hw-new>
                                </div>
                            </div>
                            <div class="<?= $candidates ? 'col-md-3' : 'col-md-4' ?> col-sm-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" title="Serial number"><i class="fas fa-fw fa-barcode"></i></span>
                                    <input type="text" class="form-control font-monospace" name="<?= $field ?>[serial]" maxlength="200" placeholder="Serial" aria-label="Serial number" data-hw-new data-hw-serial>
                                </div>
                            </div>
                            <div class="col-md col-sm-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" title="Assigned to"><i class="fas fa-fw fa-user"></i></span>
                                    <select class="form-select" name="<?= $field ?>[contact]" aria-label="Assigned to" data-hw-new>
                                        <option value="0">- Nobody -</option>
                                        <?php foreach ($contacts as $contact_id => $contact_name) { ?>
                                            <option value="<?= $contact_id ?>"><?= escapeHtml($contact_name) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-link text-body-secondary px-1" title="Leave this one for later" aria-label="Leave this one for later" data-hw-remove><i class="fas fa-fw fa-times"></i></button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="small text-body-secondary mt-1" data-hw-empty hidden>All of these are left for later.</div>
            </section>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <span class="small text-body-secondary me-auto d-none d-sm-inline">Enter in a serial moves to the next one</span>
        <button type="submit" name="invoice_assets_save" class="btn btn-primary text-bold" data-hw-save><i class="fas fa-check me-2"></i><span data-hw-save-label>Add <?= $total ?> to Assets</span></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById(<?= json_encode($form_id) ?>);
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var save = form.querySelector('[data-hw-save]');
    var label = form.querySelector('[data-hw-save-label]');
    var statusIcon = form.querySelector('[data-assets-status-icon]');

    function update() {
        var added = 0, linked = 0;
        form.querySelectorAll('[data-hw-unit]').forEach(function (row) {
            var existing = row.querySelector('[data-hw-existing]');
            var linking = existing && existing.value !== '0';
            row.querySelectorAll('[data-hw-new]').forEach(function (input) {
                input.disabled = linking;
            });
            row.classList.toggle('itflow-hw-linking', linking);
            linking ? linked++ : added++;
        });
        form.querySelectorAll('[data-hw-line]').forEach(function (line) {
            var none = !line.querySelector('[data-hw-unit]');
            line.querySelector('[data-hw-empty]').hidden = !none;
        });
        var parts = [];
        if (added) {
            parts.push('Add ' + added);
        }
        if (linked) {
            parts.push((added ? 'link ' : 'Link ') + linked);
        }
        label.textContent = parts.length ? parts.join(', ') + ' to Assets' : 'Nothing to add';
        save.disabled = !(added + linked);
        var picked = form.querySelector('[data-assets-status]').selectedOptions[0];
        statusIcon.style.color = picked ? picked.dataset.color : '';
    }
    form.addEventListener('change', update);
    form.addEventListener('click', function (e) {
        var remove = e.target.closest('[data-hw-remove]');
        if (remove) {
            remove.closest('[data-hw-unit]').remove();
            update();
        }
    });
    // A barcode scanner ends with Enter - go to the next serial instead of saving
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches('[data-hw-serial]')) {
            return;
        }
        e.preventDefault();
        var serials = Array.prototype.filter.call(form.querySelectorAll('[data-hw-serial]'), function (input) {
            return !input.disabled;
        });
        var next = serials[serials.indexOf(e.target) + 1];
        if (next) {
            next.focus();
        }
    });
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
