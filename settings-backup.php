<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-database mr-2"></i>Backup</h6>
  </div>
  <div class="card-body p-5">
    <center>
      <a class="btn btn-primary btn-lg" href="post.php?download_database"><i class="fa fa-download"></i> Download Database</a>
    </center>
  </div>
</div>

<?php include("footer.php");