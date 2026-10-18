<?php

/*
 * New license for a client from a license template - from Admin > License
 * Templates and the template's page. The template gives it its name, type,
 * license type, summary and notes; this window the client, seats, key, order
 * reference and dates. It goes on the client's Licenses
 * (add_software_from_template, agent/post/software.php). &client_id= picks
 * the client.
 */

require_once '../../includes/modal_header.php';

$template = softwareTemplateRow($_GET['id'] ?? 0);
$picked_client_id = intval($_GET['client_id'] ?? 0);

ob_start();

if (!$template || $template['software_template_archived_at']) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>New license</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That license template is gone or archived.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-cube me-2"></i>New license from <strong><?= escapeHtml($template['software_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?= intval($template['software_template_id']) ?>">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Pick a client -</option>
                        <?php
                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= intval($row['client_id']) === $picked_client_id ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Seats</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <input type="number" class="form-control" name="seats" min="0" step="1" placeholder="No limit">
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Key</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                    <input type="text" class="form-control font-monospace" name="key" maxlength="200" placeholder="The license key, if it has one">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Purchased</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                    <input type="date" class="form-control" name="purchase" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Renews</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
                    <input type="date" class="form-control" name="expire" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Order reference</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                    <input type="text" class="form-control" name="purchase_reference" maxlength="200">
                </div>
            </div>
        </div>
        <div class="small text-secondary mt-3">Its name, type, license type, summary and notes come from the template. Put devices and users on it, and pick its vendor, from the client's Licenses.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_software_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
