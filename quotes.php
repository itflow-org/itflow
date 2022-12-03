<?php include("inc_all.php");

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "quote_number";
}

// Reverse default sort
if(!isset($_GET['o'])){
  $o = "DESC";
  $disp = "ASC";
}

if(empty($_GET['canned_date'])){
  //Prevents lots of undefined variable errors.
  // $dtf and $dtt will be set by the below else to 0000-00-00 / 9999-00-00
  $_GET['canned_date'] = 'custom';
}

//Date Filter
if($_GET['canned_date'] == "custom" && !empty($_GET['dtf'])){
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM quotes 
  LEFT JOIN clients ON quote_client_id = client_id
  LEFT JOIN categories ON quote_category_id = category_id
  WHERE quotes.company_id = $session_company_id
  AND (CONCAT(quote_prefix,quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%' OR quote_amount LIKE '%$q%' OR client_name LIKE '%$q%')
  AND DATE(quote_date) BETWEEN '$dtf' AND '$dtt'
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-file"></i> Quotes</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addQuoteModal"><i class="fas fa-fw fa-plus"></i> New Quote</button>
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Quotes">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=quote_number&o=<?php echo $disp; ?>">Number</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=quote_scope&o=<?php echo $disp; ?>">Scope</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=quote_amount&o=<?php echo $disp; ?>">Amount</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=quote_date&o=<?php echo $disp; ?>">Date</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=quote_status&o=<?php echo $disp; ?>">Status</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $quote_id = $row['quote_id'];
            $quote_prefix = htmlentities($row['quote_prefix']);
            $quote_number = htmlentities($row['quote_number']);
            $quote_scope = htmlentities($row['quote_scope']);
            if(empty($quote_scope)){
              $quote_scope_display = "-";
            }else{
              $quote_scope_display = $quote_scope;
            }
            $quote_status = htmlentities($row['quote_status']);
            $quote_date = $row['quote_date'];
            $quote_amount = htmlentities($row['quote_amount']);
            $quote_currency_code = htmlentities($row['quote_currency_code']);
            $quote_created_at = $row['quote_created_at'];
            $client_id = $row['client_id'];
            $client_name = htmlentities($row['client_name']);
            $client_currency_code = htmlentities($row['client_currency_code']);
            $category_id = $row['category_id'];
            $category_name = htmlentities($row['category_name']);
            $client_net_terms = htmlentities($row['client_net_terms']);
            if($client_net_terms == 0){
              $client_net_terms = $config_default_net_terms;
            }

            if($quote_status == "Sent"){
              $quote_badge_color = "warning text-white";
            }elseif($quote_status == "Viewed"){
              $quote_badge_color = "primary";
            }elseif($quote_status == "Accepted"){
              $quote_badge_color = "success";
            }elseif($quote_status == "Declined"){
              $quote_badge_color = "danger";
            }elseif($quote_status == "Invoiced"){
              $quote_badge_color = "info";
            }else{
              $quote_badge_color = "secondary";
            }

          ?>

          <tr>
            <td><a href="quote.php?quote_id=<?php echo $quote_id; ?>"><?php echo "$quote_prefix$quote_number"; ?></a></td>
            <td><?php echo $quote_scope_display; ?></td>
            <td><a href="client_quotes.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code); ?></td>
            <td><?php echo $quote_date; ?></td>
            <td><?php echo $category_name; ?></td>
            <td>
              <span class="p-2 badge badge-<?php echo $quote_badge_color; ?>">
                <?php echo $quote_status; ?>
              </span>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editQuoteModal<?php echo $quote_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">Copy</a>
                  <div class="dropdown-divider"></div>
                  <?php if(!empty($config_smtp_host)){ ?>
                  <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">Send</a>
                  <div class="dropdown-divider"></div>
                  <?php } ?>
                  <a class="dropdown-item text-danger" href="post.php?delete_quote=<?php echo $quote_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php
            
            include("quote_edit_modal.php");
            include("quote_copy_modal.php");

          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 
  
  include("quote_add_modal.php"); 
  include("category_quick_add_modal.php");
  
  include("footer.php");

?>