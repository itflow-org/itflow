<?php

// Default Column Sortby Filter
$sort = "vendor_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM vendor_templates
    WHERE vendor_template_name LIKE '%$q%' OR vendor_template_description LIKE '%$q%' OR vendor_template_account_number LIKE '%$q%' OR vendor_template_website LIKE '%$q%' OR vendor_template_contact_name LIKE '%$q%' OR vendor_template_email LIKE '%$q%' OR vendor_template_phone LIKE '%$phone_query%' ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2">
                <i class="fas fa-fw fa-building mr-2"></i>Vendor Templates
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVendorTemplateModal">
                    <i class="fas fa-plus mr-2"></i>New Vendor Template
                </button>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Vendors Templates">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_template_name&order=<?php echo $disp; ?>">
                                Vendor <?php if ($sort == 'vendor_template_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_template_description&order=<?php echo $disp; ?>">
                                Description <?php if ($sort == 'vendor_template_description') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>Contact</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $vendor_template_id = intval($row['vendor_template_id']);
                        $vendor_template_name = nullable_htmlentities($row['vendor_template_name']);
                        $vendor_template_description = nullable_htmlentities($row['vendor_template_description']);
                        if (empty($vendor_template_description)) {
                            $vendor_template_description_display = "-";
                        } else {
                            $vendor_template_description_display = $vendor_template_description;
                        }
                        $vendor_template_account_number = nullable_htmlentities($row['vendor_template_account_number']);
                        $vendor_template_contact_name = nullable_htmlentities($row['vendor_template_contact_name']);
                        if (empty($vendor_template_contact_name)) {
                            $vendor_template_contact_name_display = "-";
                        } else {
                            $vendor_template_contact_name_display = $vendor_template_contact_name;
                        }
                        $vendor_template_phone = formatPhoneNumber($row['vendor_template_phone']);
                        $vendor_template_extension = nullable_htmlentities($row['vendor_template_extension']);
                        $vendor_template_email = nullable_htmlentities($row['vendor_template_email']);
                        $vendor_template_website = nullable_htmlentities($row['vendor_template_website']);
                        $vendor_template_hours = nullable_htmlentities($row['vendor_template_hours']);
                        $vendor_template_sla = nullable_htmlentities($row['vendor_template_sla']);
                        $vendor_template_code = nullable_htmlentities($row['vendor_template_code']);
                        $vendor_template_notes = nullable_htmlentities($row['vendor_template_notes']);

                        ?>
                        <tr>
                            <th>
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-ajax-url="ajax/ajax_vendor_template_edit.php"
                                    data-ajax-id="<?php echo $vendor_template_id; ?>"
                                    >
                                    <i class="fa fa-fw fa-building text-secondary mr-2"></i><?php echo $vendor_template_name; ?>
                                </a>
                                <?php
                                if (!empty($vendor_template_account_number)) {
                                    ?>
                                    <br>
                                    <small class="text-secondary"><?php echo $vendor_template_account_number; ?></small>
                                    <?php
                                }
                                ?>
                            </th>
                            <td><?php echo $vendor_template_description_display; ?></td>
                            <td>
                                <?php
                                if (!empty($vendor_template_contact_name)) {
                                    ?>
                                    <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $vendor_template_contact_name_display; ?>
                                    <br>
                                    <?php
                                } else {
                                    echo $vendor_template_contact_name_display;
                                }

                                if (!empty($vendor_template_phone)) { ?>
                                    <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $vendor_template_phone; ?>
                                    <br>
                                <?php }

                                if (!empty($vendor_template_email)) { ?>
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><?php echo $vendor_template_email; ?>
                                    <br>
                                <?php } ?>

                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_vendor_template_edit.php"
                                            data-ajax-id="<?php echo $vendor_template_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor=<?php echo $vendor_template_id; ?>">
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
            <?php require_once "../includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php
require_once "modals/admin_vendor_template_add_modal.php";
require_once "../includes/footer.php";
