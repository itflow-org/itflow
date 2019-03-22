<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_applications WHERE client_id = $client_id ORDER BY client_application_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Application</th>
        <th>Type</th>
        <th>License</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_application_id = $row['client_application_id'];
        $client_application_name = $row['client_application_name'];
        $client_application_type = $row['client_application_type'];
        $client_application_license = $row['client_application_license'];

      ?>
      <tr>
        <td><?php echo $client_application_name; ?></td>
        <td><?php echo $client_application_type; ?></td>
        <td><?php echo $client_application_license; ?></td>
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientApplicationModal<?php echo $client_application_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_application=<?php echo $client_application_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_application_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>