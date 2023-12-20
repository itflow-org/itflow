<?php

// Default Column Sortby Filter
$sort = "software_name";
$order = "ASC";

require_once "inc_all_client.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM software
    LEFT JOIN logins ON login_software_id = software_id
    WHERE software_client_id = $client_id
    AND software_template = 0
    AND software_$archive_query
    AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-cube mr-2"></i>Software & Licenses</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareModal">
                    <i class="fas fa-plus mr-2"></i>New License
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                    data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal"
                        data-target="#addSoftwareFromTemplateModal">
                        <i class="fas fa-fw fa-puzzle-piece mr-2"></i>Create from Template
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportSoftwareModal">
                        <i class="fa fa-fw fa-download mr-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q"
                            value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>"
                            placeholder="Search Licenses">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <?php if($archived == 1){ ?>
                        <a href="?client_id=<?php echo $client_id; ?>&archived=0" class="btn btn-primary"><i
                                class="fa fa-fw fa-archive mr-2"></i>Archived</a>
                        <?php } else { ?>
                        <a href="?client_id=<?php echo $client_id; ?>&archived=1" class="btn btn-default"><i
                                class="fa fa-fw fa-archive mr-2"></i>Archived</a>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                    <tr>
                        <?php
                        $softwareColumns = [
                            'software_name' => 'Software',
                            'software_type' => 'Type',
                            'software_license_type' => 'License Type',
                            'software_seats' => 'Seats'
                        ];

                        foreach ($softwareColumns as $sortParam => $columnName) {
                            echo "<th><a class='text-secondary' href='?$url_query_strings_sort&sort=$sortParam&order=$disp'>$columnName</a></th>";
                        }
                        ?>
                        <th></th> <!-- Assuming this column is intentionally left empty -->
                        <th class="text-center">Action</th>
                    </tr>

                    </tr>
                </thead>
                <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $software_id = intval($row['software_id']);
                        $software_name = nullable_htmlentities($row['software_name']);
                        $software_version = nullable_htmlentities($row['software_version']);
                        $software_type = nullable_htmlentities($row['software_type']);
                        $software_license_type = nullable_htmlentities($row['software_license_type']);
                        $software_key = nullable_htmlentities($row['software_key']);
                        $software_seats = nullable_htmlentities($row['software_seats']);
                        $software_purchase = nullable_htmlentities($row['software_purchase']);
                        $software_expire = nullable_htmlentities($row['software_expire']);
                        $software_notes = nullable_htmlentities($row['software_notes']);
                        $software_created_at = nullable_htmlentities($row['software_created_at']);

                        // Get Login
                        $login_id = intval($row['login_id']);
                        $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                        $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));

                        $seat_count = 0;

                        // Asset Licenses
                        $asset_licenses_sql = mysqli_query(
                            $mysqli,
                            "SELECT asset_id FROM software_assets
                            WHERE software_id = $software_id");
                        $asset_licenses_array = array();
                        while ($row = mysqli_fetch_array($asset_licenses_sql)) {
                            $asset_licenses_array[] = intval($row['asset_id']);
                            $seat_count = $seat_count + 1;
                        }
                        $asset_licenses = implode(',', $asset_licenses_array);

                        // Contact Licenses
                        $contact_licenses_sql = mysqli_query(
                            $mysqli,
                            "SELECT contact_id FROM software_contacts
                            WHERE software_id = $software_id");
                        $contact_licenses_array = array();
                        while ($row = mysqli_fetch_array($contact_licenses_sql)) {
                            $contact_licenses_array[] = intval($row['contact_id']);
                            $seat_count = $seat_count + 1;
                        }
                        $contact_licenses = implode(',', $contact_licenses_array);



                        ?>
                    <tr>
                        <td><a class="text-dark" href="#" data-toggle="modal"
                                data-target="#editSoftwareModal<?php echo $software_id; ?>">
                                <?php echo "$software_name<br><span class='text-secondary'>
                                $software_version</span>"; ?></a>
                        </td>
                        <td><?php echo $software_type; ?></td>
                        <td><?php echo $software_license_type; ?></td>
                        <td><?php echo "$seat_count / $software_seats"; ?></td>
                        <td>
                            <?php
                                if ($login_id > 0) { ?>
                            <button type="button" class="btn btn-dark btn-sm" data-toggle="modal"
                                data-target="#viewPasswordModal<?php echo $login_id; ?>"><i
                                    class="fas fa-key"></i></button>

                            <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content bg-dark">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="fa fa-fw fa-key"></i>
                                                <?php echo $software_name; ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body bg-white">
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control"
                                                        value="<?php echo $login_username; ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control"
                                                        value="<?php echo $login_password; ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php
                                }

                                ?>
                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal"
                                        data-target="#editSoftwareModal<?php echo $software_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link"
                                        href="post.php?archive_software=<?php echo $software_id; ?>">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive and<br><small>Remove
                                            Licenses</small></a>
                                    <?php if ($session_user_role == 3) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link"
                                        href="post.php?delete_software=<?php echo $software_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete and<br><small>Remove
                                            Licenses</small></a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                        require "client_software_edit_modal.php";

                    }

                    ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php";
 ?>
    </div>
</div>

<?php
require_once "client_software_add_modal.php";

require_once "client_software_add_from_template_modal.php";

require_once "client_software_export_modal.php";

require_once "footer.php";
