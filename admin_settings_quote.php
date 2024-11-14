<?php
require_once "inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-comment-dollar mr-2"></i>Quote Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Quote Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_quote_prefix" placeholder="Quote Prefix" value="<?php echo nullable_htmlentities($config_quote_prefix); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_quote_next_number" placeholder="Next Quote Number" value="<?php echo intval($config_quote_next_number); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quote Footer</label>
                    <textarea class="form-control" rows="4" name="config_quote_footer"><?php echo nullable_htmlentities($config_quote_footer); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Email address to notify when quotes are accepted/declined <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_quote_notification_email" placeholder="Address to notify for quote accept/declines, leave bank for none" value="<?php echo nullable_htmlentities($config_quote_notification_email); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_quote_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "footer.php";

