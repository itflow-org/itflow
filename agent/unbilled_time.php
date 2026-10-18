<?php

/*
 * Unbilled Time - billable tickets that are done (resolved or closed) and on
 * no invoice yet: the time to bill. And, for agents who can see expenses,
 * what we paid for clients to bill on to them (billable expenses on no
 * invoice yet), at cost with its markup, and the trips out to them to bill
 * (billable trips on no invoice yet), at the trip charge and rate.
 *
 * Every client: one row each with what is waiting - tickets, hours, what it
 * comes to at their rate, the expenses, how long the oldest has waited.
 * Tick clients and invoice them all at once, one draft each (or onto each
 * one's newest draft).
 *
 * One client (?client_id=): every ticket, with its hours to bill filled in
 * from the time logged and the rounding in Admin > Settings > Invoice, ready
 * to change before it goes on, and every expense with its price. Tick the
 * ones to bill and make an invoice, or add them to one of the client's
 * drafts - or mark them not billable.
 *
 * functions/billing.php (Billing time) counts and bills the time,
 * functions/expenses.php (Billing expenses on) the expenses and
 * functions/trips.php (Mileage money) the trips; the handlers are in
 * agent/post/billing.php.
 */

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_view = true;
} else {
    require_once "includes/inc_all.php";
    $client_view = false;
    $client_id = 0;
}

enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
$with_time = intval($config_module_enable_ticketing) === 1;
$with_expenses = lookupUserPermission('module_financial') >= 1;
$ready = intval($config_module_enable_accounting) === 1 && ($with_time || $with_expenses);
$can_bill_expenses = $can_edit && lookupUserPermission('module_financial') >= 2;
$rules = billingTimeRules();
$clients = $ready && $with_time ? billingUnbilledByClient($client_view ? $client_id : 0) : [];
$clients = $ready && $with_expenses ? tripUnbilledMerge(expenseUnbilledMerge($clients, $client_view ? $client_id : 0), $client_view ? $client_id : 0) : $clients;
$currency = (string) $session_company_currency;
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$total = ['tickets' => 0, 'seconds' => 0, 'hours' => 0.0, 'value' => 0.0, 'oldest' => 0, 'no_time' => 0, 'no_rate' => 0,
    'expenses' => 0, 'expense_value' => 0.0, 'expense_cost' => 0.0, 'trips' => 0, 'trip_value' => 0.0];
foreach ($clients as $c) {
    $total['tickets'] += count($c['tickets']);
    $total['seconds'] += $c['seconds'];
    $total['hours'] += $c['hours'];
    $total['value'] += $c['value'];
    $total['oldest'] = max($total['oldest'], $c['oldest']);
    $total['no_time'] += $c['no_time'];
    $total['no_rate'] += $c['tickets'] && $c['rate'] <= 0 ? 1 : 0;
    $total['expenses'] += count($c['expenses'] ?? []);
    $total['expense_value'] += $c['expense_value'] ?? 0;
    $total['expense_cost'] += $c['expense_cost'] ?? 0;
    $total['trips'] += count($c['trips'] ?? []);
    $total['trip_value'] += $c['trip_value'] ?? 0;
}
// Expenses and trips together, where they share a column
$total['extras'] = $total['expenses'] + $total['trips'];
$total['extra_value'] = $total['expense_value'] + $total['trip_value'];
$extras_text = function ($expenses, $trips) {
    $parts = [];
    if ($expenses) {
        $parts[] = $expenses . ' expense' . ($expenses === 1 ? '' : 's');
    }
    if ($trips) {
        $parts[] = $trips . ' trip' . ($trips === 1 ? '' : 's');
    }
    return implode(', ', $parts);
};
$this_client = $client_view ? ($clients[$client_id] ?? null) : null;
$show_time = $with_time && (!$client_view || ($this_client && $this_client['tickets']));
$markup_text = function ($markup) {
    return rtrim(rtrim(number_format(floatval($markup), 2, '.', ''), '0'), '.') . '%';
};

