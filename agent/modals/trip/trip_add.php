<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-route me-2"></i>New Trip</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if ($client_id) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php }else{ ?>

            <div class="mb-3">
                <label>Client</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="0">- Client (Optional) -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at is NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name = escapeHtml($row['client_name']);
                            ?>
                            <option value="<?= $client_id_select ?>"><?= $client_name ?></option>

                            <?php
                        }
                        ?>
                    </select>
                </div>
            </div>

        <?php } ?>

        <div class="row g-2">
            <div class="mb-3 col">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d") ?>" required>
                </div>
            </div>

            <div class="mb-3 col">
                <label>Miles <strong class="text-danger">*</strong> / <span class="text-secondary">Roundtrip</span></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                    <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,1}" name="miles" placeholder="0.0" required autofocus>
                        <div class="input-group-text">
                            <input class="form-check-input" type="checkbox" name="roundtrip" value="1">
                        </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label>Location <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                <input type="text" class="form-control" name="source" placeholder="Enter your starting location" maxlength="200" required>
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
                <select class="form-select select2" name="destination" data-tags="true" data-placeholder="- Select or Enter a Destination -" required>
                    <option value=""></option>
                    <?php
                    if ($client_id) {
                        $sql_locations = mysqli_query($mysqli, "SELECT location_address, location_city, location_name, location_state, location_zip FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_locations)) {
                        $location_name = escapeHtml($row['location_name']);
                        $location_address = escapeHtml($row['location_address']);
                        $location_city = escapeHtml($row['location_city']);
                        $location_state = escapeHtml($row['location_state']);
                        $location_zip = escapeHtml($row['location_zip']);
                        ?>
                        <option><?= formatAddress($location_address, $location_city, $location_state, $location_zip, '', ' ') ?></option>
                        <?php
                    }
                } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Purpose <strong class="text-danger">*</strong></label>
            <textarea rows="4" class="form-control" placeholder="Enter a purpose" name="purpose" maxlength="200" required></textarea>
        </div>

        <div class="mb-3">
            <label>Driver</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="user" required>
                    <option value="">- Driver -</option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users
                        WHERE user_type = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $user_id = intval($row['user_id']);
                        $user_name = escapeHtml($row['user_name']);
                        ?>
                        <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"><?= $user_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_trip" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
