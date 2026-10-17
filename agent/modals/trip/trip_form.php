<?php

/*
 * The fields of a trip - shared by New Trip, Edit, Copy and Return Trip.
 * Set before including:
 *
 *   $form_mode         new | edit | copy | return
 *   $form_row          tripRow() for edit, copy and return; null for new
 *   $form_client_id    the client a new one starts with (0 for none)
 *   $form_location_id  the site a new one goes to (0 for none)
 *   $form_ticket_id    the ticket a new one is for (0 for none)
 *
 * Posts date, driver, client_id, ticket_id, location_id, source,
 * destination, miles (one way), round_trip, odometer_start, odometer_end,
 * purpose, own_vehicle and billable. Picking a client offers their sites
 * and tickets; a site fills in where to, a ticket the purpose and its site;
 * a route driven before fills in the miles; the total follows the miles and
 * round trip, and the odometer readings make the miles when both are there.
 * Own vehicle starts as the driver's last trip was, and shows what it is
 * paid back; billable starts as the ticket is (or on, with trips billing)
 * and shows what it bills. Once paid back for or billed, what that was on
 * is locked.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_edit = $form_mode === 'edit';
$form_return = $form_mode === 'return';
$form_id = 'tripForm' . ucfirst($form_mode) . ($form_row ? intval($form_row['trip_id']) : '');

$form_client = $form_row ? intval($form_row['trip_client_id']) : intval($form_client_id ?? 0);
$form_location = $form_row ? intval($form_row['trip_location_id']) : intval($form_location_id ?? 0);
$form_ticket = $form_row ? intval($form_row['trip_ticket_id']) : intval($form_ticket_id ?? 0);
$form_driver = $form_row && $form_edit ? intval($form_row['trip_user_id']) : intval($session_user_id);
$form_date = $form_row && ($form_edit || $form_return) ? (string) $form_row['trip_date'] : date('Y-m-d');
$form_source = $form_row ? (string) $form_row['trip_source'] : (tripLastStart() ?: tripOfficeAddress());
$form_destination = $form_row ? (string) $form_row['trip_destination'] : '';
$form_round = $form_row ? intval($form_row['round_trip']) === 1 : true;
$form_miles = $form_row ? tripOneWay($form_row) : null;
$form_purpose = $form_row ? (string) $form_row['trip_purpose'] : (string) ($form_purpose ?? '');
$form_odo_start = $form_row && $form_edit ? $form_row['trip_start_odometer'] : null;
$form_odo_end = $form_row && $form_edit ? $form_row['trip_end_odometer'] : null;

// Mileage money - paid back for their own vehicle, billed to the client
$form_rules = tripRules();
$form_pay_back = tripPayBackOn();
$form_billing = tripBillingOn();
$form_vehicles = tripDriverVehicles();
$form_own = $form_row ? intval($form_row['trip_own_vehicle']) === 1 : (isset($form_vehicles[$form_driver]) ? $form_vehicles[$form_driver] === 1 : $form_pay_back);
$form_billable = $form_row ? intval($form_row['trip_billable']) === 1 : null;
if ($form_return && $form_rules['charge'] > 0) {
    $form_billable = false; // the way back is the same visit - one trip charge
}
$form_claimed = $form_edit && $form_row['claim_expense_id'] !== null;
$form_billed = $form_edit && $form_row['invoice_number'] !== null;
$form_locked = $form_claimed || $form_billed;

// The way back: from where it went to where it started, one way
if ($form_return) {
    [$form_source, $form_destination] = [$form_destination, $form_source];
    $form_location = 0;
    $form_round = false;
    $form_purpose = mb_substr('Back from ' . $form_row['trip_destination'] . ($form_purpose !== '' ? " - $form_purpose" : ''), 0, 200);
}

$form_can_manage = tripCanManage() && !$form_claimed;
$form_drivers = tripDrivers($form_row ? $form_row['trip_user_id'] : 0);
if (!$form_can_manage) {
    $form_drivers = array_intersect_key($form_drivers, array_flip(array_filter([intval($session_user_id), $form_edit ? intval($form_row['trip_user_id']) : 0])));
}
$form_clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
    WHERE (client_archived_at IS NULL OR client_id = $form_client) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $form_clients[] = $row;
}
$form_sites = tripClientSites(0, $form_location);
$form_tickets = tripClientTickets(0, $form_ticket);
$form_routes = tripKnownRoutes();
$form_places = tripStartPlaces();
$unit = tripUnit();

?>
<div class="row g-3" id="<?= $form_id ?>" data-trip-form="<?= $form_mode ?>">

    <?php if ($form_locked) { ?>
        <div class="col-12">
            <div class="alert alert-secondary small mb-0">
                <i class="fas fa-lock me-2"></i><?php if ($form_claimed) { ?>Paid back for on a <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($form_row['claim_expense_id']) ?>">mileage expense</a> - the distance, driver and vehicle stay as they were paid<?= $form_billed ? '.' : '. Delete that expense to change them.' ?><?php } ?>
                <?php if ($form_billed) { ?><?= $form_claimed ? ' ' : '' ?>Billed on <a href="invoice.php?invoice_id=<?= intval($form_row['trip_invoice_id']) ?>"><?= escapeHtml($form_row['invoice_prefix'] . $form_row['invoice_number']) ?></a> - the distance and client stay as they were billed. Take it off the invoice to change them.<?php } ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($form_return) { ?>
        <div class="col-12">
            <div class="alert alert-info small mb-0"><i class="fas fa-undo-alt me-2"></i>The way back from <?= escapeHtml($form_row['trip_destination']) ?> - a trip of its own, one way.</div>
        </div>
    <?php } ?>

    <div class="col-md-4">
        <label class="form-label">Date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_date) ?>" required>
        </div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Driver <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
            <select class="form-select select2" name="driver" required data-trip-driver <?= $form_claimed ? 'disabled' : '' ?>>
                <?php foreach ($form_drivers as $driver_id => $driver) { ?>
                    <option value="<?= $driver_id ?>" <?= $driver_id === $form_driver ? 'selected' : '' ?><?= selectOptionUser($driver_id, $driver['user_name'], $driver['user_avatar'] ?? '') ?>><?= escapeHtml($driver['user_name']) ?><?= $driver_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                <?php } ?>
            </select>
        </div>
        <?php if (!tripCanManage()) { ?>
            <div class="form-text">You log your own trips.</div>
        <?php } ?>
    </div>

    <div class="col-md-6">
        <label class="form-label">Client</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
            <select class="form-select select2" name="client_id" data-trip-client <?= $form_billed ? 'disabled' : '' ?>>
                <option value="0">- Our own - not for a client -</option>
                <?php foreach ($form_clients as $client) { ?>
                    <option value="<?= intval($client['client_id']) ?>" <?= $form_client === intval($client['client_id']) ? 'selected' : '' ?><?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-6" data-trip-ticket-box hidden>
        <label class="form-label">For the ticket</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
            <select class="form-select" name="ticket_id" data-trip-ticket data-selected="<?= $form_ticket ?>">
                <option value="0">- No ticket -</option>
            </select>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">From <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dot-circle"></i></span>
            <input type="text" class="form-control" name="source" maxlength="200" value="<?= escapeHtml($form_source) ?>" list="<?= $form_id ?>Places" placeholder="Where you started - the office, home" required data-trip-from>
            <datalist id="<?= $form_id ?>Places">
                <?php foreach ($form_places as $place) { ?>
                    <option value="<?= escapeHtml($place) ?>"></option>
                <?php } ?>
            </datalist>
        </div>
    </div>

    <div class="col-md-6" data-trip-site-box hidden>
        <label class="form-label">Their site</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
            <select class="form-select" name="location_id" data-trip-site data-selected="<?= $form_location ?>">
                <option value="0">- Somewhere else -</option>
            </select>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">To <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt text-danger"></i></span>
            <input type="text" class="form-control" name="destination" maxlength="200" value="<?= escapeHtml($form_destination) ?>" placeholder="The address, or the place" required data-trip-to>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label"><?= ucfirst($unit) ?> one way <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-road"></i></span>
            <input type="text" class="form-control text-end" name="miles" inputmode="decimal" placeholder="0.0" value="<?= $form_miles === null ? '' : number_format($form_miles, 1, '.', '') ?>" required data-trip-miles <?= $form_mode === 'new' && !$form_location ? 'autofocus' : '' ?> <?= $form_locked ? 'disabled' : '' ?>>
            <span class="input-group-text"><?= tripUnit(true) ?></span>
        </div>
        <div class="form-text" data-trip-known hidden><i class="fas fa-history me-1"></i>As the last time you drove it</div>
    </div>
    <div class="col-md-4 d-flex align-items-md-end">
        <div class="form-check form-switch mb-md-2">
            <input class="form-check-input" type="checkbox" role="switch" name="round_trip" value="1" id="<?= $form_id ?>Round" <?= $form_round ? 'checked' : '' ?> data-trip-round <?= $form_locked ? 'disabled' : '' ?>>
            <label class="form-check-label" for="<?= $form_id ?>Round">Round trip - there and back</label>
        </div>
    </div>
    <div class="col-md-4 d-flex align-items-md-end">
        <div class="mb-md-2"><span class="text-body-secondary">The trip is</span> <strong class="fs-5" data-trip-total></strong></div>
    </div>

    <?php if ($form_pay_back || $form_own) { ?>
        <div class="col-md-6">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="own_vehicle" value="1" id="<?= $form_id ?>Own" <?= $form_own ? 'checked' : '' ?> data-trip-own <?= $form_claimed ? 'disabled' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Own"><i class="fas fa-car-side text-body-secondary me-1"></i>In their own vehicle</label>
            </div>
            <div class="form-text" data-trip-own-note></div>
        </div>
    <?php } ?>
    <div class="col-md-6" data-trip-bill-box hidden>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" name="billable" value="1" id="<?= $form_id ?>Bill" <?= $form_billable ? 'checked' : '' ?> data-trip-billable <?= $form_billed ? 'disabled' : '' ?>
                data-auto="<?= $form_billable === null ? '1' : '' ?>">
            <label class="form-check-label" for="<?= $form_id ?>Bill"><i class="fas fa-file-invoice-dollar text-body-secondary me-1"></i>Bill the client for it</label>
        </div>
        <div class="form-text" data-trip-bill-note></div>
    </div>

    <div class="col-12">
        <a class="small" data-bs-toggle="collapse" href="#<?= $form_id ?>Odometer" role="button"><i class="fas fa-tachometer-alt me-1"></i>Odometer readings</a>
        <div class="collapse <?= $form_odo_start !== null || $form_odo_end !== null ? 'show' : '' ?>" id="<?= $form_id ?>Odometer">
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">At the start</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tachometer-alt"></i></span>
                        <input type="text" class="form-control text-end" name="odometer_start" inputmode="numeric" value="<?= $form_odo_start === null ? '' : intval($form_odo_start) ?>" data-trip-odo-start <?= $form_locked ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">At the end</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
                        <input type="text" class="form-control text-end" name="odometer_end" inputmode="numeric" value="<?= $form_odo_end === null ? '' : intval($form_odo_end) ?>" data-trip-odo-end <?= $form_locked ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="col-12 form-text mt-1">With both, the trip is what the odometer says.</div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">What for <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
            <textarea class="form-control" rows="2" name="purpose" maxlength="200" placeholder="Onsite - replace the front office switch" required data-trip-purpose><?= escapeHtml($form_purpose) ?></textarea>
        </div>
    </div>

</div>

<script>
(function () {
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';
    var sites = <?= json_encode((object) $form_sites) ?>;
    var tickets = <?= json_encode((object) $form_tickets) ?>;
    var routes = <?= json_encode((object) $form_routes) ?>;
    var unit = <?= json_encode(tripUnit(true)) ?>;
    var money = <?= json_encode([
        'rate' => $form_rules['rate'], 'charge' => $form_rules['charge'], 'billRate' => $form_rules['bill_rate'], 'included' => $form_rules['included'],
        'billing' => $form_billing, 'rule' => tripBillRuleText(), 'rateText' => $form_rules['rate'] > 0 ? tripRateText($form_rules['rate']) : '',
        'currency' => (string) ($session_company_currency ?? 'USD'), 'locale' => str_replace('_', '-', (string) ($session_company_locale ?? 'en_US')),
        'vehicles' => (object) $form_vehicles, 'claimed' => $form_claimed, 'billed' => $form_billed,
    ]) ?>;
    var driver = root.querySelector('[data-trip-driver]');
    var own = root.querySelector('[data-trip-own]');
    var ownNote = root.querySelector('[data-trip-own-note]');
    var billBox = root.querySelector('[data-trip-bill-box]');
    var billable = root.querySelector('[data-trip-billable]');
    var billNote = root.querySelector('[data-trip-bill-note]');
    var autoBill = billable.getAttribute('data-auto') === '1';
    var autoOwn = <?= $form_row ? 'false' : 'true' ?>;
    var client = root.querySelector('[data-trip-client]');
    var ticket = root.querySelector('[data-trip-ticket]');
    var site = root.querySelector('[data-trip-site]');
    var from = root.querySelector('[data-trip-from]');
    var to = root.querySelector('[data-trip-to]');
    var miles = root.querySelector('[data-trip-miles]');
    var round = root.querySelector('[data-trip-round]');
    var total = root.querySelector('[data-trip-total]');
    var known = root.querySelector('[data-trip-known]');
    var odoStart = root.querySelector('[data-trip-odo-start]');
    var odoEnd = root.querySelector('[data-trip-odo-end]');
    var purpose = root.querySelector('[data-trip-purpose]');
    var autoTo = '';
    var autoMiles = false;

    function number(value) {
        var n = Number(String(value || '').replace(/[\s,]/g, ''));
        return isNaN(n) ? null : n;
    }

    function cash(amount) {
        try {
            return new Intl.NumberFormat(money.locale, { style: 'currency', currency: money.currency }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }

    // Billable: as the ticket is, else on when trips bill - until it is switched by hand
    function billDefault() {
        if (!autoBill || money.billed) {
            return;
        }
        var found = (tickets[client.value] || []).filter(function (t) { return String(t[0]) === ticket.value; })[0];
        billable.checked = found ? found[4] === 1 : money.billing;
    }

    function fill(select, list, selected, none) {
        select.innerHTML = '';
        var first = document.createElement('option');
        first.value = '0';
        first.textContent = none;
        select.appendChild(first);
        list.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item[0];
            option.textContent = item[1] + (item.length > 2 && typeof item[2] === 'string' && item[2] !== item[1] ? ' - ' + item[2] : '');
            option.selected = String(item[0]) === String(selected);
            select.appendChild(option);
        });
    }

    // A client's sites and tickets
    function clientChanged(first) {
        var id = client.value;
        var siteList = sites[id] || [];
        var ticketList = tickets[id] || [];
        fill(site, siteList, first ? site.getAttribute('data-selected') : '0', '- Somewhere else -');
        fill(ticket, ticketList, first ? ticket.getAttribute('data-selected') : '0', '- No ticket -');
        root.querySelector('[data-trip-site-box]').hidden = !siteList.length;
        root.querySelector('[data-trip-ticket-box]').hidden = !ticketList.length;
        billBox.hidden = client.value === '0' || (!money.billing && !billable.checked);
        if (!first) {
            billDefault();
        }
        if (!first && autoTo !== '' && to.value === autoTo) {
            to.value = '';
            autoTo = '';
        }
    }

    // A site fills in where to, unless it was typed
    function siteChanged() {
        var found = (sites[client.value] || []).filter(function (s) { return String(s[0]) === site.value; })[0];
        if (found && (to.value === '' || to.value === autoTo)) {
            to.value = found[2];
            autoTo = found[2];
        }
        routeChanged();
    }

    // A ticket fills in the purpose, and its site when none is picked
    function ticketChanged() {
        billDefault();
        update();
        var found = (tickets[client.value] || []).filter(function (t) { return String(t[0]) === ticket.value; })[0];
        if (!found) {
            return;
        }
        if (purpose.value.trim() === '') {
            purpose.value = 'Onsite - ' + found[1].replace(/^[^ ]+ - /, '');
        }
        if (found[2] && site.value === '0' && site.querySelector('option[value="' + found[2] + '"]')) {
            site.value = String(found[2]);
            siteChanged();
        }
    }

    // A route driven before fills in the miles, unless they were typed
    function routeChanged() {
        var key = from.value.trim().toLowerCase() + '|' + to.value.trim().toLowerCase();
        var value = routes[key];
        if (value !== undefined && (miles.value.trim() === '' || autoMiles) && !odometer()) {
            miles.value = Number(value).toFixed(1);
            autoMiles = true;
        }
        known.hidden = !(autoMiles && value !== undefined && number(miles.value) === Number(value));
        update();
    }

    function odometer() {
        var a = number(odoStart.value);
        var b = number(odoEnd.value);
        return odoStart.value.trim() !== '' && odoEnd.value.trim() !== '' && a !== null && b !== null && b >= a ? b - a : null;
    }

    function update() {
        var odo = odometer();
        if (odo !== null) {
            miles.value = (odo / (round.checked ? 2 : 1)).toFixed(1);
        }
        miles.readOnly = odo !== null;
        var m = number(miles.value);
        var whole = m === null ? null : Math.round((odo !== null ? odo : m * (round.checked ? 2 : 1)) * 10) / 10;
        total.textContent = whole === null ? '-' : whole.toFixed(1) + ' ' + unit;

        // What it is paid back, and what it bills
        if (own && ownNote) {
            ownNote.textContent = money.claimed ? 'Paid back for' : (own.checked && money.rate > 0 ? 'Paid back ' + (whole !== null ? cash(Math.round(whole * money.rate * 100) / 100) + ' - ' : '') + 'at ' + money.rateText : (own.checked ? 'Not paid back - there is no mileage rate' : 'A company vehicle - not paid back'));
        }
        if (billNote) {
            var price = Math.round((money.charge + money.billRate * Math.max(0, (whole || 0) - money.included)) * 100) / 100;
            billNote.textContent = money.billed ? 'Billed' : (billable.checked ? (money.billing ? 'Bills ' + cash(price) + ' - at ' + money.rule : 'Bills nothing until trips have a charge or rate') : 'Not billed - our cost');
        }
    }

    client.addEventListener('change', function () { clientChanged(false); });
    site.addEventListener('change', siteChanged);
    ticket.addEventListener('change', ticketChanged);
    [from, to].forEach(function (el) {
        el.addEventListener('change', routeChanged);
    });
    miles.addEventListener('input', function () {
        autoMiles = false;
        known.hidden = true;
        update();
    });
    [round, odoStart, odoEnd].forEach(function (el) {
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });
    if (own) {
        own.addEventListener('change', function () {
            autoOwn = false;
            update();
        });
    }
    billable.addEventListener('change', function () {
        autoBill = false;
        update();
    });
    // Their own vehicle, or not, as the driver's last trip was
    if (driver && own) {
        driver.addEventListener('change', function () {
            if (autoOwn && Object.prototype.hasOwnProperty.call(money.vehicles, driver.value)) {
                own.checked = money.vehicles[driver.value] === 1;
                update();
            }
        });
    }

    clientChanged(true);
    if (billable.getAttribute('data-auto') === '1') {
        billDefault();
        billBox.hidden = client.value === '0' || (!money.billing && !billable.checked);
    }
    if (site.value !== '0' && to.value === '') {
        siteChanged();
    }
    if (ticket.value !== '0' && purpose.value.trim() === '') {
        ticketChanged();
    }
    routeChanged();
})();
</script>
