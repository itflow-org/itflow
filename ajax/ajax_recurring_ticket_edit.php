<?php

require_once '../includes/ajax_header.php';

$recurring_ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['recurring_ticket_client_id']);
$recurring_ticket_subject = nullable_htmlentities($row['recurring_ticket_subject']);
$recurring_ticket_details = nullable_htmlentities($row['recurring_ticket_details']);
$recurring_ticket_priority = nullable_htmlentities($row['recurring_ticket_priority']);
$recurring_ticket_frequency = nullable_htmlentities($row['recurring_ticket_frequency']);
$recurring_ticket_next_run = nullable_htmlentities($row['recurring_ticket_next_run']);
$recurring_ticket_assigned_to = intval($row['recurring_ticket_assigned_to']);
$recurring_ticket_contact_id = intval($row['recurring_ticket_contact_id']);
$recurring_ticket_asset_id = intval($row['recurring_ticket_asset_id']);
$recurring_ticket_billable = intval($row['recurring_ticket_billable']);

// Additional Assets Selected
$additional_assets_array = array();
$sql_additional_assets = mysqli_query($mysqli, "SELECT asset_id FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
while ($row = mysqli_fetch_array($sql_additional_assets)) {
    $additional_asset_id = intval($row['asset_id']);
    $additional_assets_array[] = $additional_asset_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fas fa-fw fa-calendar-check mr-2"></i>Editing Recurring Ticket: <strong><?php echo $recurring_ticket_subject; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="recurring_ticket_id" value="<?php echo $recurring_ticket_id; ?>">
    <input type="hidden" name="client" value="<?php echo $client_id; ?>">

    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-edit-details<?php echo $recurring_ticket_id; ?>"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-edit-contacts<?php echo $recurring_ticket_id; ?>"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-edit-schedule<?php echo $recurring_ticket_id; ?>"><i class="fa fa-fw fa-building mr-2"></i>Schedule</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-edit-assets<?php echo $recurring_ticket_id; ?>"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</a>
            </li>
        </ul>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-edit-details<?php echo $recurring_ticket_id; ?>">

                <div class="form-group">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="500" value="<?php echo $recurring_ticket_subject; ?>" required >
                    </div>
                </div>

                <div class="form-group">
                    <textarea class="form-control tinymce" name="details"><?php echo $recurring_ticket_details; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Priority <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        </div>
                        <select class="form-control select2" name="priority" required>
                            <option <?php if ($recurring_ticket_priority == "Low") { echo "selected"; } ?> >Low</option>
                            <option <?php if ($recurring_ticket_priority == "Medium") { echo "selected"; } ?> >Medium</option>
                            <option <?php if ($recurring_ticket_priority == "High") { echo "selected"; } ?> >High</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign To</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        </div>
                        <select class="form-control select2" name="assigned_to">
                            <option value="0">- Select Agent -</option>
                            <?php
                            $sql_users_select = mysqli_query($mysqli, "SELECT user_id, user_name FROM users
                                WHERE user_type = 1
                                AND user_archived_at IS NULL
                                ORDER BY user_name DESC"
                            );
                            while ($row = mysqli_fetch_array($sql_users_select)) {
                                $user_id_select = intval($row['user_id']);
                                $user_name_select = nullable_htmlentities($row['user_name']);

                                ?>
                                <option value="<?php echo $user_id_select; ?>" <?php if ($recurring_ticket_assigned_to == $user_id_select) { echo "selected"; } ?>><?php echo $user_name_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group <?php if (!$config_module_enable_accounting) { echo 'd-none'; } ?>">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="editTicketBillable" name="billable" 
                            <?php if ($recurring_ticket_billable == 1) { echo "checked"; } ?> value="1"
                        >
                        <label class="custom-control-label" for="editTicketBillable">Mark Billable</label>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-edit-contacts<?php echo $recurring_ticket_id; ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="0">- Select Contact -</option>
                            <?php
                            $sql_client_contacts_select = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_primary, contact_technical FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                            while ($row = mysqli_fetch_array($sql_client_contacts_select)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                $contact_primary_select = intval($row['contact_primary']);
                                if($contact_primary_select == 1) {
                                    $contact_primary_display_select = " (Primary)";
                                } else {
                                    $contact_primary_display_select = "";
                                }
                                $contact_technical_select = intval($row['contact_technical']);
                                if($contact_technical_select == 1) {
                                    $contact_technical_display_select = " (Technical)";
                                } else {
                                    $contact_technical_display_select = "";
                                }
                                $contact_title_select = nullable_htmlentities($row['contact_title']);
                                if(!empty($contact_title_select)) {
                                    $contact_title_display_select = " - $contact_title_select";
                                } else {
                                    $contact_title_display_select = "";
                                }

                                ?>
                                <option value="<?php echo $contact_id_select; ?>" <?php if ($contact_id_select  == $recurring_ticket_contact_id) { echo "selected"; } ?>><?php echo "$contact_name_select$contact_title_display_select$contact_primary_display_select$contact_technical_display_select"; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-edit-schedule<?php echo $recurring_ticket_id; ?>">

                <div class="form-group">
                    <label>Frequency <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                        </div> 
                        <select class="form-control select2" name="frequency">
                            <option <?php if ($recurring_ticket_frequency == "Weekly") { echo "selected"; } ?>>Weekly</option>
                            <option <?php if ($recurring_ticket_frequency == "Monthly") { echo "selected"; } ?>>Monthly</option>
                            <option <?php if ($recurring_ticket_frequency == "Quarterly") { echo "selected"; } ?>>Quarterly</option>
                            <option <?php if ($recurring_ticket_frequency == "Biannually") { echo "selected"; } ?>>Biannually</option>
                            <option <?php if ($recurring_ticket_frequency == "Annually") { echo "selected"; } ?>>Annually</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next run date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        </div>
                        <input class="form-control" type="date" name="next_date" max="2999-12-31" value="<?php echo $recurring_ticket_next_run; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-edit-assets<?php echo $recurring_ticket_id; ?>">

                <div class="form-group">
                    <label>Asset</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        </div>
                        <select class="form-control select2" name="asset">
                            <option value="0">- Select Asset -</option>
                            <?php

                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_array($sql_assets)) {
                                $asset_id_select = intval($row['asset_id']);
                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                                ?>
                                <option <?php if ($recurring_ticket_asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                                <?php
                            }
                            ?>
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

                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_id != $recurring_ticket_asset_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_array($sql_assets)) {
                                $asset_id_select = intval($row['asset_id']);
                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                            ?>
                                <option value="<?php echo $asset_id_select; ?>" 
                                    <?php if (in_array($asset_id_select, $additional_assets_array)) { echo "selected"; } ?>
                                    ><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
