<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-satellite-dish mr-2"></i>Telemetry</h3>
        </div>
        <div class="card-body">

            <p class="text-center">Installation ID: <strong><?php echo $installation_id; ?></strong></p>

            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <label>Telemery</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-broadcast-tower"></i></span>
                        </div>
                        <select class="form-control" name="config_telemetry">
                            <option <?php if ($config_telemetry == "0") { echo "selected"; } ?> value = "0">Disabled</option>
                            <option <?php if ($config_telemetry == "1") { echo "selected"; } ?> value = "1">Basic</option>
                            <option <?php if ($config_telemetry == "2") { echo "selected"; } ?> value = "2">Detailed</option>
                        </select>
                    </div>
                    <small class="form-text"><a href="https://docs.itflow.org/telemetry" target="_blank">Click Here for additional details regarding the information we gather <i class="fas fa-external-link-alt"></i></a></small>
                </div>

                <div class="form-group">
                    <label>Comments</label>
                    <textarea class="form-control" rows="4" name="comments" placeholder="Any Comments to send before hitting Send Telemetry Data?"></textarea>
                </div>

                <hr>

                <?php if ($config_telemetry > 0) { ?>
                    <button type="submit" name="send_telemetry_data" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send Telemetry Data</button>
                <?php } ?>
                <button type="submit" name="edit_telemetry_settings" class="btn btn-primary text-bold float-right"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
