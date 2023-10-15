<div class="modal" id="addAccountTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave mr-2"></i>New Account Type</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="name" placeholder="Account Name" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Account Type</label>
                        <select class="form-control select2" name="type">
                            <option value=""<?php if ($account_type == NULL)
                                echo ' selected';
                            ?>>- Select -</option>
                            <option value="10"<?php if ($account_type == 'Assets')
                                echo ' selected';
                            ?>>Assets</option>
                            <option value="20"<?php if ($account_type == 'Liabilities')
                                echo ' selected';
                            ?>>Liabilities</option>
                            <option value="30"<?php if ($account_type == 'Equity')
                                echo ' selected';
                            ?>>Equity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" placeholder="Description"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_account_type" class="btn btn-primary text-bold"><i class="fa fa-check mr- 2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>