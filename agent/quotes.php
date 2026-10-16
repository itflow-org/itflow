<?php

/*
 * Quotes - one client's, or every quote the agent can see.
 *
 * Along the top, the pipeline: what is out waiting for an answer, what has
 * been accepted and not invoiced yet, and what was won over the last 90
 * days with the share of answers that were a yes. The pills filter by where
 * a quote stands - Expired picks out the ones that ran out unanswered. Each
 * row has how long a quote has left while it waits. A quote's recurring
 * lines count apart, as so much a month (quote_monthly_amount), under what
 * it bills up front.
 */

// Default Column Sortby/Order Filter
$sort = "quote_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND quote_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;
$mail_ready = !empty($config_smtp_provider);

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND quote_client_id = $client";
}

$unanswered_sql = "quote_status IN ('Draft', 'Sent', 'Viewed')";
$statuses = [
    '' => ['label' => 'All', 'where' => ''],
    'Draft' => ['label' => 'Draft', 'icon' => 'fa-pencil-ruler', 'where' => "quote_status = 'Draft'", 'title' => 'Not sent yet - the client can\'t see them'],
    'Awaiting' => ['label' => 'Awaiting answer', 'icon' => 'fa-hourglass-half', 'where' => "quote_status IN ('Sent', 'Viewed') AND quote_expire >= CURDATE()", 'title' => 'Sent, not answered, and not run out'],
    'Expired' => ['label' => 'Expired', 'icon' => 'fa-hourglass-end', 'where' => "$unanswered_sql AND quote_expire < CURDATE()", 'title' => 'Ran out without an answer'],
    'Accepted' => ['label' => 'Accepted', 'icon' => 'fa-thumbs-up', 'where' => "quote_status = 'Accepted'", 'title' => 'Accepted and not invoiced yet'],
    'Invoiced' => ['label' => 'Invoiced', 'icon' => 'fa-file-invoice-dollar', 'where' => "quote_status = 'Invoiced'"],
    'Declined' => ['label' => 'Declined', 'icon' => 'fa-thumbs-down', 'where' => "quote_status = 'Declined'"],
];
$status = isset($statuses[$_GET['status'] ?? '']) ? ($_GET['status'] ?? '') : '';
$status_query = $statuses[$status]['where'] ? 'AND ' . $statuses[$status]['where'] : '';

$sortable_columns = ['quote_number' => 'quote_number', 'quote_scope' => 'quote_scope', 'client_name' => 'client_name', 'quote_date' => 'quote_date',
    'quote_expire' => 'quote_expire', 'quote_amount' => 'quote_amount', 'quote_status' => 'quote_status', 'category_name' => 'category_name'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'quote_number';
}

$scope_query = clientScopeSql('quote_client_id');
$search_query = "AND (CONCAT(quote_prefix, quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%' OR quote_amount LIKE '%$q%' OR client_name LIKE '%$q%')";
$date_query = "AND DATE(quote_date) BETWEEN '$dtf' AND '$dtt'";
$filters = "$scope_query $client_query $date_query $search_query";
$joins = "LEFT JOIN clients ON quote_client_id = client_id LEFT JOIN categories ON quote_category_id = category_id";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS quotes.*, client_name, category_name
    FROM quotes $joins
    WHERE 1 $filters $status_query
    ORDER BY {$sortable_columns[$sort]} $order, quote_id DESC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$quotes = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $quotes[] = $row;
}

