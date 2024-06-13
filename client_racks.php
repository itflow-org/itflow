<?php

// Default Column Sortby Filter
$sort = "rack_name";
$order = "ASC";

require_once "inc_all_client.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM racks
    LEFT JOIN locations ON location_id = rack_location_id 
    WHERE rack_client_id = $client_id
    AND rack_$archive_query
    AND (rack_name LIKE '%$q%' OR rack_type LIKE '%$q%' OR rack_units LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-server mr-2"></i>Network Racks</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRackModal">
                <i class="fas fa-plus mr-2"></i>New Rack
            </button>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Racks">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <a href="?client_id=<?php echo $client_id; ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                            class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                            <i class="fa fa-fw fa-archive mr-2"></i>Archived
                        </a>
                    </div>
                </div>

            </div>
        </form>
        <hr>

        <div class="row">
        
            <?php
            while ($row = mysqli_fetch_array($sql)) {
                $rack_id = intval($row['rack_id']);
                $rack_name = nullable_htmlentities($row['rack_name']);
                $rack_description = nullable_htmlentities($row['rack_description']);
                $rack_model = nullable_htmlentities($row['rack_model']);
                $rack_depth = nullable_htmlentities($row['rack_depth']);
                $rack_type = nullable_htmlentities($row['rack_type']);
                $rack_units = intval($row['rack_units']);
                $rack_photo = nullable_htmlentities($row['rack_photo']);
                $rack_physical_location = nullable_htmlentities($row['rack_physical_location']);
                $rack_notes = nullable_htmlentities($row['rack_notes']);
                $rack_location_id = nullable_htmlentities($row['rack_location_id']);
                $rack_location_name = nullable_htmlentities($row['location_name']);
                $rack_created_at = nullable_htmlentities($row['rack_created_at']);

                ?>
                <div class="col-md-6">

                    <div class="card card-dark">
                        <div class="card-header py-2">
                            <h3 class="card-title mt-2"><i class="fas fa-fw fa-server mr-2"></i><?php echo "$rack_name - $rack_units"; ?>U</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-default" data-toggle="modal" data-target="#editRackModal<?php echo $rack_id; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <?php if ($rack_photo) { ?>
                                        <img class="img-fluid" alt="rack_photo" src="<?php echo "uploads/clients/$client_id/$rack_photo"; ?>">
                                    <?php } ?>
                                    <dt>Description:</dt>
                                    <dd><?php echo $rack_description; ?></dd>
                                    <dt>Type:</dt>
                                    <dd><?php echo $rack_type; ?></dd>
                                    <dt>Model:</dt>
                                    <dd><?php echo $rack_model; ?></dd>
                                    <dt>Depth:</dt>
                                    <dd><?php echo $rack_depth; ?></dd>
                                    <dt>Location:</dt>
                                    <dd><?php echo $rack_location_name; ?></dd>
                                    <dt>Physical Location:</dt>
                                    <dd><?php echo $rack_physical_location; ?></dd>
                                    <dt>Notes:</dt>
                                    <dd><?php echo $rack_notes; ?></dd>
                                </div>
                                <div class="col-md-5">
                                    <table class="table table-bordered">
                                        <?php for ($i = $rack_units; $i >= 1; $i--) { ?>
                                        <tr>
                                            <td class="text-center"><?php echo sprintf('%02d', $i); ?></td>
                                        </tr>
                                        <?php } ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                
                </div>
                <?php require "client_rack_edit_modal.php"; ?>
            <?php } ?>

        </div>

    </div>

</div>

<?php

require_once "client_rack_add_modal.php";
require_once "footer.php";
