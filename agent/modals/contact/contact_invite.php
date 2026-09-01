<div class="modal" id="contactInviteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-user-plus me-2"></i>Invite Contact</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">

                <div class="modal-body">

                    <div class="mb-3">
                        <label>Email</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Welcome Letter</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-envelope-open-text"></i></span>
                            <select class="form-select select2" name="welcome_letter">
                                <option value="1">- Select One -</option>
                                <option value="2">Standard</option>
                                <option value="3">Big Wig</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"><?= $contact_notes ?></textarea>
                    </div>

                    <div class="row g-2">

                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="contactInviteImportantCheckbox" name="contact_important" value="1" >
                                    <label class="form-check-label" for="contactInviteImportantCheckbox">Important</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="contactInviteBillingCheckbox" name="contact_billing" value="1" >
                                    <label class="form-check-label" for="contactInviteBillingCheckbox">Billing</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="contactInviteTechnicalCheckbox" name="contact_technical" value="1" >
                                    <label class="form-check-label" for="contactInviteTechnicalCheckbox">Technical</label>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="invite_contact" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send Invite</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
