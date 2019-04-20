<div class="modal" id="viewClientFileModal<?php echo $file_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-paperclip"></i> <?php echo $file_name; ?></h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <center>
          <img class="img-fluid" src="<?php echo $file_name; ?>">
        </center>
      </div>
    </div>
  </div>
</div>