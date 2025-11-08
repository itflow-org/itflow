<?php

require_once '../../../includes/modal_header.php';

// Filters
$leads_filter = intval($_GET['lead'] ?? 0);

// Selects
$referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL ORDER BY category_name ASC");

$sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>New <?php if($leads_filter == 0){ echo "Client"; } else { echo "Lead"; } ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<ul class="modal-header nav nav-pills nav-justified">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="pill" href="#pills-location">Location</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="pill" href="#pills-contact" id="contactNavPill">Contact</a>
    </li>
    <?php if ($config_module_enable_accounting) { ?>
        <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#pills-billing">Billing</a>
        </li>
    <?php } ?>
    <li class="nav-item">
        <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
    </li>
</ul>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <div class="modal-body">

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="client_name" placeholder="Name or Company" maxlength="200" onfocusout="client_duplicate_check()" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="lead" value="1" <?php if($leads_filter == 1){ echo "checked"; } ?>>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="client_duplicate_info"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Shortened Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="abbreviation" placeholder="Shortned name for client - Max chars 6" maxlength="6" oninput="this.value = this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Industry</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                        </div>
                        <input type="text" class="form-control" name="type" placeholder="Company Type" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>Referral</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <select class="form-control select2" data-tags="true" name="referral">
                            <option value="">- Select Referral -</option>
                            <?php

                            while ($row = mysqli_fetch_array($referral_sql)) {
                                $referral = nullable_htmlentities($row['category_name']); ?>
                                <option><?php echo $referral; ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/category/category_add.php?category=Referral">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Website</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="website" placeholder="ex. google.com" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="- Select Tags -"multiple>
                            <?php

                            while ($row = mysqli_fetch_array($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = nullable_htmlentities($row['tag_name']);
                                ?>
                                <option value="<?php echo $tag_id_select; ?>"><?php echo $tag_name_select; ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=1">
                                <i class="fas fa-fw fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-location">

                <div class="form-group">
                    <label>Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <input type="text" class="form-control" name="address" placeholder="Street Address" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>City</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                        </div>
                        <input type="text" class="form-control" name="city" placeholder="City" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>State / Province</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="state" placeholder="State or Province" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>Postal Code</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                        </div>
                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                        </div>
                        <select class="form-control select2" name="country">
                            <option value="">- Select Country -</option>
                            <?php foreach($countries_array as $country_name) { ?>
                                <option <?php if ($session_company_country == $country_name) { echo "selected"; } ?> ><?php echo $country_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                
                <label>Location Phone / <span class="text-secondary">Extension</span></label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="location_phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="location_phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <input type="text" class="form-control" name="location_extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Location Fax</label>    
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="location_fax_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="location_fax" placeholder="Fax Number">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-contact">

                <div class="form-group">
                    <label>Primary Contact <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        </div>
                        <input type="text" class="form-control" id="primaryContact" name="contact" placeholder="Primary Contact Person" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                        </div>
                        <input type="text" class="form-control" name="title" placeholder="Title" maxlength="200">
                    </div>
                </div>

                <label>Contact Phone / <span class="text-secondary">Extension</span></label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="contact_phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="contact_phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <input type="text" class="form-control" name="contact_extension" placeholder="ext." maxlength="200">
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
                                <input type="tel" class="form-control col-2" name="contact_mobile_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="contact_mobile" placeholder="Mobile Phone Number">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Contact Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="contact_email" placeholder="Contact's Email Address" maxlength="200">
                    </div>
                </div>

            </div>

            <?php if ($config_module_enable_accounting) { ?>

                <div class="tab-pane fade" id="pills-billing">

                    <div class="form-group">
                        <label>Hourly Rate</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="rate" placeholder="0.00" value="<?php echo "$config_default_hourly_rate"; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Payment Terms</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <select class="form-control select2" name="net_terms">
                                <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                                    <option <?php if ($config_default_net_terms == $net_term_value) { echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tax ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            </div>
                            <input type="text" class="form-control" name="tax_id_number" placeholder="Tax ID Number" maxlength="255">
                        </div>
                    </div>

                </div>

            <?php } ?>

            <div class="tab-pane fade" id="pills-notes">
                <div class="form-group">
                    <textarea class="form-control" rows="10" name="notes" placeholder="Enter some notes"></textarea>
                </div>
            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_client" class="btn btn-primary text-bold" onclick="promptPrimaryContact()"><i class="fa fa-check mr-2"></i>Create Client</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Close</button>
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
    function client_duplicate_check() {
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
