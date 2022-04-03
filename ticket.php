<?php include("inc_all.php"); ?>

<?php

if(isset($_GET['ticket_id'])){
  $ticket_id = intval($_GET['ticket_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM tickets 
    LEFT JOIN clients ON ticket_client_id = client_id 
    LEFT JOIN contacts ON ticket_contact_id = contact_id 
    LEFT JOIN users ON ticket_assigned_to = user_id 
    LEFT JOIN locations ON ticket_location_id = location_id
    LEFT JOIN assets ON ticket_asset_id = asset_id
    WHERE ticket_id = $ticket_id AND tickets.company_id = $session_company_id");

  if(mysqli_num_rows($sql) == 0){
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

    include("footer.php");

  }else{

  $row = mysqli_fetch_array($sql);
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_type = $row['client_type'];
  $client_website = $row['client_website'];
  $client_net_terms = $row['client_net_terms'];
  if($client_net_terms == 0){
    $client_net_terms = $config_default_net_terms;
  }

  $ticket_prefix = $row['ticket_prefix'];
  $ticket_number = $row['ticket_number'];
  $ticket_category = $row['ticket_category'];
  $ticket_subject = $row['ticket_subject'];
  $ticket_details = $row['ticket_details'];
  $ticket_priority = $row['ticket_priority'];
  $ticket_feedback = $row['ticket_feedback'];
  $ticket_status = $row['ticket_status'];
  $ticket_created_at = $row['ticket_created_at'];
  $ticket_date = date('Y-m-d',strtotime($ticket_created_at));
  $ticket_updated_at = $row['ticket_updated_at'];
  $ticket_closed_at = $row['ticket_closed_at'];
  $ticket_created_by = $row['ticket_created_by'];

  if($ticket_status == "Open"){
    $ticket_status_display = "<span class='p-2 badge badge-primary'>$ticket_status</span>";
  }elseif($ticket_status == "Working"){
    $ticket_status_display = "<span class='p-2 badge badge-success'>$ticket_status</span>";
  }else{
    $ticket_status_display = "<span class='p-2 badge badge-secondary'>$ticket_status</span>";
  }

  //Set Ticket Bage Color based of priority
  if($ticket_priority == "High"){
    $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
  }elseif($ticket_priority == "Medium"){
    $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
  }elseif($ticket_priority == "Low"){
    $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
  }else{
    $ticket_priority_display = "-";
  }

  $contact_id = $row['contact_id'];
  $contact_name = $row['contact_name'];
  $contact_title = $row['contact_title'];
  $contact_email = $row['contact_email'];
  $contact_phone = formatPhoneNumber($row['contact_phone']);
  $contact_extension = $row['contact_extension'];
  $contact_mobile = formatPhoneNumber($row['contact_mobile']);

  $asset_id = $row['asset_id'];
  $asset_ip = htmlentities($row['asset_ip']);
  $asset_name = htmlentities($row['asset_name']);
  $asset_type = htmlentities($row['asset_type']);
  $asset_make = htmlentities($row['asset_make']);
  $asset_model = htmlentities($row['asset_model']);
  $asset_serial = htmlentities($row['asset_serial']);
  $asset_os = htmlentities($row['asset_os']);
  $asset_warranty_expire = $row['asset_warranty_expire'];

  $location_name = $row['location_name'];
  $location_address = $row['location_address'];
  $location_city = $row['location_city'];
  $location_state = $row['location_state'];
  $location_zip = $row['location_zip'];
  $location_phone = formatPhoneNumber($row['location_phone']);

  $ticket_assigned_to = $row['ticket_assigned_to'];
  if(empty($ticket_assigned_to)){
    $ticket_assigned_to_display = "<span class='text-danger'>Not Assigned</span>";
  }else{
    $ticket_assigned_to_display = $row['user_name'];
  }
  //Ticket Created By
  $ticket_created_by = $row['ticket_created_by'];
  $ticket_created_by_sql = mysqli_query($mysqli,"SELECT user_name FROM users WHERE user_id = $ticket_created_by");
  $row = mysqli_fetch_array($ticket_created_by_sql);
  $ticket_created_by_display = $row['user_name'];

  //Ticket Assigned To
  if(empty($ticket_assigned_to)){
    $ticket_assigned_to_display = "<span class='text-danger'>Not Assigned</span>";
  }else{
    $ticket_assigned_to_display = $row['user_name'];
  }

//  if($contact_id == $primary_contact){
//     $primary_contact_display = "<small class='text-success'>Primary Contact</small>";
//  }else{
//    $primary_contact_display = "<small class='text-danger'>Needs approval</small>";
//  }

  //Get Contact Ticket Stats
  $ticket_related_open = mysqli_query($mysqli,"SELECT COUNT(ticket_id) AS ticket_related_open FROM tickets WHERE ticket_status != 'Closed' AND ticket_contact_id = $contact_id ");
  $row = mysqli_fetch_array($ticket_related_open);
  $ticket_related_open = $row['ticket_related_open'];

  $ticket_related_closed = mysqli_query($mysqli,"SELECT COUNT(ticket_id) AS ticket_related_closed  FROM tickets WHERE ticket_status = 'Closed' AND ticket_contact_id = $contact_id ");
  $row = mysqli_fetch_array($ticket_related_closed);
  $ticket_related_closed = $row['ticket_related_closed'];

  $ticket_related_total = mysqli_query($mysqli,"SELECT COUNT(ticket_id) AS ticket_related_total FROM tickets WHERE ticket_contact_id = $contact_id ");
  $row = mysqli_fetch_array($ticket_related_total);
  $ticket_related_total = $row['ticket_related_total'];

  //Get Total Ticket Time
  $ticket_total_reply_time = mysqli_query($mysqli,"SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
  $row = mysqli_fetch_array($ticket_total_reply_time);
  $ticket_total_reply_time = $row['ticket_total_reply_time'];

  //Client Tags
  $client_tag_name_display_array = array();
  $client_tag_id_array = array();
  $sql_client_tags = mysqli_query($mysqli,"SELECT * FROM client_tags LEFT JOIN tags ON client_tags.tag_id = tags.tag_id WHERE client_tags.client_id = $client_id");
  while($row = mysqli_fetch_array($sql_client_tags)){

    $client_tag_id = $row['tag_id'];
    $client_tag_name = $row['tag_name'];
    $client_tag_color = $row['tag_color'];
    $client_tag_icon = $row['tag_icon'];
    if(empty($client_tag_icon)){
      $client_tag_icon = "tag";
    }

    $client_tag_id_array[] = $client_tag_id;
    $client_tag_name_display_array[] = "<span class='badge bg-$client_tag_color'><i class='fa fa-fw fa-$client_tag_icon'></i> $client_tag_name</span>";
  }
  $client_tags_display = implode(' ', $client_tag_name_display_array);

 // Get & format asset warranty expiry
 $date = date('Y-m-d H:i:s');
 $dt_value = $asset_warranty_expire; //sample date
 $warranty_check = date('m/d/Y',strtotime('-8 hours'));

 if($dt_value <= $date){
    $dt_value = "Expired on $asset_warranty_expire"; $warranty_status_color ='red';
 }else{
   $warranty_status_color = 'green';
 }

  if($asset_warranty_expire == '0000-00-00'){
    $dt_value = "None"; $warranty_status_color ='red';
  }


?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="tickets.php">Tickets</a>
  </li>
  <li class="breadcrumb-item">
    <a href="client.php?client_id=<?php echo $client_id; ?>&tab=tickets"><?php echo $client_name; ?></a>
  </li>
  <li class="breadcrumb-item active">Ticket Details</li>
</ol>

<div class="row mb-3">
  <div class="col-9">
    <h3><i class="fas fa-fw fa-ticket-alt text-secondary"></i> Ticket <?php echo "$ticket_prefix$ticket_number"; ?> <?php echo $ticket_status_display; ?></h3>
  </div>
  <?php if($ticket_status != "Closed") { ?>
  <div class="col-3">
    <div class="dropdown dropleft text-center">
      <button class="btn btn-secondary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown">
        <i class="fas fa-fw fa-ellipsis-v"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#mergeTicketModal<?php echo $ticket_id; ?>">Merge</a>
        <?php if($session_user_role == 3) { ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="post.php?delete_ticket=<?php echo $ticket_id; ?>">Delete</a>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>
</div>

<div class="row">

  <div class="col-md-9">

    <div class="card card-outline card-primary mb-3">
      <div class="card-header">
        <h3 class="card-title"><?php echo $ticket_subject; ?></h3>
      </div>
      <div class="card-body">
        <?php echo $ticket_details; ?>
      </div>
    </div>

    <!-- Only show ticket reply modal if status is not closed -->
    <?php if($ticket_status != "Closed"){ ?>
    <form class="mb-3" action="post.php" method="post" autocomplete="off">
      <input type="hidden" name="ticket_id" id="ticket_id" value="<?php echo $ticket_id; ?>">
      <div class="form-group">
        <textarea class="form-control summernote" name="ticket_reply" required></textarea>
      </div>
      <div class="form-row">
        <div class="col-md-2">
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
              </div>
              <select class="form-control select2" name="status" required>
                <option <?php if($ticket_status == 'Open'){ echo "selected"; } ?> >Open</option>
                <option <?php if($ticket_status == 'Working'){ echo "selected"; } ?> >Working</option>
                <option <?php if($ticket_status == 'On Hold'){ echo "selected"; } ?> >On Hold</option>
                <option <?php if($ticket_status == 'Closed'){ echo "selected"; } ?> >Closed</option>
              </select>
            </div>
          </div>
        </div>

        <div class="col-sm-2">
            <div class="form-group">
                <input class="form-control timepicker" id="time_worked" name="time" type="time" step="1" value="00:00:00" onchange="setTime()"/>
            </div>
        </div>

        <?php //if(!empty($config_smtp_host) AND !empty($client_email)){ ?>

        <div class="col-md-2">
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="public_reply_type" value="1" checked>
              <label class="custom-control-label" for="customControlAutosizing">Email update to client (Public Update)</label>
            </div>
          </div>
        </div>

        <?php //} ?>

        <div class="col-md-2">
          <button type="submit" name="add_ticket_reply" class="btn btn-primary"><i class="fa fa-fw fa-check"></i> Save & Reply</button>
        </div>

      </div>

      <p class="font-weight-light" id="ticket_collision_viewing"></p>

    </form>
    <!-- End IF for reply modal -->
    <?php } ?>

    <?php
    $sql = mysqli_query($mysqli,"SELECT * FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id LEFT JOIN contacts ON ticket_reply_by = contact_id WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL ORDER BY ticket_reply_id DESC");

      while($row = mysqli_fetch_array($sql)){;
        $ticket_reply_id = $row['ticket_reply_id'];
        $ticket_reply = $row['ticket_reply'];
        $ticket_reply_type = $row['ticket_reply_type'];
        $ticket_reply_created_at = $row['ticket_reply_created_at'];
        $ticket_reply_updated_at = $row['ticket_reply_updated_at'];
        $ticket_reply_by = $row['ticket_reply_by'];

        if($ticket_reply_type == "Client"){
          $ticket_reply_by_display = $row['contact_name'];
          $user_initials = initials($row['contact_name']);
        }
        else{
          $ticket_reply_by_display = $row['user_name'];
          $user_id = $row['user_id'];
          $user_avatar = $row['user_avatar'];
          $user_initials = initials($row['user_name']);
          $ticket_reply_time_worked = date_create($row['ticket_reply_time_worked']);
        }
    ?>

    <div class="card card-outline <?php if($ticket_reply_type == 'Internal'){ echo "card-dark"; } elseif($ticket_reply_type == 'Client') {echo "card-warning"; } else{ echo "card-info"; } ?> mb-3">
      <div class="card-header">
        <h3 class="card-title">
          <div class="media">
            <?php if(!empty($user_avatar)){ ?>
            <img src="<?php echo "uploads/users/$user_id/$user_avatar"; ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
            <?php }else{ ?>
            <span class="fa-stack fa-2x">
              <i class="fa fa-circle fa-stack-2x text-secondary"></i>
              <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
            </span>
            <?php
            }
            ?>

            <div class="media-body">
              <?php echo $ticket_reply_by_display; ?>
              <br>
              <small class="text-muted"><?php echo $ticket_reply_created_at; ?> <?php if(!empty($ticket_reply_updated_at)){ echo "modified: $ticket_reply_updated_at"; } ?></small>
              <br>
              <?php if($ticket_reply_type !== "Client") { ?>
                <small class="text-muted">Time worked: <?php echo date_format($ticket_reply_time_worked, 'H:i:s'); ?></small>
              <?php } ?>
            </div>
          </div>
        </h3>

        <?php if($ticket_reply_type !== "Client" AND $ticket_status !== "Closed") { ?>
        <div class="card-tools">
          <div class="dropdown dropleft">
            <button class="btn btn-tool" type="button" id="dropdownMenuButton" data-toggle="dropdown">
              <i class="fas fa-fw fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#replyEditTicketModal<?php echo $ticket_reply_id; ?>"><i class="fas fa-fw fa-edit text-secondary"></i> Edit</a>
              <?php if($session_user_role == 3) { ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="post.php?archive_ticket_reply=<?php echo $ticket_reply_id; ?>"><i class="fas fa-fw fa-trash text-danger"></i> Archive</a>
              <?php } ?>
            </div>
          </div>
        </div>
        <?php } ?>

      </div>

      <div class="card-body">
        <?php echo $ticket_reply; ?>
      </div>
    </div>

    <?php

    include("ticket_reply_edit_modal.php");

    }

    ?>

  </div>

  <div class="col-md-3">

    <!-- Client card -->
    <div class="card card-body card-outline card-primary mb-3">
      <div>
        <h5><strong><?php echo $client_name; ?></strong></h5>
        <?php
        if(!empty($location_phone)){
        ?>
        <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i><?php echo $location_phone; ?>
        <br>
        <?php
        }
        ?>

        <?php
        if(!empty($client_tags_display)){
          echo "$client_tags_display";
        }
        ?>
      </div>
    </div>

    <!-- Client contact card -->
    <?php if(!empty($contact_id)){ ?>
    <div class="card card-body card-outline card-dark mb-3">
      <div>
        <h4 class="text-secondary">Contact</h4>
        <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i><strong><?php echo $contact_name; ?></strong>
        <br>
<!--        <i class="fa fa-fw fa-info-circle text-secondary ml-1 mr-2 mb-2"></i>--><?php //echo $primary_contact_display; ?>
<!--        <br>-->
        <span class="ml-1">Related tickets: Open <strong><?php echo $ticket_related_open; ?></strong> | Closed <strong><?php echo $ticket_related_closed; ?></strong> | Total <strong><?php echo $ticket_related_total; ?></strong></span>
        <hr>
        <?php
        if(!empty($location_name)){
        ?>
        <i class="fa fa-fw fa-map-marker-alt text-secondary ml-1 mr-2 mb-2"></i><?php echo $location_name; ?>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_email)){
        ?>
        <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_phone)){
        ?>
        <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i><?php echo $contact_phone; ?>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_mobile)){
        ?>
        <i class="fa fa-fw fa-mobile-alt text-secondary ml-1 mr-2 mb-2"></i><?php echo $contact_mobile; ?>
        <br>
        <?php
        }
        ?>
      </div>
    </div>

    <?php } ?>

    <!-- Ticket Details card -->
    <div class="card card-body card-outline card-dark mb-3">
      <h4 class="text-secondary">Details</h4>
      <div class="ml-1"><i class="fa fa-fw fa-thermometer-half text-secondary mr-2 mb-2"></i><?php echo $ticket_priority_display; ?></div>
      <div class="ml-1"><i class="fa fa-fw fa-calendar text-secondary mr-2 mb-2"></i>Created on: <?php echo $ticket_created_at; ?></div>
      <div class="ml-1"><i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i>Created by: <?php echo $ticket_created_by_display; ?></div>
      <?php
      if($ticket_status == "Closed"){
        $sql_closed_by = mysqli_query($mysqli,"SELECT * FROM tickets, users WHERE ticket_closed_by = user_id");
        $row = mysqli_fetch_array($sql_closed_by);
        $ticket_closed_by_display = $row['user_name'];
        ?>
        <div class="ml-1"><i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i>Closed by: <?php echo strtoupper($ticket_closed_by_display); ?></a></div>
        <div class="ml-1"><i class="fa fa-fw fa-comment-dots text-secondary mr-2 mb-2"></i>Feedback: <?php echo $ticket_feedback; ?></a></div>
      <?php } ?>
      <?php if(!empty($ticket_total_reply_time)){ ?>
        <div class="ml-1"><i class="far fa-fw fa-clock text-secondary mr-2 mb-2"></i>Total time worked: <?php echo $ticket_total_reply_time; ?></div>
      <?php } ?>
    </div>

    <!-- Ticket asset details card -->
    <?php if(!empty($asset_id)){ ?>
      <div class="card card-body card-outline card-dark mb-3">
        <div>
          <h4 class="text-secondary">Asset</h4>
          <i class="fa fa-fw fa-desktop text-secondary ml-1 mr-2 mb-2"></i><strong><?php echo $asset_name; ?></strong>
          <br>

          <?php if(!empty($asset_os)) { ?>
            <i class="fab fa-fw fa-microsoft text-secondary ml-1 mr-2 mb-2"></i><?php echo $asset_os; ?>
            <br>
            <?php
          }

          if (!empty($asset_ip)) { ?>
            <i class="fa fa-fw fa-network-wired text-secondary ml-1 mr-2 mb-2"></i><?php echo "$asset_ip"; ?>
            <br>
            <?php
          }

          if (!empty($asset_make)) { ?>
            <i class="fa fa-fw fa-tag text-secondary ml-1 mr-2 mb-2"></i>Model: <?php echo "$asset_make $asset_model"; ?>
            <br>
            <?php
          }

          if (!empty($asset_serial)) {
            ?>
            <i class="fa fa-fw fa-barcode text-secondary ml-1 mr-2 mb-2"></i>Service Tag: <?php echo $asset_serial; ?>
            <br>

            <?php
          }

          if (!empty($asset_warranty_expire)) {
            ?>
            <i class="far fa-fw fa-calendar-alt text-secondary ml-1 mr-2 mb-2"></i>Warranty expires: <strong><font color="<?php echo $warranty_status_color ?>"> <?php echo $dt_value ?></font></strong>
            <br>
            <?php
          }
          ?>

          <?php
          $sql_asset_tickets = mysqli_query($mysqli,"SELECT * FROM tickets WHERE ticket_asset_id = $asset_id ORDER BY ticket_number DESC");
          $ticket_asset_count = mysqli_num_rows($sql_asset_tickets);

          if($ticket_asset_count > 0 ){
          ?>

            <button class="btn btn-block btn-secondary" data-toggle="modal" data-target="#assetTicketsModal">Service History (<?php echo $ticket_asset_count; ?>)</button>

            <div class="modal" id="assetTicketsModal" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-fw fa-desktop"></i> <?php echo $asset_name; ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                      <span>&times;</span>
                    </button>
                  </div>
                    
                  <div class="modal-body bg-white">
                    <?php
                    // Query is run from client_assets.php
                    while($row = mysqli_fetch_array($sql_asset_tickets)){
                      $service_ticket_id = $row['ticket_id'];
                      $service_ticket_prefix = $row['ticket_prefix'];
                      $service_ticket_number = $row['ticket_number'];
                      $service_ticket_subject = $row['ticket_subject'];
                      $service_ticket_status = $row['ticket_status'];
                      $service_ticket_created_at = $row['ticket_created_at'];
                      $service_ticket_updated_at = $row['ticket_updated_at'];
                      ?>
                    <p>
                      <i class="fas fa-fw fa-ticket-alt"></i>
                      Ticket: <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>"><?php echo "$service_ticket_prefix$service_ticket_number" ?></a> on <?php echo $service_ticket_created_at; ?> <?php echo $service_ticket_subject; ?>
                    </p>
                    <?php 
                    }
                    ?>
                  </div>
                  <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  </div>

                </div>
              </div>
            </div>


          <?php

          }

          ?>

      </div>
    </div>
  <?php } ?>

    <form action="post.php" method="post">
      <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
      <div class="form-group">
        <label>Assigned to</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <select class="form-control select2" name="assigned_to" <?php if($ticket_status == "Closed") {echo "disabled";} ?>>
            <option value="">Not Assigned</option>
            <?php

            $sql_assign_to_select = mysqli_query($mysqli,"SELECT * FROM users, user_companies WHERE users.user_id = user_companies.user_id AND user_companies.company_id = $session_company_id ORDER BY user_name ASC");
            while($row = mysqli_fetch_array($sql_assign_to_select)){
              $user_id = $row['user_id'];
              $user_name = $row['user_name'];
            ?>
            <option <?php if($ticket_assigned_to == $user_id){ echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>

            <?php
            }
            ?>
          </select>
          <div class="input-group-append">
            <button type="submit" class="btn btn-primary" name="assign_ticket" <?php if($ticket_status == "Closed") {echo "disabled";} ?>><i class="fas fa-check"></i></button>
          </div>
        </div>
      </div>
    </form>

    <div class="card card-body card-outline card-dark mb-2">
     <div class="">
        <a href="#" class="btn btn-outline-success btn-block" href="#" data-toggle="modal" data-target="#addInvoiceFromTicketModal">INVOICE</a>
        <?php
        if($ticket_status !== "Closed"){
        ?>
        <a href="post.php?close_ticket=<?php echo $ticket_id; ?>" class="btn btn-outline-danger btn-block">CLOSE TICKET</a>
        <?php } ?>
      </div>
    </div>

  </div>

</div>

<?php
      include("ticket_edit_modal.php");
      include("ticket_merge_modal.php");
      include("ticket_invoice_add_modal.php");
?>

<?php

}

}

?>


<?php
if($ticket_status !== "Closed"){ ?>
  <!-- Ticket Time Tracking JS -->
  <script type="text/javascript">
      // Default values
      var hours = 0;
      var minutes = 0;
      var seconds = 0;
      setInterval(countTime, 1000);

      // Counter
      function countTime()
      {
          ++seconds;
          if(seconds == 60) {
              seconds = 0;
              minutes++;
          }
          if(minutes == 60) {
              minutes = 0;
              hours++;
          }

          // Total timeworked
          var time_worked = pad(hours) + ":" + pad(minutes) + ":" + pad(seconds);
          document.getElementById("time_worked").value = time_worked;
      }

      // Allows manually adjusting the timer
      function setTime()
      {
          var time_as_text = document.getElementById("time_worked").value;
          const time_text_array = time_as_text.split(":");
          hours = parseInt(time_text_array[0]);
          minutes = parseInt(time_text_array[1]);
          seconds = parseInt(time_text_array[2]);
      }

      // This function "pads" out the values, adding zeros if they are required
      function pad(val)
      {
          var valString = val + "";
          if(valString.length < 2)
          {
              return "0" + valString;
          }
          else
          {
              return valString;
          }
      }
  </script>

<?php } ?>

<?php include("footer.php");

// jQuery is called in footer, so this must be below it
if($ticket_status !== "Closed"){ ?>
  <script type="text/javascript">

    // Collision detection
    // Adds a "view" entry of the current ticket every 2 mins into the database
    // Updates the currently viewing (ticket_collision_viewing) element with anyone that's looked at this ticket in the last two mins
    function ticket_collision_detection() {

        // Get the page ticket id
        var ticket_id = document.getElementById("ticket_id").value;

        //Send a GET request to ajax.php as ajax.php?ticket_add_view=true&ticket_id=NUMBER
        jQuery.get(
            "ajax.php",
            {ticket_add_view: 'true', ticket_id: ticket_id},
            function(data){
                // We don't care about a response
            }
        );

        //Send a GET request to ajax.php as ajax.php?ticket_query_views=true&ticket_id=NUMBER
        jQuery.get(
            "ajax.php",
            {ticket_query_views: 'true', ticket_id: ticket_id},
            function(data){
                //If we get a response from ajax.php, parse it as JSON
                const ticket_view_data = JSON.parse(data);
                document.getElementById("ticket_collision_viewing").innerText = ticket_view_data.message;
            }
        );
    }
    // Call on page load
    ticket_collision_detection();

    // Run every 2 mins
    setInterval(ticket_collision_detection, 120*1000);
  </script>
<?php } ?>