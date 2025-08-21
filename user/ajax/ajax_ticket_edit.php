<?php

require_once '../../includes/modal_header.php';

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE ticket_id = $ticket_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_category = intval($row['ticket_category']);
$ticket_subject = nullable_htmlentities($row['ticket_subject']);
$ticket_details = nullable_htmlentities($row['ticket_details']);
$ticket_priority = nullable_htmlentities($row['ticket_priority']);
$ticket_billable = intval($row['ticket_billable']);
$ticket_vendor_ticket_number = nullable_htmlentities($row['ticket_vendor_ticket_number']);
$ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
$ticket_due_at = nullable_htmlentities($row['ticket_due_at']);
$ticket_assigned_to = intval($row['ticket_assigned_to']);
$contact_id = intval($row['ticket_contact_id']);
$asset_id = intval($row['ticket_asset_id']);
$location_id = intval($row['ticket_location_id']);
$vendor_id = intval($row['ticket_vendor_id']);
$project_id = intval($row['ticket_project_id']);

// Additional Assets Selected
$additional_assets_array = array();
$sql_additional_assets = mysqli_query($mysqli, "SELECT asset_id FROM ticket_assets WHERE ticket_id = $ticket_id");
while ($row = mysqli_fetch_array($sql_additional_assets)) {
    $additional_asset_id = intval($row['asset_id']);
    $additional_assets_array[] = $additional_asset_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Editing ticket: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-contacts<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-assignment<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-desktop mr-2"></i>Assignment</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-details<?php echo $ticket_id; ?>">

                <div class="form-group">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="subject" maxlength="500" value="<?php echo $ticket_subject; ?>" placeholder="Subject" required>
                    </div>
                </div>

                <div class="form-group">
                    <textarea class="form-control tinymceTicket" rows="8" name="details"><?php echo $ticket_details; ?></textarea>
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
                                    <option <?php if ($ticket_priority == 'Low') { echo "selected"; } ?> >Low</option>
                                    <option <?php if ($ticket_priority == 'Medium') { echo "selected"; } ?> >Medium</option>
                                    <option <?php if ($ticket_priority == 'High') { echo "selected"; } ?> >High</option>
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
                                    <option value="0">- Uncategorized -</option>
                                    <?php
                                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql_categories)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);

                                        ?>
                                        <option <?php if ($ticket_category == $category_id) {echo "selected";} ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                    <?php } ?>

                                </select>
                                <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button"
                                        data-toggle="ajax-modal"
                                        data-modal-size="sm"
                                        data-ajax-url="../admin/ajax/ajax_category_add.php?category=Ticket">
                                        <i class="fas fa-fw fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
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
                                        <option <?php if ($ticket_assigned_to === $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label>Due</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                </div>
                                <input type="datetime-local" class="form-control" name="due" value="<?php echo $ticket_due_at; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="billable" <?php if ($ticket_billable == 1) { echo "checked"; } ?> value="1" id="billableSwitch<?php echo $ticket_id; ?>">
                        <label class="custom-control-label" for="billableSwitch<?php echo $ticket_id; ?>">Mark Billable</label>
                    </div>
                </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-contacts<?php echo $ticket_id; ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="0">No One</option>
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
                                <option value="<?php echo $contact_id_select; ?>" <?php if ($contact_id_select  == $contact_id) { echo "selected"; } ?>><?php echo "$contact_name_select$contact_title_display_select$contact_primary_display_select$contact_technical_display_select"; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if (!empty($config_smtp_host)) { ?>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="contact_notify" value="1" id="checkNotifyContact">
                            <label class="form-check-label" for="checkNotifyContact">
                                Send email notification
                            </label>
                        </div>
                    </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-assignment<?php echo $ticket_id; ?>">

                <div class="form-group">
                    <label>Asset</label>
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
                                <option <?php if ($asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

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

                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_id != $asset_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
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
                                <option <?php if ($location_id == $location_id_select) { echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>

                                <?php
                            }
                            ?>
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

                                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                    while ($row = mysqli_fetch_array($sql_vendors)) {
                                        $vendor_id_select = intval($row['vendor_id']);
                                        $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                        ?>
                                        <option <?php if ($vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>

                                        <?php
                                    }
                                    ?>
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
                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Vendor ticket number" value="<?php echo $ticket_vendor_ticket_number; ?>">
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
                            <option value="0">- None -</option>
                            <?php

                            $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_name FROM projects WHERE (project_client_id = $client_id OR project_client_id = 0) AND project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                            while ($row = mysqli_fetch_array($sql_projects)) {
                                $project_id_select = intval($row['project_id']);
                                $project_name_select = nullable_htmlentities($row['project_name']); ?>
                                <option <?php if ($project_id == $project_id_select) { echo "selected"; } ?> value="<?php echo $project_id_select; ?>"><?php echo $project_name_select; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../includes/modal_footer.php';
