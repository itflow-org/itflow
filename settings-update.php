<?php include("inc_all_admin.php"); ?>

<?php

//fetch the latest code changes but don't apply them
exec("git fetch");
$latest_version = exec("gitrev-parse origin/master");
$current_version = exec("git rev-parse HEAD");

if($current_version == $latest_version){
  $update_message = "No Updates available";
}else{
  $update_message = "New Updates are Available [$latest_version]";
}

$git_log = shell_exec("git log master..origin/master --pretty=format:'<tr><td>%h</td><td>%ar</td><td>%s</td></tr>'");

?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-fw fa-arrow-alt-circle-up"></i> Update</h3>
  </div>
  <div class="card-body">
    <center>
      <?php if(!empty($git_log)){ ?>
      <a class="btn btn-primary btn-lg my-4" href="post.php?update"><i class="fas fa-fw fa-4x fa-arrow-alt-circle-up mb-1"></i><h5>Update App</h5></a>
      <?php
      }else{
      ?>
      <a class="btn btn-dark btn-lg my-4" href="post.php?update_db"><i class="fas fa-fw fa-4x fa-arrow-alt-circle-up mb-1"></i><h5>Update Database</h5></a>
      <h3 class="text-success"><i class="fas fa-check-square"></i> Latest version</h3>
      <small class="text-secondary"><?php echo $current_version; ?></small>
      <?php
      }
      ?>
    </center>

    <?php
    if(!empty($git_log)){
    ?>  
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

<?php include("footer.php");