<?php

// Default Column Sortby Filter
$sort = "certificate_name";
$order = "ASC";

require_once "inc_all_client.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM certificates 
  WHERE certificate_archived_at IS NULL
  AND certificate_client_id = $client_id 
  AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_issued_by LIKE '%$q%') 
  ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-lock mr-2"></i>Certificates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCertificateModal"><i class="fas fa-plus mr-2"></i>New Certificate</button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportCertificateModal">
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
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Certificates">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <div class="dropdown" id="multiActionButton" hidden>
                            <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-list mr-2"></i>Selected (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="multi_actions" name="bulk_delete_certificates">
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

            <form id="multi_actions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <td class="pr-0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" onclick="checkAll(this)">
                            </div>
                        </td>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_name&order=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_domain&order=<?php echo $disp; ?>">Domain</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_issued_by&order=<?php echo $disp; ?>">Issued By</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_expire&order=<?php echo $disp; ?>">Expire</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $certificate_id = intval($row['certificate_id']);
                        $certificate_name = nullable_htmlentities($row['certificate_name']);
                        $certificate_domain = nullable_htmlentities($row['certificate_domain']);
                        $certificate_issued_by = nullable_htmlentities($row['certificate_issued_by']);
                        $certificate_expire = nullable_htmlentities($row['certificate_expire']);
                        $certificate_created_at = nullable_htmlentities($row['certificate_created_at']);

                        ?>
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="certificate_ids[]" value="<?php echo $certificate_id ?>">
                                </div>
                            </td>

                            <td><a class="text-dark" href="#" data-toggle="modal" onclick="populateCertificateEditModal(<?php echo $client_id, ",", $certificate_id ?>)" data-target="#editCertificateModal"><?php echo $certificate_name; ?></a></td>

                            <td><?php echo $certificate_domain; ?></td>

                            <td><?php echo $certificate_issued_by; ?></td>

                            <td><?php echo $certificate_expire; ?></td>

                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateCertificateEditModal(<?php echo $client_id, ",", $certificate_id ?>)" data-target="#editCertificateModal">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="post.php?archive_certificate=<?php echo $certificate_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold" href="post.php?delete_certificate=<?php echo $certificate_id; ?>">
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
require_once "client_certificate_edit_modal.php";

require_once "client_certificate_add_modal.php";

require_once "client_certificate_export_modal.php";

?>

<script src="js/certificate_edit_modal.js"></script>
<script src="js/multi_actions.js"></script>
<script src="js/certificate_fetch_ssl.js"></script>

<?php require_once "footer.php";
 ?>
