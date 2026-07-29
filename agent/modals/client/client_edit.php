<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['id']);

enforceClientAccess();

$sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$client_name = escapeHtml($row['client_name']);
$client_is_lead = intval($row['client_lead']);
$client_type = escapeHtml($row['client_type']);
$client_website = escapeHtml($row['client_website']);
$client_referral = escapeHtml($row['client_referral']);
$client_net_terms = intval($row['client_net_terms']);
$client_tax_id_number = escapeHtml($row['client_tax_id_number']);
$client_abbreviation = escapeHtml($row['client_abbreviation']);
$client_rate = floatval($row['client_rate']);
$client_notes = escapeHtml($row['client_notes']);
$client_created_at = escapeHtml($row['client_created_at']);
$client_archived_at = escapeHtml($row['client_archived_at']);

// Client SLA assignments
$client_sla_assignments = [];
$sql_client_slas = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = $client_id");
while ($client_sla_row = mysqli_fetch_assoc($sql_client_slas)) {
    $client_sla_assignments[$client_sla_row['sla_assignment_priority']] = intval($client_sla_row['sla_assignment_sla_id']);
}

// Client Tags
$client_tag_id_array = array();
$sql_client_tags = mysqli_query($mysqli, "SELECT tag_id FROM client_tags WHERE client_id = $client_id");
while ($row = mysqli_fetch_assoc($sql_client_tags)) {
    $client_tag_id = intval($row['tag_id']);
    $client_tag_id_array[] = $client_tag_id;
}

$net_terms_array = array (
    '0'=>'On Receipt',
    '7'=>'7 Days',
    '10'=>'10 Days',
    '15'=>'15 Days',
    '30'=>'30 Days',
    '45'=>'45 Days',
    '60'=>'60 Days',
    '90'=>'90 Days'
);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-edit mr-2'></i>Editing Client: <strong><?= $client_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <ul class="modal-header nav nav-pills nav-justified mb-3">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="pill" href="#pills-client-details<?= $client_id ?>">Details</a>
        </li>
        <?php if ($config_module_enable_accounting) { ?>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-client-billing<?= $client_id ?>">Billing</a>
            </li>
        <?php } ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-client-notes<?= $client_id ?>">Notes</a>
        </li>
    </ul>

    <div class="modal-body">

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-client-details<?= $client_id ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Name or Company" maxlength="200"
                               value="<?= $client_name ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="lead" value="1" <?php if($client_is_lead == 1){ echo "checked"; } ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Shortened Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="abbreviation" placeholder="Shortned name for client - Max chars 6" value="<?= $client_abbreviation ?>" maxlength="6" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Industry</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                        </div>
                        <input type="text" class="form-control" name="type" placeholder="Industry"
                               value="<?= $client_type ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Referral</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" name="referral">
                            <option value="">- Select Referral -</option>
                            <?php

                            $referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND (category_archived_at > '$client_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_assoc($referral_sql)) {
                                $referral = escapeHtml($row['category_name']);
                                ?>
                                <option <?php if ($client_referral == $referral) {
                                    echo "selected";
                                } ?>>
                                    <?= $referral ?>
                                </option>

                                <?php
                            }
                            ?>
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/category/category_add.php?category=Referral">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Website</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="website" placeholder="ex. google.com" maxlength="200"
                               value="<?= $client_website ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = escapeHtml($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id_select ?>" <?php if (in_array($tag_id_select, $client_tag_id_array)) { echo "selected"; } ?>><?= $tag_name_select ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=1">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <?php
                // Ticket SLA assignments - only offered when active SLAs exist
                $sla_options = [];
                $sla_options_sql = mysqli_query($mysqli, "SELECT sla_id, sla_name FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_name ASC");
                while ($sla_option_row = mysqli_fetch_assoc($sla_options_sql)) {
                    $sla_options[intval($sla_option_row['sla_id'])] = $sla_option_row['sla_name'];
                }
                if ($config_module_enable_ticketing && !empty($sla_options)) { ?>
                    <div class="form-group">
                        <label>Ticket SLAs</label>
                        <div class="form-row">
                            <?php foreach (['Low', 'Medium', 'High'] as $sla_priority) { $sla_current = $client_sla_assignments[$sla_priority] ?? 'default'; ?>
                                <div class="col-4">
                                    <small class="text-secondary"><?= $sla_priority ?></small>
                                    <select class="form-control" name="client_sla_<?= strtolower($sla_priority) ?>">
                                        <option value="default" <?php if ($sla_current === 'default') { echo "selected"; } ?>>Default</option>
                                        <option value="0" <?php if ($sla_current === 0) { echo "selected"; } ?>>None</option>
                                        <?php foreach ($sla_options as $sla_option_id => $sla_option_name) { ?>
                                            <option value="<?= $sla_option_id ?>" <?php if ($sla_current === $sla_option_id) { echo "selected"; } ?>><?= escapeHtml($sla_option_name) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                        <small class="text-muted">Default follows the global SLA assignment for each priority.</small>
                    </div>
                <?php } ?>

            </div>

            <?php if ($config_module_enable_accounting) { ?>

                <div class="tab-pane fade" id="pills-client-billing<?= $client_id ?>">

                    <div class="form-group">
                        <label>Hourly Rate</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="decimal"
                                   pattern="[0-9]*\.?[0-9]{0,2}" name="rate" placeholder="0.00"
                                   value="<?= number_format($client_rate, 2, '.', '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Invoice Net Terms</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <select class="form-control select2" name="net_terms">
                                <option value="">- Net Terms -</option>
                                <?php foreach ($net_terms_array as $net_term_value => $net_term_name) { ?>
                                    <option <?php if ($net_term_value == $client_net_terms) {
                                        echo "selected";
                                    } ?> value="<?= $net_term_value ?>">
                                        <?= $net_term_name ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tax ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            </div>
                            <input type="text" class="form-control" name="tax_id_number" maxlength="255"
                                   placeholder="Tax ID Number" value="<?= $client_tax_id_number ?>">
                        </div>
                    </div>

                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-client-notes<?= $client_id ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="10" placeholder="Enter some notes" name="notes"><?= $client_notes ?></textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_client" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
