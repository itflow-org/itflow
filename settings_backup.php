<?php include("inc_all_settings.php"); ?>

<div class="card card-dark mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Download Database</h3>
    </div>
    <div class="card-body">
        <center>
            <a class="btn btn-primary btn-lg p-3" href="post.php?download_database"><i class="fa fa-fw fa-4x fa-download"></i><br><br>Download</a>
        </center>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-key"></i> Backup Master Encryption Key</h3>
    </div>
    <div class="card-body">
        <center>
            <form action="post.php" method="POST">
                <div class="input-group col-4">
                    <div class="input-group-prepend">
                        <input type="password" class="form-control" placeholder="Enter your account password" name="password" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit" name="backup_master_key"><i class="fa fa-fw fa-key"></i> Get Master Key</button>
                </div>
            </form>
        </center>
    </div>
</div>

<?php include("footer.php");