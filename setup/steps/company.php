<?php

/*
 * Setup - Company: your company's details, on invoices, quotes and emails
 * (add_company_settings in setup/index.php, which seeds the defaults too).
 * Saved once - the rest is changed in Admin > Settings.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-building text-body-secondary me-2"></i>Company</h1>
        <p class="text-body-secondary mb-4">Your company, as it shows on invoices, quotes and emails. Only the name and country now - the rest can wait.</p>

        <?php if ($company_exists) { ?>
            <div class="d-flex gap-3 align-items-start mb-4" data-step-done>
                <i class="fas fa-check-circle text-success fa-lg mt-1"></i>
                <div>
                    <div class="fw-semibold">The company's details are saved.</div>
                    <div class="small text-body-secondary">Change them later in Admin &gt; Settings.</div>
                </div>
            </div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } elseif (!$install_is_live) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>Your account comes first.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Carry on where setup is<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="setupCompanyName">Company name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            <input type="text" class="form-control" id="setupCompanyName" name="name" placeholder="Company name" maxlength="200" autofocus required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyCountry">Country <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                            <select class="form-select select2" id="setupCompanyCountry" name="country" required>
                                <option value="">- Country -</option>
                                <?php foreach ($countries_array as $country_name) { ?>
                                    <option data-iso2="<?= $country_iso2_array[$country_name] ?? '' ?>"><?= $country_name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyLogo">Logo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                            <input type="file" class="form-control" id="setupCompanyLogo" name="file" accept=".jpg, .jpeg, .png">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupCompanyAddress">Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" id="setupCompanyAddress" name="address" placeholder="Street address" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="setupCompanyCity">City</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                            <input type="text" class="form-control" id="setupCompanyCity" name="city" placeholder="City" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="setupCompanyState">State / province</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                            <input type="text" class="form-control" id="setupCompanyState" name="state" placeholder="State" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="setupCompanyZip">Postal code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                            <input type="text" class="form-control" id="setupCompanyZip" name="zip" placeholder="Zip" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyPhone">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                            <input type="tel" class="form-control" id="setupCompanyPhone" name="phone" placeholder="Phone number" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyEmail">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control" id="setupCompanyEmail" name="email" placeholder="info@company.com" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyWebsite">Website</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                            <input type="text" class="form-control" id="setupCompanyWebsite" name="website" placeholder="company.com" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyTax">Tax ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                            <input type="text" class="form-control" id="setupCompanyTax" name="tax_id" placeholder="Tax ID" maxlength="200">
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="add_company_settings" class="btn btn-primary" data-setup-next>Save and carry on<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
