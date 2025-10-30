<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

if ($client_id) {
     $sql_location_select = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
} else {
    $sql_client_select = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
}   

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 3 ORDER BY tag_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-plus mr-2"></i>New Contact</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details"><i class="fas fa-fw fa-user mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-photo"><i class="fas fa-fw fa-image mr-2"></i>Photo</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-access"><i class="fas fa-fw fa-lock mr-2"></i>Access</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes"><i class="fas fa-fw fa-edit mr-2"></i>Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <?php } else { ?>

                    <div class="form-group">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client_id" required>
                                <option value="">- Select Client -</option>
                                <?php

                                while ($row = mysqli_fetch_array($sql_client_select)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']); ?>
                                    <option <?php if ($client_id_select == $client_id) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary Contact</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="contact_primary" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="title" placeholder="Job Title" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>Department / Group</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                        </div>
                        <input type="text" class="form-control" name="department" placeholder="Department or group" maxlength="200">
                    </div>
                </div>

                <label>Phone / <span class="text-secondary">Extension</span></label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <input type="text" class="form-control" name="extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Mobile</label>    
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="mobile_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="mobile" placeholder="Mobile Phone Number">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200">
                    </div>
                </div>

                <?php if($client_id) { ?>
                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="location">
                            <option value="">- Select Location -</option>
                            <?php

                            while ($row = mysqli_fetch_array($sql_location_select)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                            ?>
                                <option value="<?= $location_id ?>"><?= $location_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-photo">

                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control-file" name="file" accept="image/*">
                </div>

            </div>

            <div class="tab-pane fade" id="pills-access">

                <div class="form-group">
                    <label>Pin</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="pin" placeholder="Security code or pin" maxlength="255">
                    </div>
                </div>
                <?php if ($config_client_portal_enable == 1) { ?>
                    <div class="authForm">
                        <div class="form-group">
                            <label>Client Portal</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                                </div>
                                <select class="form-control select2 authMethod" name="auth_method">
                                    <option value="">- No Access -</option>
                                    <option value="local">Using Set Password</option>
                                    <option value="azure">Using Azure Credentials</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group passwordGroup" style="display: none;">
                            <label>Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" data-toggle="password" id="password-add" name="contact_password" placeholder="Password" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-default" onclick="generatePassword('add')">
                                        <i class="fa fa-fw fa-question"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                
                <label>Roles:</label>
                <div class="form-row">

                    <div class="col-md-4">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="contactImportantCheckbox" name="contact_important" value="1">
                                <label class="custom-control-label" for="contactImportantCheckbox">Important</label>
                                <p class="text-secondary"><small>Pin Top</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox" name="contact_billing" value="1">
                                <label class="custom-control-label" for="contactBillingCheckbox">Billing</label>
                                <p class="text-secondary"><small>Receives Invoices</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox" name="contact_technical" value="1">
                                <label class="custom-control-label" for="contactTechnicalCheckbox">Technical</label>
                                <p class="text-secondary"><small>Access </small></p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">

                <div class="form-group">
                    <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            while ($row = mysqli_fetch_array($sql_tags_select)) {
                                $tag_id = intval($row['tag_id']);
                                $tag_name = nullable_htmlentities($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id ?>"><?= $tag_name ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=3">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_contact" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>
<!-- JavaScript to Show/Hide Password Form Group -->
<script>

function generatePassword(type, id) {
    // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
    jQuery.get(
        "ajax.php", {
            get_readable_pass: 'true'
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const password = JSON.parse(data);

            // Set the password value to the correct modal, based on the type
            if (type == "add") {
                document.getElementById("password-add").value = password;
            } else if (type == "edit") {
                document.getElementById("password-edit-"+id.toString()).value = password;
            }
        }
    );
}

$(document).ready(function() {
    $('.authMethod').on('change', function() {
        var $form = $(this).closest('.authForm');
        if ($(this).val() === 'local') {
            $form.find('.passwordGroup').show();
        } else {
            $form.find('.passwordGroup').hide();
        }
    });
    $('.authMethod').trigger('change');

});
</script>

<?php

require_once '../../../includes/modal_footer.php';

?>
