<?php

// Default Column Sortby/Order Filter
$sort = "client_accessed_at";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_client');

$can_edit = lookupUserPermission("module_client") >= 2;
$show_tickets = $config_module_enable_ticketing == 1 && lookupUserPermission("module_support") >= 1;
$can_add_ticket = $config_module_enable_ticketing == 1 && lookupUserPermission("module_support") >= 2;
// Same gate as the Billing card on a client's own pages (inc_client_top_head.php)
$show_billing = $config_module_enable_accounting == 1 && lookupUserPermission("module_financial") >= 1;

/**
 * Builds a link to this page with filter overrides applied. Pass null to drop
 * a filter. Paging always resets - a page number from the previous filter set
 * means nothing once the result set changes.
 */
function clientsFilterUrl($overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $name => $value) {
        if ($value === null) {
            unset($params[$name]);
        } else {
            $params[$name] = $value;
        }
    }
    unset($params['page']);

    return escapeHtml('?' . http_build_query($params));
}

// Chips describing what is currently filtering the list, built as each
// filter is parsed. Each one renders with an x that drops just that filter.
$active_filters = [];

// Leads Filter
if (isset($_GET['leads']) && $_GET['leads'] == 1) {
    $leads_filter = 1;
    $leads_query = "AND client_lead = 1";
} else {
    $leads_filter = 0;
    $leads_query = "AND client_lead = 0";
}

// Archived - filter_header.php has already turned ?archived into $archive_query
if ($archived) {
    $active_filters[] = ['label' => 'Showing', 'value' => 'Archived', 'drop' => 'archived'];
}

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND clients.client_id IN (SELECT client_tags.client_id FROM client_tags WHERE client_tags.tag_id IN ($tag_filter))";

    $tag_names = [];
    $sql_tag_names = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql_tag_names)) {
        $tag_names[] = escapeHtml($row['tag_name']);
    }
    $active_filters[] = ['label' => 'Tags', 'value' => implode(', ', $tag_names), 'drop' => 'tags'];
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Industry Filter
if (!empty($_GET['industry'])) {
    $industry_query = "AND clients.client_type = '" . escapeSql($_GET['industry']) . "'";
    $industry_filter = escapeHtml($_GET['industry']);
    $active_filters[] = ['label' => 'Industry', 'value' => $industry_filter, 'drop' => 'industry'];
} else {
    $industry_query = '';
    $industry_filter = '';
}

// Referral Filter
if (!empty($_GET['referral'])) {
    $referral_query = "AND clients.client_referral = '" . escapeSql($_GET['referral']) . "'";
    $referral_filter = escapeHtml($_GET['referral']);
    $active_filters[] = ['label' => 'Referral', 'value' => $referral_filter, 'drop' => 'referral'];
} else {
    $referral_query = '';
    $referral_filter = '';
}

// Created date
$date_filter_active = $dtf !== '1970-01-01' || $dtt !== '2099-12-31';
if ($date_filter_active) {
    $active_filters[] = ['label' => 'Created', 'value' => escapeHtml("$dtf to $dtt"), 'drop' => ['canned_date', 'dtf', 'dtt']];
}

if ($q !== '') {
    $active_filters[] = ['label' => 'Search', 'value' => stripslashes(escapeHtml($q)), 'drop' => 'q'];
}

// How many of the collapsed panel's filters are set - shown on the filter button
$hidden_filter_count = count(array_filter([$tag_query, $industry_query, $referral_query, $date_filter_active]));

/*
 * Sorting. Only these keys are accepted; anything else falls back to the
 * page default instead of reaching ORDER BY as an unknown column. Balance and
 * open tickets are worked out per client, so they sort on an expression.
 */
$balance_sql = "(SELECT COALESCE(SUM(invoice_amount), 0) FROM invoices
        WHERE invoice_client_id = clients.client_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable'))
    - (SELECT COALESCE(SUM(payment_amount), 0) FROM payments JOIN invoices ON payment_invoice_id = invoice_id
        WHERE invoice_client_id = clients.client_id)";
