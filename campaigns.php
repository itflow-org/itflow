<?php include("header.php");

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
    $sb = "campaign_created_at";
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

  if(empty($_GET['canned_date'])){
    //Prevents lots of undefined variable errors.
    // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
    $_GET['canned_date'] = 'custom';
  }

  //Date Filter
  if($_GET['canned_date'] == "custom" AND !empty($_GET['dtf'])){
    $dtf = mysqli_real_escape_string($mysqli,$_GET['dtf']);
    $dtt = mysqli_real_escape_string($mysqli,$_GET['dtt']);
  }elseif($_GET['canned_date'] == "today"){
    $dtf = date('Y-m-d');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "yesterday"){
    $dtf = date('Y-m-d',strtotime("yesterday"));
    $dtt = date('Y-m-d',strtotime("yesterday"));
  }elseif($_GET['canned_date'] == "thisweek"){
    $dtf = date('Y-m-d',strtotime("monday this week"));
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastweek"){
    $dtf = date('Y-m-d',strtotime("monday last week"));
    $dtt = date('Y-m-d',strtotime("sunday last week"));
  }elseif($_GET['canned_date'] == "thismonth"){
    $dtf = date('Y-m-01');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastmonth"){
    $dtf = date('Y-m-d',strtotime("first day of last month"));
    $dtt = date('Y-m-d',strtotime("last day of last month"));
  }elseif($_GET['canned_date'] == "thisyear"){
    $dtf = date('Y-01-01');
    $dtt = date('Y-m-d');
  }elseif($_GET['canned_date'] == "lastyear"){
    $dtf = date('Y-m-d',strtotime("first day of january last year"));
    $dtt = date('Y-m-d',strtotime("last day of december last year"));  
  }else{
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
  }

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM campaigns
    WHERE (campaign_name LIKE '%$q%' OR campaign_subject LIKE '%$q%' OR campaign_status LIKE '%$q%')
    AND DATE(campaign_created_at) BETWEEN '$dtf' AND '$dtt'
    AND company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to"
  );

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card card-dark elevation-3">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-envelope"></i> Mailing Campaigns</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#campaignAddModal"><i class="fas fa-fw fa-plus"></i> New Campaign</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Campaigns">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
          <div class="float-right">
            <a href="#" class="btn btn-lg btn-default"><i class="fa fa-fw fa-pencil-ruler"></i> Draft</a>
            <a href="#" class="btn btn-lg btn-default"><i class="fa fa-fw fa-clock"></i> Queued</a>
            <a href="#" class="btn btn-lg btn-default"><i class="fa fa-fw fa-paper-plane"></i> Sent</a>
          </div>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Canned Date</label>
              <select class="form-control select2" name="canned_date">
                <option <?php if($_GET['canned_date'] == "custom"){ echo "selected"; } ?> value="custom">Custom</option>
                <option <?php if($_GET['canned_date'] == "today"){ echo "selected"; } ?> value="today">Today</option>
                <option <?php if($_GET['canned_date'] == "yesterday"){ echo "selected"; } ?> value="yesterday">Yesterday</option>
                <option <?php if($_GET['canned_date'] == "thisweek"){ echo "selected"; } ?> value="thisweek">This Week</option>
                <option <?php if($_GET['canned_date'] == "lastweek"){ echo "selected"; } ?> value="lastweek">Last Week</option>
                <option <?php if($_GET['canned_date'] == "thismonth"){ echo "selected"; } ?> value="thismonth">This Month</option>
                <option <?php if($_GET['canned_date'] == "lastmonth"){ echo "selected"; } ?> value="lastmonth">Last Month</option>
                <option <?php if($_GET['canned_date'] == "thisyear"){ echo "selected"; } ?> value="thisyear">This Year</option>
                <option <?php if($_GET['canned_date'] == "lastyear"){ echo "selected"; } ?> value="lastyear">Last Year</option>
              </select>
            </div>
          </div>
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=campaign_name&o=<?php echo $disp; ?>">Name</a></th>
            <th class="text-center">Sent</th>
            <th class="text-center">Opened</th>
            <th class="text-center">Clicked</th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=campaign_created_at&o=<?php echo $disp; ?>">Created</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=campaign_scheduled_at&o=<?php echo $disp; ?>">Scheduled</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=campaign_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $campaign_id = $row['campaign_id'];
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
              $campaign_badge_color = "warning text-white";
            }elseif($campaign_status == "Queued"){
              $campaign_badge_color = "info";
            }elseif($campaign_status == "Sending"){
              $campaign_badge_color = "primary";
            }elseif($campaign_status == "Sent"){
              $campaign_badge_color = "success";
            }else{
              $campaign_badge_color = "secondary";
            }

            //Get Stat Counts
            $sql_sent = mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_sent_at IS NOT NULL AND message_campaign_id = $campaign_id");
            $sent_count = mysqli_num_rows($sql_sent);
            
            $sql_open = mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_opened_at IS NOT NULL AND message_campaign_id = $campaign_id");
            $open_count = mysqli_num_rows($sql_open);

            $sql_click = mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_clicked_at IS NOT NULL AND message_campaign_id = $campaign_id");
            $click_count = mysqli_num_rows($sql_click);

            $sql_fail = mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_bounced_at IS NOT NULL AND message_campaign_id = $campaign_id");
            $fail_count = mysqli_num_rows($sql_fail);

          ?>
          <tr>
            <td><a href="campaign.php?campaign_id=<?php echo $campaign_id; ?>"><?php echo $campaign_name; ?></a></td>
            <td class="text-success text-center"><?php echo $sent_count; ?></td>
            <td class="text-secondary text-center"><?php echo $open_count; ?></td>
            <td class="text-info text-center"><?php echo $click_count; ?></td>
            <td><?php echo $campaign_created_at; ?></td>
            <td><?php echo $campaign_scheduled_at; ?></td>
            <td>
              <span class="p-2 badge badge-<?php echo $campaign_badge_color; ?>">
                <?php echo $campaign_status; ?>
              </span>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#campaignTestModal<?php echo $campaign_id; ?>">Test</a>
                  <div class="dropdown-divider"></div>
                  <?php if($campaign_status == 'Draft' OR $campaign_status == 'Queued'){ ?>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#campaignEditModal<?php echo $campaign_id; ?>">Edit</a>
                  <?php } ?>
                  <a class="dropdown-item" href="post.php?copy_campaign=<?php echo $campaign_id; ?>">Copy</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_campaign=<?php echo $campaign_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("campaign_copy_modal.php");
          include("campaign_edit_modal.php");
          include("campaign_test_modal.php");
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("campaign_add_modal.php"); ?>

<?php include("footer.php");