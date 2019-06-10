<?php include("header.php"); ?>

<?php 

  $sql = mysqli_query($mysqli,"SELECT * FROM tickets, clients
    WHERE tickets.client_id = clients.client_id
    ORDER BY ticket_id DESC");
?>

<div class="card mb-3">
  <div class="card-header">
    <h5 class="float-left mt-2"><i class="fa fa-fw fa-tags mr-2"></i>Tickets</h5>
    <button type="button" class="btn btn-primary badge-pill float-right" data-toggle="modal" data-target="#addTicketModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Number</th>
            <th>Client</th>
            <th>Subject</th>
            <th>Date Opened</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $ticket_id = $row['ticket_id'];
            $ticket_subject = $row['ticket_subject'];
            $ticket_details = $row['ticket_details'];
            $ticket_status = $row['ticket_status'];
            $ticket_created_at = $row['ticket_created_at'];
            $ticket_updated_at = $row['ticket_updated_at'];
            $ticket_closed_at = $row['ticket_closed_at'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];

            if($ticket_status == "Open"){
              $ticket_badge_color = "primary";
            }elseif($ticket_status == "Resolved"){
              $ticket_badge_color = "success";
            }elseif($ticket_status == "Closed"){
              $ticket_badge_color = "secondary";
            }else{
              $ticket_badge_color = "info";
            }

          ?>

          <tr>
            <td><a href="#" data-toggle="modal" data-target="#viewTicketModal<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo $ticket_id; ?></span></a></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td><?php echo $ticket_subject; ?></td>
            <td><?php echo $ticket_created_at; ?></td>
            <td>
              <span class="p-2 badge badge-<?php echo $ticket_badge_color; ?>">
                <?php echo $ticket_status; ?>
              </span>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#viewTicketModal<?php echo $ticket_id; ?>">Details</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">Delete</a>
                </div>
              </div>
              <?php

              include("edit_ticket_modal.php");
              include("view_ticket_modal.php");

              ?>      
            </td>
          </tr>

          <?php

          }

          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_ticket_modal.php"); ?>

<?php include("footer.php");