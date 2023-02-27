<div class="modal" id="editCertificateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>Editing certificate: <span class="text-bold" id="editHeader"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="certificate_id" value="" id="editCertificateId">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Certificate Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="text" class="form-control" id="editCertificateName" name="name" placeholder="Certificate name" value="" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Domain <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i>&nbsp;https://</span>
                            </div>
                            <input type="text" class="form-control" id="editDomain" name="domain" placeholder="Domain" value="" required>
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
                            <input type="text" class="form-control" id="editIssuedBy" name="issued_by" placeholder="Issued By" value="">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expire Date</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" id="editExpire" name="expire" max="2999-12-31" value="">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Public Key </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <textarea class="form-control" id="editPublicKey" name="public_key"></textarea>
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
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_certificate" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>