$taxes = billingTaxes();
$scope_options = ['Support - ' . date('F Y'), 'Support - ' . date('F Y', strtotime('first day of last month'))];

$waiting_pill = function ($days) {
    $tone = $days > 30 ? 'danger' : ($days > 7 ? 'warning' : 'secondary');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal">'
        . ($days <= 0 ? 'Today' : $days . ' day' . ($days === 1 ? '' : 's')) . '</span>';
};
$hours_text = function ($hours) {
    return billingQuantity($hours) . ' h';
};

?>

<!-- What is waiting to be billed -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-file-invoice-dollar me-1"></i>To bill</div>
            <div class="itflow-billing-stat-value <?= $total['value'] + $total['extra_value'] > 0 ? 'text-info-emphasis' : '' ?>"><?= $money($total['value'] + $total['extra_value']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($total['extras']) { ?>
                    <?= $money($total['value']) ?> of time<?= $total['expenses'] ? ' &middot; ' . $money($total['expense_value']) . ' of expenses' : '' ?><?= $total['trips'] ? ' &middot; ' . $money($total['trip_value']) . ' of travel' : '' ?>
                <?php } else { ?>
                    <?= $client_view ? 'At ' . ($this_client && $this_client['rate'] > 0 ? $money($this_client['rate']) . ' an hour' . ($this_client['default_rate'] ? ', the default rate' : '') : 'no rate set') : 'At each client\'s hourly rate' ?>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-stopwatch me-1"></i>Hours</div>
            <div class="itflow-billing-stat-value"><?= $hours_text($total['hours']) ?></div>
            <div class="itflow-billing-stat-note"><?= billingDuration($total['seconds']) ?> logged, rounded <?= escapeHtml(billingTimeRulesText($rules)) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-life-ring me-1"></i>Tickets<?= $total['extras'] ? ' &amp; ' . ($total['expenses'] && $total['trips'] ? 'more' : ($total['expenses'] ? 'expenses' : 'trips')) : '' ?></div>
            <div class="itflow-billing-stat-value"><?= $total['tickets'] ?><?= $total['extras'] ? ' <span class="text-body-secondary fw-normal">+</span> ' . $total['extras'] : '' ?></div>
            <div class="itflow-billing-stat-note">
                <?= $client_view ? ($total['extras'] ? $total['tickets'] . ' ticket' . ($total['tickets'] === 1 ? '' : 's') . ', ' . $extras_text($total['expenses'], $total['trips']) : 'Resolved or closed, not invoiced') : count($clients) . ' client' . (count($clients) === 1 ? '' : 's') ?>
                <?= $total['no_time'] ? ' &middot; <span class="text-warning-emphasis">' . $total['no_time'] . ' with no time logged</span>' : '' ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i>Waiting longest</div>
            <div class="itflow-billing-stat-value <?= $total['oldest'] > 30 ? 'text-danger-emphasis' : '' ?>"><?= $total['tickets'] || $total['extras'] ? $total['oldest'] . ' day' . ($total['oldest'] === 1 ? '' : 's') : '-' ?></div>
            <div class="itflow-billing-stat-note"><?= $total['extras'] ? 'Since the ticket was done' . ($total['expenses'] ? ', the expense paid' : '') . ($total['trips'] ? ', the trip driven' : '') : 'Since the ticket was done' ?></div>
        </div>
    </div>
</div>

<?php if (!$ready) { ?>
    <div class="card">
        <div class="itflow-asset-empty py-5">
            <i class="fas fa-user-clock fa-2x mb-2 opacity-50"></i>
            <div>Unbilled Time needs accounting turned on<?= $with_expenses ? '' : ', and ticketing' ?>, in Admin &gt; Settings &gt; Modules.</div>
        </div>
    </div>
<?php } elseif (!$clients) { ?>
    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-user-clock me-2"></i>Unbilled Time</h3>
        </div>
        <div class="itflow-asset-empty py-5">
            <i class="fas fa-check-circle fa-2x mb-2 opacity-50"></i>
            <div>Nothing waiting to be billed<?= $client_view ? ' for ' . escapeHtml($client_name) : '' ?> - billable tickets that are done<?= $with_expenses ? ', and billable expenses and trips,' : '' ?> are all on an invoice.</div>
            <div class="small mt-1">A ticket shows here once it is billable and resolved or closed<?= $with_expenses ? ', an expense or a trip once it is marked billable to its client' : '' ?>.</div>
        </div>
    </div>
<?php } else { ?>

<form action="post.php" method="post" autocomplete="off" data-unbilled="<?= $client_view ? 'client' : 'clients' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_view) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="row g-3">
        <div class="col-xl-8">
            <?php if ($client_view && !empty($this_client['expenses'])) { ?>
                <!-- What we paid for them, to bill on -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i>Expenses to bill <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($this_client['expenses']) ?></span></h3>
                        <a class="btn btn-sm btn-outline-secondary" href="expenses.php?client=<?= $client_id ?>&amp;show=to_bill" title="In the expenses list"><i class="fas fa-shopping-cart me-1"></i>Expenses</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                            <thead class="text-nowrap table-light">
                            <tr>
                                <?php if ($can_bill_expenses) { ?>
                                    <th class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked data-unbilled-expense-all title="Tick or untick them all" aria-label="All expenses">
                                        </div>
                                    </th>
                                <?php } ?>
                                <th>Expense</th>
                                <th class="d-none d-md-table-cell">Paid</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Bill</th>
                                <th class="text-end d-none d-sm-table-cell">Margin</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this_client['expenses'] as $expense_id => $expense) {
                                [$line_name] = expenseBillLine($expense); ?>
                                <tr data-unbilled-expense-row data-cost="<?= $expense['cost'] ?>">
                                    <?php if ($can_bill_expenses) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>" checked data-unbilled-expense-pick aria-label="Bill <?= escapeHtml(expenseLabel($expense)) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0">
                                        <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= $expense_id ?>"><?= escapeHtml($line_name) ?></a>
                                        <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$expense['vendor_name'], $expense['category_name']]))) ?></div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml($expense['expense_date']) ?></div>
                                        <?= $waiting_pill($expense['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= $money($expense['cost']) ?>
                                        <div class="small text-body-secondary">+ <?= $markup_text($expense['expense_markup']) ?></div>
                                    </td>
                                    <td class="text-end itflow-unbilled-price">
                                        <?php if ($can_bill_expenses) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="expense_price[<?= $expense_id ?>]" value="<?= number_format($expense['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="Price to bill" data-unbilled-expense-price>
                                        <?php } else { ?>
                                            <?= $money($expense['price']) ?>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap small text-body-secondary d-none d-sm-table-cell" data-unbilled-expense-margin><?= escapeHtml(expenseMarginText($expense['price'], $expense['cost'])) ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-body-secondary">
                        At what it cost (less any refund) with its markup<?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#billable-expenses">the default markup</a>' : '' ?>. Change a price before billing; each goes on as a line of its own.
                    </div>
                </div>
            <?php } ?>

            <?php if ($client_view && !empty($this_client['trips'])) { ?>
                <!-- The driving out to them, to bill -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-route text-secondary me-2"></i>Travel to bill <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($this_client['trips']) ?></span></h3>
                        <a class="btn btn-sm btn-outline-secondary" href="trips.php?client_id=<?= $client_id ?>&amp;show=to_bill" title="In the trips list"><i class="fas fa-route me-1"></i>Trips</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                            <thead class="text-nowrap table-light">
                            <tr>
                                <?php if ($can_bill_expenses) { ?>
                                    <th class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked data-unbilled-trip-all title="Tick or untick them all" aria-label="All trips">
                                        </div>
                                    </th>
                                <?php } ?>
                                <th>Trip</th>
                                <th class="d-none d-md-table-cell">Driven</th>
                                <th class="text-end"><?= ucfirst(tripUnit()) ?></th>
                                <th class="text-end">Bill</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this_client['trips'] as $trip_id => $trip) { ?>
                                <tr data-unbilled-trip-row>
                                    <?php if ($can_bill_expenses) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="trip_ids[]" value="<?= $trip_id ?>" checked data-unbilled-trip-pick aria-label="Bill <?= escapeHtml(tripLabel($trip)) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0 itflow-trip-route">
                                        <a href="#" class="ajax-modal d-block text-truncate" data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=<?= $trip_id ?>"><?= escapeHtml($trip['trip_purpose']) ?></a>
                                        <div class="small text-body-secondary text-truncate">
                                            <?php if (intval($trip['trip_ticket_id']) && $trip['ticket_number'] !== null) { ?><span class="font-monospace me-1"><?= escapeHtml(ticketReference($trip)) ?></span><?php } ?>
                                            <?= escapeHtml($trip['location_name'] ?? $trip['trip_destination']) ?> &middot; <?= escapeHtml($trip['driver_name'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml($trip['trip_date']) ?></div>
                                        <?= $waiting_pill($trip['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format(floatval($trip['trip_miles']), 1) ?>
                                        <?php if (intval($trip['round_trip'])) { ?><i class="fas fa-sync-alt text-body-tertiary small ms-1" title="Round trip"></i><?php } ?>
                                    </td>
                                    <td class="text-end itflow-unbilled-price">
                                        <?php if ($can_bill_expenses) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="trip_price[<?= $trip_id ?>]" value="<?= number_format($trip['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="Price to bill" data-unbilled-trip-price>
                                        <?php } else { ?>
                                            <?= $money($trip['price']) ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-body-secondary">
                        <?= tripBillingOn() ? 'At ' . escapeHtml(tripBillRuleText()) : 'Trips have no charge or rate set, so they bill nothing' ?><?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#mileage">the trip rates</a>' : '' ?>. Change a price before billing; each goes on as a line of its own.
                    </div>
                </div>
            <?php } ?>

            <div class="card">
                <div class="card-header bg-dark py-2 d-flex align-items-center">
                    <h3 class="card-title mt-2 me-auto"><i class="fas fa-fw fa-user-clock me-2"></i>Unbilled Time<?= $client_view ? '' : ' by client' ?></h3>
                    <?php if ($client_view) { ?>
                        <a class="btn btn-dark btn-sm me-1" href="unbilled_time.php" title="Every client's unbilled time"><i class="fas fa-users me-1"></i>All clients</a>
                    <?php } ?>
                    <?php if ($with_time && reportCanOpen('tickets_unbilled')) { ?>
                        <a class="btn btn-dark btn-sm" href="reports/tickets_unbilled.php<?= $client_view ? "?client_id=$client_id" : '' ?>" title="The Unbilled Work report"><i class="fas fa-chart-bar me-1"></i>Report</a>
                    <?php } ?>
                </div>
                <?php if ($client_view && !$show_time) { ?>
                    <div class="card-body small text-body-secondary"><i class="fas fa-check-circle me-1"></i>No unbilled tickets for <?= escapeHtml($client_name) ?><?= $with_time ? ' - their billable tickets that are done are all on an invoice' : '' ?>.</div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                        <thead class="text-nowrap table-light">
                        <tr>
                            <?php if ($can_edit) { ?>
                                <th class="checkbox-column border-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked data-unbilled-all title="Tick or untick them all" aria-label="All">
                                    </div>
                                </th>
                            <?php } ?>
                            <?php if ($client_view) { ?>
                                <th>Ticket</th>
                                <th class="d-none d-md-table-cell">Done</th>
                                <th class="text-end">Logged</th>
                                <th class="text-end">Bill</th>
                                <th class="text-end d-none d-sm-table-cell">Amount</th>
                            <?php } else { ?>
                                <th>Client</th>
                                <?php if ($with_time) { ?>
                                    <th class="text-end">Tickets</th>
                                    <th class="text-end d-none d-lg-table-cell">Logged</th>
                                    <th class="text-end d-none d-sm-table-cell">Bill</th>
                                    <th class="text-end d-none d-lg-table-cell">Rate</th>
                                <?php } ?>
                                <?php if ($with_expenses) { ?>
                                    <th class="text-end <?= $with_time ? 'd-none d-md-table-cell' : '' ?>"><?= $total['trips'] ? ($total['expenses'] ? 'Expenses &amp; travel' : 'Travel') : 'Expenses' ?></th>
                                <?php } ?>
                                <th class="text-end">Amount</th>
                                <th class="d-none d-md-table-cell">Oldest</th>
                                <th class="d-none d-sm-table-cell"></th>
                            <?php } ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($client_view) {
                            foreach ($this_client['tickets'] as $ticket_id => $ticket) { ?>
                                <tr data-unbilled-row data-rate="<?= floatval($ticket['rate']) ?>">
                                    <?php if ($can_edit) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" checked data-unbilled-pick aria-label="Bill <?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0">
                                        <a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><span class="font-monospace small me-1 text-nowrap"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></a>
                                        <div class="small text-body-secondary">
                                            <?= $ticket['user_name'] ? escapeHtml($ticket['user_name']) : 'Unassigned' ?>
                                            <?php if (intval($ticket['ticket_onsite'])) { ?>
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><i class="fas fa-car-side me-1"></i>Onsite</span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml(date('Y-m-d', strtotime((string) ($ticket['ticket_resolved_at'] ?: $ticket['ticket_closed_at'])))) ?></div>
                                        <?= $waiting_pill($ticket['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap <?= $ticket['seconds'] ? '' : 'text-warning-emphasis' ?>" <?= $ticket['seconds'] ? '' : 'title="No time logged - type the hours to bill, or leave it at 0 to put it on the invoice with no charge"' ?>><?= $ticket['seconds'] ? billingDuration($ticket['seconds']) : 'None' ?></td>
                                    <td class="text-end itflow-unbilled-hours">
                                        <?php if ($can_edit) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="hours[<?= $ticket_id ?>]" value="<?= billingQuantity($ticket['hours']) ?>" inputmode="decimal" aria-label="Hours to bill" data-unbilled-hours>
                                        <?php } else { ?>
                                            <?= $hours_text($ticket['hours']) ?>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold d-none d-sm-table-cell" data-unbilled-amount><?= $money($ticket['value']) ?></td>
                                </tr>
                            <?php }
                        } else {
                            foreach ($clients as $id => $c) {
                                $draft = $c['drafts'] ? reset($c['drafts']) : null; ?>
                                <tr data-unbilled-row data-value="<?= round($c['value'] + ($c['expense_value'] ?? 0) + ($c['trip_value'] ?? 0), 2) ?>" data-hours="<?= round($c['hours'], 2) ?>" data-tickets="<?= count($c['tickets']) ?>" data-expenses="<?= count($c['expenses'] ?? []) ?>" data-trips="<?= count($c['trips'] ?? []) ?>">
                                    <?php if ($can_edit) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="client_ids[]" value="<?= $id ?>" checked data-unbilled-pick aria-label="Bill <?= escapeHtml($c['client_name']) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td>
                                        <a class="fw-semibold" href="unbilled_time.php?client_id=<?= $id ?>"><?= escapeHtml($c['client_name']) ?></a>
                                        <?php if ($c['drafts']) { ?>
                                            <div class="small text-body-secondary" title="Newest draft">
                                                <i class="fas fa-pencil-ruler me-1"></i><?= count($c['drafts']) ?> draft<?= count($c['drafts']) === 1 ? '' : 's' ?> &middot; <?= escapeHtml(billingNumber('invoice', $draft)) ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <?php if ($with_time) { ?>
                                        <td class="text-end">
                                            <?= count($c['tickets']) ?: '<span class="text-body-secondary">-</span>' ?>
                                            <?php if ($c['no_time']) { ?>
                                                <div class="small text-warning-emphasis text-nowrap" title="No time logged - they go on the invoice with no charge unless you open the client and type the hours"><?= $c['no_time'] ?> with no time</div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-end text-nowrap d-none d-lg-table-cell"><?= $c['tickets'] ? billingDuration($c['seconds']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $c['tickets'] ? $hours_text($c['hours']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="text-end text-nowrap d-none d-lg-table-cell">
                                            <?php if (!$c['tickets']) { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } elseif ($c['rate'] > 0) { ?>
                                                <?= $money($c['rate']) ?><?= $c['default_rate'] ? '<div class="small text-body-tertiary" title="The client has no rate of its own">default</div>' : '' ?>
                                            <?php } else { ?>
                                                <span class="small text-warning-emphasis" title="Set an hourly rate on the client, or a default in Admin > Settings > Defaults">No rate</span>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <?php if ($with_expenses) { ?>
                                        <td class="text-end text-nowrap <?= $with_time ? 'd-none d-md-table-cell' : '' ?>">
                                            <?php if ($c['expenses'] || !empty($c['trips'])) { ?>
                                                <?= $money($c['expense_value'] + ($c['trip_value'] ?? 0)) ?>
                                                <div class="small text-body-secondary"><?= $extras_text(count($c['expenses']), count($c['trips'] ?? [])) ?></div>
                                            <?php } else { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <td class="text-end text-nowrap fw-semibold"><?= $money($c['value'] + ($c['expense_value'] ?? 0) + ($c['trip_value'] ?? 0)) ?></td>
                                    <td class="text-nowrap d-none d-md-table-cell"><?= $waiting_pill($c['oldest']) ?></td>
                                    <td class="text-end d-none d-sm-table-cell">
                                        <a class="btn btn-sm btn-outline-secondary text-nowrap" href="unbilled_time.php?client_id=<?= $id ?>">Review</a>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($with_time) { ?>
                    <div class="card-footer small text-body-secondary">
                        Time is rounded <?= escapeHtml(billingTimeRulesText($rules)) ?>, per ticket<?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#billing-time">change how</a>' : '' ?>.
                        <?= $client_view ? 'Change the hours before billing - a ticket left at 0 goes on the invoice with no charge.' : 'Review a client to change the hours on its tickets' . ($with_expenses ? ' and the prices of its expenses and trips' : '') . ' first.' ?>
                    </div>
                <?php } ?>
                <?php } ?>
            </div>

        </div>

        <?php if ($can_edit) { ?>
            <!-- How they go on -->
            <div class="col-xl-4">
                <div class="card itflow-unbilled-panel">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Bill <?= $client_view ? 'them' : 'these clients' ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if ($client_view) { ?>
                                <div class="col-12">
                                    <label class="form-label">Invoice</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-file-invoice"></i></span>
                                        <select class="form-select" name="invoice_id" data-unbilled-target>
                                            <option value="0">A new draft invoice</option>
                                            <?php foreach ($this_client['drafts'] as $draft_id => $draft) { ?>
                                                <option value="<?= $draft_id ?>">Add to <?= escapeHtml(billingNumber('invoice', $draft)) ?><?= trim((string) $draft['invoice_scope']) !== '' ? ' - ' . escapeHtml($draft['invoice_scope']) : '' ?> (<?= $money($draft['invoice_amount']) ?>)</option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <div class="col-12">
                                    <label class="form-label">Invoices</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-file-invoice"></i></span>
                                        <select class="form-select" name="target" data-unbilled-target>
                                            <option value="new">A new draft for each client</option>
                                            <option value="draft">Each client's newest draft, or a new one</option>
                                        </select>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-sm-6 col-xl-12" data-unbilled-new>
                                <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-12" data-unbilled-new>
                                <label class="form-label">Category <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    <select class="form-select" name="category" required>
                                        <option value="">- Category -</option>
                                        <?= categoryOptionsHtml('Income') ?>
                                    </select>
                                    <?= categoryAddButtonHtml('Income', 'btn btn-secondary') ?>
                                </div>
                            </div>
                            <div class="col-12" data-unbilled-new>
                                <label class="form-label">Scope</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                                    <input type="text" class="form-control" name="scope" maxlength="255" value="<?= escapeHtml($scope_options[0]) ?>" list="unbilledScopes">
                                    <datalist id="unbilledScopes">
                                        <?php foreach ($scope_options as $scope_option) { ?>
                                            <option value="<?= escapeHtml($scope_option) ?>"></option>
                                        <?php } ?>
                                    </datalist>
                                </div>
                            </div>
                            <?php if ($show_time) { ?>
                            <div class="col-12">
                                <label class="form-label">Line</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($rules['item_name']) ?>" required>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if ($client_view && $show_time) { ?>
                                <div class="col-sm-6 col-xl-12">
                                    <label class="form-label">Rate <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                        <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= number_format($this_client['rate'], 2, '.', '') ?>" required data-unbilled-rate>
                                    </div>
                                    <div class="form-text">An hour<?= $this_client['default_rate'] ? ' - the default, the client has no rate' : " - the client's rate" ?></div>
                                </div>
                            <?php } ?>
                            <div class="<?= $client_view ? 'col-sm-6 col-xl-12' : 'col-12' ?>">
                                <label class="form-label">Tax</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                                    <select class="form-select" name="tax_id" data-unbilled-tax>
                                        <?= taxOptionsHtml($taxes, TAX_USUAL, $client_view ? "Each line's usual rate" : "Each client's usual rates", $client_view ? floatval(taxRow(taxDefaultFor($client_id, 'time'))['tax_percent'] ?? 0) : 0) ?>
                                    </select>
                                </div>
                            </div>
                            <?php if ($show_time) { ?>
                            <div class="col-12">
                                <label class="form-label">Lines</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                    <select class="form-select" name="lines">
                                        <option value="each">A line for each ticket</option>
                                        <option value="one">One line for all of them</option>
                                    </select>
                                </div>
                                <?php if ($total['extras']) { ?>
                                    <div class="form-text"><?= ucfirst($total['expenses'] && $total['trips'] ? 'expenses and trips' : ($total['expenses'] ? 'expenses' : 'trips')) ?> go on with a line each, at their price<?= $client_view ? '' : ($total['expenses'] ? ' - an expense at cost with its markup' : '') ?>.</div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-end mb-3">
                            <div class="small text-body-secondary" data-unbilled-summary></div>
                            <div class="fs-4 fw-semibold itflow-money" data-unbilled-total></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="<?= $client_view ? 'unbilled_invoice' : 'unbilled_invoice_clients' ?>" value="1" class="btn btn-primary text-bold flex-grow-1" data-unbilled-submit>
                                <i class="fas fa-check me-2"></i><span data-unbilled-submit-label><?= $client_view ? 'Make Invoice' : 'Make Invoices' ?></span>
                            </button>
                            <?php if ($client_view) { ?>
                                <button type="submit" name="unbilled_write_off" value="1" class="btn btn-outline-danger confirm-link" formnovalidate
                                    data-confirm-title="Mark them not billable?" data-confirm-text="The ticked tickets<?= $total['extras'] ? ', ' . ($total['expenses'] && $total['trips'] ? 'expenses and trips' : ($total['expenses'] ? 'expenses' : 'trips')) : '' ?> stop being billable and leave this list. <?= $total['tickets'] ? "Their time stays logged on them, and each one's history says it." : 'They stay as our cost.' ?>" data-confirm-button="Not billable">
                                    <i class="fas fa-ban me-2"></i>Not billable
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</form>

<?php } ?>

<script src="/js/billing_items.js"></script>
<script src="/js/unbilled_time.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowUnbilledTime({
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
