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
                    <small class="text-secondary">We recommend updating the invoice footer to include policies on your late charges. It is added as a line every 30 days after the invoice's due date, while payment reminders are on.</small>
                </div>

                <div class="mb-3">
                    <label>Email address to notify when invoices are paid online <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <input type="email" class="form-control" name="config_invoice_paid_notification_email" placeholder="Address to notify for paid invoices, leave blank for none" maxlength="200" value="<?= escapeHtml($config_invoice_paid_notification_email) ?>">
                    </div>
                </div>

                <hr>

                <?php $reminder_rules = billingReminderRules();
                $reminder_before = implode(', ', array_reverse(array_map('abs', array_filter($reminder_rules['days'], function ($day) { return $day < 0; }))));
                $reminder_after = implode(', ', array_filter($reminder_rules['days'], function ($day) { return $day > 0; })); ?>
                <h4 id="reminders">Reminders and Statements</h4>
                <p class="text-secondary small">Payment reminders go to each client's primary and billing contacts while an invoice is still owed, with a button to view and pay it. A client can be left out of reminders and statements on the Billing tab of its Edit window. Late fees above come every 30 days late, while reminders are on.</p>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_send_invoice_reminders" value="1" id="settingsReminders" <?= $reminder_rules['enabled'] ? 'checked' : '' ?> data-reminder-input>
                        <label class="form-check-label" for="settingsReminders">Email payment reminders</label>
                    </div>
                </div>

                <div class="row" data-reminder-fields>
                    <div class="col-md-6 mb-3">
                        <label>Before it is due</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-hourglass-start"></i></span>
                            <input type="text" class="form-control" name="reminder_before" maxlength="60" value="<?= escapeHtml($reminder_before) ?>" placeholder="None - or 3, or 3, 7" data-reminder-input>
                            <span class="input-group-text">days</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>After it is due</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-exclamation-circle"></i></span>
                            <input type="text" class="form-control" name="reminder_after" maxlength="120" value="<?= escapeHtml($reminder_after) ?>" placeholder="None - or 7, 14, 30" data-reminder-input>
                            <span class="input-group-text">days late</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Then every</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
                            <input type="number" class="form-control" name="reminder_repeat" min="0" max="365" value="<?= intval($reminder_rules['repeat']) ?>" data-reminder-input>
                            <span class="input-group-text">days until paid</span>
                        </div>
                        <small class="text-secondary">0 for no more after the last one.</small>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch mt-md-3">
                            <input type="checkbox" class="form-check-input" name="reminder_on_due" value="1" id="settingsReminderOnDue" <?= in_array(0, $reminder_rules['days'], true) ? 'checked' : '' ?> data-reminder-input>
                            <label class="form-check-label" for="settingsReminderOnDue">And on the due date</label>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="small text-body-secondary"><i class="fa fa-fw fa-bell me-1"></i>Reminders go <strong data-reminder-summary><?= escapeHtml(billingReminderScheduleText($reminder_rules)) ?></strong>. An invoice a recurring autopay charges on its due date isn't reminded before then.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="config_statement_monthly" value="1" id="settingsStatements" <?= $reminder_rules['statement_monthly'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="settingsStatements">Email a statement each month to every client with a balance</label>
                        </div>
                        <small class="text-secondary">Their open invoices, how late each is and what is owed, as a PDF too.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <select class="form-select" name="config_statement_day" aria-label="Day of the month">
                                <?php for ($day = 1; $day <= 28; $day++) { ?>
                                    <option value="<?= $day ?>" <?= $reminder_rules['statement_day'] === $day ? 'selected' : '' ?>>On the <?= $day . ([1 => 'st', 2 => 'nd', 3 => 'rd', 21 => 'st', 22 => 'nd', 23 => 'rd'][$day] ?? 'th') ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_billing_email_attach_pdf" value="1" id="settingsAttachPdf" <?= $reminder_rules['attach_pdf'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="settingsAttachPdf">Attach the PDF to invoice, quote, reminder and statement emails</label>
                    </div>
                    <small class="text-secondary">The default in each Send window, and what reminders, statements and recurring invoices do.</small>
                </div>

                <script>
                (function () {
                    // The schedule in words as it is typed - the same as billingReminderScheduleText()
                    var form = document.currentScript.closest('form');
                    var summary = form.querySelector('[data-reminder-summary]');
                    function days(name) {
                        var seen = {};
                        return String(form.querySelector('[name="' + name + '"]').value).split(/[\s,;]+/).map(Number).filter(function (n) {
                            var ok = n > 0 && n <= 365 && Math.floor(n) === n && !seen[n];
                            seen[n] = true;
                            return ok;
                        }).sort(function (a, b) { return a - b; });
                    }
                    function list(numbers) {
                        return numbers.length > 1 ? numbers.slice(0, -1).join(', ') + ' and ' + numbers[numbers.length - 1] : String(numbers[0]);
                    }
                    function update() {
                        var on = form.querySelector('[name="config_send_invoice_reminders"]').checked;
                        form.querySelector('[data-reminder-fields]').classList.toggle('opacity-50', !on);
                        var before = days('reminder_before').filter(function (n) { return n <= 60; });
                        var after = days('reminder_after');
                        var onDue = form.querySelector('[name="reminder_on_due"]').checked;
                        var repeat = Math.max(0, Math.min(365, parseInt(form.querySelector('[name="reminder_repeat"]').value, 10) || 0));
                        var parts = [];
                        if (before.length) {
                            parts.push(list(before) + ' day' + (before.length === 1 && before[0] === 1 ? '' : 's') + ' before it is due');
                        }
                        if (onDue) {
                            parts.push('on the due date');
                        }
                        if (after.length) {
                            parts.push(list(after) + ' day' + (after.length === 1 && after[0] === 1 ? '' : 's') + ' late');
                        }
                        if (repeat) {
                            parts.push((parts.length ? 'then ' : '') + 'every ' + repeat + ' day' + (repeat === 1 ? '' : 's') + (after.length || onDue ? '' : ' after it is due') + ' until it is paid');
                        }
                        summary.textContent = on ? (parts.length ? parts.join(', ') : 'never') : 'never - reminders are off';
                    }
                    form.addEventListener('input', update);
                    form.addEventListener('change', update);
                    update();
                })();
                </script>

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

