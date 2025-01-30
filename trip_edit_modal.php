<?php
session_start();
// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);
?>

<div class="modal" id="editTripModal<?php echo $trip_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-route mr-2"></i><?php echo lang('editing_trip'); ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">

                    <div class="form-row">

                        <div class="form-group col">
                            <label><?php echo lang('date'); ?> <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $trip_date; ?>" required>
                            </div>
                        </div>

                        <div class="form-group col">
                            <label><?php echo lang('miles'); ?> <strong class="text-danger">*</strong> / <span class="text-secondary"><?php echo lang('roundtrip'); ?></span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-bicycle"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,1}" name="miles" value="<?php echo $trip_miles; ?>" placeholder="<?php echo lang('miles_placeholder'); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <input type="checkbox" name="roundtrip" value="1" <?php if ($round_trip == 1) { echo "checked"; } ?>>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label><?php echo lang('location'); ?> <strong class="text-danger">*</strong></label>
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
                            <select class="form-control select2" name="destination" data-tags="true" data-placeholder="<?php echo lang('destination_placeholder'); ?>" required>
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
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo lang('purpose'); ?> <strong class="text-danger">*</strong></label>
                        <textarea rows="4" class="form-control" placeholder="<?php echo lang('purpose_placeholder'); ?>" name="purpose" required><?php echo $trip_purpose; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><?php echo lang('driver'); ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="user" required>
                                <option value="">- <?php echo lang('driver_placeholder'); ?> -</option>
                                <?php
                                $sql_users = mysqli_query($mysqli, "SELECT * FROM users 
                                    LEFT JOIN user_settings on users.user_id = user_settings.user_id 
                                    WHERE (users.user_id = $trip_user_id) OR (user_archived_at IS NULL AND user_status = 1) ORDER BY user_name ASC");
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
                            <label><?php echo lang('client'); ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                </div>
                                <select class="form-control select2" name="client">
                                    <option value="">- <?php echo lang('client_placeholder'); ?> -</option>
                                    <?php
                                    $sql_clients = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at > '$trip_created_at' OR client_archived_at IS NULL ORDER BY client_archived_at ASC, client_name ASC");
                                    while ($row = mysqli_fetch_array($sql_clients)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name_select = nullable_htmlentities($row['client_name']);
                                        $client_archived_at = nullable_htmlentities($row['client_archived_at']);
                                        $client_archived_display = empty($client_archived_at) ? "" : lang('archived') . " - ";
                                        ?>
                                        <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>">
                                            <?php echo "$client_archived_display$client_name_select"; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_trip" class="btn btn-primary text-bold">
                        <i class="fa fa-check mr-2"></i><?php echo lang('save'); ?>
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fa fa-times mr-2"></i><?php echo lang('cancel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
