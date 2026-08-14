<?php

require_once '../../../includes/modal_header.php';

// Filters
$leads_filter = intval($_GET['lead'] ?? 0);

// Selects
$referral_sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL ORDER BY category_name ASC");

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");

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
    <h5 class="modal-title"><i class="fa fa-fw fa-user-plus me-2"></i>New <?php if($leads_filter == 0){ echo "Client"; } else { echo "Lead"; } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#pills-details">Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-location">Location</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-contact" id="contactNavPill">Contact</a>
    </li>
    <?php if ($config_module_enable_accounting) { ?>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#pills-billing">Billing</a>
        </li>
    <?php } ?>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#pills-notes">Notes</a>
    </li>
</ul>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="name" id="client_name" placeholder="Name or Company" maxlength="200" onfocusout="checkClientDuplicate()" required autofocus>
                            <div class="input-group-text">
                                <input class="form-check-input" type="checkbox" name="lead" value="1" <?php if($leads_filter == 1){ echo "checked"; } ?>>
                            </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="client_duplicate_info"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Shortened Name</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="abbreviation" placeholder="Shortned name for client - Max chars 6" maxlength="6" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Industry</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                        <input type="text" class="form-control" name="type" placeholder="Company Type" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Referral</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        <select class="form-select select2" data-tags="true" name="referral">
                            <option value="">- Select Referral -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($referral_sql)) {
                                $referral = escapeHtml($row['category_name']); ?>
                                <option><?= $referral ?></option>
                            <?php } ?>

                        </select>
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/category/category_add.php?category=Referral">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Website</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="website" placeholder="ex. google.com" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Tags</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        <select class="form-select select2" name="tags[]" data-placeholder="- Select Tags -"multiple>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = escapeHtml($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id_select ?>"><?= $tag_name_select ?></option>
                            <?php } ?>

                        </select>
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=1">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-location">

                <div class="mb-3">
                    <label>Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="address" placeholder="Street Address" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>City</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                        <input type="text" class="form-control" name="city" placeholder="City" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>State / Province</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        <input type="text" class="form-control" name="state" placeholder="State or Province" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Postal Code</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Country</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                        <select class="form-select select2" name="country">
                            <option value="">- Select Country -</option>
                            <?php foreach($countries_array as $country_name) { ?>
                                <option <?php if ($session_company_country == $country_name) { echo "selected"; } ?> ><?= $country_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>


                <label>Location Phone / <span class="text-secondary">Extension</span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control col-2" name="location_phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="location_phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="location_extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Location Fax</label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                                <input type="tel" class="form-control col-2" name="location_fax_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="location_fax" placeholder="Fax Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-contact">

                <div class="mb-3">
                    <label>Primary Contact <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <input type="text" class="form-control" id="primaryContact" name="contact" placeholder="Primary Contact Person" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Title</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        <input type="text" class="form-control" name="title" placeholder="Title" maxlength="200">
                    </div>
                </div>

                <label>Contact Phone / <span class="text-secondary">Extension</span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control col-2" name="contact_phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="contact_phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="contact_extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Mobile</label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                <input type="tel" class="form-control col-2" name="contact_mobile_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="contact_mobile" placeholder="Mobile Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Contact Email</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <input type="email" class="form-control" name="contact_email" placeholder="Contact's Email Address" maxlength="200">
                    </div>
                </div>

            </div>

            <?php if ($config_module_enable_accounting) { ?>

                <div class="tab-pane fade" id="pills-billing">

                    <div class="mb-3">
                        <label>Hourly Rate</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="rate" placeholder="0.00" value="<?= "$config_default_hourly_rate" ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Payment Terms</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            <select class="form-select select2" name="net_terms">
                                <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                                    <option <?php if ($config_default_net_terms == $net_term_value) { echo "selected"; } ?> value="<?= $net_term_value ?>"><?= $net_term_name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Tax ID</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            <input type="text" class="form-control" name="tax_id_number" placeholder="Tax ID Number" maxlength="255">
                        </div>
                    </div>


                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-notes">
                <div class="mb-3">
                    <textarea class="form-control" rows="10" name="notes" placeholder="Enter some notes"></textarea>
                </div>

                <?php
                // Ticket SLA assignments - only offered when active SLAs exist
                $sla_options = [];
                $sla_options_sql = mysqli_query($mysqli, "SELECT sla_id, sla_name FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_name ASC");
                while ($sla_option_row = mysqli_fetch_assoc($sla_options_sql)) {
                    $sla_options[intval($sla_option_row['sla_id'])] = $sla_option_row['sla_name'];
                }
                if ($config_module_enable_ticketing && !empty($sla_options)) { ?>
                    <div class="mb-3">
                        <label>Ticket SLAs</label>
                        <div class="row g-2">
                            <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $sla_priority) { ?>
                                <div class="col-3">
                                    <small class="text-secondary"><?= $sla_priority ?></small>
                                    <select class="form-select" name="client_sla_<?= strtolower($sla_priority) ?>">
                                        <option value="default">Default</option>
                                        <option value="0">None</option>
                                        <?php foreach ($sla_options as $sla_option_id => $sla_option_name) { ?>
                                            <option value="<?= $sla_option_id ?>"><?= escapeHtml($sla_option_name) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                        <small class="text-muted">Default follows the global SLA assignment for each priority.</small>
                    </div>
                <?php } ?>
            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_client" class="btn btn-primary text-bold" onclick="promptPrimaryContact()"><i class="fa fa-check me-2"></i>Create Client</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
    // Checks/prompts that the primary contact field (required) is populated
    function promptPrimaryContact() {
        let primaryContactField = document.getElementById("primaryContact").value;
        if (primaryContactField == null || primaryContactField === "") {
            document.getElementById("contactNavPill").click();
        }
    }
</script>

<script>
    // Checks for duplicate clients
    function checkClientDuplicate() {
        var name = document.getElementById("client_name").value;
        //Send a GET request to ajax.php as ajax.php?client_duplicate_check=true&name=NAME
        jQuery.get(
            "ajax.php",
            {client_duplicate_check: 'true', name: name},
            function(data) {
                //If we get a response from ajax.php, parse it as JSON
                const client_duplicate_data = JSON.parse(data);
                document.getElementById("client_duplicate_info").innerHTML = client_duplicate_data.message;
            }
        );
    }
</script>

<?php
require_once '../../../includes/modal_footer.php';
