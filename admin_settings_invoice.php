<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-file-invoice mr-2"></i>Invoice Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <h4>Invoice</h4>

                <div class="form-group">
                    <label>Invoice Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_invoice_prefix" placeholder="Invoice Prefix" value="<?php echo nullable_htmlentities($config_invoice_prefix); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_invoice_next_number" placeholder="Next Invoice Number" value="<?php echo intval($config_invoice_next_number); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Invoice Footer</label>
                    <textarea class="form-control" rows="4" name="config_invoice_footer"><?php echo nullable_htmlentities($config_invoice_footer); ?></textarea>
                </div>

                <h5>Invoice Late Fees</h5>

                 <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_invoice_late_fee_enable" <?php if ($config_invoice_late_fee_enable == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                        <label class="custom-control-label" for="customSwitch1">Enable Late Fee</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Late Fee %</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                        </div>
                        <input type="number" class="form-control" min="0" max="100" step="0.01" name="config_invoice_late_fee_percent" value="<?php echo $config_invoice_late_fee_percent; ?>">
                    </div>
                    <small class="text-secondary">We recommend updating the invoice footer to include policies on your late charges. This will be applied every 30 days after the invoice Due Date.</small>
                </div>

                <div class="form-group">
                    <label>Email address to notify when invoices are paid online <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_invoice_paid_notification_email" placeholder="Address to notify for paid invoices, leave blank for none" value="<?php echo nullable_htmlentities($config_invoice_paid_notification_email); ?>">
                    </div>
                </div>

                <hr>

                <h4>Recurring Invoice</h4>

                <div class="form-group">
                    <label>Recurring Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_recurring_invoice_prefix" placeholder="Recurring Invoice Prefix" value="<?php echo nullable_htmlentities($config_recurring_invoice_prefix); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_recurring_invoice_next_number" placeholder="Next Recurring Invoice Number" value="<?php echo intval($config_recurring_invoice_next_number); ?>" required>
                    </div>
                </div>



                <hr>

                <button type="submit" name="edit_invoice_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "includes/footer.php";

