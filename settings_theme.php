<?php require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fa fa-fw fa-palette"></i> Theme</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <label>Select a Theme</label>
                <div class="form-row">

                    <?php

                    foreach ($colors_array as $color) {

                        ?>

                        <div class="col-3 text-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="theme" value="<?php echo $color; ?>" <?php if ($config_theme == $color) { echo "checked"; } ?>>
                                <label class="form-check-label">
                                    <i class="fa fa-fw fa-6x fa-circle text-<?php echo $color; ?>"></i>
                                    <br>
                                    <?php echo $color; ?>
                                </label>
                            </div>
                        </div>

                    <?php } ?>

                </div>

                <hr>

                <button type="submit" name="edit_theme_settings" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Set Theme</button>

            </form>
        </div>
    </div>

<?php include("footer.php");
