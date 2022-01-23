<?php include("header.php");

if(isset($_GET['campaign_id'])){
  $campaign_id = intval($_GET['campaign_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE campaign_id = $campaign_id AND company_id = $session_company_id");

  $row = mysqli_fetch_array($sql);

  $campaign_name = $row['campaign_name'];
  $campaign_subject = $row['campaign_subject'];
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

  ?>

  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="campaigns.php">Campaigns</a>
    </li>
    <li class="breadcrumb-item active"><?php echo $campaign_name; ?></li>
  </ol>

  <div class="card">
    <div class="card-body">
      <h1><?php echo $campaign_name; ?></h1>
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

<div class="card card-dark">
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
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $message_id = $row['message_id'];
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $contact_id = $row['contact_id'];
            $contact_name = $row['contact_name'];
            $contact_email = $row['contact_email'];
            $message_sent_at = $row['message_sent_at'];

          ?>
          <tr>
            <td><?php echo $client_name; ?></td>
            <td><?php echo $contact_name; ?></td>
            <td><?php echo $contact_email; ?></td>
            <td><?php echo $message_sent_at; ?></td>
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

<?php } ?>

<?php include("footer.php");