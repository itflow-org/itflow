<?php
require_once "includes/inc_all_user.php";

$row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT user_config_calendar_first_day FROM user_settings WHERE user_id = $session_user_id"));
$user_config_calendar_first_day = intval($row['user_config_calendar_first_day']);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-fw fa-globe mr-2"></i>Preferences</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <h5>Dark Mode</h5>

                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-outline-primary <?php if ($user_config_theme_dark === 0) { echo "active"; } ?>">
                    <input type="radio" name="dark_mode" id="light-mode" autocomplete="off" <?php if ($user_config_theme_dark === 0) { echo "checked"; } ?>>
                    <i class="fas fa-sun mr-2"></i>Light
                    </label>
                    <label class="btn btn-outline-dark <?php if ($user_config_theme_dark === 1) { echo "active"; } ?>">
                    <input type="radio" name="dark_mode" id="dark-mode" autocomplete="off" value="1" <?php if ($user_config_theme_dark === 1) { echo "checked"; } ?>>
                    <i class="fas fa-moon mr-2"></i>Dark
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Calendar starts on<strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                    </div>
                    <select class="form-control select2" name="calendar_first_day" required>
                        <option <?php if ($user_config_calendar_first_day == '0') { echo "selected"; } ?> value="0" >Sunday</option>
                        <option <?php if ($user_config_calendar_first_day == '1') { echo "selected"; } ?> value="1" >Monday</option>
                    </select>
                </div>
            </div>

            <?php if ($session_user_role > 1) { ?>

                <div class="form-group">
                    <div class="form-check">
                        <input disabled="disabled" type="checkbox" class="form-check-input" name="extension" id="extension" value="Yes" <?php if (isset($_COOKIE['user_extension_key'])) {echo "checked";} ?>>
                        <label class="form-check-label" for="extension">Enable Browser Extention?</label>
                        <p class="small">Not currently in use / Note: You must log out and back in again for these changes take effect.</p>
                    </div>
                </div>

            <?php } ?>

            <button type="submit" name="edit_your_user_preferences" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Save</button>

        </form>

    </div>
</div>

<?php
require_once "../includes/footer.php";
