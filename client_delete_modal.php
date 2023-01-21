<script>
    function validateClientNameDelete(client_id) {
        if (document.getElementById("clientNameProvided" + client_id).value === document.getElementById("clientName" + client_id).value) {
            document.getElementById("clientDeleteButton" + client_id).className = "btn btn-danger btn-lg px-5";
        }
        else{
            document.getElementById("clientDeleteButton" + client_id).className = "btn btn-danger btn-lg px-5 disabled";
        }
    }
</script>

<div class="modal" id="deleteClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-body">
      	<div class="mb-4" style="text-align: center;">
      		<i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
      		<h2>Are you sure?</h2>
          <h6 class="mb-4 text-secondary">Do you really want to <b>delete <?php echo $client_name; ?></b> and all associated data including financial data, logs, shared links etc.? This process cannot be undone.</h6>
          <div class="form-group">
            <input type="hidden" id="clientName<?php echo $client_id ?>" value="<?php echo $client_name; ?>">
            <input class="form-control" type="text" id="clientNameProvided<?php echo $client_id ?>" onkeyup="validateClientNameDelete(<?php echo $client_id ?>)" placeholder="Please enter: '<?php echo $client_name; ?>'">
          </div>
      		<button type="button" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
      		<a class="btn btn-danger btn-lg px-5 disabled" id="clientDeleteButton<?php echo $client_id ?>" href="post.php?delete_client=<?php echo $client_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">Yes, Delete!</a>
      	</div>
      </div>
    </div>
  </div>
</div>
