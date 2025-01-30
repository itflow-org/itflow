<?php

// Default Column Sortby/Order Filter
$sort = "vendor_name";
$order = "ASC";

require_once "inc_all.php";

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

// Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM vendors
    WHERE vendor_client_id = 0
    AND vendor_template = 0
    AND DATE(vendor_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%' OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_phone LIKE '%$phone_query%')
    AND vendor_$archive_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-building mr-2"></i><?php echo lang('vendors'); ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVendorModal"><i class="fas fa-plus mr-2"><?php echo lang('new_vendor'); ?></button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="<?php echo lang('search_vendors'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="btn-toolbar float-right">
                        <div class="btn-group mr-2">
                            <a href="?<?php echo $url_query_strings_sort ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                               class="btn btn-<?php if ($archived == 1) { echo "primary"; } else { echo "default"; } ?>">
                               <i class="fa fa-fw fa-archive mr-2"></i><?php echo lang('archived'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('canned_date'); ?></label>
                            <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom"><?php echo lang('custom'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today"><?php echo lang('today'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday"><?php echo lang('yesterday'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek"><?php echo lang('this_week'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek"><?php echo lang('last_week'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth"><?php echo lang('this_month'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth"><?php echo lang('last_month'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear"><?php echo lang('this_year'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear"><?php echo lang('last_year'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('date_from'); ?></label>
                            <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('date_to'); ?></label>
                            <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-hover table-borderless">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">
                            <?php echo lang('vendor'); ?> <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_description&order=<?php echo $disp; ?>">
                            <?php echo lang('description'); ?> <?php if ($sort == 'vendor_description') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_contact_name&order=<?php echo $disp; ?>">
                            <?php echo lang('contact'); ?> <?php if ($sort == 'vendor_contact_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center"><?php echo lang('action'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql)) {
                    $vendor_id = intval($row['vendor_id']);
                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                    $vendor_description = nullable_htmlentities($row['vendor_description']);
                    $vendor_description_display = empty($vendor_description) ? "-" : $vendor_description;
                    $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
                    $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
                    $vendor_contact_name_display = empty($vendor_contact_name) ? "-" : $vendor_contact_name;
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
                            <a class="text-dark" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></a>
                            <?php if (!empty($vendor_account_number)) { ?>
                                <br>
                                <small class="text-secondary"><?php echo $vendor_account_number; ?></small>
                            <?php } ?>
                        </th>
                        <td><?php echo $vendor_description_display; ?></td>
                        <td>
                            <?php if (!empty($vendor_contact_name)) { ?>
                                <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $vendor_contact_name; ?><br>
                            <?php } else {
                                echo $vendor_contact_name_display;
                            }

                            if (!empty($vendor_phone)) { ?>
                                <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $vendor_phone; ?> <?php if (!empty($vendor_extension)) { echo "x$vendor_extension"; } ?><br>
                            <?php }

                            if (!empty($vendor_email)) { ?>
                                <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><?php echo $vendor_email; ?><br>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i><?php echo lang('edit'); ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_vendor=<?php echo $vendor_id; ?>">
                                        <i class="fas fa-fw fa-archive mr-2"></i><?php echo lang('archive'); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                    require "vendor_edit_modal.php";
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php"; ?>
    </div>
</div>

<?php
require_once "vendor_add_modal.php";
require_once "footer.php";
?>
