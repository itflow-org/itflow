<div class="modal" id="editTripModal<?php echo $trip_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-route mr-2"></i>Editing Trip</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
                    <input type="hidden" name="roundtrip" value="0">

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
                                <input type="number" step="0.1" min="0" class="form-control" name="miles" value="<?php echo $trip_miles; ?>" required>
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
                            <input type="text" class="form-control" name="source" value="<?php echo $trip_source; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="destination" value="<?php echo $trip_destination; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Purpose <strong class="text-danger">*</strong></label>
                        <textarea rows="4" class="form-control" placeholder="Enter a purpose" name="purpose" required><?php echo $trip_purpose; ?></textarea>
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

                                // WIP Need to only show users within the session company
                                $sql_trips = mysqli_query($mysqli, "SELECT * FROM users ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_array($sql_trips)) {
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

                                    $sql_clients = mysqli_query($mysqli, "SELECT * FROM clients ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_array($sql_clients)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name_select = nullable_htmlentities($row['client_name']);
                                        ?>
                                        <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                    <?php } ?>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_trip" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
