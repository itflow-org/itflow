<?php

// Default Column Sortby Filter
$sort = "service_template_name";
$order = "ASC";
$start = 0;
$limit = 10;

require_once("inc_all_settings.php");

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query($mysqli, "SELECT * FROM service_templates  ORDER BY $sort $order LIMIT $start, $limit");

$num_rows = mysqli_fetch_array(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM service_templates"));

$importance_dict = array(
    1 => "Low",
    2 => "Medium",
    3 => "High",
    4 => "Critical"
);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-rocket mr-2"></i>Service Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addServiceTemplateModal"><i class="fas fa-plus mr-2"></i>New Service Template</button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Services">
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
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=service_template_name&order=<?php echo $disp; ?>">Service Name</a></th>
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=service_template_description&order=<?php echo $disp; ?>">Description</a></th>
                    <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=service_template_billable&order=<?php echo $disp; ?>">Importance</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while($row = mysqli_fetch_array($sql)){
                    $service_template_id = intval($row['service_template_id']);
                    $service_template_name = nullable_htmlentities($row['service_template_name']);
                    $service_template_category = nullable_htmlentities($row['service_template_category']);
                    $service_template_seats = intval($row['service_template_seats']);
                    $service_template_billable = intval($row['service_template_billable']);
                    $service_template_description = nullable_htmlentities($row['service_template_description']);
                    $service_template_notes = nullable_htmlentities($row['service_template_notes']);
                    $service_template_backup = intval($row['service_template_backup']);
                    $service_template_price = floatval($row['service_template_price']);
                    $service_template_cost = floatval($row['service_template_cost']);
                    $service_template_importance = intval($row['service_template_importance']);
                    $service_template_seats = intval($row['service_template_seats']);                    
                    ?>
                    <tr>
                        <td><a class="text-dark text-bold" href="#" data-toggle="modal" data-target="#editServiceTemplateModal<?php echo $service_template_id; ?>"><?php echo "$service_template_name<br><span class='text-secondary'>$service_template_category</span>"; ?></a></td>
                        <td><?php echo $service_template_description; ?></td>
                        <td><?php echo $service_template_importance ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editServiceTemplateModal<?php echo $service_template_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if($session_user_role == 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_service_template=<?php echo $service_template_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php
                    require("settings_service_template_edit_modal.php");
                }
                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<?php
require_once("settings_service_template_add_modal.php");
require_once("footer.php");
