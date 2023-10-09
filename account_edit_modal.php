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
                            <?php
                            $sql_account_type = mysqli_query($mysqli, "SELECT * FROM account_types WHERE account_type_id = $account_type_id");
                            $row = mysqli_fetch_array($sql_account_type);
                            $account_type_name = nullable_htmlentities($row['account_type_name']);
                            echo "<option value='$account_type_id' selected>$account_type_name</option>";
                            ?>
                            <option value="">----------------</option>
                            <?php
                            $sql_account_types = mysqli_query($mysqli, "SELECT * FROM account_types ORDER BY account_type_name ASC");
                            while ($row = mysqli_fetch_array($sql_account_types)) {
                                $account_type_id = intval($row['account_type_id']);
                                $account_type_name = nullable_htmlentities($row['account_type_name']);
                                if($account_type_id % 10 != 0) {
                                    echo "<option value='$account_type_id'>$account_type_name</option>";}}?>
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
