<div class="modal" id="viewNoteModal<?php echo $note_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-edit mr-2"></i><?php echo $note_subject; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body bg-white">
        <?php echo $note_body; ?>
      </div>
    </div>
  </div>
</div>