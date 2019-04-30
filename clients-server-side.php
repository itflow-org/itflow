<?php include("header.php"); ?>

<?php 

if(isset($_GET['orderby'])){
  $orderby = "ORDER BY " . $_GET['orderby'];
}

if(isset($_GET['order'])){
  $order = $_GET['order'];
}

if(isset($_GET['search'])){
  $search = $_GET['search'];
}

?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_name LIKE '%$search%' $orderby $order LIMIT 10"); ?>


<div class="card">
  <div class="card-header">
    <div class="row">
      <div class="col-4">
        <form>
          <div class="input-group">
            <input type="text" class="form-control" placeholder="Search clients..." name="search">
            <div class="input-group-append">
              <button class="btn btn-dark" type="submit"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </form>
      </div>
      <div class="col-8">
        <button type="button" class="btn btn-primary mr-auto float-right" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-plus"></i></button>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead>
          <tr>
            <th><a href="<?php $_SERVER['PHP_SELF']; ?>?orderby=client_name&order=asc">Name <i class="fa fa-sort-alpha-down"></i></a></th>
            <th><a href="?orderby=client_email">Email</a></th>
            <th><a href="?sortby=client_phone">Phone</a></th>
            <th class="text-right">Balance</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
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

            //Add up all the payments for the invoice and get the total amount paid to the invoice
            $sql_invoice_amounts = mysqli_query($mysqli,"SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE client_id = $client_id AND invoice_status NOT LIKE 'Draft'");
            $row = mysqli_fetch_array($sql_invoice_amounts);

            $invoice_amounts = $row['invoice_amounts'];

            $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payments.invoice_id = invoices.invoice_id AND invoices.client_id = $client_id");
            $row = mysqli_fetch_array($sql_amount_paid);
            
            $amount_paid = $row['amount_paid'];

            $balance = $invoice_amounts - $amount_paid;
            //set Text color on balance
            if($balance > 0){
              $balance_text_color = "text-danger";
            }

          ?>
          <tr>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>"><?php echo "$client_name"; ?></a></td>
            <td><a href="mailto:<?php echo$email; ?>"><?php echo "$client_email"; ?></a></td>
            <td><?php echo "$client_phone"; ?></td>
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
            </td>
          </tr>

          <?php
          include("edit_client_modal.php");
          }
          ?>

        </tbody>
      </table>

      <nav aria-label="Page navigation example">
        <ul class="pagination justify-content-end">
          <li class="page-item disabled">
            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
          </li>
          <li class="page-item active"><a class="page-link" href="#">1</a></li>
          <li class="page-item"><a class="page-link" href="#">2</a></li>
          <li class="page-item"><a class="page-link" href="#">3</a></li>
          <li class="page-item"><a class="page-link" href="#">4</a></li>
          <li class="page-item"><a class="page-link" href="#">5</a></li>
          <li class="page-item">
            <a class="page-link" href="#">Next</a>
          </li>
        </ul>
      </nav>

    </div>
  </div>
</div>

<?php include("add_client_modal.php"); ?>

<?php include("footer.php");