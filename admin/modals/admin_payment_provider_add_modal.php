<div class="form-group">
    <div class="modal" id="addPaymentProviderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Add Payment Provider</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <div class="alert alert-info">An income account named after the provider will always be created and used for income of payed invoices.
                    If "Enable Expense" option is enabled, a matching vendor will also be automatically created (if it doesn't already exist), and used for expense tracking. Additionally, an expense category named "Payment Processing" will be created if it does not already exist.
                    </div>

                    <div class="form-group">
                        <label>Provider <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            </div>
                            <select class="form-control select2" name="provider">
                                <option>Stripe</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Publishable key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <input type="text" class="form-control" name="public_key" placeholder="Publishable API Key (pk_...)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Secret key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <input type="text" class="form-control" name="private_key" placeholder="Secret API Key (sk_...)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Threshold</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="Threshold" placeholder="1000.00">
                        </div>
                        <small class="form-text text-muted">Will not show as an option at Checkout if above this number</small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" name="enable_expense" checked value="1" id="enableExpenseSwitch">
                            <label class="custom-control-label" for="enableExpenseSwitch">Enable Expense</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Percentage Fee to expense</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="percentage_fee" placeholder="Enter Percentage">
                        </div>
                        <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                    </div>

                    <div class="form-group">
                        <label>Flat Fee to expense</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,3}" name="flat_fee" placeholder="0.030">
                        </div>
                        <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_payment_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
