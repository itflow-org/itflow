<?php

// Default Column Sortby Filter
$sort = "vendor_name";
$order = "ASC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_client');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM vendors
    WHERE vendor_client_id = $client_id
    AND vendor_$archive_query
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
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addVendorFromTemplateModal">
                            <i class="fa fa-fw fa-puzzle-piece mr-2"></i>Create from Template
                        </a>
                        <?php if ($num_rows[0] > 0) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportVendorModal">
                                <i class="fa fa-fw fa-download mr-2"></i>Export
                            </a>
                        <?php } ?>
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Vendors">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="btn-group float-right">
                            <a href="?client_id=<?php echo $client_id; ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
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
                                        type="submit" form="bulkActions" name="bulk_unarchive_vendors">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_vendors">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_vendors">
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
            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">
                                    Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_description&order=<?php echo $disp; ?>">
                                    Description <?php if ($sort == 'vendor_description') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_contact_name&order=<?php echo $disp; ?>">
                                    Contact <?php if ($sort == 'vendor_contact_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_website&order=<?php echo $disp; ?>">
                                    Website <?php if ($sort == 'vendor_website') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $vendor_id = intval($row['vendor_id']);
                            $vendor_name = nullable_htmlentities($row['vendor_name']);
                            $vendor_description = nullable_htmlentities($row['vendor_description']);
                            if ($vendor_description) {
                                $vendor_description_display = $vendor_description;
                            } else {
                                $vendor_description_display = "-";
                            }
                            $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
                            if ($vendor_account_number) {
                                $vendor_account_number_display = "<div><small class='text-secondary'>Account #: $vendor_account_number</small></div>";
                            } else {
                                $vendor_account_number_display = '';
                            }
                            $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
                            if ($vendor_contact_name) {
                                $vendor_contact_name_display = $vendor_contact_name;
                            } else {
                                $vendor_contact_name_display = "-";
                            }
                            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                            $vendor_extension = nullable_htmlentities($row['vendor_extension']);
                            $vendor_email = nullable_htmlentities($row['vendor_email']);
                            $vendor_website = nullable_htmlentities($row['vendor_website']);
                            if ($vendor_website) {
                                 $vendor_website_display = "<a href='https://$vendor_website' target='_blank'>$vendor_website <i class='fa fa-external-link-alt'></i></a><button type='button' class='btn btn-sm clipboardjs' data-clipboard-text='$vendor_website'><i class='far fa-copy text-secondary'></i></button>";
                            } else {
                                $vendor_website_display = "-";
                            }
                            $vendor_hours = nullable_htmlentities($row['vendor_hours']);
                            $vendor_sla = nullable_htmlentities($row['vendor_sla']);
                            $vendor_code = nullable_htmlentities($row['vendor_code']);
                            $vendor_notes = nullable_htmlentities($row['vendor_notes']);
                            $vendor_created_at = nullable_htmlentities($row['vendor_created_at']);
                            $vendor_archived_at = nullable_htmlentities($row['vendor_archived_at']);
                            $vendor_template_id = intval($row['vendor_template_id']);

                            
                            ?>
                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="vendor_ids[]" value="<?php echo $vendor_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-building mr-3"></i>
                                            <div class="media-body">
                                                <div><?php echo $vendor_name; ?></div>
                                                <?php echo $vendor_account_number_display; ?>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $vendor_description_display; ?>
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
                                 <td><?php echo $vendor_website_display; ?></td>
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
                                                <?php if ($vendor_archived_at) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-info confirm-link" href="post.php?unarchive_vendor=<?php echo $vendor_id; ?>">
                                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                </a>
                                                <?php if ($config_destructive_deletes_enable) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor=<?php echo $vendor_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                                <?php } ?>
                                                <?php } else { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_vendor=<?php echo $vendor_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                                <?php } ?>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php

                            require "vendor_edit_modal.php";

                        } ?>

                        </tbody>
                    </table>
                </div>
            </form>
            <?php require_once "includes/filter_footer.php";
 ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "vendor_add_modal.php";

require_once "vendor_add_from_template_modal.php";

require_once "client_vendor_export_modal.php";

require_once "includes/footer.php";

