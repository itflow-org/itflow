<?php
require_once "includes/inc_all_admin.php";

$theme_colors_array = array (
    'lightblue',
    'blue',
    'cyan',
    'green',
    'olive',
    'teal',
    'red',
    'maroon',
    'pink',
    'purple',
    'indigo',
    'fuchsia',
    'yellow',
    'orange',
    'yellow',
    'black',
    'navy',
    'gray'
);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-paint-brush mr-2"></i>Theme</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <label>Select a Theme</label>
            <div class="form-row">

                <?php

                foreach ($theme_colors_array as $theme_color) {

                    ?>

                    <div class="col-4 text-center mb-3">
                        <div class="form-group">
                            <div class="custom-control custom-radio">
                                <input class="custom-control-input" type="radio" onchange="this.form.submit()" id="customRadio<?= $theme_color ?>" name="edit_theme_settings" value="<?= $theme_color ?>" <?php if ($config_theme == $theme_color) { echo "checked"; } ?>>
                                <label for="customRadio<?= $theme_color ?>" class="custom-control-label">
                                    <i class="fa fa-fw fa-6x fa-circle text-<?= $theme_color ?>"></i>
                                    <br>
                                    <?= $theme_color ?>
                                </label>
                            </div>
                        </div>
                    </div>

                <?php } ?>

            </div>

        </form>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-image mr-2"></i>Favicon</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <img class="mb-3" src="<?php if(file_exists("../uploads/favicon.ico")) { echo "../uploads/favicon.ico"; } else { echo "../favicon.ico"; } ?>">

            <div class="form-group">
                <input type="file" class="form-control-file" name="file" accept=".ico">
            </div>

            <hr>

            <button type="submit" name="edit_favicon_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Upload Icon</button>
            <?php if(file_exists("../uploads/favicon.ico")) { ?>
            <a href="post.php?reset_favicon&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger"><i class="fas fa-redo-alt mr-2"></i>Reset Favicon</a>
            <?php } ?>
        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";
