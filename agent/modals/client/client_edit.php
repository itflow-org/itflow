<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['id']);

enforceClientAccess();

$sql = mysqli_query($mysqli, "SELECT client_abbreviation, client_archived_at, client_created_at, client_lead, client_name,
    client_notes, client_reminders, client_referral, client_tax_id_number,
    client_default_tax_id, client_tax_exempt, client_tax_exempt_number, client_tax_exempt_expires,
    client_type, client_website FROM clients WHERE client_id = $client_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$client_tax_row = $row;
$client_name = escapeHtml($row['client_name']);
$client_is_lead = intval($row['client_lead']);
$client_type = escapeHtml($row['client_type']);
$client_website = escapeHtml($row['client_website']);
$client_referral = escapeHtml($row['client_referral']);
$row_referral = (string) $row['client_referral'];
$client_reminders = intval($row['client_reminders'] ?? 1);
$client_tax_id_number = escapeHtml($row['client_tax_id_number']);
$client_abbreviation = escapeHtml($row['client_abbreviation']);
$client_notes = escapeHtml($row['client_notes']);
$client_created_at = escapeHtml($row['client_created_at']);
$client_archived_at = escapeHtml($row['client_archived_at']);

// Client Tags
$client_tag_id_array = array();
$sql_client_tags = mysqli_query($mysqli, "SELECT tag_id FROM client_tags WHERE client_id = $client_id");
while ($row = mysqli_fetch_assoc($sql_client_tags)) {
    $client_tag_id = intval($row['tag_id']);
    $client_tag_id_array[] = $client_tag_id;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-edit me-2'></i>Editing Client: <strong><?= $client_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <ul class="modal-header nav nav-pills nav-justified mb-3">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href="#pills-client-details<?= $client_id ?>">Details</a>
        </li>
        <?php if ($config_module_enable_accounting) { ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-client-billing<?= $client_id ?>">Billing</a>
            </li>
        <?php } ?>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#pills-client-notes<?= $client_id ?>">Notes</a>
        </li>
    </ul>

    <div class="modal-body">

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-client-details<?= $client_id ?>">

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Name or Company" maxlength="200"
                               value="<?= $client_name ?>" required>
                            <div class="input-group-text">
                                <input class="form-check-input" type="checkbox" name="lead" value="1" <?php if($client_is_lead == 1){ echo "checked"; } ?>>
                            </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Shortened Name</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="abbreviation" placeholder="Shortened name for client - Max chars 6" value="<?= $client_abbreviation ?>" maxlength="6" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Industry</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                        <input type="text" class="form-control" name="type" placeholder="Industry" maxlength="200"
                               value="<?= $client_type ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Referral</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        <select class="form-select select2" data-tags="true" name="referral">
                            <option value="">- Select Referral -</option>
                            <?= categoryOptionsHtml('Referral', $row_referral) ?>
                        </select>
                        <?= categoryAddButtonHtml('Referral', 'btn btn-secondary') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Website</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="website" placeholder="ex. google.com" maxlength="200"
                               value="<?= $client_website ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Tags</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        <select class="form-select select2" name="tags[]" data-placeholder="Add some tags" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
                            <?= tagOptionsHtml('client', $client_tag_id_array) ?>
                        </select>
                    </div>
                </div>

            </div>

            <?php if ($config_module_enable_accounting) { ?>

                <div class="tab-pane fade" id="pills-client-billing<?= $client_id ?>">

                    <?php $terms_note_client_id = $client_id; require __DIR__ . '/client_terms_note.php'; ?>

                    <?php $contract_pick_edit = true; require __DIR__ . '/client_contract_pick.php'; ?>

                    <div class="mb-3">
                        <label>Tax ID</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            <input type="text" class="form-control" name="tax_id_number" maxlength="255"
                                   placeholder="Tax ID Number" value="<?= $client_tax_id_number ?>">
                        </div>
                    </div>

                    <?php
                    $client_tax = $client_tax_row;
                    $client_tax_key = $client_id;
                    require __DIR__ . '/client_tax_fields.php';
                    ?>

                    <div class="mb-3">
                        <input type="hidden" name="reminders_field" value="1">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="reminders" value="1" id="clientReminders<?= $client_id ?>" <?= $client_reminders ? 'checked' : '' ?>>
                            <label class="form-check-label" for="clientReminders<?= $client_id ?>">Payment reminders and statements</label>
                        </div>
                        <small class="text-secondary">Emailed as set in Invoice Settings. Off, this client gets neither - invoices can still be sent by hand.</small>
                    </div>


                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-client-notes<?= $client_id ?>">

                <div class="mb-3">
                    <textarea class="form-control" rows="10" placeholder="Enter some notes" name="notes"><?= $client_notes ?></textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_client" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
