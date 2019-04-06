<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_contacts WHERE client_id = $client_id ORDER BY client_contact_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Title</th>
        <th>Email</th>
        <th>Phone</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_contact_id = $row['client_contact_id'];
        $client_contact_name = $row['client_contact_name'];
        $client_contact_title = $row['client_contact_title'];
        $client_contact_phone = $row['client_contact_phone'];
        if(strlen($client_contact_phone)>2){ 
          $client_contact_phone = substr($row['client_contact_phone'],0,3)."-".substr($row['client_contact_phone'],3,3)."-".substr($row['client_contact_phone'],6,4);
        }
        $client_contact_email = $row['client_contact_email'];
  
      ?>
      <tr>
        <td><?php echo "$client_contact_name"; ?></td>
        <td><?php echo "$client_contact_title"; ?></td>
        <td><a href="mailto:<?php echo $client_contact_email; ?>"><?php echo "$client_contact_email"; ?></a></td>
        <td><?php echo "$client_contact_phone"; ?></td>
        
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientContactModal<?php echo $client_contact_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_contact=<?php echo $client_contact_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_contact_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>