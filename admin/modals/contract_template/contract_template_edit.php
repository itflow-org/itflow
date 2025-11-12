<?php
require_once '../../../includes/modal_header.php';

$contract_template_id = intval($_GET['id']);

$contract_types_array = ['Fully Managed', 'Partialy Managed', 'Break/Fix'];
$update_frequency_array = ['Manual', 'Annually', '2 Year', '3 Year', '5 Year', '7 Year'];

// Fetch existing template
$sql = mysqli_query($mysqli, "SELECT * FROM contract_templates WHERE contract_template_id = $contract_template_id LIMIT 1");
$row = mysqli_fetch_array($sql);

// Assign locals
$name          = nullable_htmlentities($row['contract_template_name']);
$description   = nullable_htmlentities($row['contract_template_description']);
$type          = nullable_htmlentities($row['contract_template_type']);
$renewal_frequency = nullable_htmlentities($row['contract_template_renewal_frequency']);
$sla_low_resp  = intval($row['contract_template_sla_low_response_time']);
$sla_med_resp  = intval($row['contract_template_sla_medium_response_time']);
$sla_high_resp = intval($row['contract_template_sla_high_response_time']);
$sla_low_res   = intval($row['contract_template_sla_low_resolution_time']);
$sla_med_res   = intval($row['contract_template_sla_medium_resolution_time']);
$sla_high_res  = intval($row['contract_template_sla_high_resolution_time']);
$hourly_rate   = intval($row['contract_template_rate_standard']);
$after_hours   = intval($row['contract_template_rate_after_hours']);
$support_hours = nullable_htmlentities($row['contract_template_support_hours']);
$net_terms     = intval($row['contract_template_net_terms']);
$details       = nullable_htmlentities($row['contract_template_details']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract mr-2"></i>Edit Contract Template</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<!-- Tabs Navigation -->
<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">General Info</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="sla-tab" data-toggle="tab" href="#sla" role="tab">SLA</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="rates-tab" data-toggle="tab" href="#rates" role="tab">Rates & Support</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="details-tab" data-toggle="tab" href="#details" role="tab">Details</a>
    </li>
</ul>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="contract_template_id" value="<?php echo $contract_template_id; ?>">

    <div class="modal-body">
        <div class="tab-content" id="contractTemplateTabContent">

            <!-- General Info Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="form-group">
                    <label>Template Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-file-contract"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name"
                            placeholder="Contract Template Name" maxlength="200" required autofocus
                            value="<?= $name ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Template Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description"
                            placeholder="Contract Template Description" maxlength="200" required
                            value="<?= $description ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Contract Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                        </div>
                        <select class="form-control select2" name="type" required>
                            <option value="">- Select Type -</option>
                            <?php foreach ($contract_types_array as $type_select) { ?>
                                <option <?php if ($type == $type_select) { echo "selected"; } ?>><?= $type_select ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Renewal Frequency</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="renewal_frequency">
                            <option value="">- Select Frequency -</option>
                            <?php foreach ($renewal_frequency_array as $renewal_frequency_select) { ?>
                                <option <?php if ($renewal_frequency == $renewal_frequency_select) { echo "selected"; } ?>><?= $renewal_frequency_select ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SLA Tab -->
            <div class="tab-pane fade" id="sla" role="tabpanel">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Low Priority Response (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_low_response_time" placeholder="e.g., 24"
                                value="<?= $sla_low_resp ?>">
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Low Priority Resolution (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_low_resolution_time" placeholder="e.g., 48"
                                value="<?= $sla_low_res ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Medium Priority Response (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_medium_response_time" placeholder="e.g., 12"
                                value="<?= $sla_med_resp ?>">
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Medium Priority Resolution (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_medium_resolution_time" placeholder="e.g., 24"
                                value="<?= $sla_med_res ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>High Priority Response (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_high_response_time" placeholder="e.g., 1"
                                value="<?= $sla_high_resp ?>">
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>High Priority Resolution (hrs)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                            </div>
                            <input type="number" class="form-control" name="sla_high_resolution_time" placeholder="e.g., 4"
                                value="<?= $sla_high_res ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rates & Support Tab -->
            <div class="tab-pane fade" id="rates" role="tabpanel">
                <div class="form-group">
                    <label>Standard Hourly Rate</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        </div>
                        <input type="text" class="form-control" name="rate_standard" placeholder="e.g., 100"
                            value="<?= $rate_standard ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>After Hours Hourly Rate</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-moon"></i></span>
                        </div>
                        <input type="text" class="form-control" name="rate_after_hours" placeholder="e.g., 150"
                            value="<?= $rate_after_hours ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Hours</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        </div>
                        <input type="text" class="form-control" name="support_hours" placeholder="e.g., Mon-Fri 9am-5pm"
                            value="<?= $support_hours ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Net Terms</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
                        </div>
                        <input type="text" class="form-control" name="net_terms" placeholder="e.g., Net 30"
                            value="<?= $net_terms ?>">
                    </div>
                </div>
            </div>

            <!-- Details Tab -->
            <div class="tab-pane fade" id="details" role="tabpanel">
                <div class="form-group">
                    <label>Contract Details</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <textarea class="form-control tinymce" rows="6" name="details"
                            placeholder="Enter Contract Details"><?= $details ?></textarea>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_contract_template" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Save Changes
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
?>
