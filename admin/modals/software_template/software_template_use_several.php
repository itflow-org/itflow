<?php

/*
 * New licenses from a license template for several clients at once - from
 * Admin > License Templates and the template's page. Each client gets the
 * same license: its details and vendor from the template (the client's own
 * vendor made from the template's vendor template, or one made for them),
 * with the seats, dates and order reference given here once for all of them.
 * Keys and who is on each are put on from the client's Licenses. ?id= the
 * template. Made by make_licenses_from_template
 * (admin/post/software_template.php).
 */

require_once '../../includes/modal_header.php';

$template = softwareTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template || $template['software_template_archived_at']) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-copy me-2"></i>New licenses</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That license template is gone or archived.</div>
<?php } else {
    $software_template_id = intval($template['software_template_id']);
    $term = (string) $template['software_template_term'];
    $vendor_name = $template['vendor_template_archived_at'] ? '' : (string) $template['vendor_template_name'];

    // The clients that already have a current one from it - said in the picker
    $has_one = [];
    $sql = mysqli_query($mysqli, "SELECT DISTINCT software_client_id FROM software WHERE software_template_id = $software_template_id AND software_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $has_one[intval($row[0])] = true;
    }
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-copy me-2"></i>New licenses from <strong><?= escapeHtml($template['software_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?= $software_template_id ?>">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Clients <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <select class="form-select select2" name="client_ids[]" multiple required data-placeholder="- Pick clients -">
                        <?php
                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_clients)) {
                            $client_option_id = intval($row['client_id']); ?>
                            <option value="<?= $client_option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?><?= isset($has_one[$client_option_id]) ? ' data-description="Already has one"' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="skip_existing" value="1" id="softwareTemplateSkipExisting" checked>
                    <label class="form-check-label" for="softwareTemplateSkipExisting">Skip clients that already have one</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Seats</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <input type="number" class="form-control" name="seats" min="0" step="1" placeholder="No limit" value="<?= $template['software_template_seats'] === null ? '' : intval($template['software_template_seats']) ?>">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Order reference</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                    <input type="text" class="form-control" name="purchase_reference" maxlength="200">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Purchased</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                    <input type="date" class="form-control" name="purchase" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Renews</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
                    <input type="date" class="form-control" name="expire" max="2999-12-31">
                </div>
                <?php if (isset(SOFTWARE_RENEWAL_TERMS[$term])) { ?><div class="form-text">Blank is <?= escapeHtml(SOFTWARE_RENEWAL_TERMS[$term]) ?> after it was bought (or today).</div><?php } ?>
            </div>
        </div>
        <div class="small text-secondary mt-3">Each client gets the same license: its name, type, license type, summary and notes from the template<?= $vendor_name !== '' ? ', and the client\'s <strong>' . escapeHtml($vendor_name) . '</strong> as its vendor - added for those that haven\'t got it' : '' ?>. Keys, devices and users go on from each client's Licenses.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="make_licenses_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