$count_sql = [];
foreach ($statuses as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM quotes $joins WHERE 1 $filters"));
$counts = [];
foreach (array_keys($statuses) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

// The pipeline along the top - everything in this scope
$pipeline = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COALESCE(SUM(CASE WHEN {$statuses['Awaiting']['where']} THEN quote_amount END), 0) AS awaiting,
        COALESCE(SUM(CASE WHEN {$statuses['Awaiting']['where']} THEN quote_monthly_amount END), 0) AS awaiting_monthly,
        COALESCE(SUM(CASE WHEN quote_status = 'Accepted' THEN quote_monthly_amount END), 0) AS accepted_monthly,
        COALESCE(SUM(CASE WHEN quote_status IN ('Accepted', 'Invoiced') AND quote_date >= CURDATE() - INTERVAL 90 DAY THEN quote_monthly_amount END), 0) AS won_monthly,
        COALESCE(SUM(CASE WHEN {$statuses['Awaiting']['where']} THEN 1 END), 0) AS awaiting_count,
        COALESCE(SUM(CASE WHEN quote_status = 'Accepted' THEN quote_amount END), 0) AS accepted,
        COALESCE(SUM(CASE WHEN quote_status = 'Accepted' THEN 1 END), 0) AS accepted_count,
        COALESCE(SUM(CASE WHEN quote_status IN ('Accepted', 'Invoiced') AND quote_date >= CURDATE() - INTERVAL 90 DAY THEN quote_amount END), 0) AS won,
        COALESCE(SUM(CASE WHEN quote_status IN ('Accepted', 'Invoiced') AND quote_date >= CURDATE() - INTERVAL 90 DAY THEN 1 END), 0) AS won_count,
        COALESCE(SUM(CASE WHEN quote_status IN ('Accepted', 'Invoiced', 'Declined') AND quote_date >= CURDATE() - INTERVAL 90 DAY THEN 1 END), 0) AS answered_count
    FROM quotes WHERE 1 $scope_query $client_query"));
$win_rate = intval($pipeline['answered_count']) ? round(intval($pipeline['won_count']) / intval($pipeline['answered_count']) * 100) : null;

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$stat_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};
// "+ $2,400.00/mo" under a figure, when there is recurring in it
$stat_monthly = function ($amount) use ($stat_money) {
    return floatval($amount) != 0 ? '<span class="fs-6 fw-normal text-body-secondary ms-1">+ ' . $stat_money($amount) . '/mo</span>' : '';
};

?>

<!-- The pipeline -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Awaiting' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Awaiting'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i>Awaiting answer</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($pipeline['awaiting']) ?><?= $stat_monthly($pipeline['awaiting_monthly']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($pipeline['awaiting_count']) ?> out with clients</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Accepted' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Accepted'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-thumbs-up me-1"></i>Accepted, to invoice</div>
            <div class="itflow-billing-stat-value <?= $pipeline['accepted'] > 0 || $pipeline['accepted_monthly'] > 0 ? 'text-primary-emphasis' : '' ?>"><?= $stat_money($pipeline['accepted']) ?><?= $stat_monthly($pipeline['accepted_monthly']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($pipeline['accepted_count']) ?> ready to bill</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-trophy me-1"></i>Won, 90 days</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $stat_money($pipeline['won']) ?><?= $stat_monthly($pipeline['won_monthly']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($pipeline['won_count']) ?> quote<?= intval($pipeline['won_count']) === 1 ? '' : 's' ?> accepted</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat" title="Of the quotes dated in the last 90 days that have been answered, the share accepted">
            <div class="itflow-billing-stat-label"><i class="fas fa-percentage me-1"></i>Win rate, 90 days</div>
            <div class="itflow-billing-stat-value"><?= $win_rate === null ? '-' : $win_rate . '%' ?></div>
            <div class="itflow-billing-stat-note"><?= intval($pipeline['answered_count']) ?> answered</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-comment-dollar me-2"></i>Quotes</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/quote/quote_add.php?<?= $client_url ?>"><i class="fas fa-plus me-2"></i>New Quote</button>
                <?php } ?>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/quote/quote_export.php', ['client_id', 'q'], ['dtf' => $dtf, 'dtt' => $dtt, 'client_id' => $client ?: '']) ?>">
                            <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?= escapeHtml($status) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-4' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search number, scope, client, amount...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Quote date" aria-label="Quote date">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                JOIN quotes ON quote_client_id = client_id WHERE 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_option_id = intval($row['client_id']); ?>
                                <option <?= $client === $client_option_id ? 'selected' : '' ?> value="<?= $client_option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($statuses as $key => $s) {
                $alarm = $key === 'Accepted' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $status === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-primary' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th class="ps-3"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=quote_number&order=<?= $disp ?>">Quote <?php if ($sort == 'quote_number') { echo $order_icon; } ?></a></th>
                <?php if (!$client_url) { ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=quote_date&order=<?= $disp ?>">Date <?php if ($sort == 'quote_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=quote_expire&order=<?= $disp ?>">Valid until <?php if ($sort == 'quote_expire') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=quote_amount&order=<?= $disp ?>">Total <?php if ($sort == 'quote_amount') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=quote_status&order=<?= $disp ?>">Status <?php if ($sort == 'quote_status') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($quotes as $row) {
                $quote_id = intval($row['quote_id']);
                $row_client_id = intval($row['quote_client_id']);
                $row_number = billingNumber('quote', $row);
                $row_status = (string) $row['quote_status'];
                $row_url = "quote.php?{$client_url}quote_id=$quote_id";
                ?>
                <tr>
                    <td class="ps-3 itflow-billing-number">
                        <a class="fw-semibold" href="<?= $row_url ?>"><?= escapeHtml($row_number) ?></a>
                        <div class="small text-body-secondary text-truncate" style="max-width: 24rem;"><?= escapeHtml(implode(' · ', array_filter([$row['quote_scope'], $row['category_name']]))) ?: '&nbsp;' ?></div>
                    </td>
                    <?php if (!$client_url) { ?>
                        <td><a class="text-reset" href="quotes.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                    <?php } ?>
                    <td class="text-nowrap"><?= escapeHtml($row['quote_date']) ?></td>
                    <td class="text-nowrap">
                        <?= escapeHtml($row['quote_expire']) ?>
                        <?php $expiry_pill = quoteExpiryPill($row); if ($expiry_pill) { ?><div><?= $expiry_pill ?></div><?php } ?>
                    </td>
                    <td class="text-end itflow-money">
                        <div class="fw-semibold"><?= escapeHtml(billingMoney($row['quote_amount'], $row['quote_currency_code'])) ?></div>
                        <?php if (floatval($row['quote_monthly_amount']) != 0) { ?>
                            <div class="small text-body-secondary text-nowrap" title="Its recurring lines, as so much a month">+ <?= escapeHtml(billingMoney($row['quote_monthly_amount'], $row['quote_currency_code'])) ?>/mo</div>
                        <?php } ?>
                    </td>
                    <td class="text-nowrap"><?= quoteStatusBadge($row) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $row_url ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <a class="dropdown-item" href="post.php?export_quote_pdf=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank"><i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>Download PDF</a>
                                <?php if ($can_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($mail_ready && in_array($row_status, ['Draft', 'Sent', 'Viewed'], true)) { ?>
                                        <button type="submit" class="dropdown-item confirm-link" form="quickSendQuote" name="quote_id" value="<?= $quote_id ?>"
                                            data-confirm-title="Send <?= escapeHtml($row_number) ?> now?" data-confirm-text="It goes to the client's primary contact." data-confirm-button="Send">
                                            <i class="fas fa-fw fa-bolt text-secondary me-2"></i>Quick Send
                                        </button>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/quote/quote_email.php?quote_id=<?= $quote_id ?>">
                                            <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Send Email<span class="text-body-secondary">...</span>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row_status === 'Draft') { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_mark_sent.php?quote_id=<?= $quote_id ?>">
                                            <i class="fas fa-fw fa-check text-secondary me-2"></i>Mark Sent<span class="text-body-secondary">...</span>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row_status === 'Accepted') { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_to_invoice.php?quote_id=<?= $quote_id ?>">
                                            <i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Make Invoice<span class="text-body-secondary">...</span>
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/quote/quote_copy.php?id=<?= $quote_id ?>">
                                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                    </a>
                                <?php } ?>
                                <?php if ($can_delete) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= escapeHtml($row_number) ?> goes for good, with its lines and history.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if (!$num_rows[0]) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-comment-dollar fa-2x mb-2 opacity-50"></i>
            <div><?= $q || $status || $dtf !== '1970-01-01' ? 'No quotes match.' : 'No quotes yet.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php if ($can_edit && $mail_ready) { ?>
    <form id="quickSendQuote" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="email_quote" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<?php require_once "../includes/footer.php";
