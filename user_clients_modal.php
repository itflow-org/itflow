<div class="modal" id="editUserClientsModal<?php echo $user_id ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-users mr-2"></i><?php echo $name; ?> Clients</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

        <div class="modal-body bg-white">

          <div class="alert alert-info">
            Select Clients that the user will need access to
          </div>

          <ul class="list-group">

            <?php
            $sql_clients_select = mysqli_query($mysqli,"SELECT * FROM clients, companies WHERE clients.company_id = companies.company_id ORDER BY client_name ASC");

            while($row = mysqli_fetch_array($sql_clients_select)){
              $client_id_select = $row['client_id'];
              $client_name_select = $row['client_name'];
              $company_id_select = $row['company_id'];
              $company_name_select = $row['company_name'];

            ?>
              <li class="list-group-item">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="clients[]" value="<?php echo $client_id_select; ?>" <?php if(in_array("$client_id_select",$permission_clients_array)){ echo "checked"; } ?> >
                  <label class="form-check-label ml-2"><?php echo $client_name_select; ?></label>
                </div>
              </li>

            <?php
            }
            ?>

          </ul>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_user_clients" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>