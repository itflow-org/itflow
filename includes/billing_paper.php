<?php

/*
 * The document itself - an invoice, quote or recurring invoice as the
 * client sees it, on the agent pages and the guest pages alike, and as it
 * prints. Set $paper before including:
 *
 *   type       invoice | quote | recurring
 *   row        billingRow()
 *   items      billingItems()
 *   totals     billingTotals()
 *   paid       what has been paid (invoices)
 *   balance    what is still owed (invoices)
 *   edit       the lines can be changed here (agent pages): drag handles,
 *              a menu on each line and the add-a-line row
 *   notes_edit the notes save in place (agent pages)
 *   taxes      billingTaxes(), for the add-a-line row
 *   logo_url   the company logo, relative to the page
 *   client     shown to the client (the client portal) - no agent-only notes
 *   csrf       the agent's token, for the edit controls
 *
 * Everything in it is escaped here.
 */

isset($paper) || die("Direct file access is not allowed");

$paper_type = $paper['type'];
$paper_t = billingType($paper_type);
$paper_c = $paper_t['col'];
$paper_row = $paper['row'];
$paper_items = $paper['items'];
$paper_totals = $paper['totals'];
$paper_edit = !empty($paper['edit']);
$paper_currency = (string) ($paper_row["{$paper_c}_currency_code"] ?? '');
$paper_id = intval($paper_row["{$paper_c}_id"]);
$paper_company = billingCompany();
$paper_bill_to = billingBillTo($paper_row['client_id']);
$paper_status = $paper_type === 'recurring' ? (intval($paper_row['recurring_invoice_status']) ? 'Active' : 'Paused') : (string) $paper_row["{$paper_c}_status"];
$paper_money = function ($amount) use ($paper_currency) {
    return escapeHtml(billingMoney($amount, $paper_currency));
};
$paper_title = ['invoice' => 'Invoice', 'quote' => 'Quote', 'recurring' => 'Recurring Invoice'][$paper_type];
$paper_footer = $paper_type === 'quote' ? ($GLOBALS['config_quote_footer'] ?? '') : ($GLOBALS['config_invoice_footer'] ?? '');

// A stamp across a document that is done with
$paper_stamp = '';
if ($paper_type === 'invoice' && $paper_status === 'Paid') {
    $paper_stamp = ['Paid', 'success'];
} elseif ($paper_type === 'invoice' && $paper_status === 'Cancelled') {
    $paper_stamp = ['Cancelled', 'danger'];
} elseif ($paper_type === 'quote' && in_array($paper_status, ['Accepted', 'Invoiced'], true)) {
    $paper_stamp = ['Accepted', 'success'];
} elseif ($paper_type === 'quote' && $paper_status === 'Declined') {
    $paper_stamp = ['Declined', 'danger'];
}

$paper_dates = [];
if ($paper_type === 'invoice') {
    $paper_dates = ['Invoice date' => $paper_row['invoice_date'], 'Due' => $paper_row['invoice_due']];
} elseif ($paper_type === 'quote') {
    $paper_dates = ['Quote date' => $paper_row['quote_date'], 'Valid until' => $paper_row['quote_expire']];
} else {
    $paper_dates = ['Billed' => recurringInvoiceFrequencies()[$paper_row['recurring_invoice_frequency']] ?? ucfirst((string) $paper_row['recurring_invoice_frequency']),
        'Next invoice' => $paper_row['recurring_invoice_next_date']];
}

// A recurring invoice's seat lines - what each counts now, and whether the line is off
$paper_seats = $paper_type === 'recurring' ? recurringSeatLines($paper_id, intval($paper_row['client_id'])) : [];
$paper_seat_sources = recurringSeatSources();

// A quote with lines billed each month, quarter or year shows them apart from
// what is billed once - the lines under a heading each, and a total each
$paper_groups = $paper_type === 'quote' && !empty($paper_totals['recurring']) ? $paper_totals['groups'] : null;
$paper_line_groups = $paper_groups ?: ['' => ['frequency' => '', 'label' => '', 'items' => $paper_items]];

