<?php $sql = mysqli_query($mysqli,"SELECT * FROM files WHERE client_id = $client_id ORDER BY file_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-paperclip"></i> Files</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientFileModal"><i class="fa fa-cloud-upload-alt"></i></button>
  </div>
  <div class="card-body">

    <div class="row">

      <?php
      
      while($row = mysqli_fetch_array($sql)){
        $file_id = $row['file_id'];
        $file_name = $row['file_name'];
      
        ?>
        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">    
          <div class="card">
            <a href="#" data-toggle="modal" data-target="#viewClientFileModal<?php echo $file_id; ?>">
              <img class="img-fluid" src="<?php echo $file_name; ?>">
            </a>
            <div class="card-footer p-1">
              <center>
                <a href="<?php echo $file_name; ?>" download="<?php echo $file_name; ?>" class="text-secondary float-left ml-1"><i class="fa fa-cloud-download-alt"></i></a>
                <small class="text-secondary"><?php echo basename($file_name); ?></small>

                <a href="post.php?delete_file=<?php echo $file_id; ?>" class="text-secondary float-right mr-1"><i class="fa fa-times"></i></a>
              </center>
            </div>
          </div>   
        </div>
       
        <?php 
        include("view_client_file_modal.php");
         } 
        ?>

    </div>
  </div>
</div>

<?php include("add_client_file_modal.php"); ?>