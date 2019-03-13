<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM clients ORDER BY client_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-users"></i> Clients</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-plus"></i> Add New</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th class="text-right">Unpaid</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $client_email = $row['client_email'];
            $client_phone = $row['client_phone'];
            if(strlen($client_phone)>2){ 
              $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
            }
      
          ?>
          <tr>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo "$client_name"; ?></a></td>
            <td><?php echo "$client_email"; ?></td>
            <td><?php echo "$client_phone"; ?></td>
            <td class="text-right text-monospace">$0.00</td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_client_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_client_modal.php"); ?>

<?php include("footer.php");