$paper_post = function ($params) use ($paper_type, $paper_id, $paper) {
    return 'post.php?' . http_build_query(array_merge($params, ['type' => $paper_type, 'doc_id' => $paper_id, 'csrf_token' => $paper['csrf'] ?? '']));
};
$paper_company_lines = array_filter([
    formatAddress($paper_company['company_address'] ?? '', $paper_company['company_city'] ?? '', $paper_company['company_state'] ?? '', $paper_company['company_zip'] ?? '', $paper_company['company_country'] ?? '', "\n"),
    implode(' · ', array_filter([$paper_company['company_email'] ?? '', $paper_company['phone_display'] ?? ''])),
    $paper_company['company_website'] ?? '',
    !empty($GLOBALS['config_invoice_show_tax_id']) && !empty($paper_company['company_tax_id']) ? 'Tax ID: ' . $paper_company['company_tax_id'] : '',
]);
$paper_bill_lines = array_filter([
    formatAddress($paper_bill_to['location_address'] ?? '', $paper_bill_to['location_city'] ?? '', $paper_bill_to['location_state'] ?? '', $paper_bill_to['location_zip'] ?? '', $paper_bill_to['location_country'] ?? '', "\n"),
    $paper_bill_to['contact_name'] ?? '',
    implode(' · ', array_filter([$paper_bill_to['contact_email'] ?? '', $paper_bill_to['contact_phone'] ?? ''])),
    !empty($paper_bill_to['client_tax_id_number']) ? 'Tax ID: ' . $paper_bill_to['client_tax_id_number'] : '',
]);

