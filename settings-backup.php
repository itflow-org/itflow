<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Backup Database</h3>
  </div>
  <div class="card-body">
    <center>
      <a class="btn btn-primary btn-lg p-3" href="post.php?download_database"><i class="fa fa-fw fa-4x fa-download"></i><br><br>Download Database</a>
    </center>
  </div>
</div>

<br> <br>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-key"></i> Backup Master Encryption Key</h3>
    </div>
    <div class="card-body">
        <center>
            <!--<a class="btn btn-primary btn-lg p-3" href="post.php?download_database"><i class="fa fa-fw fa-4x fa-key"></i><br><br>Get AES Master Key</a> -->
            <form action="post.php" method="POST">
                <div class="input-group col-3">
                    <input type="password" class="form-control" placeholder="Account Password" name="password" value="" required="">
                </div>
                <br>
                <div class="input-group col-3 offset-2">
                    <button class="btn btn-primary btn-lg p-3" type="submit" name="backup_master_key"><i class="fa fa-fw fa-2x fa-key"></i><br>Get Master Key</button>
                </div>
            </form>
        </center>
    </div>
</div>

<?php include("footer.php");