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
                            <!-- If $account_type is set and exists in the array, show it as selected -->
                            <?php if (isset($account_type) && isset($account_types[$account_type])): ?>
                                <option value="<?php echo $account_type; ?>" selected><?php echo $account_types[$account_type]; ?></option>
                            <?php endif; ?>
                            <option value="">----------------</option>
                            <!-- Loop through the associative array to generate the options -->
                            <?php foreach ($account_types as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
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
