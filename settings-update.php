<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

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
    <h3 class="card-title"><i class="fa fa-fw fa-wrench"></i> Update</h3>
  </div>
  <div class="card-body">
    <center>
      <h5><small class="text-secondary">Current Version</small><br><?php echo $current_version; ?></h5>
      <?php if(!empty($git_log)){ ?>
      <a class="btn btn-primary btn-lg my-4" href="post.php?update"><i class="fa fa-fw fa-4x fa-check-square"></i><br>Update<br>NOW</a>
      <?php
      }else{
      ?>
      <h3 class="text-success">Congratulations you are up to date!</h3>
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