$open_tickets_sql = "(SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = clients.client_id AND ticket_resolved_at IS NULL)";

$sortable = [
    'client_name' => 'client_name',
    'contact_name' => 'contact_name',
    'location_city' => 'location_city',
    'client_accessed_at' => 'client_accessed_at',
    'client_created_at' => 'client_created_at',
];
if ($show_billing) {
    $sortable['balance'] = $balance_sql;
}
if ($show_tickets) {
    $sortable['open_tickets'] = $open_tickets_sql;
}
if (!isset($sortable[$sort])) {
    $sort = 'client_accessed_at';
}
$order_by = $sortable[$sort];

// Sort link for a column header, plus the arrow when it is the active sort
function clientsSortLink($column, $label)
{
    global $sort, $disp, $order_icon;
    $url = clientsFilterUrl(['sort' => $column, 'order' => $sort === $column ? $disp : ($column === 'client_name' || $column === 'contact_name' || $column === 'location_city' ? 'ASC' : 'DESC')]);
    return '<a class="text-reset text-decoration-none" href="' . $url . '">' . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
}

$client_scope = clientScopeSql('clients.client_id');

/*
 * One row per client. The primary contact and location are picked by id
 * rather than joined on the primary flag, so a client with two "primary"
 * contacts still lists once - which used to need a GROUP BY over every
 * column of three tables.
 */
$sql = mysqli_query(
    $mysqli,
    "
    SELECT SQL_CALC_FOUND_ROWS
        clients.client_id, client_name, client_type, client_abbreviation, client_rate, client_currency_code,
        client_lead, client_archived_at,
        contact_id, contact_name, contact_phone, contact_phone_country_code, contact_extension,
        contact_mobile, contact_mobile_country_code, contact_email, contact_photo,
        location_id, location_address, location_city, location_state, location_zip, location_country
    FROM clients
    LEFT JOIN contacts ON contact_id = (
        SELECT c.contact_id FROM contacts c WHERE c.contact_client_id = clients.client_id AND c.contact_primary = 1 ORDER BY c.contact_id LIMIT 1
    )
    LEFT JOIN locations ON location_id = (
        SELECT l.location_id FROM locations l WHERE l.location_client_id = clients.client_id AND l.location_primary = 1 ORDER BY l.location_id LIMIT 1
    )
    WHERE (client_name LIKE '%$q%' OR client_abbreviation LIKE '%$q%' OR client_type LIKE '%$q%' OR client_referral LIKE '%$q%'
           OR contact_email LIKE '%$q%' OR contact_name LIKE '%$q%' OR contact_phone LIKE '%$phone_query%'
           OR contact_mobile LIKE '%$phone_query%' OR location_address LIKE '%$q%'
           OR location_city LIKE '%$q%' OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%' OR location_country LIKE '%$q%'
           OR client_tax_id_number LIKE '%$q%'
           OR clients.client_id IN (SELECT client_tags.client_id FROM client_tags JOIN tags ON tags.tag_id = client_tags.tag_id WHERE tag_name LIKE '%$q%'))
      AND client_$archive_query
      AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt'
      $leads_query
      $client_scope
      $tag_query
      $industry_query
      $referral_query
    ORDER BY $order_by $order, client_name ASC
    LIMIT $record_from, $record_to
");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$clients = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[intval($row['client_id'])] = $row;
}

/*
 * Everything else a row shows, fetched for the whole page at once - a query
 * per lookup, not a query per lookup per row.
 */
$client_tags = [];
$client_money = [];
$client_tickets = [];

