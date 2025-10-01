<?php

require_once '../../../includes/modal_header.php';

$trip_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM trips WHERE trip_id = $trip_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$trip_date = nullable_htmlentities($row['trip_date']);
$trip_purpose = nullable_htmlentities($row['trip_purpose']);
$trip_source = nullable_htmlentities($row['trip_source']);
$trip_destination = nullable_htmlentities($row['trip_destination']);
$trip_miles = number_format(floatval($row['trip_miles']),1);
$trip_user_id = intval($row['trip_user_id']);
$trip_created_at = nullable_htmlentities($row['trip_created_at']);
$trip_archived_at = nullable_htmlentities($row['trip_archived_at']);
$round_trip = nullable_htmlentities($row['round_trip']);
$client_id = intval($row['trip_client_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-route mr-2"></i>Editing Trip</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">

        <div class="form-row">

            <div class="form-group col">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    </div>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $trip_date; ?>" required>
                </div>
            </div>

            <div class="form-group col">
                <label>Miles <strong class="text-danger">*</strong> / <span class="text-secondary">Roundtrip</span></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                    </div>
                    <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,1}" name="miles" value="<?php echo $trip_miles; ?>" placeholder="0.0" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <input type="checkbox" name="roundtrip" value="1" <?php if ($round_trip == 1) { echo "checked"; } ?>>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="form-group">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-map-marker-alt"></i></span>
                </div>
                <input type="text" class="form-control" name="source" maxlength="200" value="<?php echo $trip_source; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                </div>
                <select class="form-control select2" name="destination" data-tags="true" data-placeholder="- Select / Input Destination -" required>
                    <option><?php echo $trip_destination; ?></option>
                    <?php

                    $sql_locations_select = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_array($sql_locations_select)) {
                        $location_name = nullable_htmlentities($row['location_name']);
                        $location_address = nullable_htmlentities($row['location_address']);
                        $location_city = nullable_htmlentities($row['location_city']);
                        $location_state = nullable_htmlentities($row['location_state']);
                        $location_zip = nullable_htmlentities($row['location_zip']);
                        $location_full_address = "$location_address $location_city $location_state $location_zip";

                        ?>
                        <option><?php echo $location_full_address; ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Purpose <strong class="text-danger">*</strong></label>
            <textarea rows="4" class="form-control" name="purpose" placeholder="Enter a purpose" maxlength="200" required><?php echo $trip_purpose; ?></textarea>
        </div>

        <div class="form-group">
            <label>Driver</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="user" required>
                    <option value="">- Driver -</option>
                    <?php

                    $sql_users = mysqli_query($mysqli, "SELECT * FROM users
                        LEFT JOIN user_settings on users.user_id = user_settings.user_id
                        WHERE (users.user_id = $trip_user_id) OR (user_archived_at IS NULL AND user_status = 1) ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_array($sql_users)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']);
                        ?>
                        <option <?php if ($trip_user_id == $user_id_select) { echo "selected"; } ?> value="<?php echo $user_id_select; ?>"><?php echo $user_name_select; ?></option>

                    <?php } ?>

                </select>
            </div>
        </div>

        <?php if (isset($_GET['client_id'])) { ?>
            <input type="hidden" name="client" value="<?php echo $client_id; ?>">
        <?php } else { ?>

            <div class="form-group">
                <label>Client</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <select class="form-control select2" name="client">
                        <option value="">- Client (Optional) -</option>
                        <?php

                        $sql_clients = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at > '$trip_created_at' OR client_archived_at IS NULL ORDER BY client_archived_at ASC, client_name ASC");
                        while ($row = mysqli_fetch_array($sql_clients)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = nullable_htmlentities($row['client_name']);
                            $client_archived_at = nullable_htmlentities($row['client_archived_at']);
                            if (empty($client_archived_at)) {
                                $client_archived_display = "";
                            } else {
                                $client_archived_display = "Archived - ";
                            }
                            ?>
                            <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo "$client_archived_display$client_name_select"; ?></option>

                            <?php
                        }
                        ?>
                    </select>
                </div>
            </div>

        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_trip" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
