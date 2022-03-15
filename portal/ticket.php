<?php
/*
 * Client Portal
 * Ticket detail page
 */

include('../config.php');
include('../functions.php');
include('check_login.php');

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

if(isset($_GET['id']) && intval($_GET['id'])) {
  $ticket_id = intval($_GET['id']);
  $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = '$session_client_id'");
  $ticket = mysqli_fetch_array($ticket_sql);

  if ($ticket) {
    ?>

    <!DOCTYPE html>
    <html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $config_app_name; ?> | Client Portal - Tickets</title>

    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">

    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  </head>
  <div class="container">

    <h2>Ticket Details - <?php echo $ticket['ticket_subject'] ?></h2>
    <p>State: <?php echo $ticket['ticket_status'] ?></p>
    <p>Priority: <?php echo $ticket['ticket_priority'] ?></p>

    <hr>

    <?php
    $sql = mysqli_query($mysqli,"SELECT * FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_type = 'Public' ORDER BY ticket_reply_id DESC");

    while($row = mysqli_fetch_array($sql)){;
      $ticket_reply_id = $row['ticket_reply_id'];
      $ticket_reply = $row['ticket_reply'];
      $ticket_reply_created_at = $row['ticket_reply_created_at'];
      $ticket_reply_by = $row['ticket_reply_by'];
      $ticket_reply_by_display = $row['user_name'];
      $user_id = $row['user_id'];
      $user_avatar = $row['user_avatar'];
      $user_initials = initials($row['user_name']);
      ?>

      <div class="card card-outline card-info mb-3">
        <div class="card-header">
          <h3 class="card-title">
            <div class="media">
              <?php if(!empty($user_avatar)){ ?>
                <img src="<?php echo "../uploads/users/$user_id/$user_avatar"; ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
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
        </div>

        <div class="card-body">
          <?php echo $ticket_reply; ?>
        </div>
      </div>

      <?php

    }

    ?>


  </div>


    <?php
  }
  else{
    echo "Ticket ID not found!";
  }
}
else{
  header("Location: index.php");
}