<?php $sql = mysqli_query($mysqli,"SELECT * FROM files WHERE client_id = $client_id ORDER BY file_id DESC"); ?>
<h3>Pictures</h3>
<hr>
<div class="row">

  <?php
  
  while($row = mysqli_fetch_array($sql)){
    $file_id = $row['file_id'];
    $file_name = $row['file_name'];
  
    ?>
    <div class="col-2 mb-3">
      <?php echo $file_name; ?>
      <a href="#" data-toggle="modal" data-target="#viewClientFileModal<?php echo $file_id; ?>">
        <img class="img-fluid" src="<?php echo $file_name; ?>">
      </a>
    </div>
   
    <?php 
    include("view_client_file_modal.php");
     } 
    ?>

</div>