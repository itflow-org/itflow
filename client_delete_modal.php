<div class="modal" id="clientDeleteModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body">
      	<center class="mb-4">
      		<i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
      		<h2>Are you sure?</h2>
      		<h6 class="mb-4 text-secondary">Do you really want to delete <?php echo $client_name; ?>? This process cannot be undone.</h6>	
      		<button type="button" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
      		<a class="btn btn-danger btn-lg px-5" href="post.php?delete_client=<?php echo $client_id; ?>">Yes, Delete!</a>
      	</center>
      </div>
    </div>
  </div>
</div>
