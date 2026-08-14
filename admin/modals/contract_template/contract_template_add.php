<?php
require_once '../../includes/modal_header.php';


$contract_types_array = ['Fully Managed', 'Partialy Managed', 'Break/Fix'];
$renewal_frequency_array = ['Manual', 'Annually', '2 Year', '3 Year', '5 Year', '7 Year'];

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract me-2"></i>New Contract Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<!-- Tabs Navigation -->
<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab">General Info</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="sla-tab" data-bs-toggle="tab" href="#sla" role="tab">SLA</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="rates-tab" data-bs-toggle="tab" href="#rates" role="tab">Rates & Support</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="details-tab" data-bs-toggle="tab" href="#details" role="tab">Details</a>
    </li>
</ul>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="tab-content" id="contractTemplateTabContent">

            <!-- General Info Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="mb-3">
                    <label>Template Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-file-contract"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Contract Template Name" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Template Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description"
                        placeholder="Contract Template Description" maxlength="200" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Contract Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                        <select class="form-control select2" name="type" required>
                            <option value="">- Select Type -</option>
                            <?php foreach ($contract_types_array as $type) { ?>
                                <option><?= $type ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Renewal Frequency</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
                        <select class="form-control select2" name="renewal_frequency">
                            <option value="">- Select Frequency -</option>
                            <?php foreach ($renewal_frequency_array as $renewal_frequency) { ?>
                                <option><?= $renewal_frequency ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SLA Tab -->
            <div class="tab-pane fade" id="sla" role="tabpanel">
                <div class="row g-2">
                    <div class="mb-3 col-md-6">
                        <label>Low Priority Response (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            <input type="number" class="form-control" name="sla_low_response_time" placeholder="e.g., 24">
                        </div>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label>Low Priority Resolution (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            <input type="number" class="form-control" name="sla_low_resolution_time" placeholder="e.g., 48">
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="mb-3 col-md-6">
                        <label>Medium Priority Response (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            <input type="number" class="form-control" name="sla_medium_response_time" placeholder="e.g., 12">
                        </div>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label>Medium Priority Resolution (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                            <input type="number" class="form-control" name="sla_medium_resolution_time" placeholder="e.g., 24">
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="mb-3 col-md-6">
                        <label>High Priority Response (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
                            <input type="number" class="form-control" name="sla_high_response_time" placeholder="e.g., 1">
                        </div>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label>High Priority Resolution (hrs)</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                            <input type="number" class="form-control" name="sla_high_resolution_time" placeholder="e.g., 4">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rates & Support Tab -->
            <div class="tab-pane fade" id="rates" role="tabpanel">
                <div class="mb-3">
                    <label>Standard Hourly Rate</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        <input type="text" class="form-control" name="rate_standard" placeholder="e.g., 100">
                    </div>
                </div>

                <div class="mb-3">
                    <label>After Hours Hourly Rate</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-moon"></i></span>
                        <input type="text" class="form-control" name="rate_after_hours" placeholder="e.g., 150">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Support Hours</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="text" class="form-control" name="support_hours" placeholder="e.g., Mon-Fri 9am-5pm" maxlength="100">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Net Terms</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
                        <input type="text" class="form-control" name="net_terms" placeholder="e.g., Net 30" maxlength="50">
                    </div>
                </div>
            </div>

            <!-- Details Tab -->
            <div class="tab-pane fade" id="details" role="tabpanel">
                <div class="mb-3">
                    <textarea class="form-control tinymce" rows="6" name="details" placeholder="Enter Contract Details"></textarea>
                </div>
            </div>

        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_contract_template" class="btn btn-primary text-bold">
            <i class="fa fa-check me-2"></i>Create Template
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
?>
