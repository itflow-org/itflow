<?php
/*
 * Client Portal
 * Ticket detail page
 */

require_once("inc_portal.php");

if(isset($_GET['id']) && intval($_GET['id'])) {
  $ticket_id = intval($_GET['id']);

  if($session_contact_id == $session_client_primary_contact_id){
    $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = '$session_client_id'");
  }
  else{
    $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = '$session_client_id' AND ticket_contact_id = '$session_contact_id'");
  }

  $ticket = mysqli_fetch_array($ticket_sql);

  if ($ticket) {
    ?>
    
  <nav class="navbar navbar-dark bg-dark">
    
   <i class="fas fa-fw fa-ticket-alt text-secondary"></i> <a class="navbar-brand" href="#">Ticket number # <?php echo $ticket['ticket_prefix'], $ticket['ticket_number'] ?></a>
 

      
    <span class="navbar-text">
      <?php
    if($ticket_status !== "Closed"){
    ?>

     
   <button class="btn btn-sm btn-outline-success my-2 my-sm-0 form-inline my-2 my-lg-0" type="submit"><a href="post.php?close_ticket=<?php echo $ticket_id; ?>"><i class="fas fa-fw fa-check text-secondary text-success"></i>  Close ticket</a></button>

    

    
    
    <?php
    }
    ?>
    </span>
  
  </nav>
    
    

    

    

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><b>Subject:</b> <?php echo $ticket['ticket_subject'] ?></h3>
      </div>
      <div class="card-body">
        <p>
          <b>State:</b> <?php echo $ticket['ticket_status'] ?>
          <br>
          <b>Priority:</b> <?php echo $ticket['ticket_priority'] ?>
        </p>
        <b>Issue:</b> <?php echo $ticket['ticket_details'] ?>
      </div>
    </div>



          
          
    <!-- Either show the reply comments box, ticket smiley feedback, or thanks for feedback -->

    <?php if($ticket['ticket_status'] !== "Closed") { ?>
      <div class="form-group">
        <form action="portal_post.php" method="post">
          <div class="form-group">
            <textarea class="form-control" name="comment" placeholder="Add comments.."></textarea>
          </div>
          <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id'] ?>">
          <button type="submit" class="btn btn-primary" name="add_ticket_comment">Save reply</button>
        </form>
      </div>
    <?php }

    elseif(empty($ticket['ticket_feedback'])) { ?>

      <h4>Rate your ticket</h4>

      <form action="portal_post.php" method="post">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id'] ?>">

        <button type="submit" class="btn btn-primary btn-lg" name="add_ticket_feedback" value="Good" onclick="this.form.submit()">
          <span class="fa fa-smile" aria-hidden="true"></span> Good
        </button>

        <button type="submit" class="btn btn-danger btn-lg" name="add_ticket_feedback" value="Bad" onclick="this.form.submit()">
          <span class="fa fa-frown" aria-hidden="true"></span> Bad
        </button>
      </form>

    <?php }

    else{ ?>

      <h4>Rated <?php echo $ticket['ticket_feedback'] ?> -- Thanks for your feedback!</h4>

    <?php } ?>

    <!-- End comments/feedback -->

    <hr><br>

    <?php
    $sql = mysqli_query($mysqli,"SELECT * FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id LEFT JOIN contacts ON ticket_reply_by = contact_id WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_type != 'Internal' ORDER BY ticket_reply_id DESC");

    while($row = mysqli_fetch_array($sql)){;
      $ticket_reply_id = $row['ticket_reply_id'];
      $ticket_reply = $row['ticket_reply'];
      $ticket_reply_created_at = $row['ticket_reply_created_at'];
      $ticket_reply_updated_at = $row['ticket_reply_updated_at'];
      $ticket_reply_by = $row['ticket_reply_by'];
      $ticket_reply_type = $row['ticket_reply_type'];

      if($ticket_reply_type == "Client"){
        $ticket_reply_by_display = $row['contact_name'];
        $user_initials = initials($row['contact_name']);
      }
      else{
        $ticket_reply_by_display = $row['user_name'];
        $user_id = $row['user_id'];
        $user_avatar = $row['user_avatar'];
        $user_initials = initials($row['user_name']);
      }
      ?>

      <div class="card card-outline <?php if($ticket_reply_type == 'Client') {echo "card-warning"; } else{ echo "card-info"; } ?> mb-3">
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
                <small class="text-muted"><?php echo $ticket_reply_created_at; ?> <?php if(!empty($ticket_reply_updated_at)){ echo "(edited: $ticket_reply_updated_at)"; } ?></small>
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


    <?php
  }
  else{
    echo "Ticket ID not found!";
  }
}
else{
  header("Location: index.php");
}

require_once("portal_footer.php");
