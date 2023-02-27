<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-file mr-2"></i>Quote Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <label>Quote Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_quote_prefix" placeholder="Quote Prefix" value="<?php echo htmlentities($config_quote_prefix); ?>" required>
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
                    <textarea class="form-control" rows="4" name="config_quote_footer"><?php echo htmlentities($config_quote_footer); ?></textarea>
                </div>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_quote_from_email" placeholder="From Email" value="<?php echo htmlentities($config_quote_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_quote_from_name" placeholder="Name" value="<?php echo htmlentities($config_quote_from_name); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_quote_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
