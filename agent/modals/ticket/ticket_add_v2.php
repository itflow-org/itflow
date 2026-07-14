<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>New Ticket (v2)</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <!-- Hidden/System fields -->
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <?php } ?>
    <?php if ($project_id) { ?>
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
    <?php } ?>
    <input type="hidden" name="billable" value="0">

    <div class="modal-body">

        <!-- Nav -->
        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-add-relationships"><i class="fa fa-fw fa-desktop mr-2"></i>Assignment</a>
            </li>
        </ul>

        <!-- Content -->
        <div class="tab-content">

            <!-- Ticket details -->
            <div class="tab-pane fade show active" id="pills-add-details">

                <!-- Ticket client/contact -->
                <?php if ($contact_id) { ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                <?php } else { ?>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Client <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="client_id" id="changeClientSelect" required <?php if ($client_id) { echo "disabled"; } ?>>
                                        <option value="">- Select a Client -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                        while ($row = mysqli_fetch_assoc($sql)) {
                                            $client_id_select = intval($row['client_id']);
                                            $client_name = escapeHtml($row['client_name']); ?>

                                            <option value="<?php echo $client_id_select; ?>" <?php if ($client_id == $client_id_select) {echo "selected"; } ?>><?php echo $client_name; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label>Contact </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact_id" id="contactSelect">
                                        <option value="">No Contact</option>
                                    </select>
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

                            while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
                                $ticket_template_id_select = intval($row['ticket_template_id']);
                                $ticket_template_name_select = escapeHtml($row['ticket_template_name']);
                                $ticket_template_subject_select = escapeHtml($row['ticket_template_subject']);
                                $ticket_template_details_select = escapeHtml($row['ticket_template_details']);
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
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details"></textarea>
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
                                <select class="form-control select2" name="category_id">
                                    <option value="0">- Not Categorized -</option>
                                    <?php
                                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_categories)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = escapeHtml($row['category_name']);
                                        ?>
                                        <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                    <?php } ?>

                                </select>
                                <div class="input-group-append">
                                    <button class="btn btn-secondary ajax-modal" type="button"
                                            data-modal-url="../admin/modals/category/category_add.php?category=Ticket">
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
                            <option value="0">- Unassigned -</option>
                            <?php

                            $sql = mysqli_query(
                                $mysqli,
                                "SELECT user_id, user_name FROM users
                                WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                            );
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $user_id = intval($row['user_id']);
                                $user_name = escapeHtml($row['user_name']); ?>
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

            <div class="tab-pane fade" id="pills-add-relationships">
                To-do: project, etc.

                <div class="form-group">
                    <label>Asset</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        </div>
                        <select class="form-control select2" name="asset_id" id="assetSelect">
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="location_id" id="locationSelect">
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
                                <select class="form-control select2" name="vendor_id" id="vendorSelect">
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
        <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create Ticket</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<!-- Ticket Templates -->
<script>
$(document).on('change', '#ticket_template_select', function () {
    const $opt = $(this).find(':selected');
    const templateSubject = $opt.data('subject') || '';
    const templateDetails = $opt.data('details') || '';

    $('#subjectInput').val(templateSubject);

    if (window.tinymce) {
        const editor = tinymce.get('detailsInput');
        if (editor) {
            editor.setContent(templateDetails);
        } else {
            $('#detailsInput').val(templateDetails);
        }
    } else {
        $('#detailsInput').val(templateDetails);
    }
});
</script>

<!-- Ticket Client/Contact JS -->
<link rel="stylesheet" href="/libs/jquery-ui/jquery-ui.min.css">
<script src="/libs/jquery-ui/jquery-ui.min.js"></script>
<script src="/agent/js/tickets_add_modal.js"></script>

<?php

require_once '../../../includes/modal_footer.php';
