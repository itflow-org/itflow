<div class="modal" id="contactInviteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-user-plus mr-2"></i>Invite Contact</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            </div>
                            <input type="email" class="form-control" name="email" placeholder="Email Address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Welcome Letter</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fw fa-envelope-open-text"></i></span>
                            </div>
                            <select class="form-control select2" name="welcome_letter">
                                <option value="1">- Select One -</option>
                                <option value="2">Standard</option>
                                <option value="3">Big Wig</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"><?php echo $contact_notes; ?></textarea>
                    </div>

                    <div class="form-row">

                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="contactInviteImportantCheckbox" name="contact_important" value="1" >
                                    <label class="custom-control-label" for="contactInviteImportantCheckbox">Important</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="contactInviteBillingCheckbox" name="contact_billing" value="1" >
                                    <label class="custom-control-label" for="contactInviteBillingCheckbox">Billing</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="contactInviteTechnicalCheckbox" name="contact_technical" value="1" >
                                    <label class="custom-control-label" for="contactInviteTechnicalCheckbox">Technical</label>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="invite_contact" class="btn btn-primary text-bold"><i class="fas fa-paper-plane mr-2"></i>Send Invite</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>