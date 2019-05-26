<?php $sql = mysqli_query($mysqli,"SELECT * FROM contacts WHERE client_id = $client_id ORDER BY contact_id DESC"); ?>
<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-users"></i> Contacts</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addContactModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th class="text-center">Name</th>
            <th>Title</th>
            <th>Email</th>
            <th>Phone</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_title = $row['contact_title'];
            $contact_phone = $row['contact_phone'];
            if(strlen($contact_phone)>2){ 
              $contact_phone = substr($row['contact_phone'],0,3)."-".substr($row['contact_phone'],3,3)."-".substr($row['contact_phone'],6,4);
            }
            $contact_email = $row['contact_email'];
            $contact_photo = $row['contact_photo'];
            $contact_initials = initials($contact_name);
      
          ?>
          <tr>
            <td class="text-center">
              <?php if(!empty($contact_photo)){ ?>
            
              <img height="48" width="48" class="img-fluid rounded-circle" src="<?php echo $contact_photo; ?>">
    
              <?php }else{ ?>
  
              <span class="fa-stack fa-2x">
                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
              </span>
              <br>
              
              <?php } ?>
              <div class="text-secondary"><?php echo $contact_name; ?></div>
            </td>
            
            <td><?php echo $contact_title; ?></td>
            <td><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a></td>
            <td><?php echo $contact_phone; ?></td>
            
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContactModal<?php echo $contact_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_contact=<?php echo $contact_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_contact_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_contact_modal.php"); ?>