<?php
require_once "includes/inc_all_admin.php";


$sql = mysqli_query($mysqli,"SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");

$row = mysqli_fetch_array($sql);
$company_locale = nullable_htmlentities($row['company_locale']);
$company_currency = nullable_htmlentities($row['company_currency']);

// Get a list of all available timezones
$timezones = DateTimeZone::listIdentifiers();

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-globe mr-2"></i>Localization</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Language <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                        </div>
                        <select class="form-control select2" name="locale" required>
                            <option value="">- Select a Locale -</option>
                            <?php foreach($locales_array as $locale_code => $locale_name) { ?>
                                <option <?php if ($company_locale == $locale_code) { echo "selected"; } ?> value="<?php echo $locale_code; ?>"><?php echo $locale_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Currency <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                        </div>
                        <select class="form-control select2" name="currency_code" required>
                            <option value="">- Currency -</option>
                            <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                <option <?php if ($company_currency == $currency_code) { echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Timezone <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                        </div>
                        <select class="form-control select2" name="timezone" required>
                            <option value="">- Select a Timezone -</option>
                            <?php foreach ($timezones as $tz) { ?>
                                <option <?php if ($config_timezone == $tz) { echo "selected"; } ?> value="<?php echo $tz; ?>"><?php echo $tz; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_localization" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "includes/footer.php";

