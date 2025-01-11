<?php

// Default Column Sortby Filter
$sort = "software_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM software
    WHERE software_template = 1 
    AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%') 
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-cube mr-2"></i>License Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareTemplateModal"><i class="fas fa-plus mr-2"></i>New License Template</button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search License Templates">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_name&order=<?php echo $disp; ?>">
                            Template <?php if ($sort == 'software_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_type&order=<?php echo $disp; ?>">
                            Type <?php if ($sort == 'software_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_license_type&order=<?php echo $disp; ?>">
                            License Type <?php if ($sort == 'software_license_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                    $software_id = intval($row['software_id']);
                    $software_name = nullable_htmlentities($row['software_name']);
                    $software_version = nullable_htmlentities($row['software_version']);
                    $software_description = nullable_htmlentities($row['software_description']);
                    $software_type = nullable_htmlentities($row['software_type']);
                    $software_license_type = nullable_htmlentities($row['software_license_type']);
                    $software_notes = nullable_htmlentities($row['software_notes']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareTemplateModal<?php echo $software_id; ?>">
                                <div class="media">
                                    <i class="fa fa-fw fa-2x fa-cube mr-3"></i>
                                    <div class="media-body">
                                        <div><?php echo "$software_name <span>$software_version</span>"; ?></div>
                                        <div><small class="text-secondary"><?php echo $software_description; ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td><?php echo $software_type; ?></td>
                        <td><?php echo $software_license_type; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareTemplateModal<?php echo $software_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if($session_user_role == 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_software=<?php echo $software_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                    require "admin_software_template_edit_modal.php";

                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "includes/filter_footer.php";
 ?>
    </div>
</div>

<?php
require_once "admin_software_template_add_modal.php";

require_once "includes/footer.php";

