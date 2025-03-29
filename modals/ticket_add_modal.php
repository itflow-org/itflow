<div class="modal" id="addTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>New Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">

                <?php if (isset($_GET['project_id'])) { ?>
                <input type="hidden" name="project" value="<?php echo intval($_GET['project_id']); ?>">
                <?php } ?>
                
                <div class="modal-body bg-white">

                    <?php if (isset($_GET['client_id'])) { ?>
                        <ul class="nav nav-pills nav-justified mb-3">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="pill" href="#pills-ticket-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-ticket-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-ticket-assignment"><i class="fa fa-fw fa-desktop mr-2"></i>Assignment</a>
                            </li>
                        </ul>

                        <hr>

                    <?php } ?>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-ticket-details">

                            <?php if (empty($_GET['client_id'])) { ?>

                                <div class="form-group">
                                    <label>Client <strong class="text-danger">*</strong> / <span class="text-secondary">Use Primary Contact</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client" required>
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']); ?>
                                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <input type="checkbox" name="use_primary_contact" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php } ?>

                            <div class="form-group">
                                <label>Template</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <select class="form-control select2" id="ticket_template_select" name="ticket_template_id" required>
                                        <option value="0">- Choose a Template -</option>
                                        <?php
                                            $sql_ticket_templates = mysqli_query($mysqli, "
                                                SELECT tt.ticket_template_id, 
                                                       tt.ticket_template_name,
                                                       tt.ticket_template_subject, 
                                                       tt.ticket_template_details,
                                                       COUNT(ttt.task_template_id) as task_count
                                                FROM ticket_templates tt
                                                LEFT JOIN task_templates ttt 
                                                    ON tt.ticket_template_id = ttt.task_template_ticket_template_id
                                                WHERE tt.ticket_template_archived_at IS NULL
                                                GROUP BY tt.ticket_template_id
                                                ORDER BY tt.ticket_template_name ASC
                                            ");

                                            while ($row = mysqli_fetch_array($sql_ticket_templates)) {
                                                $ticket_template_id_select = intval($row['ticket_template_id']);
                                                $ticket_template_name_select = nullable_htmlentities($row['ticket_template_name']);
                                                $ticket_template_subject_select = nullable_htmlentities($row['ticket_template_subject']);
                                                $ticket_template_details_select = nullable_htmlentities($row['ticket_template_details']);
                                                $task_count = intval($row['task_count']);
                                            ?>
                                                <option value="<?php echo $ticket_template_id_select; ?>"
                                                        data-subject="<?php echo $ticket_template_subject_select; ?>"
                                                        data-details="<?php echo $ticket_template_details_select; ?>">
                                                    <?php echo $ticket_template_name_select; ?> (<?php echo $task_count; ?> tasks)
                                                </option>
                                            <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="subjectInput" name="subject" placeholder="Subject" maxlength="500" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymceTicket<?php if($config_ai_enable) { echo "AI"; } ?>" id="detailsInput" name="details"></textarea>
                            </div>

                            <div class="row">
                                
                                <div class="col">
                                    <div class="form-group">
                                        <label>Priority <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                            </div>
                                            <select class="form-control select2" name="priority" required>
                                                <option>Low</option>
                                                <option>Medium</option>
                                                <option>High</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                            </div>
                                            <select class="form-control select2" name="category">
                                                <option value="0">- Not Categorized -</option>
                                                <?php
                                                $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                                                while ($row = mysqli_fetch_array($sql_categories)) {
                                                    $category_id = intval($row['category_id']);
                                                    $category_name = nullable_htmlentities($row['category_name']);

                                                    ?>
                                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                                <?php } ?>

                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-secondary" type="button"
                                                    data-toggle="ajax-modal"
                                                    data-modal-size="sm"
                                                    data-ajax-url="ajax/ajax_category_add.php?category=Ticket">
                                                    <i class="fas fa-fw fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="form-group">
                                <label>Assign to</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                    </div>
                                    <select class="form-control select2" name="assigned_to">
                                        <option value="0">Not Assigned</option>
                                        <?php

                                        $sql = mysqli_query(
                                            $mysqli,
                                            "SELECT user_id, user_name FROM users
                                            WHERE user_role_id > 1
                                            AND user_type = 1
                                            AND user_status = 1
                                            AND user_archived_at IS NULL
                                            ORDER BY user_name ASC"
                                        );
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $user_id = intval($row['user_id']);
                                            $user_name = nullable_htmlentities($row['user_name']); ?>
                                            <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) { ?>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billableSwitch">
                                    <label class="custom-control-label" for="billableSwitch">Mark Billable</label>
                                </div>
                            </div>
                            <?php } ?>

                        </div>

                        <?php if (isset($_GET['client_id'])) { ?>

                            <div class="tab-pane fade" id="pills-ticket-contacts">

                                <input type="hidden" name="client" value="<?php echo $client_id; ?>">

                                <div class="form-group">
                                    <label>Contact</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="contact">
                                            <option value="0">- No One -</option>
                                            <?php
                                            $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_primary, contact_technical FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $contact_id_select = intval($row['contact_id']);
                                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                                $contact_primary_select = intval($row['contact_primary']);
                                                if($contact_primary_select == 1) {
                                                    $contact_primary_display = " (Primary)";
                                                } else {
                                                    $contact_primary_display = "";
                                                }
                                                $contact_technical_select = intval($row['contact_technical']);
                                                if($contact_technical_select == 1) {
                                                    $contact_technical_display = " (Technical)";
                                                } else {
                                                    $contact_technical_display = "";
                                                }
                                                $contact_title_select = nullable_htmlentities($row['contact_title']);
                                                if($contact_title_select) {
                                                    $contact_title_display = " - $contact_title_select";
                                                } else {
                                                    $contact_title_display = "";
                                                }

                                                ?>
                                                <option value="<?php echo $contact_id_select; ?>" 
                                                    <?php 
                                                    if (isset($_GET['contact_id']) && $contact_id_select == intval($_GET['contact_id'])) {
                                                        echo "selected";
                                                    } elseif (empty($_GET['contact_id']) && $contact_primary_select == 1) {
                                                        echo "selected";
                                                    } 
                                                    ?>
                                                    >
                                                    <?php echo "$contact_name_select$contact_title_display$contact_primary_display$contact_technical_display"; ?> 
                                                </option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Watchers</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        </div>
                                        <select class="form-control select2" name="watchers[]" data-tags="true" data-placeholder="Enter or select email address" multiple>
                                            <option value=""></option>
                                            <?php
                                            $sql = mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email IS NOT NULL ORDER BY contact_email ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $contact_email = nullable_htmlentities($row['contact_email']);
                                                ?>
                                                <option><?php echo $contact_email; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="pills-ticket-assignment">

                                <div class="form-group">
                                    <label>Primary Asset</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="asset">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                                            ?>
                                                <option value="<?php echo $asset_id_select; ?>" 
                                                    <?php if (isset($_GET['asset_id']) && $asset_id_select == $_GET['asset_id']) { echo "selected"; } 
                                                    ?>
                                                    ><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Additional Assets</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="additional_assets[]" data-tags="true" data-placeholder="- Select Additional Assets -" multiple>
                                            <option value=""></option>
                                            <?php

                                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                                            ?>
                                                <option value="<?php echo $asset_id_select; ?>">
                                                    <?php echo "$asset_name_select - $asset_contact_name_select"; ?>
                                                </option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Location</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                        </div>
                                        <select class="form-control select2" name="location">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_locations = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                                            while ($row = mysqli_fetch_array($sql_locations)) {
                                                $location_id_select = intval($row['location_id']);
                                                $location_name_select = nullable_htmlentities($row['location_name']);
                                            ?>
                                                <option value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                
                                    <div class="col">

                                        <div class="form-group">
                                            <label>Vendor</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                                </div>
                                                <select class="form-control select2" name="vendor">
                                                    <option value="0">- None -</option>
                                                    <?php

                                                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_template = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                                    while ($row = mysqli_fetch_array($sql_vendors)) {
                                                        $vendor_id_select = intval($row['vendor_id']);
                                                        $vendor_name_select = nullable_htmlentities($row['vendor_name']); ?>
                                                        <option value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>

                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="col">

                                        <div class="form-group">
                                            <label>Vendor Ticket Number</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                                </div>
                                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Vendor ticket number">
                                            </div>
                                        </div>
                                    
                                    </div>

                                </div>

                                <div class="form-group">
                                    <label>Project</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                                        </div>
                                        <select class="form-control select2" name="project">
                                            <option value="0">- Select Project -</option>
                                            <?php

                                            $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_name FROM projects WHERE project_client_id = $client_id AND project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                                            while ($row = mysqli_fetch_array($sql_projects)) {
                                                $project_id_select = intval($row['project_id']);
                                                $project_name_select = nullable_htmlentities($row['project_name']); ?>
                                                <option <?php if (isset($_GET['project_id']) && $project_id_select == $_GET['project_id']) { echo "selected"; } ?> value="<?php echo $project_id_select; ?>"><?php echo $project_name_select; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                        <?php } ?>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var templateSelect = $('#ticket_template_select');
    var subjectInput = document.getElementById('subjectInput');
    var detailsInput = document.getElementById('detailsInput');

    templateSelect.on('select2:select', function(e) {
        var selectedOption = e.params.data.element;
        var templateSubject = selectedOption.getAttribute('data-subject');
        var templateDetails = selectedOption.getAttribute('data-details');

        // Update Subject
        subjectInput.value = templateSubject || '';

        // Update Details
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('detailsInput');
            if (editor) {
                editor.setContent(templateDetails || '');
            } else {
                detailsInput.value = templateDetails || '';
            }
        } else {
            detailsInput.value = templateDetails || '';
        }
    });
});
</script>