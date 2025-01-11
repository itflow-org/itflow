<?php

// Default Column Sortby Filter
$sort = "domain_name";
$order = "ASC";

require_once "includes/inc_all_reports.php";

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS domains.*,
    clients.*,
    registrar.vendor_name AS registrar_name,
    dnshost.vendor_name AS dnshost_name,
    mailhost.vendor_name AS mailhost_name,
    webhost.vendor_name AS webhost_name
    FROM domains
    LEFT JOIN clients ON client_id = domain_client_id
    LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
    LEFT JOIN vendors AS dnshost ON domains.domain_dnshost = dnshost.vendor_id
    LEFT JOIN vendors AS mailhost ON domains.domain_mailhost = mailhost.vendor_id
    LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
    WHERE domain_archived_at IS NULL
    AND (domain_name LIKE '%$q%' OR domain_description LIKE '%$q%' OR registrar.vendor_name LIKE '%$q%' OR dnshost.vendor_name LIKE '%$q%' OR mailhost.vendor_name LIKE '%$q%' OR webhost.vendor_name LIKE '%$q%' OR client_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fa fa-fw fa-globe mr-2"></i>Domain Management</h3>
            <div class="card-tools">
                <div class="btn-group">
                    
                </div>
            </div>
        </div>

        <div class="card-body">
            <form autocomplete="off">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Domains">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="btn-group float-right">
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item text-danger text-bold"
                                            type="submit" form="bulkActions" name="bulk_delete_domains">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
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

                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
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
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
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
                            $domain_expire_timestamp = strtotime($row['domain_expire']);
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

                            $domain_registrar_name = nullable_htmlentities($row['registrar_name']);
                            if ($domain_registrar_name) {
                                $domain_registrar_name_display = $domain_registrar_name;
                            } else {
                                $domain_registrar_name_display = "-";
                            }
                            $domain_webhost_name = nullable_htmlentities($row['webhost_name']);
                            $domain_dnshost_name = nullable_htmlentities($row['dnshost_name']);
                            $domain_mailhost_name = nullable_htmlentities($row['mailhost_name']);
                            $domain_created_at = nullable_htmlentities($row['domain_created_at']);
                            // Add - if empty on the table
                            $domain_registrar_name_display = $domain_registrar_name ? $domain_registrar_name : "-";
                            $domain_webhost_name_display = $domain_webhost_name ? $domain_webhost_name : "-";
                            $domain_dnshost_name_display = $domain_dnshost_name ? $domain_dnshost_name : "-";
                            $domain_mailhost_name_display = $domain_mailhost_name ? $domain_mailhost_name : "-";
                            $client_id = intval($row['client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);

                            ?>
                            <tr class="<?php echo $tr_class; ?>">
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="domain_ids[]" value="<?php echo $domain_id ?>">
                                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark">
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
                                <td><?php echo $client_name; ?></td>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php if ($session_user_role > 1) { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_domain=<?php echo $domain_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                            <?php } ?>
                                            <?php if ($session_user_role == 3) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_domain=<?php echo $domain_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
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
            <?php require_once "includes/filter_footer.php";
            ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php require_once "includes/footer.php";

