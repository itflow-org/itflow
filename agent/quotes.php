<?php

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

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS category_id, category_name, client_currency_code, client_id, client_name, client_net_terms,
        quote_amount, quote_created_at, quote_currency_code, quote_date, quote_discount_amount,
        quote_expire, quote_id, quote_number, quote_prefix, quote_scope, quote_status FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN categories ON quote_category_id = category_id
    WHERE (CONCAT(quote_prefix,quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%' OR quote_amount LIKE '%$q%' OR client_name LIKE '%$q%')
    AND DATE(quote_date) BETWEEN '$dtf' AND '$dtt'
    " . clientScopeSql('quote_client_id') . "
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-comment-dollar me-2"></i>Quotes</h3>
        <div class="card-tools">
        <?php if (lookupUserPermission("module_sales") >= 2) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/quote/quote_add.php?<?= $client_url ?>"><i class="fas fa-plus me-2"></i>New Quote</button>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/quote/quote_export.php', ['client_id', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Quotes">
                            <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="float-end">

                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo"show"; } ?>" id="advancedFilter">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div>
                            <label class="form-label">Date range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date']) ?? '' ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
            <tr>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_number&order=<?= $disp ?>">
                        Number <?php if ($sort == 'quote_number') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_scope&order=<?= $disp ?>">
                        Scope <?php if ($sort == 'quote_scope') { echo $order_icon; } ?>
                    </a>
                </th>
                <?php if (!$client_url) { ?>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                        Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <?php } ?>
                <th class="text-end">
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_amount&order=<?= $disp ?>">
                        Amount <?php if ($sort == 'quote_amount') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_date&order=<?= $disp ?>">
                        Date <?php if ($sort == 'quote_number') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_expire&order=<?= $disp ?>">
                        Expire <?php if ($sort == 'quote_number') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                        Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=quote_status&order=<?= $disp ?>">
                        Status <?php if ($sort == 'quote_status') { echo $order_icon; } ?>
                    </a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $quote_id = intval($row['quote_id']);
                $quote_prefix = escapeHtml($row['quote_prefix']);
                $quote_number = intval($row['quote_number']);
                $quote_scope = escapeHtml($row['quote_scope']);
                if (empty($quote_scope)) {
                    $quote_scope_display = "-";
                } else {
                    $quote_scope_display = $quote_scope;
                }
                $quote_status = escapeHtml($row['quote_status']);
                $quote_date = escapeHtml($row['quote_date']);
                $quote_expire = escapeHtml($row['quote_expire']);
                $quote_amount = floatval($row['quote_amount']);
                $quote_discount = floatval($row['quote_discount_amount']);
                $quote_currency_code = escapeHtml($row['quote_currency_code']);
                $quote_created_at = escapeHtml($row['quote_created_at']);
                $client_id = intval($row['client_id']);
                $client_name = escapeHtml($row['client_name']);
                $client_currency_code = escapeHtml($row['client_currency_code']);
                $category_id = intval($row['category_id']);
                $category_name = escapeHtml($row['category_name']);
                $client_net_terms = intval($row['client_net_terms']);
                if ($client_net_terms == 0) {
                    $client_net_terms = $config_default_net_terms;
                }

                if ($quote_status == "Sent") {
                    $quote_badge_color = "warning";
                } elseif ($quote_status == "Viewed") {
                    $quote_badge_color = "primary";
                } elseif ($quote_status == "Accepted") {
                    $quote_badge_color = "success";
                } elseif ($quote_status == "Declined") {
                    $quote_badge_color = "danger";
                } elseif ($quote_status == "Invoiced") {
                    $quote_badge_color = "info";
                } else {
                    $quote_badge_color = "secondary";
                }

                ?>

                <tr>
                    <td class="text-bold">
                        <a href="quote.php?client_id=<?= $client_id ?>&quote_id=<?= $quote_id ?>">
                            <?= "$quote_prefix$quote_number" ?>
                        </a>
                    </td>
                    <td><?= $quote_scope_display ?></td>
                    <?php if (!$client_url) { ?>
                    <td class="text-bold">
                        <a href="quotes.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
                    </td>
                    <?php } ?>
                    <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) ?></td>
                    <td><?= $quote_date ?></td>
                    <td><?= $quote_expire ?></td>
                    <td><?= $category_name ?></td>
                    <td>
                        <span class="p-2 badge text-bg-<?= $quote_badge_color ?>">
                            <?= $quote_status ?>
                        </span>
                    </td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/quote/quote_copy.php?id=<?= $quote_id ?>">
                                        <i class="fas fa-fw fa-copy me-2"></i>Copy
                                    </a>
                                    <?php if (!empty($config_smtp_provider)) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="post.php?email_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-paper-plane me-2"></i>Email
                                        </a>
                                    <?php } ?>
                                    <?php if (lookupUserPermission("module_sales") >= 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>

                <?php

            }

            ?>

            </tbody>
        </table>
    </div>
    <?php require_once "../includes/filter_footer.php";
?>
</div>

<?php
require_once "../includes/footer.php";
