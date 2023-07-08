<?php

// Default Column Sortby Filter
$sort = "domain_name";
$order = "ASC";

require_once("inc_all_client.php");

//Rebuild URL
$url_query_strings_sort = http_build_query(array_merge($_GET, array('sort' => $sort, 'order' => $order)));

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM domains LEFT JOIN vendors ON domain_registrar = vendor_id
  WHERE domain_client_id = $client_id AND (domain_name LIKE '%$q%' OR vendor_name LIKE '%$q%') 
  ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-globe mr-2"></i>Domains</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal"><i class="fas fa-plus mr-2"></i>New Domain</button>
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
                        <div class="float-right">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportDomainModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
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
                        $domain_registrar = intval($row['domain_registrar']);
                        $domain_webhost = intval($row['domain_webhost']);
                        $domain_expire = nullable_htmlentities($row['domain_expire']);
                        $domain_registrar_name = nullable_htmlentities($row['vendor_name']);
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
                            <td><a class="text-dark" href="#" data-toggle="modal" onclick="populateDomainEditModal(<?php echo $client_id, ",", $domain_id ?>)" data-target="#editDomainModal"><?php echo $domain_name; ?></a></td>
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
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold" href="post.php?delete_domain=<?php echo $domain_id; ?>">
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
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>
    <script src="js/domain_edit_modal.js"></script>

<?php
require_once("client_domain_edit_modal.php");
require_once("client_domain_add_modal.php");
require_once("client_domain_export_modal.php");
require_once("footer.php");
