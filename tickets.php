<?php include("header.php"); 

  //Paging
  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$config_records_per_page;
    $record_to = $config_records_per_page;
  }else{
    $record_from = 0;
    $record_to = $config_records_per_page;
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

  //Date From and Date To Filter
  if(!empty($_GET['dtf'])){
    $dtf = $_GET['dtf'];
    $dtt = $_GET['dtt'];
  }else{
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
  }

  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM tickets, clients
    WHERE tickets.client_id = clients.client_id
    AND tickets.company_id = $session_company_id
    AND ticket_status LIKE '%$status%'
    AND DATE(ticket_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(ticket_prefix,ticket_number) LIKE '%$q%' OR client_name LIKE '%$q%' OR ticket_subject LIKE '%$q%' OR ticket_priority LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-tags"></i> Tickets</h3>
    <div class='card-tools'>
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTicketModal"><i class="fas fa-fw fa-plus"></i> New Ticket</button>
    </div>
  </div>
  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Tickets">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        
        </div>
        <div class="col-sm-8">
          <div class="btn-group float-right">
            <a href="?status=%" class="btn <?php if($status == '%'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">All</a>
            <a href="?status=Open" class="btn <?php if($status == 'Open'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Open</a>
            <a href="?status=In-Progress" class="btn <?php if($status == 'In-Progress'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">In-Progress</a>
            <a href="?status=On-Hold" class="btn <?php if($status == 'On-Hold'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">On-Hold</a>
            <a href="?status=Resolved" class="btn <?php if($status == 'Resolved'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Resolved</a>
            <a href="?status=Closed" class="btn <?php if($status == 'Closed'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Closed</a>
          </div>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Date From</label>
              <input type="date" class="form-control" name="dtf" value="<?php echo $dtf; ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" value="<?php echo $dtt; ?>">
            </div>
          </div>
        </div>    
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_status&o=<?php echo $disp; ?>">Status</a>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_created_at&o=<?php echo $disp; ?>">Created</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_subject&o=<?php echo $disp; ?>">Subject</a></th>
            <th>Last Response</th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=ticket_priority&o=<?php echo $disp; ?>">Priority</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $ticket_id = $row['ticket_id'];
            $ticket_prefix = $row['ticket_prefix'];
            $ticket_number = $row['ticket_number'];
            $ticket_subject = $row['ticket_subject'];
            $ticket_details = $row['ticket_details'];
            $ticket_priority = $row['ticket_priority'];
            $ticket_status = $row['ticket_status'];
            $ticket_created_at = $row['ticket_created_at'];
            $ticket_updated_at = $row['ticket_updated_at'];
            $ticket_closed_at = $row['ticket_closed_at'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];

            if($ticket_status == "Open"){
              $ticket_status_display = "<span class='p-2 badge badge-primary'>$ticket_status</span>";
            }elseif($ticket_status == "Working"){
              $ticket_status_display = "<span class='p-2 badge badge-success'>$ticket_status</span>";
            }else{
              $ticket_status_display = "<span class='p-2 badge badge-secondary'>$ticket_status</span>";
            }

            if($ticket_priority == "High"){
              $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
            }elseif($ticket_priority == "Medium"){
              $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
            }elseif($ticket_priority == "Low"){
              $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
            }else{
              $ticket_priority_display = "-";
            }

          ?>

          <tr>
            <td><a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?php echo "$ticket_prefix$ticket_number"; ?></span></a></td>
            <td><?php echo $ticket_status_display; ?></td>
            <td><?php echo $ticket_created_at; ?></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=tickets"><?php echo $client_name; ?></a></td>
            <td><?php echo $ticket_subject; ?></td>
            <td>Never</td>
            <td><?php echo $ticket_priority_display; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
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
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_ticket_modal.php"); ?>

<?php include("footer.php");