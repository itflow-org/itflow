<?php include("inc_all.php");

if(isset($_GET['campaign_id'])){
  $campaign_id = intval($_GET['campaign_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");

  $row = mysqli_fetch_array($sql);

  $campaign_name = $row['campaign_name'];
  $campaign_subject = $row['campaign_subject'];
  $campaign_from_name = $row['campaign_from_name'];
  $campaign_from_email = $row['campaign_from_email'];
  $campaign_content = $row['campaign_content'];
  $campaign_status = $row['campaign_status'];
  $campaign_scheduled_at = $row['campaign_scheduled_at'];
  $campaign_created_at = $row['campaign_created_at'];

  //Set Badge color based off of campaign status
  if($campaign_status == "Sent"){
    $campaign_badge_color = "success";
  }elseif($campaign_status == "Queued"){
    $campaign_badge_color = "info";
  }elseif($campaign_status == "Sending"){
    $campaign_badge_color = "primary";
  }else{
    $campaign_badge_color = "secondary";
  }

  //Get Stat Counts
  //Subscribers
  $subscriber_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_campaign_id = $campaign_id"));
  //Sent
  $sent_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_sent_at IS NOT NULL AND message_campaign_id = $campaign_id"));
  //Opem
  $open_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_opened_at IS NOT NULL AND message_campaign_id = $campaign_id"));
  //Click
  $click_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_clicked_at IS NOT NULL AND message_campaign_id = $campaign_id"));
  //Fail
  $fail_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_bounced_at IS NOT NULL AND message_campaign_id = $campaign_id"));

  ?>

  <ol class="breadcrumb elevation-2">
    <li class="breadcrumb-item">
      <a href="campaigns.php">Campaigns</a>
    </li>
    <li class="breadcrumb-item active"><?php echo $campaign_name; ?></li>
  </ol>
  <div class="row">
    
    <div class="col-sm-4">
      <div class="card card-body elevation-2">
        <div class="row">
          <div class="col">
            <a class="text-secondary" href="#" data-toggle="modal" data-target="#campaignEditModal<?php echo $campaign_id; ?>"><i class="fa fa-fw fa-edit ml-2 float-right"></i></a>
            <a class="text-secondary" href="#" data-toggle="modal" data-target="#campaignCopyModal<?php echo $campaign_id; ?>"><i class="fa fa-fw fa-copy ml-2 float-right"></i></a>
            <a class="text-secondary" href="#" data-toggle="modal" data-target="#campaignTestModal<?php echo $campaign_id; ?>"><i class="fa fa-fw fa-wrench ml-2 float-right"></i></a>
            <h6 class="text-secondary">CAMPAIGN</h6>
          </div>
        </div>
        
        <h5><?php echo $campaign_name; ?></h5>
        <div class="p-2 badge badge-pill badge-<?php echo $campaign_badge_color; ?>">
          <?php echo $campaign_status; ?>
        </div>
      </div>
    </div>

    <div class="col-sm-4">
      <div class="card card-body elevation-2">
        <h6 class="text-secondary">DETAILS</h6>
        <div class="mb-1"><i class="fa fa-fw fa-bullhorn text-secondary mr-2"></i><strong><?php echo $campaign_subject; ?></strong></div>
        <div class="mb-1"><i class="fa fa-fw fa-user text-secondary mr-2"></i><strong><?php echo $campaign_from_name; ?></strong> (<?php echo $campaign_from_email; ?>)</div>
        <div class="mb-1"><i class="fa fa-fw fa-clock text-secondary mr-2"></i><strong><?php echo $campaign_created_at; ?></strong></div>
      </div>
    </div>

    <div class="col-sm-1">
      <div class="card card-body card-outline card-success text-center elevation-2">
        <h6 class="text-success">Sent</h6>
        <h3><?php echo "$sent_count/$subscriber_count"; ?></h3>
      </div>
    </div>
    
    <div class="col-sm-1">
      <div class="card card-body card-outline card-secondary text-center elevation-2">
        <h6 class="text-secondary">Opened</h6>
        <h3><?php echo "$open_count/$subscriber_count"; ?></h3>
      </div>
    </div>

    <div class="col-sm-1">
      <div class="card card-body card-outline card-info text-center elevation-2">
        <h6 class="text-info">Clicked</h6>
        <h3><?php echo "$click_count/$subscriber_count"; ?></h3>
      </div>
    </div>

    <div class="col-sm-1">
      <div class="card card-body card-outline card-danger text-center elevation-2">
        <h6 class="text-danger">Failed</h6>
        <h3><?php echo "$fail_count/$subscriber_count"; ?></h3>
      </div>
    </div>

  </div>

  <?php
  //Paging
  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$_SESSION['records_per_page'];
    $record_to = $_SESSION['records_per_page'];
  }else{
    $record_from = 0;
    $record_to = $_SESSION['records_per_page'];
    $p = 1;
  }
    
  if(isset($_GET['q'])){
    $q = mysqli_real_escape_string($mysqli,$_GET['q']);
  }else{
    $q = "";
  }

  if(!empty($_GET['sb'])){
    $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
  }else{
    $sb = "message_id";
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

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM campaign_messages
    LEFT JOIN contacts ON contact_id = message_contact_id
    LEFT JOIN clients ON primary_contact = contact_id
    WHERE (contact_name LIKE '%$q%' OR contact_email LIKE '%$q%' OR client_name LIKE '%$q%')
    AND message_campaign_id = $campaign_id
    AND campaign_messages.company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to"
  );

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card card-dark elevation-3">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-envelope"></i> Messages</h3>
  </div>
  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Messages">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Contact Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_email&o=<?php echo $disp; ?>">Email</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=message_sent_at&o=<?php echo $disp; ?>">Sent</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=message_opened_at&o=<?php echo $disp; ?>">Opened</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=message_ip&o=<?php echo $disp; ?>">IP</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $message_id = $row['message_id'];
            $message_ip = $row['message_ip'];
            $message_user_agent = $row['message_user_agent'];
            $message_sent_at = $row['message_sent_at'];
            $message_opened_at = $row['message_opened_at'];
            
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_email = $row['contact_email'];
            

          ?>
          <tr>
            <td><?php echo $client_name; ?></td>
            <td><?php echo $contact_name; ?></td>
            <td><?php echo $contact_email; ?></td>
            <td><?php echo $message_sent_at; ?></td>
            <td><?php echo $message_opened_at; ?></td>
            <td><?php echo $message_ip; ?></td>
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

<?php

//include("campaign_copy_modal.php"); --Doesnt Exist Yet
include("campaign_edit_modal.php");
include("campaign_test_modal.php");

} 

?>

<?php include("footer.php"); ?>