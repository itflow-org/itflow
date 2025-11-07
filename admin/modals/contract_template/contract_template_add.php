<?php
require_once '../../../includes/modal_header.php';
ob_start();

$contract_types_array = ['Fully Managed', 'Partialy Managed', 'Break/Fix'];
$update_frequency_array = ['Manual', 'Annually', '2 Year', '3 Year', '5 Year', '7 Year'];
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract mr-2"></i>New Contract Template</h5>
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

    <div class="modal-body">

        <div class="tab-content" id="contractTemplateTabContent">

            <!-- General Info Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="form-group">
                    <label>Template Name <strong class="text-danger">*</strong></label>
                    <input type="text" class="form-control" name="contract_template_name" placeholder="Contract Template Name" maxlength="200" required autofocus>
                </div>

                <div class="form-group">
                    <label>Contract Type <strong class="text-danger">*</strong></label>
                    <select class="form-control select2" name="contract_template_type" required>
                        <option value="">- Select Type -</option>
                        <?php foreach ($contract_types_array as $type) { ?>
                            <option><?php echo $type; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Update Frequency</label>
                    <select class="form-control select2" name="contract_template_update_frequency">
                        <option value="">- Select Frequency -</option>
                        <?php foreach ($update_frequency_array as $freq) { ?>
                            <option><?php echo $freq; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <!-- SLA Tab -->
            <div class="tab-pane fade" id="sla" role="tabpanel">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Low Priority Response (hrs)</label>
                        <input type="number" class="form-control" name="sla_low_response_time" placeholder="e.g., 24">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Low Priority Resolution (hrs)</label>
                        <input type="number" class="form-control" name="sla_low_resolution_time" placeholder="e.g., 48">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Medium Priority Response (hrs)</label>
                        <input type="number" class="form-control" name="sla_medium_response_time" placeholder="e.g., 12">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Medium Priority Resolution (hrs)</label>
                        <input type="number" class="form-control" name="sla_medium_resolution_time" placeholder="e.g., 24">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>High Priority Response (hrs)</label>
                        <input type="number" class="form-control" name="sla_high_response_time" placeholder="e.g., 1">
                    </div>
                    <div class="form-group col-md-6">
                        <label>High Priority Resolution (hrs)</label>
                        <input type="number" class="form-control" name="sla_high_resolution_time" placeholder="e.g., 4">
                    </div>
                </div>
            </div>

            <!-- Rates & Support Tab -->
            <div class="tab-pane fade" id="rates" role="tabpanel">
                <div class="form-group">
                    <label>Standard Hourly Rate</label>
                    <input type="text" class="form-control" name="contract_template_hourly_rate" placeholder="e.g., 100">
                </div>

                <div class="form-group">
                    <label>After Hours Hourly Rate</label>
                    <input type="text" class="form-control" name="contract_template_after_hours_hourly_rate" placeholder="e.g., 150">
                </div>

                <div class="form-group">
                    <label>Support Hours</label>
                    <input type="text" class="form-control" name="contract_template_support_hours" placeholder="e.g., Mon-Fri 9am-5pm">
                </div>

                <div class="form-group">
                    <label>Net Terms</label>
                    <input type="text" class="form-control" name="contract_template_net_terms" placeholder="e.g., Net 30">
                </div>
            </div>

            <!-- Details Tab -->
            <div class="tab-pane fade" id="details" role="tabpanel">
                <div class="form-group">
                    <textarea class="form-control tinymce" rows="6" name="contract_template_details" placeholder="Enter Contract Details"></textarea>
                </div>
            </div>

        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_contract_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create Template</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
?>
