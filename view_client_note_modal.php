<div class="modal" id="viewClientNoteModal<?php echo $client_note_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-note"></i> <?php echo $client_note_subject; ?></h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <?php
          $Parsedown = new Parsedown();
          echo $Parsedown->text("$client_note_body");
        ?>
      </div>
    </div>
  </div>
</div>