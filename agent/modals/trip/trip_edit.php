<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$trip_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT round_trip, trip_archived_at, trip_client_id, trip_created_at, trip_date, trip_destination,
    trip_miles, trip_purpose, trip_source, trip_user_id FROM trips WHERE trip_id = $trip_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$trip_date = escapeHtml($row['trip_date']);
$trip_purpose = escapeHtml($row['trip_purpose']);
$trip_source = escapeHtml($row['trip_source']);
$trip_destination = escapeHtml($row['trip_destination']);
$trip_miles = number_format(floatval($row['trip_miles']),1);
$trip_user_id = intval($row['trip_user_id']);
$trip_created_at = escapeHtml($row['trip_created_at']);
$trip_archived_at = escapeHtml($row['trip_archived_at']);
$round_trip = escapeHtml($row['round_trip']);
$client_id = intval($row['trip_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-route me-2"></i>Editing Trip</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="trip_id" value="<?= $trip_id ?>">

        <div class="row g-2">

            <div class="mb-3 col">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= $trip_date ?>" required>
                </div>
            </div>

            <div class="mb-3 col">
                <label>Miles <strong class="text-danger">*</strong> / <span class="text-secondary">Roundtrip</span></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                    <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,1}" name="miles" value="<?= $trip_miles ?>" placeholder="0.0" required>
                        <div class="input-group-text">
                            <input class="form-check-input" type="checkbox" name="roundtrip" value="1" <?php if ($round_trip == 1) { echo "checked"; } ?>>
                        </div>
                </div>
            </div>

        </div>

        <div class="mb-3">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-map-marker-alt"></i></span>
                <input type="text" class="form-control" name="source" maxlength="200" value="<?= $trip_source ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                <select class="form-select select2" name="destination" data-tags="true" data-placeholder="- Select / Input Destination -" required>
                    <option><?= $trip_destination ?></option>
                    <?php

                    $sql_locations_select = mysqli_query($mysqli, "SELECT location_address, location_city, location_name, location_state, location_zip FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_locations_select)) {
                        $location_name = escapeHtml($row['location_name']);
                        $location_address = escapeHtml($row['location_address']);
                        $location_city = escapeHtml($row['location_city']);
                        $location_state = escapeHtml($row['location_state']);
                        $location_zip = escapeHtml($row['location_zip']);
                        $location_full_address = formatAddress($location_address, $location_city, $location_state, $location_zip, '', ' ');

                        ?>
                        <option><?= $location_full_address ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Purpose <strong class="text-danger">*</strong></label>
            <textarea rows="4" class="form-control" name="purpose" placeholder="Enter a purpose" maxlength="200" required><?= $trip_purpose ?></textarea>
        </div>

        <div class="mb-3">
            <label>Driver</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="user" required>
                    <option value="">- Driver -</option>
                    <?php

                    $sql_users = mysqli_query($mysqli, "SELECT users.user_id, user_name FROM users
                        LEFT JOIN user_settings on users.user_id = user_settings.user_id
                        WHERE (users.user_id = $trip_user_id) OR (user_archived_at IS NULL AND user_status = 1) ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_assoc($sql_users)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = escapeHtml($row['user_name']);
                        ?>
                        <option <?php if ($trip_user_id == $user_id_select) { echo "selected"; } ?> value="<?= $user_id_select ?>"><?= $user_name_select ?></option>

                    <?php } ?>

                </select>
            </div>
        </div>

        <?php if (isset($_GET['client_id'])) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php } else { ?>

            <div class="mb-3">
                <label>Client</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id">
                        <option value="">- Client (Optional) -</option>
                        <?php

                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_clients)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = escapeHtml($row['client_name']);
                            ?>
                            <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name_select ?></option>

                        <?php } ?>
                    </select>
                </div>
            </div>

        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_trip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
