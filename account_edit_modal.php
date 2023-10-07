<div class="modal" id="editAccountModal<?php echo $account_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>Editing account: <strong><?php echo $account_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="account_id" value="<?php echo $account_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Account Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" value="<?php echo $account_name; ?>" placeholder="Account name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Account Type <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>
                            <select class="form-control select" name="type" required>
                                <option value="">- Select -</option>
                                <option value="11" <?php if ($account_type == 'Current Assets') echo 'selected'; ?>>Current Assets</option>
                                <option value="12" <?php if ($account_type == 'Fixed Assets') echo 'selected'; ?>>Fixed Assets</option>
                                <option value="13" <?php if ($account_type == 'Other Assets') echo 'selected'; ?>>Other Assets</option>
                                <option value="21" <?php if ($account_type == 'Current Liabilities') echo 'selected'; ?>>Current Liabilities</option>
                                <option value="22" <?php if ($account_type == 'Long Term Liabilities') echo 'selected'; ?>>Long Term Liabilities</option>
                                <option value="23" <?php if ($account_type == 'Other Liabilities') echo 'selected'; ?>>Other Liabilities</option>
                                <option value="30" <?php if ($account_type == 'Equity') echo 'selected'; ?>>Equity</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"><?php echo $account_notes; ?></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_account" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
