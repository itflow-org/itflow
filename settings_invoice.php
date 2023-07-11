<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-file mr-2"></i>Invoice Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <legend>Invoice</legend>

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

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_invoice_from_email" placeholder="From Email" value="<?php echo nullable_htmlentities($config_invoice_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_invoice_from_name" placeholder="From Name" value="<?php echo nullable_htmlentities($config_invoice_from_name); ?>">
                    </div>
                </div>

                <hr>

                <legend>Invoice Late Fees</legend>

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

                <hr>

                <legend>Recurring Invoice</legend>

                <div class="form-group">
                    <label>Recurring Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_recurring_prefix" placeholder="Recurring Prefix" value="<?php echo nullable_htmlentities($config_recurring_prefix); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_recurring_next_number" placeholder="Next Recurring Number" value="<?php echo intval($config_recurring_next_number); ?>" required>
                    </div>
                </div>



                <hr>

                <button type="submit" name="edit_invoice_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
