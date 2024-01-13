<?php
require_once "inc_all_user.php";

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-cog mr-2"></i>Browser Extension</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <?php if ($session_user_role > 1) { ?>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="extension" id="extension" value="Yes" <?php if (isset($_COOKIE['user_extension_key'])) {echo "checked";} ?>>
                        <label class="form-check-label" for="extension">Enable Browser Extention?</label>
                        <p class="small">Note: You must log out and back in again for these changes take effect.</p>
                    </div>
                </div>

            <?php } ?>

            <button type="submit" name="edit_your_user_browser_extension" class="btn btn-primary btn-block mt-3"><i class="fas fa-check mr-2"></i>Save</button>

        </form>

    </div>
</div>

<?php
require_once "footer.php";