if ($clients) {
    $page_ids = implode(',', array_keys($clients));

    $sql_tags = mysqli_query($mysqli, "SELECT client_tags.client_id, tags.tag_id, tag_name, tag_description, tag_color, tag_icon
        FROM client_tags JOIN tags ON tags.tag_id = client_tags.tag_id
        WHERE client_tags.client_id IN ($page_ids)
        ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql_tags)) {
        $client_tags[intval($row['client_id'])][] = $row;
    }

    if ($show_billing) {
        foreach (array_keys($clients) as $id) {
            $client_money[$id] = ['invoiced' => 0, 'paid' => 0, 'overdue' => 0, 'recurring' => 0];
        }

        // Balance the way the client's own Billing card works it out: invoiced minus paid
        $sql_money = mysqli_query($mysqli, "SELECT invoice_client_id AS id, SUM(invoice_amount) AS v FROM invoices
            WHERE invoice_client_id IN ($page_ids) AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            GROUP BY invoice_client_id");
        while ($row = mysqli_fetch_assoc($sql_money)) {
            $client_money[intval($row['id'])]['invoiced'] = floatval($row['v']);
        }

        $sql_money = mysqli_query($mysqli, "SELECT invoice_client_id AS id, SUM(payment_amount) AS v FROM payments
            JOIN invoices ON payment_invoice_id = invoice_id
            WHERE invoice_client_id IN ($page_ids)
            GROUP BY invoice_client_id");
        while ($row = mysqli_fetch_assoc($sql_money)) {
            $client_money[intval($row['id'])]['paid'] = floatval($row['v']);
        }

        // What is still owed on unpaid invoices past their due date
        $sql_money = mysqli_query($mysqli, "SELECT invoice_client_id AS id,
                SUM(invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0)) AS v
            FROM invoices
            WHERE invoice_client_id IN ($page_ids) AND invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due < CURDATE()
            GROUP BY invoice_client_id");
        while ($row = mysqli_fetch_assoc($sql_money)) {
            $client_money[intval($row['id'])]['overdue'] = floatval($row['v']);
        }

        // Monthly recurring - quarterly ones count as a third, yearly as a twelfth
        $sql_money = mysqli_query($mysqli, "SELECT recurring_invoice_client_id AS id,
                SUM(" . recurringInvoiceMonthlySql() . ") AS v
            FROM recurring_invoices
            WHERE recurring_invoice_client_id IN ($page_ids) AND recurring_invoice_status = 1
            AND recurring_invoice_frequency IN ('month', 'quarter', 'year')
            GROUP BY recurring_invoice_client_id");
        while ($row = mysqli_fetch_assoc($sql_money)) {
            $client_money[intval($row['id'])]['recurring'] = floatval($row['v']);
        }
    }

    if ($show_tickets) {
        $sql_tickets = mysqli_query($mysqli, "SELECT ticket_client_id AS id, COUNT(ticket_id) AS open,
                SUM(ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2
                    OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0)) AS breached
            FROM tickets
            WHERE ticket_client_id IN ($page_ids) AND ticket_resolved_at IS NULL
            GROUP BY ticket_client_id");
        while ($row = mysqli_fetch_assoc($sql_tickets)) {
            $client_tickets[intval($row['id'])] = ['open' => intval($row['open']), 'breached' => intval($row['breached'])];
        }
    }
}

// Clients / Leads counts for the header, in the archive state being shown
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(client_lead = 0), 0) AS clients, COALESCE(SUM(client_lead = 1), 0) AS leads
    FROM clients WHERE client_$archive_query $client_scope"));
$total_clients = intval($row['clients']);
$total_leads = intval($row['leads']);

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-user-friends me-2"></i>Clients
            <small class="ms-3">
                <a href="<?= clientsFilterUrl(['leads' => null, 'industry' => null, 'referral' => null]) ?>" class="badge rounded-pill p-1 <?= $leads_filter ? 'text-light' : 'bg-light text-dark' ?>"><strong><?= $total_clients ?></strong> Client<?= $total_clients === 1 ? '' : 's' ?></a> |
                <a href="<?= clientsFilterUrl(['leads' => 1, 'industry' => null, 'referral' => null]) ?>" class="badge rounded-pill p-1 <?= $leads_filter ? 'bg-light text-dark' : 'text-light' ?>"><strong><?= $total_leads ?></strong> Lead<?= $total_leads === 1 ? '' : 's' ?></a>
            </small>
        </h3>
        <?php if ($can_edit) { ?>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/client/client_add.php<?php if ($leads_filter) { echo "?lead=1"; } ?>">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New <?= $leads_filter ? 'Lead' : 'Client' ?></span>
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" title="More"></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_import.php">
                            <i class="fa fa-fw fa-upload me-2"></i>Import
                        </a>
                        <a class="dropdown-item ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/client/client_export.php', ['leads', 'tags', 'industry', 'referral', 'q', 'archived'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="leads" value="<?= $leads_filter ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
            <input type="hidden" name="order" value="<?= escapeHtml($order) ?>">

            <div class="row g-2 align-items-center">
                <div class="col-sm-6 col-lg-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q)) ?>" placeholder="Search <?= $leads_filter ? 'leads' : 'clients' ?>, contacts, addresses, tags" autofocus>
                        <button class="btn <?= $hidden_filter_count ? 'btn-warning' : 'btn-secondary' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter" title="Filters">
                            <i class="fas fa-filter"></i><?php if ($hidden_filter_count) { ?><span class="ms-1"><?= $hidden_filter_count ?></span><?php } ?>
                        </button>
                        <button class="btn btn-primary" title="Search" type="submit"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-7">
                    <div class="d-flex justify-content-sm-end gap-2">
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group"></i><span class="d-none d-sm-inline ms-2">Action</span> (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_add_ticket.php" data-modal-size="lg" data-bulk="true">
                                    <i class="fas fa-fw fa-life-ring me-2"></i>Open Tickets
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_email.php" data-modal-size="lg" data-bulk="true">
                                    <i class="fas fa-fw fa-paper-plane me-2"></i>Send Email
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="client" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_edit_industry.php" data-bulk="true">
                                    <i class="fas fa-fw fa-briefcase me-2"></i>Set Industry
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_edit_referral.php" data-bulk="true">
                                    <i class="fas fa-fw fa-link me-2"></i>Set Referral
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_edit_hourly_rate.php" data-bulk="true">
                                    <i class="fas fa-fw fa-clock me-2"></i>Set Hourly Rate
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_bulk_edit_net_terms.php" data-bulk="true">
                                    <i class="fas fa-fw fa-calendar me-2"></i>Set Net Terms
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_unarchive_clients">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_clients">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                        <a href="<?= clientsFilterUrl(['archived' => $archived ? null : 1]) ?>" class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active ' . ($leads_filter ? 'leads' : 'clients') : 'Show archived ' . ($leads_filter ? 'leads' : 'clients') ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-none d-xl-inline ms-2">Archived</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="collapse mt-3 <?php if ($hidden_filter_count) { echo "show"; } ?>" id="advancedFilter">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tags</label>
                        <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="Any tag" multiple>
                            <?php
                            $sql_tags_filter = mysqli_query($mysqli, "
                                SELECT tags.tag_id, tags.tag_name, MAX(tags.tag_color) AS tag_color, MAX(tags.tag_icon) AS tag_icon
                                FROM tags
                                LEFT JOIN client_tags ON client_tags.tag_id = tags.tag_id
                                WHERE tag_type = 1 AND (tags.tag_archived_at IS NULL OR tags.tag_id IN ($tag_filter))
                                GROUP BY tags.tag_id, tags.tag_name
                                HAVING COUNT(client_tags.client_id) > 0 OR tags.tag_id IN ($tag_filter)
                                ORDER BY tags.tag_name
                            ");
                            while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                                $filter_tag_id = intval($row['tag_id']);
                                $filter_tag_name = escapeHtml($row['tag_name']); ?>
                                <option value="<?= $filter_tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($filter_tag_id, array_map('intval', $_GET['tags']), true)) { echo 'selected'; } ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= $filter_tag_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Industry</label>
                        <select class="form-select select2" name="industry" onchange="this.form.submit()">
                            <option value="">Any industry</option>
                            <?php
                            $sql_industries_filter = mysqli_query($mysqli, "SELECT DISTINCT client_type FROM clients WHERE client_$archive_query AND client_type != '' $leads_query ORDER BY client_type ASC");
                            while ($row = mysqli_fetch_assoc($sql_industries_filter)) {
                                $industry_name = escapeHtml($row['client_type']); ?>
                                <option <?php if ($industry_name == $industry_filter) { echo "selected"; } ?>><?= $industry_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Referral</label>
                        <select class="form-select select2" name="referral" onchange="this.form.submit()">
                            <option value="">Any referral</option>
                            <?php
                            $sql_referrals_filter = mysqli_query($mysqli, "SELECT DISTINCT client_referral FROM clients WHERE client_$archive_query AND client_referral != '' $leads_query ORDER BY client_referral ASC");
                            while ($row = mysqli_fetch_assoc($sql_referrals_filter)) {
                                $referral_name = escapeHtml($row['client_referral']); ?>
                                <option <?php if ($referral_name == $referral_filter) { echo "selected"; } ?>><?= $referral_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Created</label>
                        <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                        <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                        <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                        <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                    </div>
                </div>
            </div>
        </form>

        <?php if ($active_filters) { ?>
            <!-- The filters are collapsed by default, so this line is what stops an
                 active filter from being invisible -->
            <div class="mt-2">
                <?php foreach ($active_filters as $filter) {
                    $drop = is_array($filter['drop']) ? array_fill_keys($filter['drop'], null) : [$filter['drop'] => null];
                    ?>
                    <span class="badge rounded-pill bg-light border p-2 me-1 fw-normal">
                        <span class="text-secondary"><?= $filter['label'] ?>:</span> <?= $filter['value'] ?>
                        <a href="<?= clientsFilterUrl($drop) ?>" class="text-danger ms-1" title="Remove this filter"><i class="fas fa-times"></i></a>
                    </span>
                <?php } ?>
                <a href="<?= $leads_filter ? '?leads=1' : '?' ?>" class="small text-danger ms-1">Clear all</a>
            </div>
        <?php } ?>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <!-- position-relative keeps the visually-hidden labels inside the scroll box on narrow screens -->
        <div class="table-responsive position-relative">
            <table class="table table-hover align-middle mb-0 itflow-clients">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?> table-light">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all on this page">
                        </div>
                    </th>
                    <th><?= clientsSortLink('client_name', $leads_filter ? 'Lead' : 'Client') ?></th>
                    <th class="d-none d-md-table-cell"><?= clientsSortLink('contact_name', 'Primary contact') ?></th>
                    <th class="d-none d-xl-table-cell"><?= clientsSortLink('location_city', 'Location') ?></th>
                    <?php if ($show_tickets) { ?>
                        <th class="text-center d-none d-sm-table-cell"><?= clientsSortLink('open_tickets', 'Tickets') ?></th>
                    <?php } ?>
                    <?php if ($show_billing) { ?>
                        <th class="text-end"><?= clientsSortLink('balance', 'Balance') ?></th>
                        <th class="text-end d-none d-lg-table-cell">Recurring</th>
                    <?php } ?>
                    <?php if ($can_edit) { ?>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $client_id => $row) {
                    $client_name = escapeHtml($row['client_name']);
                    $client_type = escapeHtml($row['client_type']);
                    $client_archived_at = escapeHtml($row['client_archived_at']);
                    $client_currency_code = escapeHtml($row['client_currency_code'] ?: $session_company_currency);
                    $client_rate = floatval($row['client_rate']);

                    $client_initials = escapeHtml(clientInitials($row['client_abbreviation'], $row['client_name']));
                    $client_tone = clientInitialsTone($client_id);

                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
                    $contact_phone_digits = preg_replace('/[^0-9+]/', '', (string) $row['contact_phone']);
                    $contact_extension = escapeHtml($row['contact_extension']);
                    $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));
                    $contact_mobile_digits = preg_replace('/[^0-9+]/', '', (string) $row['contact_mobile']);
                    $contact_email = escapeHtml($row['contact_email']);

                    $location_id = intval($row['location_id']);
                    $location_street = escapeHtml($row['location_address']);
                    $location_place = escapeHtml(formatAddress('', $row['location_city'], $row['location_state'], '', ''));
                    $location_full = escapeHtml(formatAddress($row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_country'], ', '));

                    $tickets_open = $client_tickets[$client_id]['open'] ?? 0;
                    $tickets_breached = $client_tickets[$client_id]['breached'] ?? 0;

                    if ($show_billing) {
                        $money = $client_money[$client_id];
                        $balance = $money['invoiced'] - $money['paid'];
                    }
                    ?>
                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="client_ids[]" value="<?= $client_id ?>" aria-label="Select <?= $client_name ?>">
                            </div>
                        </td>

                        <!-- The whole cell opens the client (stretched-link); the tag pills and + sit above it -->
                        <td class="position-relative itflow-clients-client">
                            <div class="d-flex align-items-center gap-3">
                                <span class="itflow-client-avatar d-none d-sm-inline-flex bg-<?= $client_tone ?>-subtle text-<?= $client_tone ?>-emphasis" aria-hidden="true"><?= $client_initials ?></span>
                                <div class="overflow-hidden">
                                    <a class="fw-semibold stretched-link" href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
                                    <?php if ($client_type) { ?>
                                        <div class="small text-body-secondary"><?= $client_type ?></div>
                                    <?php } ?>
                                    <?php if (!empty($client_tags[$client_id]) || $can_edit) { ?>
                                        <div class="mt-1"><?= tagPills('client', $client_id, $client_tags[$client_id] ?? [], $leads_filter ? 'clients.php?leads=1&' : 'clients.php?') ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>

                        <td class="d-none d-md-table-cell">
                            <?php if ($contact_id) { ?>
                                <?= contactLink($client_id, $contact_id, $row['contact_name'], $row['contact_photo'], ['class' => 'text-reset']) ?>
                                <div class="d-flex align-items-center gap-2 small text-body-secondary mt-1">
                                    <?php if ($contact_phone) { ?>
                                        <a class="text-reset text-decoration-none text-nowrap" href="tel:<?= escapeHtml($contact_phone_digits) ?>"><i class="fas fa-fw fa-phone me-1" aria-hidden="true"></i><?= $contact_phone ?><?php if ($contact_extension) { echo " x$contact_extension"; } ?></a>
                                    <?php } elseif ($contact_mobile) { ?>
                                        <a class="text-reset text-decoration-none text-nowrap" href="tel:<?= escapeHtml($contact_mobile_digits) ?>"><i class="fas fa-fw fa-mobile-alt me-1" aria-hidden="true"></i><?= $contact_mobile ?></a>
                                    <?php } ?>
                                    <?php if ($contact_email) { ?>
                                        <?php if (!$contact_phone && !$contact_mobile) { ?>
                                            <a class="text-reset text-decoration-none text-truncate" href="mailto:<?= $contact_email ?>"><i class="far fa-fw fa-envelope me-1" aria-hidden="true"></i><?= $contact_email ?></a>
                                        <?php } else { ?>
                                            <a class="text-reset" href="mailto:<?= $contact_email ?>" title="Email <?= $contact_email ?>"><i class="far fa-fw fa-envelope" aria-hidden="true"></i><span class="visually-hidden">Email <?= $contact_email ?></span></a>
                                        <?php } ?>
                                        <button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $contact_email ?>"><i class="far fa-copy"></i></button>
                                    <?php } ?>
                                </div>
                            <?php } elseif ($can_edit) { ?>
                                <a class="ajax-modal small text-body-secondary" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact_add.php?client_id=<?= $client_id ?>&primary=1">
                                    <i class="fas fa-fw fa-plus me-1" aria-hidden="true"></i>Add primary contact
                                </a>
                            <?php } else { ?>
                                <span class="text-body-tertiary">-</span>
                            <?php } ?>
                        </td>

                        <td class="d-none d-xl-table-cell">
                            <?php if ($location_id && $location_full) { ?>
                                <div title="<?= $location_full ?>">
                                    <div class="text-nowrap"><?= $location_place ?: $location_street ?></div>
                                    <?php if ($location_place && $location_street) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-clients-street"><?= $location_street ?></div>
                                    <?php } ?>
                                </div>
                            <?php } elseif (!$location_id && $can_edit) { ?>
                                <a class="ajax-modal small text-body-secondary" href="#" data-modal-url="modals/location/location_add.php?client_id=<?= $client_id ?>&primary=1">
                                    <i class="fas fa-fw fa-plus me-1" aria-hidden="true"></i>Add primary location
                                </a>
                            <?php } else { ?>
                                <span class="text-body-tertiary">-</span>
                            <?php } ?>
                        </td>

                        <?php if ($show_tickets) { ?>
                            <td class="text-center d-none d-sm-table-cell">
                                <?php if ($tickets_open) { ?>
                                    <a href="tickets.php?client_id=<?= $client_id ?>" class="badge rounded-pill <?= $tickets_breached ? 'text-bg-danger' : 'text-bg-primary' ?>"
                                        title="<?= $tickets_open ?> open ticket<?= $tickets_open === 1 ? '' : 's' ?><?= $tickets_breached ? ", $tickets_breached past SLA" : '' ?>"><?= $tickets_open ?></a>
                                <?php } else { ?>
                                    <span class="text-body-tertiary" title="No open tickets">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>

                        <?php if ($show_billing) { ?>
                            <td class="text-end text-nowrap">
                                <?php if ($balance > 0.004) { ?>
                                    <a href="invoices.php?client_id=<?= $client_id ?>&status=Unpaid" class="fw-semibold text-decoration-none <?= $money['overdue'] > 0.004 ? 'text-danger-emphasis' : 'text-reset' ?>"><?= numfmt_format_currency($currency_format, $balance, $client_currency_code) ?></a>
                                    <?php if ($money['overdue'] > 0.004) { ?>
                                        <div class="small text-danger-emphasis"><?= numfmt_format_currency($currency_format, $money['overdue'], $client_currency_code) ?> overdue</div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-body-tertiary"><?= numfmt_format_currency($currency_format, max(0, $balance), $client_currency_code) ?></span>
                                <?php } ?>
                            </td>
                            <td class="text-end text-nowrap d-none d-lg-table-cell">
                                <?php if ($money['recurring'] > 0.004) { ?>
                                    <a href="recurring_invoices.php?client_id=<?= $client_id ?>" class="text-reset text-decoration-none"><?= numfmt_format_currency($currency_format, $money['recurring'], $client_currency_code) ?><span class="small text-body-secondary">/mo</span></a>
                                <?php } ?>
                                <?php if ($client_rate > 0) { ?>
                                    <div class="small text-body-secondary"><?= numfmt_format_currency($currency_format, $client_rate, $client_currency_code) ?>/hr</div>
                                <?php } elseif ($money['recurring'] <= 0.004) { ?>
                                    <span class="text-body-tertiary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>

                        <?php if ($can_edit) { ?>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-body-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_edit.php?id=<?= $client_id ?>">
                                            <i class="fas fa-fw fa-edit me-2"></i>Edit
                                        </a>
                                        <?php if ($can_add_ticket && !$client_archived_at) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
                                                <i class="fas fa-fw fa-life-ring me-2"></i>New Ticket
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($client_archived_at) { ?>
                                            <a class="dropdown-item text-info confirm-link" href="post.php?restore_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo me-2"></i>Restore
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php
    $empty_state = [
        'thing' => $leads_filter ? 'leads' : 'clients',
        'icon' => 'fa-user-friends',
        'views' => ['leads'],
        'text' => $leads_filter ? 'Prospects you are talking to, kept apart from your clients.' : 'Add the companies you look after, or import them all at once.',
        'actions' => $can_edit ? array_merge(
            [['label' => $leads_filter ? 'New Lead' : 'New Client', 'modal' => 'modals/client/client_add.php' . ($leads_filter ? '?lead=1' : '')]],
            $leads_filter ? [] : [['label' => 'Import', 'modal' => 'modals/client/client_import.php', 'icon' => 'fa-upload']]
        ) : [],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