?>
<div class="itflow-paper" id="items" data-billing-type="<?= $paper_type ?>" data-billing-id="<?= $paper_id ?>">

    <?php if ($paper_stamp) { ?>
        <div class="itflow-paper-stamp text-<?= $paper_stamp[1] ?>" aria-hidden="true"><?= $paper_stamp[0] ?></div>
    <?php } ?>

    <!-- Who it is from, and what it is -->
    <div class="itflow-paper-head">
        <div class="itflow-paper-from">
            <?php if (!empty($paper['logo_url']) && !empty($paper_company['logo_url'])) { ?>
                <img class="itflow-paper-logo" src="<?= escapeHtml($paper['logo_url']) ?>" alt="<?= escapeHtml($paper_company['company_name'] ?? '') ?>">
            <?php } ?>
            <div class="fw-semibold fs-5"><?= escapeHtml($paper_company['company_name'] ?? '') ?></div>
            <div class="small text-body-secondary"><?= nl2br(escapeHtml(implode("\n", $paper_company_lines))) ?></div>
        </div>
        <div class="itflow-paper-what text-md-end">
            <div class="itflow-paper-title"><?= $paper_title ?></div>
            <div class="fs-5 fw-semibold font-monospace"><?= escapeHtml(billingNumber($paper_type, $paper_row)) ?></div>
            <dl class="itflow-paper-dates">
                <?php foreach ($paper_dates as $label => $value) { ?>
                    <dt><?= $label ?></dt><dd><?= escapeHtml($value) ?></dd>
                <?php } ?>
            </dl>
        </div>
    </div>

    <!-- Who it is for, and what it comes to -->
    <div class="itflow-paper-parties">
        <div>
            <div class="itflow-paper-label"><?= $paper_type === 'quote' ? 'Prepared for' : 'Bill to' ?></div>
            <div class="fw-semibold"><?= escapeHtml($paper_bill_to['client_name'] ?? $paper_row['client_name']) ?></div>
            <div class="small text-body-secondary"><?= nl2br(escapeHtml(implode("\n", $paper_bill_lines))) ?></div>
        </div>
        <div class="itflow-paper-due text-md-end">
            <?php if ($paper_type === 'invoice') { ?>
                <div class="itflow-paper-label"><?= in_array($paper_status, ['Paid', 'Cancelled', 'Non-Billable'], true) ? 'Balance' : 'Amount due' ?></div>
                <div class="itflow-paper-amount"><?= $paper_money($paper['balance'] ?? $paper_totals['total']) ?></div>
                <?php if (!in_array($paper_status, ['Paid', 'Cancelled', 'Non-Billable', 'Draft'], true) && ($paper['balance'] ?? 0) > 0) { ?>
                    <div class="small text-body-secondary">by <?= escapeHtml($paper_row['invoice_due']) ?></div>
                <?php } ?>
            <?php } elseif ($paper_type === 'quote' && $paper_groups) {
                $paper_first = true;
                foreach ($paper_groups as $paper_frequency => $paper_group) {
                    if ($paper_frequency === '' && !$paper_group['items'] && $paper_group['discount'] == 0) {
                        continue;
                    } ?>
                    <div class="itflow-paper-label <?= $paper_first ? '' : 'mt-2' ?>"><?= $paper_frequency === '' ? 'Up front' : escapeHtml($paper_group['label']) ?></div>
                    <div class="<?= $paper_first ? 'itflow-paper-amount' : 'fs-5 fw-semibold' ?>"><?= $paper_money($paper_group['total']) ?></div>
                <?php $paper_first = false;
                }
            } elseif ($paper_type === 'quote') { ?>
                <div class="itflow-paper-label">Quote total</div>
                <div class="itflow-paper-amount"><?= $paper_money($paper_totals['total']) ?></div>
            <?php } else { ?>
                <div class="itflow-paper-label">Each <?= recurringInvoicePeriodWord($paper_row['recurring_invoice_frequency']) ?></div>
                <div class="itflow-paper-amount"><?= $paper_money($paper_totals['total']) ?></div>
            <?php } ?>
        </div>
    </div>

    <?php if (trim((string) $paper_row["{$paper_c}_scope"]) !== '') { ?>
        <div class="itflow-paper-scope"><span class="itflow-paper-label me-2">For</span><?= escapeHtml($paper_row["{$paper_c}_scope"]) ?></div>
    <?php } ?>

    <!-- The lines -->
    <?php if ($paper_edit) { ?>
        <form id="billingAddLine" action="post.php" method="post" autocomplete="off" class="d-none">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($paper['csrf'] ?? '') ?>">
            <input type="hidden" name="billing_item_save" value="1">
            <input type="hidden" name="type" value="<?= $paper_type ?>">
            <input type="hidden" name="doc_id" value="<?= $paper_id ?>">
            <input type="hidden" name="product_id" value="0" data-line-product>
        </form>
    <?php } ?>
    <div class="table-responsive">
        <table class="table itflow-paper-items align-top mb-0" data-billing-items>
            <thead>
            <tr>
                <?php if ($paper_edit) { ?><th class="itflow-paper-handle d-print-none"></th><?php } ?>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Price</th>
                <th class="text-end">Tax</th>
                <th class="text-end">Amount</th>
                <?php if ($paper_edit) { ?><th class="d-print-none"></th><?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($paper_line_groups as $paper_frequency => $paper_line_group) {
                if ($paper_groups) { ?>
                    <tr class="itflow-paper-group" data-group="<?= $paper_frequency ?>">
                        <td colspan="7"><span class="itflow-paper-label"><?= escapeHtml($paper_line_group['label']) ?></span></td>
                    </tr>
                <?php }
                foreach ($paper_line_group['items'] as $item) {
                    $item_id = intval($item['item_id']);
                    $item_tax_label = floatval($item['item_tax']) != 0 || intval($item['item_tax_id'])
                        ? trim(($item['tax_name'] ?? '') . ' ' . ($item['tax_percent'] !== null ? billingQuantity($item['tax_percent']) . '%' : ''))
                        : ''; ?>
                    <tr id="line-<?= $item_id ?>" data-item-id="<?= $item_id ?>" data-group="<?= $paper_frequency ?>">
                        <?php if ($paper_edit) { ?>
                            <td class="itflow-paper-handle d-print-none"><span class="drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span></td>
                        <?php } ?>
                        <td class="itflow-paper-item">
                            <div class="fw-semibold"><?= escapeHtml($item['item_name']) ?></div>
                            <?php if (trim((string) $item['item_description']) !== '') { ?>
                                <div class="small text-body-secondary"><?= nl2br(escapeHtml($item['item_description'])) ?></div>
                            <?php } ?>
                            <?php if (isset($paper_seats[$item_id])) {
                                $seat = $paper_seats[$item_id];
                                $seat_source = $paper_seat_sources[$seat['item_seat_source']] ?? ['unit' => 'seat', 'icon' => 'fa-users']; ?>
                                <div class="small mt-1 d-print-none">
                                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal text-wrap text-start" title="The quantity is counted again for each invoice">
                                        <i class="fas <?= $seat_source['icon'] ?> me-1"></i>Per <?= $seat_source['unit'] ?> &middot; <?= escapeHtml($seat['seat_label']) ?><?= floatval($seat['item_seat_minimum']) > 0 ? ' &middot; at least ' . billingQuantity($seat['item_seat_minimum']) : '' ?>
                                    </span>
                                    <?php if ($seat['seat_count'] === null) { ?>
                                        <?php if (empty($paper['client'])) { ?><span class="badge rounded-pill text-bg-danger fw-normal">The license is gone - it stays at <?= billingQuantity($item['item_quantity']) ?></span><?php } ?>
                                    <?php } elseif ($seat['seat_drift']) { ?>
                                        <span class="badge rounded-pill text-bg-warning fw-normal" title="The next invoice bills the new count">Now <?= billingQuantity($seat['seat_quantity']) ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td class="text-end text-nowrap"><?= billingQuantity($item['item_quantity']) ?></td>
                        <td class="text-end text-nowrap"><?= $paper_money($item['item_price']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($item_tax_label !== '') { ?>
                                <?= $paper_money($item['item_tax']) ?>
                                <div class="small text-body-secondary"><?= escapeHtml($item_tax_label) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="text-end text-nowrap fw-semibold"><?= $paper_money($item['item_total']) ?></td>
                        <?php if ($paper_edit) { ?>
                            <td class="text-end d-print-none itflow-paper-actions">
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-link text-body-secondary" title="Line actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/billing/billing_item.php?type=<?= $paper_type ?>&doc_id=<?= $paper_id ?>&item_id=<?= $item_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="<?= escapeHtml($paper_post(['billing_item_delete' => $item_id])) ?>"
                                            data-confirm-text="<?= escapeHtml($item['item_name']) ?> comes off <?= escapeHtml(billingNumber($paper_type, $paper_row)) ?><?= $paper_type === 'invoice' && intval($item['item_product_id']) ? ' - anything taken from stock goes back' : '' ?>.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Remove
                                        </a>
                                    </div>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>
                <?php }
            } ?>
            <?php if (!$paper_items && !$paper_edit) { ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">Nothing on it yet.</td></tr>
            <?php } ?>
            </tbody>
            <?php if ($paper_edit) { ?>
                <tfoot class="d-print-none">
                <tr class="itflow-paper-add" id="add-line">
                    <td colspan="7">
                        <div class="itflow-paper-add-grid<?= in_array($paper_type, ['recurring', 'quote'], true) ? ' itflow-paper-add-grid-seats' : '' ?>" data-billing-add>
                            <div class="itflow-paper-add-item">
                                <input type="text" class="form-control form-control-sm mb-1" name="name" form="billingAddLine" placeholder="<?= $paper_items ? 'Add a line - type a product, or anything' : 'First line - type a product, or anything' ?>" maxlength="200" required data-line-name aria-label="Item">
                                <textarea class="form-control form-control-sm" name="description" form="billingAddLine" rows="1" placeholder="Description (optional)" data-line-description aria-label="Description"></textarea>
                            </div>
                            <div class="itflow-paper-add-qty">
                                <label class="form-label small text-body-secondary mb-0">Qty</label>
                                <input type="text" class="form-control form-control-sm text-end" name="qty" form="billingAddLine" inputmode="decimal" placeholder="1" value="1" data-line-qty>
                            </div>
                            <?php if ($paper_type === 'recurring') { ?>
                                <div class="itflow-paper-add-seat">
                                    <label class="form-label small text-body-secondary mb-0">Counted</label>
                                    <select class="form-select form-select-sm" name="seat_source" form="billingAddLine" data-add-seat title="Bill per user or per device - counted again for each invoice">
                                        <option value="">As typed</option>
                                        <option value="contacts" data-count="<?= intval(recurringSeatCount($paper_row['client_id'], 'contacts', [])) ?>">Per user</option>
                                        <option value="assets" data-count="<?= intval(recurringSeatCount($paper_row['client_id'], 'assets', [])) ?>">Per device</option>
                                    </select>
                                </div>
                            <?php } elseif ($paper_type === 'quote') { ?>
                                <div class="itflow-paper-add-seat">
                                    <label class="form-label small text-body-secondary mb-0">Billed</label>
                                    <select class="form-select form-select-sm" name="frequency" form="billingAddLine" title="Billed once, or each month, quarter or year - those go on a recurring invoice when the quote is invoiced">
                                        <?php foreach (quoteFrequencies() as $frequency_value => $frequency_label) { ?>
                                            <option value="<?= $frequency_value ?>"><?= $frequency_label ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                            <div class="itflow-paper-add-price">
                                <label class="form-label small text-body-secondary mb-0">Price</label>
                                <input type="text" class="form-control form-control-sm text-end" name="price" form="billingAddLine" inputmode="decimal" placeholder="0.00" data-line-price>
                            </div>
                            <div class="itflow-paper-add-tax">
                                <label class="form-label small text-body-secondary mb-0">Tax</label>
                                <select class="form-select form-select-sm" name="tax_id" form="billingAddLine" data-line-tax>
                                    <option value="0" data-percent="0">No tax</option>
                                    <?php foreach ($paper['taxes'] ?? [] as $tax_id => $tax) { ?>
                                        <option value="<?= intval($tax_id) ?>" data-percent="<?= floatval($tax['tax_percent']) ?>"><?= escapeHtml($tax['tax_name'] . ' ' . billingQuantity($tax['tax_percent']) . '%') ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="itflow-paper-add-go">
                                <span class="small text-body-secondary text-nowrap" data-line-preview></span>
                                <button type="submit" form="billingAddLine" class="btn btn-sm btn-primary text-nowrap" title="Add the line"><i class="fas fa-plus me-1"></i>Add</button>
                            </div>
                        </div>
                    </td>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>

    <!-- Notes, and what it all comes to -->
    <div class="itflow-paper-bottom">
        <div class="itflow-paper-notes">
            <?php if (!empty($paper['notes_edit'])) { ?>
                <div class="itflow-paper-label d-flex align-items-center">Notes <span class="ms-2 d-print-none fw-normal text-transform-none" data-note-status-for="billingNotes"></span></div>
                <textarea class="form-control form-control-sm itflow-inline-note d-print-none" rows="3" id="billingNotes"
                    placeholder="Payment details, terms, a thank you - shown on the <?= strtolower($paper_title) ?>"
                    data-endpoint="<?= $paper_c ?>_set_notes" data-id-field="<?= $paper_t['param'] ?>" data-id="<?= $paper_id ?>"
                    data-csrf="<?= escapeHtml($paper['csrf'] ?? '') ?>"><?= escapeHtml($paper_row["{$paper_c}_note"]) ?></textarea>
                <div class="d-none d-print-block small"><?= nl2br(escapeHtml($paper_row["{$paper_c}_note"])) ?></div>
            <?php } elseif (trim((string) $paper_row["{$paper_c}_note"]) !== '') { ?>
                <div class="itflow-paper-label">Notes</div>
                <div class="small"><?= nl2br(escapeHtml($paper_row["{$paper_c}_note"])) ?></div>
            <?php } ?>
        </div>
        <?php if ($paper_groups) { ?>
            <table class="itflow-paper-totals">
                <?php foreach ($paper_groups as $paper_frequency => $paper_group) {
                    if ($paper_frequency === '' && !$paper_group['items'] && $paper_group['discount'] == 0) {
                        continue;
                    } ?>
                    <tr class="itflow-paper-totals-head"><th colspan="2"><?= escapeHtml($paper_group['label']) ?></th></tr>
                    <tr><th>Subtotal</th><td><?= $paper_money($paper_group['subtotal']) ?></td></tr>
                    <?php if ($paper_group['discount'] != 0) { ?>
                        <tr><th>Discount</th><td>-<?= $paper_money($paper_group['discount']) ?></td></tr>
                    <?php } ?>
                    <?php foreach ($paper_group['taxes'] as $tax_label => $tax_amount) { ?>
                        <tr><th><?= escapeHtml($tax_label) ?></th><td><?= $paper_money($tax_amount) ?></td></tr>
                    <?php } ?>
                    <tr class="itflow-paper-total"><th><?= $paper_frequency === '' ? 'Total up front' : 'Total ' . strtolower(escapeHtml($paper_group['label'])) ?></th><td><?= $paper_money($paper_group['total']) ?></td></tr>
                <?php } ?>
            </table>
        <?php } else { ?>
        <table class="itflow-paper-totals">
            <tr><th>Subtotal</th><td><?= $paper_money($paper_totals['subtotal']) ?></td></tr>
            <?php if ($paper_totals['discount'] != 0) { ?>
                <tr><th>Discount</th><td>-<?= $paper_money($paper_totals['discount']) ?></td></tr>
            <?php } ?>
            <?php foreach ($paper_totals['taxes'] as $tax_label => $tax_amount) { ?>
                <tr><th><?= escapeHtml($tax_label) ?></th><td><?= $paper_money($tax_amount) ?></td></tr>
            <?php } ?>
            <?php if ($paper_totals['credit'] > 0) { ?>
                <tr><th>Credit</th><td>-<?= $paper_money($paper_totals['credit']) ?></td></tr>
            <?php } ?>
            <tr class="itflow-paper-total"><th>Total</th><td><?= $paper_money($paper_totals['total']) ?></td></tr>
            <?php if ($paper_type === 'invoice' && ($paper['paid'] ?? 0) > 0) { ?>
                <tr class="text-success"><th>Paid</th><td>-<?= $paper_money($paper['paid']) ?></td></tr>
                <tr class="itflow-paper-total"><th>Balance due</th><td><?= $paper_money($paper['balance']) ?></td></tr>
            <?php } ?>
        </table>
        <?php } ?>
    </div>

    <?php if (trim((string) $paper_footer) !== '') { ?>
        <div class="itflow-paper-footer"><?= nl2br(escapeHtml($paper_footer)) ?></div>
    <?php } ?>

</div>
