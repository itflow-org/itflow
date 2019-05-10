<?php $sql = mysqli_query($mysqli,"SELECT * FROM files WHERE client_id = $client_id ORDER BY file_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-paperclip"></i> Files</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientFileModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

    <h3>Pictures</h3>
    <hr>
    <div class="row">

      <?php
      
      while($row = mysqli_fetch_array($sql)){
        $file_id = $row['file_id'];
        $file_name = $row['file_name'];
      
        ?>
        <div class=" col-xl-2 col-lg-3 col-md-6 col-sm-6 mb-3">
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
  </div>
</div>

<?php include("add_client_file_modal.php"); ?>