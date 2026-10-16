<?php

/*
 * Your account > Preferences - how ITFlow looks and behaves for you: light
 * or dark, the day calendars start on, and how many rows a list shows.
 */

require_once "includes/inc_all_user.php";

$me = userRow($session_user_id);
$calendar_first_day = intval($me['user_config_calendar_first_day']);
$per_page = intval($me['user_config_records_per_page']) ?: 10;

?>

<div class="card">
    <div class="card-header bg-dark">
        <h3 class="card-title"><i class="fas fa-fw fa-sliders-h me-2"></i>Preferences</h3>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="card-body">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label d-block">Theme</label>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Theme">
                        <input class="btn-check" type="radio" name="dark_mode" id="themeLight" value="0" autocomplete="off" <?= $user_config_theme_dark === 0 ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary itflow-theme-pick" for="themeLight">
                            <span class="itflow-theme-swatch is-light" aria-hidden="true"></span><i class="fas fa-sun me-2"></i>Light
                        </label>
                        <input class="btn-check" type="radio" name="dark_mode" id="themeDark" value="1" autocomplete="off" <?= $user_config_theme_dark === 1 ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary itflow-theme-pick" for="themeDark">
                            <span class="itflow-theme-swatch is-dark" aria-hidden="true"></span><i class="fas fa-moon me-2"></i>Dark
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Calendars start on</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                        <select class="form-select select2" name="calendar_first_day">
                            <option value="0" <?= $calendar_first_day === 0 ? 'selected' : '' ?>>Sunday</option>
                            <option value="1" <?= $calendar_first_day === 1 ? 'selected' : '' ?>>Monday</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Rows on a list</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-list-ol"></i></span>
                        <select class="form-select select2" name="records_per_page">
                            <?php foreach ([5, 10, 20, 50, 100, 500] as $n) { ?>
                                <option value="<?= $n ?>" <?= $per_page === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Also changed from the bottom of any list.</div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" name="edit_your_user_preferences" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save</button>
        </div>
    </form>
</div>

<?php
require_once "../../includes/footer.php";
