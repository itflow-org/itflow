<?php
require_once "includes/inc_all_admin.php";


$sql = mysqli_query($mysqli,"SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");

$row = mysqli_fetch_array($sql);
$company_id = intval($row['company_id']);
$company_name = nullable_htmlentities($row['company_name']);
$company_country = nullable_htmlentities($row['company_country']);
$company_address = nullable_htmlentities($row['company_address']);
$company_city = nullable_htmlentities($row['company_city']);
$company_state = nullable_htmlentities($row['company_state']);
$company_zip = nullable_htmlentities($row['company_zip']);
$company_phone = formatPhoneNumber($row['company_phone']);
$company_email = nullable_htmlentities($row['company_email']);
$company_website = nullable_htmlentities($row['company_website']);
$company_logo = nullable_htmlentities($row['company_logo']);
$company_locale = nullable_htmlentities($row['company_locale']);
$company_currency = nullable_htmlentities($row['company_currency']);

$company_initials = nullable_htmlentities(initials($company_name));

?>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-fw fa-briefcase mr-2"></i>Company Details</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                    <div class="row">
                        <div class="col-md-3 text-center">
                            <?php if($company_logo) { ?>
                            <img class="img-thumbnail" src="<?php echo "uploads/settings/$company_logo"; ?>">
                            <a href="post.php?remove_company_logo" class="btn btn-outline-danger btn-block">Remove Logo</a>
                            <hr>
                            <?php } ?>
                            <div class="form-group">
                                <label>Upload company logo</label>
                                <input type="file" class="form-control-file" name="file" accept=".jpg, .jpeg, .png">
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Company Name" value="<?php echo $company_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="address" placeholder="Street Address" value="<?php echo $company_address; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>City</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $company_city; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>State / Province</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="state" placeholder="State or Province" value="<?php echo $company_state; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Postal Code</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" value="<?php echo $company_zip; ?>">
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
                                            <option <?php if ($company_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Phone</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                    </div>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo $company_phone; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Email address" value="<?php echo $company_email; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Website</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="website" placeholder="Website address" value="<?php echo $company_website; ?>">
                                </div>
                            </div>

                            <hr>

                            <button type="submit" name="edit_company" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php
require_once "includes/footer.php";

