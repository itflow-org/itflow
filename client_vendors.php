<?php

// Default Column Sortby Filter
$sort = "vendor_name";
$order = "ASC";

require_once("inc_all_client.php");

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM vendors
    WHERE vendor_client_id = $client_id
    AND vendor_archived_at IS NULL
    AND vendor_template = 0
    AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%' OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_phone LIKE '%$phone_query%') ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2">
                <i class="fas fa-fw fa-building mr-2"></i>Vendors
            </h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVendorModal">
                        <i class="fas fa-plus mr-2"></i>New Vendor
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addVendorFromTemplateModal">From Template</a>
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Vendors">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="float-right">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportVendorModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">Vendor</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_description&order=<?php echo $disp; ?>">Description</a></th>
                        <th>Contact</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $vendor_id = intval($row['vendor_id']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                        $vendor_description = nullable_htmlentities($row['vendor_description']);
                        if (empty($vendor_description)) {
                            $vendor_description_display = "-";
                        } else {
                            $vendor_description_display = $vendor_description;
                        }
                        $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
                        $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
                        if (empty($vendor_contact_name)) {
                            $vendor_contact_name_display = "-";
                        } else {
                            $vendor_contact_name_display = $vendor_contact_name;
                        }
                        $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                        $vendor_extension = nullable_htmlentities($row['vendor_extension']);
                        $vendor_email = nullable_htmlentities($row['vendor_email']);
                        $vendor_website = nullable_htmlentities($row['vendor_website']);
                        $vendor_hours = nullable_htmlentities($row['vendor_hours']);
                        $vendor_sla = nullable_htmlentities($row['vendor_sla']);
                        $vendor_code = nullable_htmlentities($row['vendor_code']);
                        $vendor_notes = nullable_htmlentities($row['vendor_notes']);
                        $vendor_template_id = intval($row['vendor_template_id']);

                        ?>
                        <tr>
                            <th>
                                <i class="fa fa-fw fa-building text-secondary"></i>
                                <a class="text-dark" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></a>
                                <?php
                                if (!empty($vendor_account_number)) { ?>
                                    <br>
                                    <small class="text-secondary"><?php echo $vendor_account_number; ?></small>
                                <?php } ?>
                            </th>
                            <td><?php echo $vendor_description_display; ?></td>
                            <td>
                                <?php
                                if (!empty($vendor_contact_name)) { ?>
                                    <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $vendor_contact_name_display; ?>
                                    <br>
                                <?php } else {
                                    echo $vendor_contact_name_display;
                                }

                                if (!empty($vendor_phone)) { ?>
                                    <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $vendor_phone; ?>
                                    <br>
                                <?php }

                                if (!empty($vendor_email)) { ?>
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><?php echo $vendor_email; ?>
                                    <br>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_vendor=<?php echo $vendor_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor=<?php echo $vendor_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("vendor_edit_modal.php");
                    } ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("vendor_add_modal.php");
require_once("vendor_add_from_template_modal.php");
require_once("client_vendor_export_modal.php");
require_once("footer.php");
