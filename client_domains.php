<?php

// Default Column Sortby Filter
$sort = "domain_name";
$order = "ASC";

require_once "inc_all_client.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM domains 
    LEFT JOIN vendors ON domain_registrar = vendor_id
    WHERE domain_client_id = $client_id 
    AND domain_archived_at IS NULL
    AND (domain_name LIKE '%$q%' OR vendor_name LIKE '%$q%') 
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-globe mr-2"></i>Domains</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal"><i class="fas fa-plus mr-2"></i>New Domain</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportDomainModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
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
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=domain_name&order=<?php echo $disp; ?>">Domain</a></th>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">Registrar</a></th>
                            <th>Web Host</th>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=domain_expire&order=<?php echo $disp; ?>">Expires</a></th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $domain_id = intval($row['domain_id']);
                            $domain_name = nullable_htmlentities($row['domain_name']);
                            $domain_description = nullable_htmlentities($row['domain_description']);
                            $domain_registrar = intval($row['domain_registrar']);
                            $domain_webhost = intval($row['domain_webhost']);
                            $domain_expire = nullable_htmlentities($row['domain_expire']);
                            $domain_registrar_name = nullable_htmlentities($row['vendor_name']);
                            $domain_created_at = nullable_htmlentities($row['domain_created_at']);
                            if (empty($domain_registrar_name)) {
                                $domain_registrar_name = "-";
                            }

                            $sql_domain_webhost = mysqli_query($mysqli, "SELECT vendor_name FROM vendors WHERE vendor_id = $domain_webhost");
                            $row = mysqli_fetch_array($sql_domain_webhost);
                            $domain_webhost_name = "-";
                            if ($row) {
                                $domain_webhost_name = nullable_htmlentities($row['vendor_name']);
                            }

                            ?>
                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="domain_ids[]" value="<?php echo $domain_id ?>">
                                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark" href="#" data-toggle="modal" onclick="populateDomainEditModal(<?php echo $client_id, ",", $domain_id ?>)" data-target="#editDomainModal">
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-globe mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $domain_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $domain_description; ?></small></div>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td><?php echo $domain_registrar_name; ?></td>
                                <td><?php echo $domain_webhost_name; ?></td>
                                <td><?php echo $domain_expire; ?></td>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateDomainEditModal(<?php echo $client_id, ",", $domain_id ?>)" data-target="#editDomainModal">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php if ($session_user_role == 2) { ?>
                                                <div class="dropdown-divider"></div>
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
            <?php require_once "pagination.php";
            ?>
        </div>
    </div>

<?php
require_once "client_domain_edit_modal.php";

require_once "client_domain_add_modal.php";

require_once "client_domain_export_modal.php";
?>

<script src="js/domain_edit_modal.js"></script>
<script src="js/bulk_actions.js"></script>

<?php require_once "footer.php";

