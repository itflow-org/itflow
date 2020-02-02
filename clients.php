<?php include("header.php");

//Rebuild URL

//$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*10;
  $record_to =  10;
}else{
  $record_from = 0;
  $record_to = 10;
  $p = 1;
}
  
//Custom Query Filter  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

//Column Filter
if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "client_name";
}

//Column Order Filter
if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM clients WHERE (client_name LIKE '%$q%' OR client_email LIKE '%$q%' OR client_contact LIKE '%$q%') AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt' AND company_id = $session_company_id ORDER BY $sb $o LIMIT $record_from, $record_to"); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-users mr-2"></i>Clients</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Clients">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
          <button class="btn btn-primary float-right" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
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
    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Name <i class="fa fa-sort-alpha<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th>Contact</th>
            <th class="text-right">Balance</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
            $client_type = $row['client_type'];
            $client_address = $row['client_address'];
            $client_city = $row['client_city'];
            $client_state = $row['client_state'];
            $client_zip = $row['client_zip'];
            $client_contact = $row['client_contact'];
            $client_phone = $row['client_phone'];
            if(strlen($client_phone)>2){ 
              $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
            }
            $client_extension = $row['client_extension'];
            $client_mobile = $row['client_mobile'];
            if(strlen($client_mobile)>2){ 
              $client_mobile = substr($row['client_mobile'],0,3)."-".substr($row['client_mobile'],3,3)."-".substr($row['client_mobile'],6,4);
            }
            $client_email = $row['client_email'];
            $client_website = $row['client_website'];
            $client_net_terms = $row['client_net_terms'];
            $client_hours = $row['client_hours'];
            $client_company_size = $row['client_company_size'];
            $client_notes = $row['client_notes'];

            //Add up all the payments for the invoice and get the total amount paid to the invoice
            $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' ");
            $row = mysqli_fetch_array($sql_invoice_amounts);

            $invoice_amounts = $row['invoice_amounts'];

            $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id");
            $row = mysqli_fetch_array($sql_amount_paid);
            
            $amount_paid = $row['amount_paid'];

            $balance = $invoice_amounts - $amount_paid;
            //set Text color on balance
            if($balance > 0){
              $balance_text_color = "text-danger font-weight-bold";
            }else{
              $balance_text_color = "";
            }

          ?>
          <tr>
            <td>
              <a href="client.php?client_id=<?php echo $client_id; ?>&tab=contacts"><?php echo $client_name; ?></a>
              <br>
              <small class="text-secondary"><?php echo $client_type; ?></small>
            </td>
            <td>
              <?php
              if(!empty($client_contact)){
              ?>
              <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $client_contact; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_phone)){
              ?>
              <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $client_phone; ?> <?php if(!empty($client_extension)){ echo "x$client_extension"; } ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_mobile)){
              ?>
              <i class="fa fa-fw fa-mobile-alt text-secondary mr-2 mb-2"></i><?php echo $client_mobile; ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($client_email)){
              ?>
              <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a>
              <?php
              }
              ?>
            </td>
            <td class="text-right <?php echo $balance_text_color; ?>">$<?php echo number_format($balance,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_client=<?php echo $client_id; ?>">Delete</a>
                </div>
              </div>  

              <?php include("edit_client_modal.php"); ?>
            
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
      <div class="mr-3">
        <?php include("pagination.php"); ?>
      </div>
    </div>
  </div>
</div>

<?php include("add_client_modal.php"); ?>

<?php include("footer.php");