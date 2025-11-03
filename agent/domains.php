<?php

// Default Column Sortby Filter
$sort = "domain_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND domain_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "domain_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "domain_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR domain_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND domain_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_support');

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (domain_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS domains.*, clients.*,
    registrar.vendor_id AS registrar_id,
    registrar.vendor_name AS registrar_name,
    dnshost.vendor_id AS dnshost_id,
    dnshost.vendor_name AS dnshost_name,
    mailhost.vendor_id AS mailhost_id,
    mailhost.vendor_name AS mailhost_name,
    webhost.vendor_id AS webhost_id,
    webhost.vendor_name AS webhost_name
    FROM domains
    LEFT JOIN clients ON client_id = domain_client_id
    LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
    LEFT JOIN vendors AS dnshost ON domains.domain_dnshost = dnshost.vendor_id
    LEFT JOIN vendors AS mailhost ON domains.domain_mailhost = mailhost.vendor_id
    LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
    WHERE (domains.domain_name LIKE '%$q%' OR domains.domain_description LIKE '%$q%' OR registrar.vendor_name LIKE '%$q%' OR dnshost.vendor_name LIKE '%$q%' OR mailhost.vendor_name LIKE '%$q%' OR webhost.vendor_name LIKE '%$q%' OR client_name LIKE '%$q%')
    AND $archive_query
    $access_permission_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-globe mr-2"></i>Domains</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/domain/domain_add.php?<?= $client_url ?>"><i class="fas fa-plus mr-2"></i>New Domain</button>
                    <?php if ($num_rows[0] > 0) { ?>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportDomainModal">
                                <i class="fa fa-fw fa-download mr-2"></i>Export
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form autocomplete="off">
                <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <input type="hidden" name="archived" value="<?php echo $archived; ?>">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Domains">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <?php if ($client_url) { ?>
                    <div class="col-md-2"></div>
                    <?php } else { ?>
                    <div class="col-md-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="client" onchange="this.form.submit()">
                                <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                                <?php
                                    $sql_clients_filter = mysqli_query($mysqli, "
                                    SELECT DISTINCT client_id, client_name 
                                    FROM clients
                                    JOIN domains ON domain_client_id = client_id
                                    WHERE $archive_query
                                    $access_permission_query
                                    ORDER BY client_name ASC
                                ");
                                while ($row = mysqli_fetch_array($sql_clients_filter)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);
                                ?>
                                    <option <?php if ($client == $client_id) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <?php } ?>

                    <div class="col-md-6">
                        <div class="btn-group float-right">
                            <a href="?<?php echo $client_url; ?>archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                                class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_unarchive_domains">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_domains">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_domains">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">

                <form id="bulkActions" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                    <?php if ($client_url) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <?php } ?>
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=domain_name&order=<?php echo $disp; ?>">
                                    Domain <?php if ($sort == 'domain_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=registrar_name&order=<?php echo $disp; ?>">
                                    Registrar <?php if ($sort == 'registrar_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=webhost_name&order=<?php echo $disp; ?>">
                                    Web Host <?php if ($sort == 'webhost_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=dnshost_name&order=<?php echo $disp; ?>">
                                    DNS Host <?php if ($sort == 'dnshost_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=mailhost_name&order=<?php echo $disp; ?>">
                                    Mail Host <?php if ($sort == 'mailhost_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=domain_expire&order=<?php echo $disp; ?>">
                                    Expires <?php if ($sort == 'domain_expire') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php if (!$client_url) { ?>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $domain_id = intval($row['domain_id']);
                            $domain_name = nullable_htmlentities($row['domain_name']);
                            $domain_description = nullable_htmlentities($row['domain_description']);
                            $domain_expire = nullable_htmlentities($row['domain_expire']);
                            $domain_expire_ago = timeAgo($domain_expire);
                            // Convert the expiry date to a timestamp
                            $domain_expire_timestamp = strtotime($row['domain_expire'] ?? '');
                            $current_timestamp = time(); // Get current timestamp

                            // Calculate the difference in days
                            $days_until_expiry = ($domain_expire_timestamp - $current_timestamp) / (60 * 60 * 24);

                            // Determine the class based on the number of days until expiry
                            if ($days_until_expiry <= 0) {
                                $tr_class = "table-secondary";
                            } elseif ($days_until_expiry <= 14) {
                                $tr_class = "table-danger";
                            } elseif ($days_until_expiry <= 90) {
                                $tr_class = "table-warning";
                            } else {
                                $tr_class = '';
                            }
                            $domain_registrar_id = intval($row['registrar_id']);
                            $domain_webhost_id = intval($row['webhost_id']);
                            $domain_dnshost_id = intval($row['dnshost_id']);
                            $domain_mailhost_id = intval($row['mailhost_id']);
                            $domain_registrar_name = nullable_htmlentities($row['registrar_name']);
                            $domain_webhost_name = nullable_htmlentities($row['webhost_name']);
                            $domain_dnshost_name = nullable_htmlentities($row['dnshost_name']);
                            $domain_mailhost_name = nullable_htmlentities($row['mailhost_name']);
                            $domain_created_at = nullable_htmlentities($row['domain_created_at']);
                            $domain_archived_at = nullable_htmlentities($row['domain_archived_at']);
                            $client_id = intval($row['domain_client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);
                            // Add - if empty on the table
                            $domain_registrar_name_display = $domain_registrar_name ? "
                                <a class='ajax-modal' href='#' data-modal-url='modals/vendor/vendor_details.php?id=$domain_registrar_id'>
                                    $domain_registrar_name
                                </a>" : "-";
                            $domain_webhost_name_display = $domain_webhost_name ? "
                                <a class='ajax-modal' href='#' data-modal-url='modals/vendor/vendor_details.php?id=$domain_webhost_id'>
                                    $domain_webhost_name
                                </a>" : "-";
                            $domain_dnshost_name_display = $domain_dnshost_name ? "
                                <a class='ajax-modal' href='#' data-modal-url='modals/vendor/vendor_details.php?id=$domain_dnshost_id'>
                                    $domain_dnshost_name
                                </a>" : "-";
                            $domain_mailhost_name_display = $domain_mailhost_name ? "
                                <a class='ajax-modal' href='#' data-modal-url='modals/vendor/vendor_details.php?id=$domain_mailhost_id'>
                                    $domain_mailhost_name
                                </a>" : "-";

                            ?>
                            <tr class="<?php echo $tr_class; ?>">
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="domain_ids[]" value="<?php echo $domain_id ?>">
                                    </div>
                                </td>
                                <td class="">
                                    <a class="text-dark ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="modals/domain/domain_edit.php?<?= $client_url ?>&id=<?= $domain_id ?>">
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-globe mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $domain_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $domain_description; ?></small></div>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td><?php echo $domain_registrar_name_display; ?></td>
                                <td><?php echo $domain_webhost_name_display; ?></td>
                                <td><?php echo $domain_dnshost_name_display; ?></td>
                                <td><?php echo $domain_mailhost_name_display; ?></td>
                                <td>
                                    <div><?php echo $domain_expire; ?></div>
                                    <div><small><?php echo $domain_expire_ago; ?></small></div>
                                </td>
                                <?php if (!$client_url) { ?>
                                <td><a href="domains.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                <?php } ?>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-size="lg"
                                                data-modal-url="modals/domain/domain_edit.php?<?= $client_url ?>&id=<?= $domain_id ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php if ($session_user_role == 3) { ?>
                                                <?php if ($domain_archived_at) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_domain=<?php echo $domain_id; ?>">
                                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_domain=<?php echo $domain_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                                <?php } else { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_domain=<?php echo $domain_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
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

                </form>
            </div>
            <?php require_once "../includes/filter_footer.php";
            ?>
        </div>
    </div>

<?php
require_once "modals/domain/domain_export.php";
?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";

