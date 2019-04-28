<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-image"></i> Logo</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" enctype="multipart/form-data" autocomplete="off"> 
      
      <img class="img-fluid" src="uploads/invoice_logo.png">

      <div class="form-group mb-5">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="file">
      </div>
      
      <hr>
      <button type="submit" name="edit_invoice_settings" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");