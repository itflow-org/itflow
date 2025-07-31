<div class="modal" id="addTicketModalv2" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>New Ticket (v2)</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <!-- Hidden/System fields -->
                <?php if ($client_url && isset($client_id)) { ?>
                    <input type="hidden" name="client" value="<?php echo $client_id; ?>>">
                <?php }
                if (isset($_GET['contact_id'])) { ?>
                    <input type="hidden" name="contact" value="<?php echo intval($_GET['contact_id']); ?>">
                <?php }
                if (isset($_GET['project_id'])) { ?>
                    <input type="hidden" name="project" value="<?php echo intval($_GET['project_id']); ?>">
                <?php } ?>
                <input type="hidden" name="billable" value="0">

                <div class="modal-body">

                    <!-- Nav -->
                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <!-- Hide contact if in URL as it means we're creating a ticket from a contact record -->
                        <?php if (!isset($_GET['contact_id'])) { ?>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-add-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-relationships"><i class="fa fa-fw fa-desktop mr-2"></i>Assignment</a>
                        </li>
                    </ul>

                    <!-- Content -->
                    <div class="tab-content">

                        <!-- Ticket details -->
                        <div class="tab-pane fade show active" id="pills-add-details">

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
                                            <?php echo "aaaaa"; ?>
                                            <?php var_dump($row); ?>
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
                                <textarea class="form-control tinymceTicket<?php if ($config_ai_enable) { echo "AI"; } ?>" id="detailsInput" name="details"></textarea>
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
                                                $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
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
                                        <option value="0">- Not Assigned -</option>
                                        <?php

                                        $sql = mysqli_query(
                                            $mysqli,
                                            "SELECT user_id, user_name FROM users
                                            WHERE user_role_id > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                        );
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $user_id = intval($row['user_id']);
                                            $user_name = nullable_htmlentities($row['user_name']); ?>
                                            <option value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) { ?>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billable">
                                        <label class="custom-control-label" for="billable">Mark Billable</label>
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                        <!-- Ticket client/contact -->
                        <?php if (!isset($_GET['contact_id'])) { ?>
                            <div class="tab-pane fade" id="pills-add-contacts">

                                <div class="form-group">
                                    <label>Client <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client" id="changeClientSelect" required <?php if ($client_url && isset($client_id)) { echo "disabled"; } ?>>
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $selectable_client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']); ?>

                                                <option value="<?php echo $selectable_client_id; ?>" <?php if ($client_url && isset($client_id) && $client_id == $selectable_client_id) {echo "selected"; } ?>><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Contact </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="contact" id="contactSelect">
                                        </select>
                                    </div>
                                </div>

                            </div>
                        <?php } ?>

                        <div class="tab-pane fade" id="pills-add-relationships">
                            To-do: project, etc.

                            <div class="form-group">
                                <label> Asset </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                    </div>
                                    <select class="form-control select2" name="asset" id="assetSelect">
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label> Location </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <select class="form-control select2" name="location" id="locationSelect">
                                    </select>
                                </div>
                            </div>

                            <div class="row">

                                <div class="col">
                                    <div class="form-group">
                                        <label> Vendor </label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                            </div>
                                            <select class="form-control select2" name="vendor" id="vendorSelect">
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

                    </div>

                </div>

        </div>

        <div class="modal-footer">
            <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
            <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>

        </form>
    </div>
</div>
</div>

<!-- Ticket Templates -->
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

<!-- Ticket Client/Contact JS -->
<link rel="stylesheet" href="../plugins/jquery-ui/jquery-ui.min.css">
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="js/tickets_add_modal.js"></script>

