<div class="modal" id="viewFileModal<?php echo $file_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl ">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-image mr-2"></i><?php echo basename($file_name); ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>     
      
      <center>
        <img class="img-fluid" src="<?php echo $file_name; ?>">
      </center>
      
    </div>
  </div>
</div>