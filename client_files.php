<?php 

$sql_files_images = mysqli_query($mysqli,"SELECT * FROM files WHERE client_id = $client_id AND (file_ext LIKE 'JPG' OR file_ext LIKE 'jpg' OR file_ext LIKE 'JPEG' OR file_ext LIKE 'jpeg' OR file_ext LIKE 'png' OR file_ext LIKE 'PNG') ORDER BY file_name ASC");

$sql_files_other = mysqli_query($mysqli,"SELECT * FROM files WHERE client_id = $client_id AND file_ext NOT LIKE 'JPG' AND file_ext NOT LIKE 'jpg' AND file_ext NOT LIKE 'png' AND file_ext NOT LIKE 'PNG' ORDER BY file_name ASC"); 

$num_of_files = mysqli_num_rows($sql_files_images) + mysqli_num_rows($sql_files_other);

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-paperclip"></i> Files</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFileModal"><i class="fas fa-fw fa-cloud-upload-alt"></i> Upload File</button>
    </div>
  </div>
  <div class="card-body">

    <?php
      if($num_of_files == 0){
        
        echo "<center><h3 class='text-secondary'>No Records Here</h3></center>";
      }

    ?>

    <div class="row">
      
        <?php
        
        while($row = mysqli_fetch_array($sql_files_images)){
          $file_id = $row['file_id'];
          $file_name = $row['file_name'];
          $file_ext = $row['file_ext'];
        
          ?>

          <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">    
            <div class="card">
              <a href="#" data-toggle="modal" data-target="#viewFileModal<?php echo $file_id; ?>">  
                <img class="img-fluid" src="<?php echo $file_name; ?>">       
              </a>
              <div class="card-footer bg-dark text-white p-1">
                <center>
                  <a href="<?php echo $file_name; ?>" download="<?php echo $file_name; ?>" class="text-white float-left ml-1"><i class="fa fa-cloud-download-alt"></i></a>
                  <small><?php echo basename($file_name); ?></small>

                  <a href="post.php?delete_file=<?php echo $file_id; ?>" class="text-white float-right mr-1"><i class="fa fa-times"></i></a>
                </center>
              </div>
            </div>   
          </div>
         
          <?php 
          include("view_file_modal.php");
          } 
          ?>
      </div>

      <div class="row">
        
        <table class="table">
     
        <?php
        while($row = mysqli_fetch_array($sql_files_other)){
          $file_id = $row['file_id'];
          $file_name = $row['file_name'];
          $file_ext = $row['file_ext'];
          if($file_ext == 'pdf'){
            $file_icon = "file-pdf";
          }elseif($file_ext == 'gz' or $file_ext == 'tar' or $file_ext == 'zip' or $file_ext == '7z' or $file_ext == 'rar'){
            $file_icon = "file-archive";
          }elseif($file_ext == 'txt'){
            $file_icon = "file-alt";
          }elseif($file_ext == 'doc' or $file_ext == 'docx'){
            $file_icon = "file-word";
          }elseif($file_ext == 'xls' or $file_ext == 'xlsx' or $file_ext == 'ods'){
            $file_icon = "file-excel";
          }elseif($file_ext == 'mp3' or $file_ext == 'wav' or $file_ext == 'ogg'){
            $file_icon = "file-audio";
          }else{
            $file_icon = "file";
          }
          ?>

          <tr>
            <td><a href="<?php echo $file_name; ?>" target="_blank" class="text-secondary"><i class="fa fa-fw fa-2x fa-<?php echo $file_icon; ?> mr-3"></i> <?php echo basename($file_name); ?></a></td>
            <td>
              <a href="<?php echo $file_name; ?>" download="<?php echo $file_name; ?>" class="text-secondary float-left ml-1"><i class="fa fa-cloud-download-alt"></i></a>
              <a href="post.php?delete_file=<?php echo $file_id; ?>" class="text-secondary float-right mr-1"><i class="fa fa-times"></i></a>
            </td>
          </tr>
        <?php
        }
        ?>
   
    </div>
  </div>
</div>

<?php include("add_file_modal.php"); ?>