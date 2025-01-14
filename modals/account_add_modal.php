<div class="modal" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>New Account</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Account Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Account name" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Opening Balance <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="opening_balance" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label>Currency <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                            </div>
                            <select class="form-control select2" name="currency_code" required>
                                <option value="">- Currency -</option>
                                <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                                    <option <?php if ($session_company_currency == $currency_code) { echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_account" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
