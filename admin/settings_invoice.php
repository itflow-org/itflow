<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-file-invoice me-2"></i>Invoice Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <h4>Invoice</h4>

                <div class="mb-3">
                    <label>Invoice Prefix</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="text" class="form-control" name="config_invoice_prefix" placeholder="Invoice Prefix" maxlength="200" value="<?= escapeHtml($config_invoice_prefix) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Next Number</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="number" min="0" class="form-control" name="config_invoice_next_number" placeholder="Next Invoice Number" value="<?= intval($config_invoice_next_number) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Invoice Footer</label>
                    <textarea class="form-control" rows="4" name="config_invoice_footer"><?= escapeHtml($config_invoice_footer) ?></textarea>
                </div>

                <h5>Show Tax ID On Invoices</h5>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_invoice_show_tax_id" <?php if ($config_invoice_show_tax_id == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                        <label class="form-check-label" for="customSwitch1">Show Tax ID</label>
                    </div>
                </div>

                <h5>Invoice Late Fees</h5>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_invoice_late_fee_enable" <?php if ($config_invoice_late_fee_enable == 1) { echo "checked"; } ?> value="1" id="customSwitch2">
                        <label class="form-check-label" for="customSwitch2">Enable Late Fee</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Late Fee %</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                        <input type="number" class="form-control" min="0" max="100" step="0.01" name="config_invoice_late_fee_percent" value="<?= $config_invoice_late_fee_percent ?>">
                    </div>
                    <small class="text-secondary">We recommend updating the invoice footer to include policies on your late charges. This will be applied every 30 days after the invoice Due Date.</small>
                </div>

                <div class="mb-3">
                    <label>Email address to notify when invoices are paid online <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <input type="email" class="form-control" name="config_invoice_paid_notification_email" placeholder="Address to notify for paid invoices, leave blank for none" maxlength="200" value="<?= escapeHtml($config_invoice_paid_notification_email) ?>">
                    </div>
                </div>

                <hr>

                <h4>Recurring Invoice</h4>

                <div class="mb-3">
                    <label>Recurring Prefix</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="text" class="form-control" name="config_recurring_invoice_prefix" placeholder="Recurring Invoice Prefix" maxlength="200" value="<?= escapeHtml($config_recurring_invoice_prefix) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Next Number</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="number" min="0" class="form-control" name="config_recurring_invoice_next_number" placeholder="Next Recurring Invoice Number" value="<?= intval($config_recurring_invoice_next_number) ?>" required>
                    </div>
                </div>

                <hr>

                <?php $time_rules = billingTimeRules(); ?>
                <h4 id="billing-time">Billing Time</h4>
                <p class="text-secondary small">How the time logged on a billable ticket becomes hours on an invoice - in Unbilled Time, a ticket's Invoice window and Add Tickets. Each ticket's time is rounded once, as a whole. It is billed at the client's hourly rate, or the default rate in Defaults when the client has none.</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Round to</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                            <select class="form-select" name="config_time_increment">
                                <?php foreach (billingTimeIncrements() as $minutes => $label) { ?>
                                    <option value="<?= $minutes ?>" <?= $time_rules['increment'] === $minutes ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Rounding</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-sort-amount-up"></i></span>
                            <select class="form-select" name="config_time_rounding">
                                <option value="nearest" <?= $time_rules['rounding'] === 'nearest' ? 'selected' : '' ?>>To the nearest - under half a step rounds down</option>
                                <option value="up" <?= $time_rules['rounding'] === 'up' ? 'selected' : '' ?>>Always up - any part of a step bills the whole step</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Least a ticket bills</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-arrow-down"></i></span>
                            <select class="form-select" name="config_time_minimum">
                                <?php foreach ([0 => 'No minimum', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours'] as $minutes => $label) { ?>
                                    <option value="<?= $minutes ?>" <?= $time_rules['minimum'] === $minutes ? 'selected' : '' ?>><?= $label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <small class="text-secondary">Once it has any time logged. A ticket with none bills nothing unless the hours are typed.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Invoice line</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="config_time_item_name" maxlength="200" value="<?= escapeHtml($time_rules['item_name']) ?>" placeholder="Support [Hourly]" required>
                        </div>
                        <small class="text-secondary">The name of the line the time goes on. The ticket number, subject and date go in its description.</small>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_invoice_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "../includes/footer.php";

