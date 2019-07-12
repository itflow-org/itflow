<?php include("header.php");

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*10;
  $record_to =  10;
}else{
  $record_from = 0;
  $record_to = 10;
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = $_GET['q'];
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = $_GET['sb'];
}else{
  $sb = "client_id";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM clients WHERE client_name LIKE '%$q%' OR client_email LIKE '%$q%' ORDER BY $sb $o LIMIT $record_from, $record_to"); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
   
    <h6 class="float-left mt-2"><i class="fa fa-users mr-2"></i>Clients</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-fw fa-user-plus"></i> New</button>
      
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo $q;} ?>" placeholder="Search Clients">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark">
          <tr>
            <th class="w-40"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Name <i class="fa fa-sort<?php if($disp=='ASC'){ echo "-up"; }else{ echo "-down"; }?>"></i></a></th>
            <th class="w-15"><a href="?<?php echo $url_query_strings_sb; ?>&sb=client_type&o=<?php echo $disp; ?>">Type <i class="fa fa-sort-up"></i></a></th>
            <th class="w-15">Email</th>
            <th class="w-10">Phone</th>
            <th class="w-10 text-right">Balance</th>
            <th class="w-10 text-center">Action</th>
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
            $client_phone = $row['client_phone'];
            if(strlen($client_phone)>2){ 
              $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
            }
            $client_email = $row['client_email'];
            $client_website = $row['client_website'];
            $client_net_terms = $row['client_net_terms'];
            if($client_net_terms == 0){
              $client_net_terms = $config_default_net_terms;
            }

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
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
            <td><?php echo $client_type; ?></td>
            <td><a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a></td>
            <td><?php echo $client_phone; ?></td>
            <td class="text-right text-monospace <?php echo $balance_text_color; ?>">$<?php echo number_format($balance,2); ?></td>
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

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_client_modal.php"); ?>

<?php include("footer.php");