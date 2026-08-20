<?php
require_once "includes/inc_all_admin.php";

// Keep in step with the .theme-<name> blocks in css/itflow_custom.css - a name
// listed here with no matching block renders as an uncoloured, dead choice.
$theme_colors_array = array(
    'lightblue',
    'blue',
    'navy',
    'indigo',
    'purple',
    'fuchsia',
    'pink',
    'maroon',
    'red',
    'orange',
    'yellow',
    'olive',
    'green',
    'teal',
    'cyan',
    'gray',
    'black'
);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-paint-brush me-2"></i>Theme</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <p class="text-muted">Sets the colour of the top bar, links, buttons and highlights.</p>

            <div class="row row-cols-3 row-cols-sm-4 row-cols-lg-6 g-3">

                <?php

                foreach ($theme_colors_array as $theme_color) {

                    ?>

                    <div class="col">
                        <input type="radio" class="btn-check" onchange="this.form.submit()"
                            id="theme-<?= $theme_color ?>" name="edit_theme_settings"
                            value="<?= $theme_color ?>"
                            <?php if ($config_theme == $theme_color) { echo "checked"; } ?>>
                        <label class="itflow-theme-swatch" for="theme-<?= $theme_color ?>">
                            <?php /* the circle paints itself from --itflow-accent, so it can
                                     never drift out of step with the stylesheet */ ?>
                            <span class="itflow-theme-circle theme-<?= $theme_color ?>">
                                <i class="fas fa-circle"></i>
                                <i class="fas fa-check itflow-theme-tick"></i>
                            </span>
                            <span class="itflow-theme-name"><?= escapeHtml($theme_color) ?></span>
                        </label>
                    </div>

                <?php } ?>

            </div>

        </form>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-image me-2"></i>Favicon</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <img class="mb-3" src="<?php if(file_exists("../uploads/favicon.ico")) { echo "../uploads/favicon.ico"; } else { echo "../favicon.ico"; } ?>">

            <div class="mb-3">
                <input type="file" class="form-control" name="file" accept=".ico">
            </div>

            <hr>

            <button type="submit" name="edit_favicon_settings" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Upload Icon</button>
            <?php if(file_exists("../uploads/favicon.ico")) { ?>
            <a href="post.php?reset_favicon&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger"><i class="fas fa-redo-alt me-2"></i>Reset Favicon</a>
            <?php } ?>
        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";
