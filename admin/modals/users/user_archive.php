<div class="modal" id="archiveUserModal<?php echo $user_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body">
        <div class="mb-4" style="text-align: center;">
          <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
          <h2>Are you sure?</h2>
          <h6 class="mb-4 text-secondary">Do you really want to <b>archive <?php echo $user_name; ?></b>? This process cannot be undone.</h6>
          <h6 class="mb-4 text-secondary"><?php echo $user_name ?> will no longer be able to log in or use ITFlow, but all associated content will remain accessible.</h6>
          <button type="button" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
          <a class="btn btn-danger btn-lg px-5" href="post.php?archive_user=<?php echo $user_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">Yes, archive!</a>
        </div>
      </div>
    </div>
  </div>
</div>
