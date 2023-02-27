<div class="modal" id="addVendorTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-building mr-2"></i>New Vendor Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">

                <input type="hidden" name="client_id" value="<?php if (isset($_GET['client_id'])) { echo $client_id; } else { echo 0; } ?>">

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-support">Support</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Vendor Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Vendor Name" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Description">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Account Number</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="account_number" placeholder="Account number">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Account Manager</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="contact_name" placeholder="Account manager's name">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-support">

                            <label>Support Phone</label>
                            <div class="form-row">
                                <div class="col-8">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="phone" placeholder="Phone Number">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control" name="extension" placeholder="Prompts">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Support Hours</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="hours" placeholder="Support Hours">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Support Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Support Email">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Support Website URL</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="website" placeholder="Do not include http(s)://">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Pin/Code</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="code" placeholder="Access Code or Pin">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>SLA</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="sla" placeholder="SLA Response Time">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-notes">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>
                            </div>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_vendor_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create Template</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
