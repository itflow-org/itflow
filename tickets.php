<?php include("header.php"); 

  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*10;
    $record_to =  10;
  }else{
    $record_from = 0;
    $record_to = 10;
    $p = 1;
  }
    
  if(isset($_GET['q'])){
    $q = mysqli_real_escape_string($mysqli,$_GET['q']);
  }else{
    $q = "";
  }

  if(isset($_GET['status'])){
    $status = mysqli_real_escape_string($mysqli,$_GET['status']);
  }else{
    $status = "";
  }

  if(!empty($_GET['sb'])){
    $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
  }else{
    $sb = "ticket_number";
  }

  if(isset($_GET['o'])){
    if($_GET['o'] == 'ASC'){
      $o = "ASC";
      $disp = "DESC";
    }else{
      $o = "DESC";
      $disp = "ASC";
    }
  }else{
    $o = "DESC";
    $disp = "ASC";
  }

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM tickets, clients
    WHERE tickets.client_id = clients.client_id
    AND tickets.company_id = $session_company_id
    AND ticket_status LIKE '%$status%'
    AND (ticket_id LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-2"><i class="fa fa-fw fa-tags mr-2"></i>Tickets</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addTicketModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-4">
        <form autocomplete="off">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Tickets">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </form>
      </div>
      <div class="col-md-8">
        <div class="float-right">
          <a href="?status=Open" class="btn <?php if($status == 'Open'){ echo 'btn-primary'; }else{ echo 'btn-secondary'; } ?>">Open</a>
          <a href="?status=In-Progress" class="btn <?php if($status == 'In-Progress'){ echo 'btn-primary'; }else{ echo 'btn-secondary'; } ?>">In-Progress</a>
          <a href="?status=On-Hold" class="btn <?php if($status == 'On-Hold'){ echo 'btn-primary'; }else{ echo 'btn-secondary'; } ?>">On-Hold</a>
          <a href="?status=Resolved" class="btn <?php if($status == 'Resolved'){ echo 'btn-primary'; }else{ echo 'btn-secondary'; } ?>">Resolved</a>
          <a href="?status=Closed" class="btn <?php if($status == 'Closed'){ echo 'btn-primary'; }else{ echo 'btn-secondary'; } ?>">Closed</a>
        </div>
      </div>
    </div>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_id&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_status&o=<?php echo $disp; ?>">Status</a>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_created_at&o=<?php echo $disp; ?>">Created</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_subject&o=<?php echo $disp; ?>">Subject</a></th>
            <th>Last Response</th>
            <th class="text-center">Action</th>
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
            <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo $ticket_id; ?></a></td>
            <td>
              <span class="p-2 badge badge-<?php echo $ticket_badge_color; ?>">
                <?php echo $ticket_status; ?>
              </span>
            </td>
            <td><?php echo $ticket_created_at; ?></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=tickets"><?php echo $client_name; ?></a></td>
            <td><?php echo $ticket_subject; ?></td>
            <td>Never</td>
            
            
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">Delete</a>
                </div>
              </div>
              <?php

              include("edit_ticket_modal.php");

              ?>      
            </td>
          </tr>

          <?php

          }

          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_ticket_modal.php"); ?>

<?php include("footer.php");