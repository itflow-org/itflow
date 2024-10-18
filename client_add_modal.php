<div class="modal" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>Create <?php if($leads == 0){ echo "Client"; } else { echo "Lead"; } ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="lead" value="0">
                <input type="hidden" name="net_terms" value="0">
                <input type="hidden" name="currency_code" value="<?php echo $session_company_currency; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
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

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Is Lead</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Name or Company" required autofocus>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="lead" value="1" <?php if($leads == 1){ echo "checked"; } ?>>
                                        </div>
                                    </div>
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
                                    <input type="text" class="form-control" name="type" placeholder="Company Type">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Referral</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                                    </div>
                                    <select class="form-control select2" data-tags="true" name="referral">
                                        <option value="">N/A</option>
                                        <?php

                                        $referral_sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                        while ($row = mysqli_fetch_array($referral_sql)) {
                                            $referral = nullable_htmlentities($row['category_name']); ?>
                                            <option><?php echo $referral; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Website</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="website" placeholder="ex. google.com">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Tags</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                                    </div>
                                    <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                                        <?php

                                        $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");
                                        while ($row = mysqli_fetch_array($sql_tags_select)) {
                                            $tag_id_select = intval($row['tag_id']);
                                            $tag_name_select = nullable_htmlentities($row['tag_name']);
                                            ?>
                                            <option value="<?php echo $tag_id_select; ?>"><?php echo $tag_name_select; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-location">

                            <label>Location Phone</label>

                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="location_phone" placeholder="Location's Phone Number">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="address" placeholder="Street Address">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>City</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="city" placeholder="City">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>State / Province</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="state" placeholder="State or Province">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Postal Code</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Country</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                                    </div>
                                    <select class="form-control select2" name="country">
                                        <option value="">- Country -</option>
                                        <?php foreach($countries_array as $country_name) { ?>
                                            <option <?php if ($session_company_country == $country_name) { echo "selected"; } ?> ><?php echo $country_name; ?></option>
                                        <?php } ?>
                                    </select>
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
                                    <input type="text" class="form-control" id="primaryContact" name="contact" placeholder="Primary Contact Person" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Title</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="title" placeholder="Title">
                                </div>
                            </div>

                            <label>Contact Phone</label>
                            <div class="form-row">
                                <div class="col-8">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="contact_phone" placeholder="Contact's Phone Number">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control" name="contact_extension" placeholder="Extension">
                                </div>
                            </div>

                            <label>Contact Mobile</label>

                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="contact_mobile" placeholder="Contact's Mobile Number">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Contact Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="contact_email" placeholder="Contact's Email Address">
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
                                <label>Currency <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                    </div>
                                    <select class="form-control select2" name="currency_code" required>
                                        <option value="">- Currency -</option>
                                        <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                            <option <?php if ($session_company_currency == $currency_code) { echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                        <?php } ?>
                                    </select>
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
                                    <input type="text" class="form-control" name="tax_id_number" placeholder="Tax ID Number">
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
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_client" class="btn btn-primary text-bold" onclick="promptPrimaryContact()"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Checks/prompts that the primary contact field (required) is populated
    function promptPrimaryContact() {
        let primaryContactField = document.getElementById("primaryContact").value;
        if (primaryContactField == null || primaryContactField === "") {
            document.getElementById("contactNavPill").click();
        }
    }
</script>
