<div class="modal" id="editCertificateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>Editing certificate: <span class="text-bold" id="editCertificateHeader"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="certificate_id" value="" id="editCertificateId">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pillsEditDetails">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pillsEditCertificate">Certificate</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pillsEditNotes">Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

                        <div class="tab-pane fade show active" id="pillsEditDetails">

                            <div class="form-group">
                                <label>Certificate Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editCertificateName" name="name" placeholder="Certificate name" maxlength="200" value="" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editCertificateDescription" name="description" placeholder="Short Description">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Domain</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <select class="form-control select2" id="editDomainId" name="domain_id">
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pillsEditCertificate">

                            <div class="form-group">
                                <label>Domain <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i>&nbsp;https://</span>
                                    </div>
                                    <input type="text" class="form-control" id="editCertificateDomain" name="domain" placeholder="Domain" maxlength="200" value="" required>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-secondary" onclick="fetchSSL('edit')"><i class="fas fa-fw fa-sync-alt"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Issued By</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editCertificateIssuedBy" name="issued_by" maxlength="200" placeholder="Issued By" value="">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Expire Date</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                                    </div>
                                    <input type="date" class="form-control" id="editCertificateExpire" name="expire" max="2999-12-31" value="">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Public Key </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <textarea class="form-control" rows="8" id="editCertificatePublicKey" name="public_key"></textarea>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pillsEditNotes">

                            <div class="form-group">
                                <textarea class="form-control" id="editCertificateNotes" name="notes" rows="12" placeholder="Enter some notes"></textarea>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_certificate" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
