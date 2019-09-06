<?php include("config.php"); ?>
<?php include("header.php"); ?>

<?php 

if(isset($_GET['ticket_id'])){
  $ticket_id = intval($_GET['ticket_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM tickets, clients WHERE tickets.client_id = clients.client_id AND ticket_id = $ticket_id AND tickets.company_id = $session_company_id");

  if(mysqli_num_rows($sql) == 0){
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

    include("footer.php");
  }else{

  $row = mysqli_fetch_array($sql);
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_type = $row['client_type'];
  $client_address = $row['client_address'];
  $client_city = $row['client_city'];
  $client_state = $row['client_state'];
  $client_zip = $row['client_zip'];
  $client_email = $row['client_email'];
  $client_phone = $row['client_phone'];
  if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
  }
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
  $ticket_status = $row['ticket_status'];
  $ticket_created_at = $row['ticket_created_at'];
  $ticket_updated_at = $row['ticket_updated_at'];
  $ticket_closed_at = $row['ticket_closed_at'];
  $ticket_created_by = $row['ticket_created_by'];

  if($ticket_status == "Open"){
    $ticket_badge_color = "primary";
  }elseif($ticket_status == "Working"){
    $ticket_badge_color = "success";
  }else{
    $ticket_badge_color = "secondary";
  }

?>



<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="tickets.php">Tickets</a>
  </li>
  <li class="breadcrumb-item active">Ticket Details</li>
</ol>
<div class="row">
  <div class="col-md-3">
    <div class="card mb-3">
      <div class="card-header">
        <h2>
          Ticket <?php echo $ticket_number; ?>
        </h2>
        <span class="p-2 badge badge-<?php echo $ticket_badge_color; ?>">
          <?php echo $ticket_status; ?>
          </span>

      </div>
      <div class="card-body">
  
        <div class="mb-4">  
          <h4 class="text-secondary">Client</h4>
          <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_name; ?>
          <br>
          <?php
          if(!empty($client_email)){
          ?>
          <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i> <a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a>
          <br>
          <?php
          }
          ?>
          <?php
          if(!empty($client_phone)){
          ?>
          <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <?php echo $client_phone; ?>
          <br>
          <?php 
          } 
          ?>
        </div>
  
        <h4 class="text-secondary">Details</h4>
        <div class="ml-1"><i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i> <?php echo $ticket_created_by; ?></div>
        <div class="ml-1"><i class="fa fa-fw fa-clock text-secondary mr-2 mb-2"></i> <?php echo $ticket_created_at; ?></div>
        
      </div>
    </div>
  </div>
  <div class="col-md-9">

    <div class="card mb-3">
      <div class="card-header">
        <h6 class="float-left mt-1"><?php echo $ticket_subject; ?></h6>
        <div class="dropdown dropleft text-center">
          <button class="btn btn-dark btn-sm float-right" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-fw fa-ellipsis-v"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="client_print.php?client_id=<?php echo $client_id; ?>">Print</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
            <a class="dropdown-item" href="post.php?delete_client=<?php echo $client_id; ?>">Delete</a>
          </div>
        </div>
      </div>
      <div class="card-body">
        <p><?php echo $ticket_details; ?></p>
      </div>
    </div>

    <form class="mb-3" action="post.php" method="post" autocomplete="off">
      <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
      <div class="form-group">
        <textarea rows="5" class="form-control" name="ticket_update"></textarea>
      </div>
      <button type="submit" name="add_ticket_update" class="btn btn-primary">Save</button>
      <button type="submit" name="close_ticket" class="btn btn-secondary">Close Ticket</button> 
    </form>

    <?php
    $sql = mysqli_query($mysqli,"SELECT * FROM ticket_updates WHERE ticket_id = $ticket_id");

      while($row = mysqli_fetch_array($sql)){;
      $ticket_update_id = $row['ticket_update_id'];
      $ticket_update = $row['ticket_update'];
      $ticket_update_created_at = $row['ticket_update_created_at'];
      $user_id = $row['user_id'];

    ?>

    <div class="card mb-3">
      <div class="card-body">
        <p class="blockquote"><?php echo $ticket_update; ?></p>
      </div>
      <div class="card-footer"><?php echo $ticket_update_created_at - $user_id; ?></div>
    </div>

    <?php
    }
    ?>
  
  </div>

</div>

<?php include("edit_ticket_modal.php"); ?>

<?php 

}

}

?>

<?php include("footer.php");