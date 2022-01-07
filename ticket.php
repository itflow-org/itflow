<?php include("config.php"); ?>
<?php include("header.php"); ?>

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
  $ticket_status = $row['ticket_status'];
  $ticket_created_at = $row['ticket_created_at'];
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
  $asset_name = htmlentities($row['asset_name']);
  $asset_type = htmlentities($row['asset_type']);
  $asset_make = htmlentities($row['asset_make']);
  $asset_model = htmlentities($row['asset_model']);
  $asset_serial = htmlentities($row['asset_serial']);
  $asset_os = htmlentities($row['asset_os']);

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
    <h3>Ticket <?php echo "$ticket_prefix$ticket_number"; ?> <?php echo $ticket_status_display; ?></h3>
  </div>
  <div class="col-3">

    <div class="dropdown dropleft text-center">
      <button class="btn btn-secondary btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown">
        <i class="fas fa-fw fa-ellipsis-v"></i>
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketModal<?php echo $ticket_id; ?>">Edit</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-danger" href="post.php?delete_client=<?php echo $client_id; ?>">Delete</a>
      </div>
    </div>
  </div>
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

    <form class="mb-3" action="post.php" method="post" autocomplete="off">
      <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
      <div class="form-group">
        <textarea class="form-control summernote" name="ticket_reply" required></textarea>
      </div>
      <div class="form-row">
        <div class="col-md-3">
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

        <?php if(!empty($config_smtp_host) AND !empty($client_email)){ ?>

        <div class="col-md-2">
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_ticket_reply" value="1" checked>
              <label class="custom-control-label" for="customControlAutosizing">Email update to client</label>
            </div>
          </div>
        </div>

        <?php } ?>
        
        <div class="col-md-1">
          <button type="submit" name="add_ticket_reply" class="btn btn-primary"><i class="fa fa-fw fa-check"></i> Save</button>
        </div>

        <?php
        if($ticket_status !== "Closed"){
        ?>
          <div class="col-md-2">
            <a href="post.php?close_ticket=<?php echo $ticket_id; ?>" class="btn btn-outline-danger btn-block">Close Ticket</a>
          </div>
        <?php
        }
        ?>

      </div>
    
    </form>

    <?php
    $sql = mysqli_query($mysqli,"SELECT * FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL ORDER BY ticket_reply_id DESC");

      while($row = mysqli_fetch_array($sql)){;
        $ticket_reply_id = $row['ticket_reply_id'];
        $ticket_reply = $row['ticket_reply'];
        $ticket_reply_created_at = $row['ticket_reply_created_at'];
        $ticket_reply_updated_at = $row['ticket_reply_updated_at'];
        $ticket_reply_by = $row['ticket_reply_by'];
        $ticket_reply_by_display = $row['user_name'];
        $user_id = $row['user_id'];
        $user_avatar = $row['user_avatar'];
        $user_initials = initials($row['user_name']);
    ?>

    <div class="card mb-3">
      
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
            </div>
          </div>
        </h3>
      
        <div class="card-tools">
          <div class="dropdown dropleft">
            <button class="btn btn-tool" type="button" id="dropdownMenuButton" data-toggle="dropdown">
              <i class="fas fa-fw fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketReplyModal<?php echo $ticket_reply_id; ?>"><i class="fas fa-fw fa-edit text-secondary"></i> Edit</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger" href="post.php?archive_ticket_reply=<?php echo $ticket_reply_id; ?>"><i class="fas fa-fw fa-trash text-danger"></i> Delete</a>
            </div>
          </div>
        </div>
      </div>

      <div class="card-body">
        <?php echo $ticket_reply; ?>
      </div>
    </div>

    <?php
    
    include("edit_ticket_reply_modal.php");
    
    }
    
    ?>
  
  </div>

  <div class="col-md-3">

    <div class="card mb-3">
      <div class="card-body">
        <div>  
          <h4 class="text-secondary">Client</h4>
          <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_name; ?>
        </div>
      </div>
    </div>

    <?php if(!empty($contact_id)){ ?>

    <div class="card mb-3">
      <div class="card-body">
        <div>  
          <h4 class="text-secondary">Contact</h4>
          <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_name; ?>
          <br>
          <?php
          if(!empty($location_name)){
          ?>
          <i class="fa fa-fw fa-map-marker-alt text-secondary ml-1 mr-2 mb-2"></i> <?php echo $location_name; ?>
          <br>
          <?php
          }
          ?>
          <?php
          if(!empty($contact_email)){
          ?>
          <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i> <a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a>
          <br>
          <?php
          }
          ?>
          <?php
          if(!empty($contact_phone)){
          ?>
          <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_phone; ?>
          <br>
          <?php 
          } 
          ?>
          <?php
          if(!empty($contact_mobile)){
          ?>
          <i class="fa fa-fw fa-mobile text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_mobile; ?>
          <br>
          <?php 
          } 
          ?>
        </div>
      </div>
    </div>

    <?php } ?>

    <?php if(!empty($asset_id)){ ?>

    <div class="card mb-3">
      <div class="card-body">
        <div>  
          <h4 class="text-secondary">Asset</h4>
          <i class="fa fa-fw fa-desktop text-secondary ml-1 mr-2 mb-2"></i> <?php echo $asset_name; ?>
          <br>
          <?php
          if(!empty($asset_make)){
          ?>
          <i class="fa fa-fw fa-tag text-secondary ml-1 mr-2 mb-2"></i> <?php echo "$asset_make $asset_model"; ?>
          <br>
          <?php
          }
          ?>
          <?php
          if(!empty($asset_serial)){
          ?>
          <i class="fa fa-fw fa-barcode text-secondary ml-1 mr-2 mb-2"></i> <?php echo $asset_serial; ?>
          <br>
          <?php
          }
          ?>
          <?php
          if(!empty($asset_os)){
          ?>
          <i class="fa fa-fw fa-tag text-secondary ml-1 mr-2 mb-2"></i> <?php echo $asset_os; ?>
          <br>
          <?php 
          } 
          ?>
        </div>
      </div>
    </div>

    <?php } ?>


    <div class="card card-body mb-3"> 
      <h4 class="text-secondary">Details</h4>
      <div class="ml-1"><i class="fa fa-fw fa-thermometer-half text-secondary mr-2 mb-2"></i> <?php echo $ticket_priority_display; ?></div>
      <div class="ml-1"><i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i> <?php echo $ticket_assigned_to_display; ?></div>
      <div class="ml-1"><i class="fa fa-fw fa-clock text-secondary mr-2 mb-2"></i> <?php echo $ticket_created_at; ?></div>
    </div>

  </div>

</div>

<?php include("edit_ticket_modal.php"); ?>

<?php 

}

}

?>

<?php include("footer.php");
