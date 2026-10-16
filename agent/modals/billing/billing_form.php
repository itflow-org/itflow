<?php

/*
 * The details of an invoice, quote or recurring invoice - shared by the New
 * and Edit windows for all three. Set before including:
 *
 *   $form_type       invoice | quote | recurring
 *   $form_row        billingRow() when editing, null for a new one
 *   $form_client_id  the client a new one is for (0 = pick it here)
 *
 * Posts the fields the existing handlers read: client_id, scope, category,
 * and then by kind - invoice: date, due (edit), invoice_discount; quote:
 * date, expire, quote_discount; recurring: frequency, start_date (new) or
 * next_date (edit), recurring_invoice_discount, auto_send, status (edit).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_t = billingType($form_type);
$form_c = $form_t['col'];
$form_edit = !empty($form_row);
$form_id = 'billingForm' . ucfirst($form_type) . ($form_edit ? intval($form_row["{$form_c}_id"]) : 'New');
$form_value = function ($column, $default = '') use ($form_row, $form_c) {
    return $form_row ? (string) ($form_row["{$form_c}_$column"] ?? $default) : $default;
};
$form_currency = $form_edit ? (string) $form_row["{$form_c}_currency_code"] : (string) $session_company_currency;
$form_category_id = intval($form_value('category_id', '0'));
$form_categories = billingCategories($form_edit ? $form_value('created_at') : null);
$form_discount = floatval($form_value('discount_amount', '0'));

// Net terms for a new invoice's due date - the client's (0 is On Receipt), as
// the invoice is saved with; the default until a client is picked
$form_terms = intval($config_default_net_terms);
if ($form_client_id) {
    $form_terms = intval(getFieldById('clients', $form_client_id, 'client_net_terms'));
}

$form_clients = [];
if (!$form_edit && !$form_client_id) {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, client_net_terms FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $form_clients[] = $row;
    }
}

$form_scope_placeholder = [
    'invoice' => 'Managed services - October, onsite visit 12 March, new laptop for Sam',
    'quote' => 'Firewall replacement, Microsoft 365 migration, new starter kit',
    'recurring' => 'Monthly managed services, backup and hosting',
][$form_type];

?>
<div class="row g-3" id="<?= $form_id ?>" data-billing-form="<?= $form_type ?>" data-terms="<?= $form_terms ?>">

    <?php if (!$form_edit && !$form_client_id) { ?>
        <div class="col-12">
            <label class="form-label">Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                <select class="form-select select2" name="client_id" required data-billing-client>
                    <option value="">- Select a client -</option>
                    <?php foreach ($form_clients as $form_client) { ?>
                        <option value="<?= intval($form_client['client_id']) ?>" data-terms="<?= intval($form_client['client_net_terms']) ?>"<?= selectOptionClient($form_client['client_id'], $form_client['client_name'], $form_client['client_abbreviation'] ?? '') ?>><?= escapeHtml($form_client['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    <?php } elseif (!$form_edit) { ?>
        <input type="hidden" name="client_id" value="<?= intval($form_client_id) ?>">
    <?php } ?>

    <div class="col-12">
        <label class="form-label">Scope</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
            <input type="text" class="form-control" name="scope" maxlength="255" placeholder="<?= escapeHtml($form_scope_placeholder) ?>" value="<?= escapeHtml($form_value('scope')) ?>" <?= $form_edit ? '' : 'autofocus' ?>>
        </div>
        <div class="form-text">
            What it is for, in a few words - shown on it and in lists.
            <?php if ($form_type === 'recurring') { ?>
                Put <code>{month}</code>, <code>{quarter}</code>, <code>{year}</code> or <code>{period}</code> in it - here, in the notes or on a line - and each invoice says the period it covers: "Managed services - {month}" reads "Managed services - November 2026".
            <?php } ?>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <select class="form-select select2" name="category" required>
                <option value="">- Category -</option>
                <?php foreach ($form_categories as $category_id => $category) { ?>
                    <option value="<?= intval($category_id) ?>" <?= $form_category_id === intval($category_id) ? 'selected' : '' ?><?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-secondary ajax-modal" title="New category" type="button" data-modal-url="../admin/modals/category/category_add.php?category=Income">
                <i class="fas fa-fw fa-plus"></i>
            </button>
        </div>
    </div>

    <?php if ($form_type === 'invoice') { ?>
        <div class="col-sm-6">
            <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_value('date', date('Y-m-d'))) ?>" required data-billing-date>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label">Due <?= $form_edit ? '<strong class="text-danger">*</strong>' : '' ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                <?php if ($form_edit) { ?>
                    <input type="date" class="form-control" name="due" max="2999-12-31" value="<?= escapeHtml($form_value('due')) ?>" required data-billing-due>
                <?php } else { ?>
                    <input type="date" class="form-control" value="<?= date('Y-m-d', strtotime("+$form_terms days")) ?>" disabled data-billing-due>
                <?php } ?>
            </div>
            <?php if (!$form_edit) { ?>
                <div class="form-text" data-billing-terms-note><?= $form_terms ? "$form_terms days from the invoice date" : 'Due on receipt' ?> - <?= $form_client_id ? "the client's" : 'the default' ?> terms.</div>
            <?php } ?>
        </div>
    <?php } elseif ($form_type === 'quote') { ?>
        <div class="col-sm-6">
            <label class="form-label">Quote date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_value('date', date('Y-m-d'))) ?>" required data-billing-date>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label">Valid until <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= escapeHtml($form_value('expire', date('Y-m-d', strtotime('+30 days')))) ?>" required data-billing-expire>
            </div>
            <div class="form-text">
                <?php foreach ([15, 30, 60, 90] as $days) { ?>
                    <a href="#" class="me-2" data-billing-expire-days="<?= $days ?>"><?= $days ?> days</a>
                <?php } ?>
            </div>
        </div>
    <?php } else { ?>
        <div class="col-sm-6">
            <label class="form-label">Billed <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
                <select class="form-select" name="frequency" required>
                    <?php foreach (recurringInvoiceFrequencies() as $frequency => $label) { ?>
                        <option value="<?= $frequency ?>" <?= $form_value('frequency', 'month') === $frequency ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label"><?= $form_edit ? 'Next invoice' : 'First invoice' ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" name="<?= $form_edit ? 'next_date' : 'start_date' ?>" max="2999-12-31"
                    value="<?= escapeHtml($form_edit ? $form_value('next_date') : date('Y-m-01', strtotime('first day of next month'))) ?>" required>
            </div>
            <div class="form-text">Made on this date, then every period after.</div>
        </div>
    <?php } ?>

    <div class="col-sm-6">
        <label class="form-label">Discount</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
            <input type="text" class="form-control text-end" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}"
                name="<?= ['invoice' => 'invoice_discount', 'quote' => 'quote_discount', 'recurring' => 'recurring_invoice_discount'][$form_type] ?>"
                placeholder="0.00" value="<?= $form_discount != 0 ? number_format($form_discount, 2, '.', '') : '' ?>">
            <span class="input-group-text"><?= escapeHtml($form_currency) ?></span>
        </div>
        <div class="form-text">Taken off the total, after the lines.</div>
    </div>

    <?php if ($form_type === 'recurring') { ?>
        <div class="col-sm-6">
            <label class="form-label">When it is made</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                <select class="form-select" name="auto_send">
                    <option value="1" <?= $form_value('auto_send', '1') === '1' ? 'selected' : '' ?>>Send it automatically</option>
                    <option value="0" <?= $form_value('auto_send', '1') === '0' ? 'selected' : '' ?>>Leave a draft to check</option>
                </select>
            </div>
            <?php if (empty($config_recurring_auto_send_invoice)) { ?>
                <div class="form-text text-warning-emphasis">Sending recurring invoices is off in the billing settings - they are made as drafts.</div>
            <?php } ?>
        </div>
        <?php if ($form_edit) { ?>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="status" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="status" value="1" id="<?= $form_id ?>Active" <?= intval($form_value('status', '1')) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= $form_id ?>Active">Active - invoices are made on schedule</label>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>

<script>
(function () {
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.hasAttribute('data-wired')) {
        return;
    }
    root.setAttribute('data-wired', '1');

    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function plus(value, days) {
        var parts = String(value || '').split('-');
        var d = parts.length === 3 ? new Date(+parts[0], +parts[1] - 1, +parts[2]) : new Date();
        d.setDate(d.getDate() + days);
        return ymd(d);
    }

    var date = root.querySelector('[data-billing-date]');

    // A new invoice: the due date follows the date and the client's terms
    var due = root.querySelector('[data-billing-due][disabled]');
    var client = root.querySelector('[data-billing-client]');
    var note = root.querySelector('[data-billing-terms-note]');
    function terms() {
        var option = client && client.options[client.selectedIndex];
        return option && option.getAttribute('data-terms') ? Number(option.getAttribute('data-terms')) : Number(root.getAttribute('data-terms') || 0);
    }
    function follow() {
        if (due && date) {
            due.value = plus(date.value, terms());
            if (note) {
                note.textContent = (terms() ? terms() + ' days from the invoice date' : 'Due on receipt') + ' - ' + (client && client.value ? "the client's" : 'the default') + ' terms.';
            }
        }
    }
    if (due) {
        date.addEventListener('change', follow);
        if (client) {
            client.addEventListener('change', follow);
        }
    }

    // A quote: valid for so many days from its date
    root.querySelectorAll('[data-billing-expire-days]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var expire = root.querySelector('[data-billing-expire]');
            expire.value = plus(date ? date.value : '', Number(link.getAttribute('data-billing-expire-days')));
        });
    });
})();
</script>
