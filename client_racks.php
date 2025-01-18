<?php

// Default Column Sortby Filter
$sort = "rack_name";
$order = "ASC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_support');

// Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM racks
    LEFT JOIN locations ON location_id = rack_location_id 
    WHERE rack_client_id = $client_id
    AND rack_$archive_query
    AND (rack_name LIKE '%$q%' OR rack_type LIKE '%$q%' OR rack_units LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

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

                // Fetch rack units
                $unit_sql = mysqli_query($mysqli, "SELECT * FROM rack_units LEFT JOIN assets ON unit_asset_id = asset_id WHERE unit_rack_id = $rack_id ORDER BY unit_start_number ASC");
                $rack_units_data = [];
                while ($unit_row = mysqli_fetch_assoc($unit_sql)) {
                    $rack_units_data[] = $unit_row;
                }

                ?>
                <div class="col-md-6">

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-server mr-2"></i><?php echo "$rack_name - $rack_units"; ?>U</h3>
                            
                            <div class="card-tools">
                                <div class="dropdown dropleft">
                                    <button class="btn btn-tool" type="button" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addRackUnitModal<?php echo $rack_id; ?>">
                                            <i class="fas fa-fw fa-plus text-secondary mr-2"></i>Add Device
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#editRackModal<?php echo $rack_id; ?>">
                                            <i class="fas fa-fw fa-edit text-secondary mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_rack=<?php echo $rack_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_rack=<?php echo $rack_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if ($rack_photo) { ?>
                                        <img class="img-thumbnail mb-3" alt="rack_photo" src="<?php echo "uploads/clients/$client_id/$rack_photo"; ?>">
                                    <?php } ?>
                                    <table class="table table-sm table-borderless border">
                                        <tbody>
                                            <?php if ($rack_description) { ?>
                                                <tr>
                                                    <th colspan="2">Description</th>
                                                </tr>
                                                <tr>
                                                    <td colspan="2"><?php echo $rack_description; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_type) { ?>
                                                <tr>
                                                    <th>Type</th>
                                                    <td><?php echo $rack_type; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_model) { ?>
                                                <tr>
                                                    <th>Model</th>
                                                    <td><?php echo $rack_model; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_depth) { ?>
                                                <tr>
                                                    <th>Depth</th>
                                                    <td><?php echo $rack_depth; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_location_name) { ?>
                                                <tr>
                                                    <th>Location</th>
                                                    <td><?php echo $rack_location_name; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_physical_location) { ?>
                                                <tr>
                                                    <th>Physical Location</th>
                                                    <td><?php echo $rack_physical_location; ?></td>
                                                </tr>
                                            <?php } ?>
                                            <?php if ($rack_notes) { ?>
                                                <tr>
                                                    <th colspan="2">Notes</th>
                                                </tr>
                                                <tr>
                                                    <td><?php echo $rack_notes; ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm border">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th class="text-center px-0">U</th>
                                                <th class="text-center">Device</th>
                                                <th class=""></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php 
                                        // Keep track of which device_ids we've already printed
                                        $printedDevices = [];

                                        for ($i = $rack_units; $i >= 1; $i--) {
                                            
                                            // Find all devices that occupy the current unit $i
                                            $unit_devices = [];
                                            foreach ($rack_units_data as $unit_data) {
                                                $start = (int) $unit_data['unit_start_number'];
                                                $end   = (int) $unit_data['unit_end_number'];
                                                
                                                // If $i is between start and end, device occupies this unit
                                                if ($i >= $start && $i <= $end) {
                                                    $unit_devices[] = [
                                                        'unit_id'          => (int) $unit_data['unit_id'],
                                                        'unit_device'      => nullable_htmlentities($unit_data['unit_device']),
                                                        'unit_start_number'=> $start,
                                                        'unit_end_number'  => $end,
                                                        'asset_id'         => (int) $unit_data['asset_id'],
                                                        'asset_name'       => nullable_htmlentities($unit_data['asset_name']),
                                                        'asset_type'       => nullable_htmlentities($unit_data['asset_type']),
                                                        'icon'             => getAssetIcon($unit_data['asset_type'])
                                                    ];
                                                }
                                            }

                                            ?>
                                            <tr>
                                                <!-- Always print the left-hand U #, for reference -->
                                                <td class="px-0 text-center bg-light border">
                                                    <?php echo sprintf('%02d', $i); ?>
                                                </td>

                                                <?php
                                                // If there's exactly one device in this row, attempt to rowSpan it.
                                                // (If you can have multiple overlapping devices, you'll need more logic.)
                                                if (count($unit_devices) === 1) {

                                                    $d = $unit_devices[0];
                                                    $deviceId = $d['unit_id'];

                                                    // If not already printed, this is the *first* row of the device
                                                    if (!in_array($deviceId, $printedDevices)) {

                                                        // Mark it printed so it won't appear in later rows
                                                        $printedDevices[] = $deviceId;

                                                        // Calculate how many rows (U's) it spans
                                                        $span = $d['unit_end_number'] - $d['unit_start_number'] + 1;
                                                        if ($span < 1) {
                                                            $span = 1; // safety check
                                                        }

                                                        // Print the device cell and action cell with rowSpan
                                                        ?>
                                                        <td class="text-center align-middle" rowspan="<?php echo $span; ?>">
                                                            <!-- DEVICE INFO HERE -->
                                                            <?php 
                                                            echo $d['unit_device']; 
                                                            if (!empty($d['asset_name'])) {
                                                                $icon = $d['icon']; // already from getAssetIcon
                                                                ?>
                                                                <i class="fa fa-<?php echo $icon; ?>"></i>
                                                                <a href="client_asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $d['asset_id']; ?>" 
                                                                   target="_blank">
                                                                   <?php echo $d['asset_name']; ?>
                                                                   <i class="fas fa-external-link-alt ml-1"></i>
                                                                </a>
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                        
                                                        <td class="px-0 text-right align-middle" rowspan="<?php echo $span; ?>">
                                                            <!-- ACTION ICON / DROPDOWN -->
                                                            <div class="dropdown dropleft">
                                                                <button class="btn btn-tool" type="button" data-toggle="dropdown">
                                                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item text-danger text-bold confirm-link" 
                                                                       href="post.php?remove_rack_unit=<?php echo $d['unit_id']; ?>">
                                                                       <i class="fas fa-fw fa-minus mr-2"></i>Remove
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <?php
                                                    } else {
                                                        // This device was already spanned from a higher row, so skip printing device cell
                                                        // but we still have the left column (U #).
                                                    }

                                                } elseif (count($unit_devices) > 1) {
                                                    // If your data might have multiple devices in the same row,
                                                    // you have to decide how to handle them. 
                                                    // For now, we can fallback to older logic or display them all in one cell, etc.
                                                    ?>
                                                    <td class="text-center">
                                                        <?php foreach ($unit_devices as $d) { ?>
                                                            <?php echo $d['unit_device']; ?><br>
                                                            <?php // Could also show asset_name, etc. ?>
                                                        <?php } ?>
                                                    </td>
                                                    
                                                    <td class="text-right">
                                                        <div class="dropdown dropleft">
                                                            <button class="btn btn-tool" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-fw fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <?php foreach ($unit_devices as $d) { ?>
                                                                    <a class="dropdown-item text-danger text-bold confirm-link" 
                                                                       href="post.php?remove_rack_unit=<?php echo $d['unit_id']; ?>">
                                                                       <i class="fas fa-fw fa-minus mr-2"></i>Remove
                                                                    </a>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <?php

                                                } else {
                                                    // No device in this row
                                                    ?>
                                                    <td class="text-center">No device</td>
                                                    <td></td>
                                                    <?php
                                                }
                                                ?>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                    </div>
                
                </div>
                <?php require "modals/client_rack_edit_modal.php"; ?>
                <?php require "modals/client_rack_unit_add_modal.php"; ?>
            <?php } ?>

        </div>

    </div>

</div>

<?php

require_once "modals/client_rack_add_modal.php";
require_once "includes/footer.php";
?>
