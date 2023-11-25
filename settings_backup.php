<?php
require_once "inc_all_settings.php";
 ?>

    <div class="card card-dark mb-3">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Download Database</h3>
        </div>
        <div class="card-body" style="text-align: center;">
            <a class="btn btn-primary btn-lg p-3" href="post.php?download_database&csrf_token=<?php echo $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-4x fa-download"></i><br><br>Download</a>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-key mr-2"></i>Backup Master Encryption Key</h3>
        </div>
        <div class="card-body">
            <div class="card-body">
                <form action="post.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                    <div class="row d-flex justify-content-center">
                        <div class="input-group col-4">
                            <div class="input-group-prepend">
                                <input type="password" class="form-control" placeholder="Enter your account password" name="password" autocomplete="new-password" required>
                            </div>
                            <button class="btn btn-primary" type="submit" name="backup_master_key"><i class="fas fa-fw fa-key mr-2"></i>Get Master Key</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
require_once "footer.php";

