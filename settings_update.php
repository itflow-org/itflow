<?php
require_once("inc_all_settings.php");
require_once("database_version.php");
require_once("config.php");

// Fetch the latest code changes but don't apply them
exec("git fetch", $output, $result);
$latest_version = exec("git rev-parse origin/$repo_branch");
$current_version = exec("git rev-parse HEAD");

if ($current_version == $latest_version) {
    $update_message = "No Updates available";
} else {
    $update_message = "New Updates are Available [$latest_version]";
}

$git_log = shell_exec("git log $repo_branch..origin/$repo_branch --pretty=format:'<tr><td>%h</td><td>%ar</td><td>%s</td></tr>'");

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-download mr-2"></i>Update</h3>
        </div>
        <div class="card-body" style="text-align: center;">

            <!-- Check if git fetch result is zero (success) -->
            <?php if ($result !== 0) { ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Warning: Could not find execute 'git fetch'. Do you have git installed?</strong>
                </div>
            <?php } ?>

            <?php if (!empty($git_log)) { ?>
                <a class="btn btn-primary btn-lg my-4" href="post.php?update"><i class="fas fa-fw fa-4x fa-download mb-1"></i><h5>Update App</h5></a>
                <hr>

            <?php } else {
                if (LATEST_DATABASE_VERSION > CURRENT_DATABASE_VERSION) { ?>
                    <div class="alert alert-warning" role="alert">
                        <strong>Ensure you have a current <a href="https://docs.itflow.org/backups">app & database backup</a> before updating!</strong>
                    </div>
                    <br>
                    <a class="btn btn-dark btn-lg my-4" href="post.php?update_db"><i class="fas fa-fw fa-4x fa-download mb-1"></i><h5>Update Database</h5></a>
                    <br>
                    <small class="text-secondary">Current DB Version: <?php echo CURRENT_DATABASE_VERSION; ?></small>
                    <br>
                    <small class="text-secondary">Latest DB Version: <?php echo LATEST_DATABASE_VERSION; ?></small>
                <?php } else { ?>
                    <h3 class="text-success text-bold">Congratulations!<br><i class="far fa-3x text-dark fa-smile-wink"></i><br><small>You are on the latest version!</small></h3>
                    <p class="text-secondary">Current Database Version:<br><strong><?php echo CURRENT_DATABASE_VERSION; ?></strong></p>
                    <p class="text-secondary">Current App Version:<br><strong><?php echo $current_version; ?></strong></p>
                <?php }
            }

            if (!empty($git_log)) { ?>
                <table class="table ">
                    <thead>
                    <tr>
                        <th>Commit</th>
                        <th>When</th>
                        <th>Description</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    echo $git_log;
                    ?>
                    </tbody>
                </table>
                <?php
            }

            ?>

        </div>
    </div>

<?php

require_once("footer.php");
