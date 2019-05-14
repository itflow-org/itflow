<div class="modal" id="viewTicketModal<?php echo $ticket_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-tag mr-2"></i>Ticket # <?php echo $ticket_id; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="row">
            <div class="col-10">
              <h4><?php echo $client_name; ?></h4>
            </div>
            <div class="col-2">
              <h6>
                <span class="p-2 float-right badge badge-<?php echo $ticket_badge_color; ?>">
                  <?php echo $ticket_status; ?>
                </span>
              </h6>
            </div>
          </div>
          <div class="row">
            <div class="col">
              <div class="text-secondary"><i class="far fa-fw fa-clock"></i> <?php echo $ticket_created_at; ?></div>
            </div>
          </div>
          <hr>
          <center><h5><?php echo $ticket_subject; ?></h5></center>
          <p><?php echo $ticket_details; ?></p>
          <hr>

          <form>
            <textarea class="form-control" rows="8" name="details"></textarea>
          </form>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-success">Resolve</button>
          <button type="button" class="btn btn-secondary">Close</button>
          <button type="submit" name="add_ticket" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>