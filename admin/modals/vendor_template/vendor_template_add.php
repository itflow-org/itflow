<?php

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-building me-2"></i>New Vendor Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-support">Support</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="mb-3">
                    <label>Vendor Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Vendor Name" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Description" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Account Number</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                        <input type="text" class="form-control" name="account_number" placeholder="Account number" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Account Manager</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="contact_name" placeholder="Account manager's name" maxlength="200">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-support">

                <label>Support Phone / <span class="text-secondary">Extension</span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control col-2" name="phone_country_code" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="phone" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="extension" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Support Hours</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="text" class="form-control" name="hours" placeholder="Support Hours" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Support Email</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="Support Email" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Support Website URL</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="website" placeholder="Do not include http(s)://" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Pin/Code</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <input type="text" class="form-control" name="code" placeholder="Access Code or Pin" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>SLA</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                        <input type="text" class="form-control" name="sla" placeholder="SLA Response Time" maxlength="200">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">

                <div class="mb-3">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>
                </div>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_vendor_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
